<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Booking\AvailabilityChecker;
use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\RoomMeta;

/**
 * The plugin's public, versioned data contract for anything outside
 * this site's own PHP — a decoupled front end, another site, a build
 * step. "v1" is a promise the same way SC Events Manager's own REST
 * endpoint documents one: existing fields won't be renamed or
 * removed within it; a breaking change gets its own v2 route instead.
 *
 * Four routes, all under scrb/v1:
 *   GET  /rooms                  — every room, across every configured type
 *   GET  /availability            — is one room free for a given start/end?
 *   GET  /rooms/{id}/schedule     — a room's opening hours/rules plus every
 *                                   blocked range in a date window, for
 *                                   rendering a calendar (assets/js/
 *                                   booking-widget.js) rather than probing
 *                                   one candidate slot at a time
 *   POST /bookings                — submit a booking request
 *
 * Deliberately open (no auth) on all three — same trust model as a
 * plain HTML contact form. GET routes only return what a room's own
 * front-end page already shows; POST is rate-limited by nothing more
 * than WordPress's own request handling, same as every core comment
 * form. A site that wants to gate this behind a capability can still
 * do so with a `rest_pre_dispatch` filter — that's a deployment
 * decision, not something to bake into the plugin's own default.
 */
final class BookingsRestController implements Hookable
{
    private const NAMESPACE = 'scrb/v1';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        \register_rest_route(self::NAMESPACE, '/rooms', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'handleListRooms'],
            'permission_callback' => '__return_true',
            'args' => [
                'type' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_key',
                    'description' => 'A single room type post_type key. Empty means every configured type.',
                ],
            ],
        ]);

        \register_rest_route(self::NAMESPACE, '/availability', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'handleAvailability'],
            'permission_callback' => '__return_true',
            'args' => [
                'room_id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'start' => ['type' => 'string', 'required' => true, 'description' => 'ISO 8601 / strtotime-parsable.'],
                'end' => ['type' => 'string', 'required' => true, 'description' => 'ISO 8601 / strtotime-parsable.'],
            ],
        ]);

        \register_rest_route(self::NAMESPACE, '/rooms/(?P<room_id>\d+)/schedule', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'handleSchedule'],
            'permission_callback' => '__return_true',
            'args' => [
                'room_id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
                'start' => ['type' => 'string', 'required' => true, 'description' => 'ISO 8601 / strtotime-parsable.'],
                'end' => ['type' => 'string', 'required' => true, 'description' => 'ISO 8601 / strtotime-parsable.'],
            ],
        ]);

        \register_rest_route(self::NAMESPACE, '/bookings', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handleCreateBooking'],
            'permission_callback' => '__return_true',
            'args' => [
                'room_id' => ['type' => 'integer', 'required' => true],
                'start' => ['type' => 'string', 'required' => true],
                'end' => ['type' => 'string', 'required' => true],
                'name' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'required' => true, 'format' => 'email'],
                'phone' => ['type' => 'string', 'default' => ''],
                'message' => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    public function handleListRooms(\WP_REST_Request $request): \WP_REST_Response
    {
        $rooms = RoomListing::query(['type' => (string) $request->get_param('type')], $this->settings);

        return new \WP_REST_Response(['rooms' => $rooms, 'total' => \count($rooms)]);
    }

    public function handleAvailability(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = \scrb_check_availability(
            (int) $request->get_param('room_id'),
            (string) $request->get_param('start'),
            (string) $request->get_param('end')
        );

        return new \WP_REST_Response([
            'available' => $result === true,
            'reason' => $result === true ? null : $result,
        ]);
    }

    /**
     * A room's own booking rules (opening days/hours, minimum booking
     * length, buffer) plus every existing booking's already-buffered
     * blocked range that overlaps [start, end] — everything assets/js/
     * booking-widget.js needs to shade out a week view without
     * probing scrb_check_availability() one candidate slot at a time.
     */
    public function handleSchedule(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $roomId = (int) $request->get_param('room_id');

        try {
            $start = new \DateTimeImmutable((string) $request->get_param('start'));
            $end = new \DateTimeImmutable((string) $request->get_param('end'));
        } catch (\Exception) {
            return new \WP_Error(
                'scrb_invalid_request',
                \__('That date/time couldn\'t be understood.', 'sc-room-bookings'),
                ['status' => 422]
            );
        }

        $meta = RoomMeta::read($roomId);
        $blocked = \array_map(
            static fn (array $range): array => [
                'start' => $range['start']->format(\DATE_ATOM),
                'end' => $range['end']->format(\DATE_ATOM),
            ],
            (new AvailabilityChecker($this->settings))->blockedRanges($roomId, $start, $end)
        );

        return new \WP_REST_Response([
            'available_days' => $meta['available_days'],
            'available_start_time' => $meta['available_start_time'],
            'available_end_time' => $meta['available_end_time'],
            'min_booking_minutes' => $meta['min_booking_minutes'],
            'buffer_minutes' => $meta['buffer_minutes'],
            'blocked' => $blocked,
        ]);
    }

    public function handleCreateBooking(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $result = \scrb_request_booking([
            'room_id' => (int) $request->get_param('room_id'),
            'start' => (string) $request->get_param('start'),
            'end' => (string) $request->get_param('end'),
            'name' => (string) $request->get_param('name'),
            'email' => (string) $request->get_param('email'),
            'phone' => (string) $request->get_param('phone'),
            'message' => (string) $request->get_param('message'),
        ]);

        if (\is_wp_error($result)) {
            $result->add_data(['status' => 422]);

            return $result;
        }

        return new \WP_REST_Response(['booking_id' => $result, 'status' => \get_post_meta($result, '_scrb_status', true)], 201);
    }
}

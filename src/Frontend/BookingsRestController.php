<?php

namespace SCRoomBookings\Frontend;

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
 * Three routes, all under scrb/v1:
 *   GET  /rooms         — every room, across every configured type
 *   GET  /availability   — is one room free for a given start/end?
 *   POST /bookings       — submit a booking request
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

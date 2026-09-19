<?php

/**
 * Plain template tags for theme code, alongside the REST endpoints in
 * Frontend\BookingsRestController — both ultimately call
 * Booking\AvailabilityChecker / Booking\Booking, so a theme calling
 * these directly and an external caller hitting the REST API always
 * agree on whether a slot is free.
 */

use SCRoomBookings\Booking\AvailabilityChecker;
use SCRoomBookings\Booking\Booking;
use SCRoomBookings\Frontend\RoomListing;
use SCRoomBookings\PostTypes\BookingPostType;
use SCRoomBookings\Settings\Settings;

if (! function_exists('scrb_get_rooms')) {
    /**
     * Every room, across every configured type by default — the
     * plugin's stable data API for theme code that wants to render
     * its own markup rather than anything this plugin draws itself
     * (it doesn't draw a room listing anywhere on its own — there's
     * nothing to opt out of). A REST equivalent (GET /wp-json/scrb/v1/
     * rooms, same optional type param) exists for anything outside
     * this site's own PHP — see Frontend\BookingsRestController. Both
     * call the same underlying query (Frontend\RoomListing::query()),
     * so results always match.
     *
     * @param array{type?: string} $args Optional 'type' to narrow to one room type's post_type key.
     * @return array<int, array<string, mixed>>
     */
    function scrb_get_rooms(array $args = []): array
    {
        return RoomListing::query($args);
    }
}

if (! function_exists('scrb_get_room')) {
    /**
     * One room, shaped exactly like a scrb_get_rooms() row — a
     * theme's single-{post_type} template's equivalent of
     * scrb_get_rooms(), so it doesn't have to filter the full list
     * down to "this one" itself. Defaults to the post currently being
     * displayed (get_the_ID()), same as calling it with no argument
     * inside the loop.
     *
     * @return array<string, mixed> Empty if $postId isn't a published room of a configured type.
     */
    function scrb_get_room(?int $postId = null): array
    {
        $postId ??= (int) \get_the_ID();

        return RoomListing::find($postId) ?? [];
    }
}

if (! function_exists('scrb_check_availability')) {
    /**
     * @return true|string True if the slot is free, or a human-readable reason it isn't.
     */
    function scrb_check_availability(int $roomId, string $start, string $end): true|string
    {
        $checker = new AvailabilityChecker(new Settings());

        $reason = $checker->checkAvailability(
            $roomId,
            new DateTimeImmutable($start),
            new DateTimeImmutable($end)
        );

        return $reason ?? true;
    }
}

if (! function_exists('scrb_request_booking')) {
    /**
     * Validates, checks availability, and — if both pass — creates the
     * booking. This is the one place "is this request even allowed?"
     * and "record it" happen together; scrb_check_availability() and
     * Booking\Booking::create() individually don't do both, on
     * purpose (see Booking\Booking's own docblock).
     *
     * @param array{
     *     room_id: int,
     *     start: string,
     *     end: string,
     *     name: string,
     *     email: string,
     *     phone?: string,
     *     message?: string,
     * } $data
     * @return int|WP_Error The new booking's post ID, or a WP_Error with code
     *                      'scrb_unavailable' or 'scrb_invalid_request' on failure.
     */
    function scrb_request_booking(array $data): int|WP_Error
    {
        $roomId = (int) ($data['room_id'] ?? 0);
        $name = \sanitize_text_field($data['name'] ?? '');
        $email = \sanitize_email($data['email'] ?? '');

        if ($roomId <= 0 || $name === '' || ! \is_email($email)) {
            return new WP_Error(
                'scrb_invalid_request',
                __('Please fill in the room, your name, and a valid email address.', 'sc-room-bookings')
            );
        }

        try {
            $start = new DateTimeImmutable($data['start'] ?? '');
            $end = new DateTimeImmutable($data['end'] ?? '');
        } catch (\Exception) {
            return new WP_Error('scrb_invalid_request', __('That date/time couldn\'t be understood.', 'sc-room-bookings'));
        }

        $settings = new Settings();
        $checker = new AvailabilityChecker($settings);
        $reason = $checker->checkAvailability($roomId, $start, $end);

        if ($reason !== null) {
            return new WP_Error('scrb_unavailable', $reason);
        }

        $status = $settings->requireApproval() ? BookingPostType::STATUS_PENDING : BookingPostType::STATUS_CONFIRMED;

        return Booking::create([
            'room_id' => $roomId,
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
            'requester_name' => $name,
            'requester_email' => $email,
            'requester_phone' => \sanitize_text_field($data['phone'] ?? ''),
            'requester_message' => \sanitize_textarea_field($data['message'] ?? ''),
            'status' => $status,
        ]);
    }
}

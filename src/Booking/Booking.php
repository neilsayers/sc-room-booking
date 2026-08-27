<?php

namespace SCRoomBookings\Booking;

use SCRoomBookings\PostTypes\BookingPostType;
use SCRoomBookings\Support\BookingMeta;

/**
 * Create/read/update for a single booking. Deliberately just
 * persistence — this class doesn't check availability or decide
 * whether a request is allowed, it only records one once something
 * else (Frontend\template-functions.php's scrb_request_booking(),
 * ultimately) has already decided it should exist. Keeping "is this
 * slot free?" and "save this booking" as separate concerns is what
 * lets AvailabilityChecker be tested/reasoned about on its own.
 */
final class Booking
{
    /**
     * @param array{
     *     room_id: int,
     *     start_datetime: string,
     *     end_datetime: string,
     *     requester_name: string,
     *     requester_email: string,
     *     requester_phone?: string,
     *     requester_message?: string,
     *     status?: string,
     * } $data
     * @return int|\WP_Error The new booking's post ID, or a WP_Error on failure.
     */
    public static function create(array $data): int|\WP_Error
    {
        $room = \get_post($data['room_id']);

        if (! $room) {
            return new \WP_Error('scrb_invalid_room', \__('That room could not be found.', 'sc-room-bookings'));
        }

        // Title is for wp-admin's benefit only (list screens, search) —
        // nothing in this plugin's own UI relies on parsing it back
        // apart, all the real data lives in postmeta via BookingMeta.
        $title = \sprintf(
            '%s — %s (%s)',
            \get_the_title($room),
            $data['requester_name'],
            $data['start_datetime']
        );

        $postId = \wp_insert_post([
            'post_type' => BookingPostType::POST_TYPE,
            'post_title' => $title,
            'post_status' => 'publish',
        ], true);

        if (\is_wp_error($postId)) {
            return $postId;
        }

        \update_post_meta($postId, '_scrb_room_id', (int) $data['room_id']);
        \update_post_meta($postId, '_scrb_start_datetime', $data['start_datetime']);
        \update_post_meta($postId, '_scrb_end_datetime', $data['end_datetime']);
        \update_post_meta($postId, '_scrb_requester_name', $data['requester_name']);
        \update_post_meta($postId, '_scrb_requester_email', $data['requester_email']);
        \update_post_meta($postId, '_scrb_requester_phone', $data['requester_phone'] ?? '');
        \update_post_meta($postId, '_scrb_requester_message', $data['requester_message'] ?? '');
        \update_post_meta($postId, '_scrb_status', $data['status'] ?? BookingPostType::STATUS_PENDING);

        /**
         * Fires once a booking has actually been persisted — the hook
         * for site-specific notification emails, calendar sync, etc.
         * rather than this plugin guessing what every installation
         * wants to happen next.
         *
         * @param int   $postId The new booking's post ID.
         * @param array $data   The data it was created from.
         */
        \do_action('scrb_booking_created', $postId, $data);

        return $postId;
    }

    public static function setStatus(int $bookingId, string $status): void
    {
        if (! \array_key_exists($status, BookingPostType::STATUSES)) {
            return;
        }

        $previous = BookingMeta::read($bookingId)['status'];

        \update_post_meta($bookingId, '_scrb_status', $status);

        if ($previous !== $status) {
            /**
             * @param int    $bookingId
             * @param string $status   The new status.
             * @param string $previous The status it changed from.
             */
            \do_action('scrb_booking_status_changed', $bookingId, $status, $previous);
        }
    }

    public static function setAdminNotes(int $bookingId, string $notes): void
    {
        \update_post_meta($bookingId, '_scrb_admin_notes', $notes);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $bookingId): ?array
    {
        $post = \get_post($bookingId);

        if (! $post || $post->post_type !== BookingPostType::POST_TYPE) {
            return null;
        }

        return ['id' => $post->ID, ...BookingMeta::read($post->ID)];
    }
}

<?php

namespace SCRoomBookings\Support;

use SCRoomBookings\PostTypes\BookingPostType;

/**
 * Reads every _scrb_* field for a single booking post. Shared by
 * Booking\Booking (which writes it), Admin\BookingsPage (which lists
 * it), and Booking\AvailabilityChecker (which only reads start/end/
 * room/status) — one definition of what a booking looks like.
 */
final class BookingMeta
{
    /**
     * @return array<string, mixed>
     */
    public static function read(int $postId): array
    {
        return [
            'room_id' => (int) \get_post_meta($postId, '_scrb_room_id', true),
            'start_datetime' => (string) \get_post_meta($postId, '_scrb_start_datetime', true),
            'end_datetime' => (string) \get_post_meta($postId, '_scrb_end_datetime', true),
            'requester_name' => (string) \get_post_meta($postId, '_scrb_requester_name', true),
            'requester_email' => (string) \get_post_meta($postId, '_scrb_requester_email', true),
            'requester_phone' => (string) \get_post_meta($postId, '_scrb_requester_phone', true),
            'requester_message' => (string) \get_post_meta($postId, '_scrb_requester_message', true),
            'admin_notes' => (string) \get_post_meta($postId, '_scrb_admin_notes', true),
            'status' => (string) \get_post_meta($postId, '_scrb_status', true) ?: BookingPostType::STATUS_PENDING,
        ];
    }
}

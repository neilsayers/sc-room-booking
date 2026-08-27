<?php

namespace SCRoomBookings\Support;

use SCRoomBookings\Taxonomies\AmenityTaxonomy;

/**
 * Reads every _scrb_* field for a single room post. Shared by
 * MetaBoxes\RoomDetailsMetaBox (which writes it) and
 * Booking\AvailabilityChecker (which only reads it), so there's one
 * definition of what a room's metadata looks like no matter which
 * side is asking.
 *
 * Amenities aren't postmeta — they're terms in the Amenities taxonomy
 * (Taxonomies\AmenityTaxonomy), a shared, admin-managed list rather
 * than free text typed per room. read() doesn't include them for that
 * reason; use amenities() separately.
 */
final class RoomMeta
{
    /**
     * Every weekday key available() checks against, Monday first to
     * match how the admin UI lays the checkboxes out.
     */
    public const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * @return array<string, mixed>
     */
    public static function read(int $postId): array
    {
        $availableDays = \get_post_meta($postId, '_scrb_available_days', true);

        return [
            'capacity' => (int) \get_post_meta($postId, '_scrb_capacity', true),
            'suitable_for' => (string) \get_post_meta($postId, '_scrb_suitable_for', true),
            'contact_for_pricing' => (bool) \get_post_meta($postId, '_scrb_contact_for_pricing', true),
            // '' (not 0) when unset — a genuinely free room and a room
            // whose price just hasn't been entered yet aren't the same
            // thing, and a theme/REST consumer needs to be able to
            // tell them apart rather than seeing 0 for both.
            'price_amount' => (string) \get_post_meta($postId, '_scrb_price_amount', true),
            'price_unit' => (string) \get_post_meta($postId, '_scrb_price_unit', true) ?: 'hour',
            // All seven days by default — an admin who never touches
            // this screen still gets a room that's actually bookable,
            // rather than one that silently blocks every request.
            'available_days' => \is_array($availableDays) && $availableDays !== [] ? $availableDays : self::WEEKDAYS,
            'available_start_time' => (string) \get_post_meta($postId, '_scrb_available_start_time', true) ?: '09:00',
            'available_end_time' => (string) \get_post_meta($postId, '_scrb_available_end_time', true) ?: '17:00',
            'min_booking_minutes' => (int) \get_post_meta($postId, '_scrb_min_booking_minutes', true) ?: 30,
            'buffer_minutes' => (int) \get_post_meta($postId, '_scrb_buffer_minutes', true),
        ];
    }

    /**
     * @return string[] Amenity term names assigned to this room, in the order they were assigned.
     */
    public static function amenities(int $postId): array
    {
        $terms = \wp_get_post_terms($postId, AmenityTaxonomy::TAXONOMY, ['fields' => 'names']);

        return \is_array($terms) ? $terms : [];
    }
}

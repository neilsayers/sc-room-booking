<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\RoomMeta;

/**
 * Every room, shaped for theme/REST consumption — the shared query
 * behind scrb_get_rooms() and Frontend\BookingsRestController's own
 * GET /rooms, so both agree on what a "room" looks like. Same
 * "extract the query, not the HTTP layer" split as SC Events
 * Manager's EventListingShortcode::query().
 */
final class RoomListing
{
    /**
     * @param array{type?: string} $args
     * @return array<int, array<string, mixed>>
     */
    public static function query(array $args = [], ?Settings $settings = null): array
    {
        $settings ??= new Settings();
        $type = (string) ($args['type'] ?? '');
        $roomTypes = $type !== '' ? [$type] : \array_keys($settings->allRoomTypes());

        if ($roomTypes === []) {
            return [];
        }

        $query = new \WP_Query([
            'post_type' => $roomTypes,
            'post_status' => 'publish',
            // Oldest first, not WP_Query's default newest-first — a
            // room list reads as a fixed catalogue an admin built up
            // in some order, not a feed of recent activity.
            'orderby' => 'date',
            'order' => 'ASC',
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ]);

        return \array_map(static function (\WP_Post $post): array {
            $meta = RoomMeta::read($post->ID);

            return [
                'id' => $post->ID,
                'type' => $post->post_type,
                'name' => \get_the_title($post),
                'excerpt' => \get_the_excerpt($post),
                'featured_image_url' => \get_the_post_thumbnail_url($post, 'medium') ?: '',
                'view_url' => (string) \get_permalink($post),
                'capacity' => $meta['capacity'],
                'suitable_for' => $meta['suitable_for'],
                'contact_for_pricing' => $meta['contact_for_pricing'],
                'price_amount' => $meta['price_amount'],
                'price_unit' => $meta['price_unit'],
                'amenities' => RoomMeta::amenities($post->ID),
                'available_days' => $meta['available_days'],
                'available_start_time' => $meta['available_start_time'],
                'available_end_time' => $meta['available_end_time'],
            ];
        }, $query->posts);
    }
}

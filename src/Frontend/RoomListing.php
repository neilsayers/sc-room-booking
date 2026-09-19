<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\LayoutTypes;
use SCRoomBookings\Support\PriceUnits;
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
     * @param array{type?: string} $args 'type' is one post_type key, or several comma-separated (e.g. "room,court").
     * @return array<int, array<string, mixed>>
     */
    public static function query(array $args = [], ?Settings $settings = null): array
    {
        $settings ??= new Settings();
        $type = (string) ($args['type'] ?? '');
        $roomTypes = $type !== ''
            ? \array_values(\array_filter(\array_map('trim', \explode(',', $type))))
            : \array_keys($settings->allRoomTypes());

        if ($roomTypes === []) {
            return [];
        }

        $query = new \WP_Query([
            'post_type' => $roomTypes,
            'post_status' => 'publish',
            // menu_order first — Admin\RoomOrderPage's drag-and-drop
            // screen is the only thing that ever sets it, so a site
            // that's never opened that screen has every room at 0 and
            // this falls through to the date tiebreaker exactly as
            // before: oldest first, a fixed catalogue an admin built
            // up in some order, not a feed of recent activity.
            'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ]);

        return \array_map([self::class, 'shape'], $query->posts);
    }

    /**
     * One room by post ID, shaped exactly like a query() row — the
     * single-room counterpart, for a theme's single-{post_type}
     * template, the [sc_room] shortcode, or scrb_get_room(). Returns
     * null for a missing post, an unpublished one, or a post whose
     * type isn't one of this site's configured room types (so callers
     * can render a "not found" state instead of a room with every
     * field empty).
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $postId, ?Settings $settings = null): ?array
    {
        $post = \get_post($postId);

        if (! $post instanceof \WP_Post || $post->post_status !== 'publish') {
            return null;
        }

        $settings ??= new Settings();

        if (! \array_key_exists($post->post_type, $settings->allRoomTypes())) {
            return null;
        }

        return self::shape($post);
    }

    /**
     * @return array<string, mixed>
     */
    private static function shape(\WP_Post $post): array
    {
        $meta = RoomMeta::read($post->ID);

        return [
            'id' => $post->ID,
            'type' => $post->post_type,
            'name' => \get_the_title($post),
            'excerpt' => \get_the_excerpt($post),
            // 'large' rather than 'medium' (300px max) — this URL
            // now feeds full-width slideshow/card displays across
            // more than one theme template, not just a small list
            // thumbnail, and 'medium' was visibly soft/pixelated
            // stretched that wide.
            'featured_image_url' => \get_the_post_thumbnail_url($post, 'large') ?: '',
            'view_url' => (string) \get_permalink($post),
            'capacity' => $meta['capacity'],
            'suitable_for' => $meta['suitable_for'],
            'accessibility' => $meta['accessibility'],
            'gallery_image_urls' => \array_values(\array_filter(\array_map(
                static fn (int $id): string => \wp_get_attachment_image_url($id, 'large') ?: '',
                RoomMeta::galleryImages($post->ID)
            ))),
            'layout_variants' => \array_map(static function (array $variant): array {
                return [
                    'layout' => $variant['layout'],
                    'label' => LayoutTypes::label($variant['layout']),
                    'capacity' => $variant['capacity'],
                    'image_url' => $variant['image_id'] > 0
                        ? (\wp_get_attachment_image_url($variant['image_id'], 'medium') ?: '')
                        : '',
                ];
            }, RoomMeta::layoutVariants($post->ID)),
            'price_options' => \array_map(static function (array $row): array {
                return [
                    'label' => $row['label'],
                    'amount' => $row['amount'],
                    'unit' => $row['unit'],
                    'unit_label' => PriceUnits::label($row['unit']),
                    'contact_for_pricing' => $row['contact_for_pricing'],
                ];
            }, RoomMeta::priceOptions($post->ID)),
            'facilities' => RoomMeta::facilities($post->ID),
            'available_days' => $meta['available_days'],
            'available_start_time' => $meta['available_start_time'],
            'available_end_time' => $meta['available_end_time'],
        ];
    }
}

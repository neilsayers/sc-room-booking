<?php

namespace SCRoomBookings\Support;

use SCRoomBookings\Taxonomies\FacilityTaxonomy;

/**
 * Reads every _scrb_* field for a single room post. Shared by
 * MetaBoxes\RoomDetailsMetaBox (which writes it) and
 * Booking\AvailabilityChecker (which only reads it), so there's one
 * definition of what a room's metadata looks like no matter which
 * side is asking.
 *
 * Facilities aren't postmeta — they're terms in the Facilities
 * taxonomy (Taxonomies\FacilityTaxonomy), a shared, admin-managed list
 * rather than free text typed per room. read() doesn't include them
 * for that reason; use facilities() separately. Price options and
 * layout options are postmeta, but each a small array-of-rows rather
 * than a single scalar, so they get their own accessor too rather than
 * cluttering read()'s flat shape.
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
            'accessibility' => (string) \get_post_meta($postId, '_scrb_accessibility', true),
            // Set regardless of Settings::bookingIsExternalLink() — a
            // room that already has one keeps working on the front end
            // even if the site-wide setting is later switched off, same
            // "nothing already saved is lost" rule every other field
            // here follows.
            'booking_url' => (string) \get_post_meta($postId, '_scrb_booking_url', true),
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
     * One or more priced ways to book this room (e.g. a plain default
     * price, or several rows like "Off-peak non-commercial"/"Peak
     * commercial") — always at least one row, so the admin form and
     * any consumer can render unconditionally rather than checking for
     * emptiness first.
     *
     * @return array<int, array{label: string, amount: string, unit: string, contact_for_pricing: bool}>
     */
    public static function priceOptions(int $postId): array
    {
        $stored = \get_post_meta($postId, '_scrb_price_options', true);

        if (\is_array($stored) && $stored !== []) {
            return \array_map([self::class, 'normalizePriceOption'], \array_values($stored));
        }

        // Pre-0.4.0 rooms had one flat price rather than a repeater —
        // read the old fields once here so a room priced before this
        // version doesn't silently go blank until someone re-saves it.
        $legacyAmount = (string) \get_post_meta($postId, '_scrb_price_amount', true);
        $legacyContactForPricing = (bool) \get_post_meta($postId, '_scrb_contact_for_pricing', true);

        if ($legacyAmount !== '' || $legacyContactForPricing) {
            return [self::normalizePriceOption([
                'amount' => $legacyAmount,
                'unit' => (string) \get_post_meta($postId, '_scrb_price_unit', true) ?: 'hour',
                'contact_for_pricing' => $legacyContactForPricing,
            ])];
        }

        return [self::normalizePriceOption([])];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{label: string, amount: string, unit: string, contact_for_pricing: bool}
     */
    private static function normalizePriceOption(array $row): array
    {
        $unit = (string) ($row['unit'] ?? 'hour');

        return [
            'label' => (string) ($row['label'] ?? ''),
            // '' (not 0) when unset — a genuinely free room and a
            // price that just hasn't been entered yet aren't the same
            // thing, and a theme/REST consumer needs to be able to
            // tell them apart rather than seeing 0 for both.
            'amount' => (string) ($row['amount'] ?? ''),
            'unit' => \array_key_exists($unit, PriceUnits::UNITS) ? $unit : 'hour',
            'contact_for_pricing' => (bool) ($row['contact_for_pricing'] ?? false),
        ];
    }

    /**
     * Extra photos for the front-end slideshow, on top of the room's
     * own Featured Image (which a consumer is expected to show first
     * — this doesn't repeat it). Empty by default: a room with just
     * one photo only needs the Featured Image, not this.
     *
     * @return int[] Attachment IDs, in the order an admin added them.
     */
    public static function galleryImages(int $postId): array
    {
        $stored = \get_post_meta($postId, '_scrb_gallery_images', true);

        if (! \is_array($stored)) {
            return [];
        }

        return \array_values(\array_filter(\array_map('absint', $stored)));
    }

    /**
     * The alternate seating/configuration layouts this room can be set
     * out in, each with its own capacity and (optionally) its own
     * photo — e.g. a hall that's 300 capacity cleared, 220
     * theatre-style, 150 cabaret. Empty by default: most rooms only
     * ever need the single Capacity figure in read(), not this.
     *
     * @return array<int, array{layout: string, capacity: int, image_id: int}>
     */
    public static function layoutVariants(int $postId): array
    {
        $stored = \get_post_meta($postId, '_scrb_layout_variants', true);

        if (! \is_array($stored)) {
            return [];
        }

        $variants = [];

        foreach ($stored as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $layout = (string) ($row['layout'] ?? '');

            if (! \array_key_exists($layout, LayoutTypes::TYPES)) {
                continue;
            }

            $variants[] = [
                'layout' => $layout,
                'capacity' => (int) ($row['capacity'] ?? 0),
                'image_id' => (int) ($row['image_id'] ?? 0),
            ];
        }

        return $variants;
    }

    /**
     * @return string[] Facility term names assigned to this room, in the order they were assigned.
     */
    public static function facilities(int $postId): array
    {
        $terms = \wp_get_post_terms($postId, FacilityTaxonomy::TAXONOMY, ['fields' => 'names']);

        return \is_array($terms) ? $terms : [];
    }
}

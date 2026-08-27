<?php

namespace SCRoomBookings\Taxonomies;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\PostTypes\RoomPostTypes;
use SCRoomBookings\Settings\Settings;

/**
 * A single "Amenities" taxonomy shared across every room type — an
 * amenity like "Wi-Fi" or "Projector" isn't tied to any one kind of
 * room, so unlike SC Events Manager's per-event-type custom
 * taxonomies, this one is fixed rather than user-named, and always
 * registered. Same reasoning as that plugin's Venue post type.
 *
 * Managed on its own screen (Admin\AmenitiesPage links straight to
 * WordPress's own term-manager for it — no custom CRUD needed, that
 * screen already does add/edit/delete/merge for free) rather than
 * free-typed per room, so the list stays a controlled vocabulary a
 * front end can filter/group rooms by, not forty near-duplicate
 * spellings of "wifi".
 *
 * hierarchical: true is deliberate despite amenities having no real
 * parent/child structure — it's what gets WordPress to render the
 * room-edit checkbox list (post_categories_meta_box) instead of tags'
 * free-text autocomplete input, which fits "pick from the existing
 * list" better than "type anything, new terms welcome".
 */
final class AmenityTaxonomy implements Hookable
{
    public const TAXONOMY = 'scrb_amenity';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        // Priority 20 so it runs after RoomPostTypes registers the
        // post types this attaches to (both hook 'init', default 10).
        \add_action('init', [$this, 'registerTaxonomy'], 20);
        \add_action('add_meta_boxes', [$this, 'maybeHideMetaBoxInSimpleMode'], 20);
    }

    /**
     * Simple mode (Settings::simpleMode(), see Admin\RoomTypesPage) is
     * for sites only using this plugin to list rooms, not amenities or
     * bookings — the checkbox list this taxonomy's own default meta
     * box renders is exactly the kind of "gubbins" it's meant to hide.
     * Priority 20 so it runs after WordPress core has already added
     * the box (hook 'add_meta_boxes', default priority 10) — nothing
     * to remove otherwise. Terms already assigned to a room are left
     * alone; only the box disappears.
     */
    public function maybeHideMetaBoxInSimpleMode(): void
    {
        if (! $this->settings->simpleMode()) {
            return;
        }

        foreach (\array_keys($this->settings->allRoomTypes()) as $postType) {
            \remove_meta_box(self::TAXONOMY.'div', $postType, 'side');
        }
    }

    public function registerTaxonomy(): void
    {
        $roomPostTypes = \array_keys($this->settings->allRoomTypes());

        if ($roomPostTypes === []) {
            return;
        }

        \register_taxonomy(self::TAXONOMY, $roomPostTypes, [
            'labels' => [
                'name' => 'Amenities',
                'singular_name' => 'Amenity',
                'search_items' => 'Search Amenities',
                'all_items' => 'All Amenities',
                'edit_item' => 'Edit Amenity',
                'update_item' => 'Update Amenity',
                'add_new_item' => 'Add New Amenity',
                'new_item_name' => 'New Amenity Name',
                'menu_name' => 'Amenities',
            ],
            'public' => true,
            'hierarchical' => true, // See class docblock — this is for the checkbox UI, not real parent/child data.
            'show_in_rest' => true, // So a room's amenities are readable via WP's own core REST post fields too, alongside Frontend\BookingsRestController's own /rooms endpoint.
            'show_admin_column' => true,
            'show_in_menu' => false, // Admin\AmenitiesPage is the entry point, not a second "Amenities" item under every room type's own menu.
        ]);

        if (\get_transient(RoomPostTypes::FLUSH_TRANSIENT)) {
            \delete_transient(RoomPostTypes::FLUSH_TRANSIENT);
            \flush_rewrite_rules();
        }
    }
}

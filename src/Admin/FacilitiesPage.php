<?php

namespace SCRoomBookings\Admin;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Taxonomies\FacilityTaxonomy;

/**
 * "Facilities" under Room Bookings — not a page this class renders
 * itself, just a menu entry pointing straight at WordPress's own
 * term-manager screen for Taxonomies\FacilityTaxonomy (add/edit/
 * delete/merge already built in, no custom CRUD needed here).
 * FacilityTaxonomy registers with show_in_menu => false specifically
 * so this is the one place it's reachable from, rather than a
 * confusing second "Facilities" item nested under every configured
 * room type's own menu.
 */
final class FacilitiesPage implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu'], 30);
    }

    public function registerMenu(): void
    {
        // See Admin\RoomTypesPage's simple-mode checkbox and
        // Admin\BookingsPage::registerMenu() for the fuller reasoning
        // — this only hides the menu entry, the taxonomy itself and
        // any terms already assigned are untouched.
        if ($this->settings->simpleMode()) {
            return;
        }

        $roomPostTypes = \array_keys($this->settings->allRoomTypes());

        if ($roomPostTypes === []) {
            return;
        }

        \add_submenu_page(
            'scrb-settings',
            'Facilities',
            'Facilities',
            'manage_categories',
            'edit-tags.php?taxonomy='.FacilityTaxonomy::TAXONOMY.'&post_type='.\reset($roomPostTypes)
        );
    }
}

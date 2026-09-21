<?php

namespace SCRoomBookings;

use SCRoomBookings\Admin\BookingsPage;
use SCRoomBookings\Admin\DocumentationPage;
use SCRoomBookings\Admin\FacilitiesPage;
use SCRoomBookings\Admin\RoomOrderPage;
use SCRoomBookings\Admin\RoomTypesPage;
use SCRoomBookings\Admin\ShortcodeButton;
use SCRoomBookings\Blocks\Blocks;
use SCRoomBookings\Frontend\BookingsRestController;
use SCRoomBookings\Frontend\BookingWidget;
use SCRoomBookings\Frontend\DefaultTemplates;
use SCRoomBookings\Frontend\FrontendAssets;
use SCRoomBookings\Frontend\RoomDetailShortcode;
use SCRoomBookings\Frontend\RoomListingShortcode;
use SCRoomBookings\MetaBoxes\RoomDetailsMetaBox;
use SCRoomBookings\Notifications\BookingNotifications;
use SCRoomBookings\PostTypes\BookingPostType;
use SCRoomBookings\PostTypes\RoomPostTypes;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Setup\Upgrader;
use SCRoomBookings\Setup\UpdateChecker;
use SCRoomBookings\Taxonomies\FacilityTaxonomy;

/**
 * Composes the plugin's features and wires them into WordPress.
 *
 * To grow the plugin (a public booking calendar widget, iCal export,
 * payment integration, ...) write a class implementing
 * Contracts\Hookable and add it to the list in boot().
 */
final class Plugin
{
    private static ?self $instance = null;

    private Settings $settings;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->settings = new Settings();
    }

    public function boot(): void
    {
        $features = [
            new Upgrader(),
            new UpdateChecker(),
            new RoomTypesPage($this->settings),
            new BookingsPage($this->settings),
            new FacilitiesPage($this->settings),
            new RoomOrderPage($this->settings),
            new DocumentationPage($this->settings),
            new BookingsRestController($this->settings),
            new RoomPostTypes($this->settings),
            new BookingPostType(),
            new FacilityTaxonomy($this->settings),
            new RoomDetailsMetaBox($this->settings),
            new BookingNotifications($this->settings),
            new RoomListingShortcode($this->settings),
            new RoomDetailShortcode(),
            new DefaultTemplates($this->settings),
            new FrontendAssets(),
            new BookingWidget($this->settings),
            new ShortcodeButton($this->settings),
            new Blocks($this->settings),
        ];

        foreach ($features as $feature) {
            $feature->register();
        }
    }

    public function settings(): Settings
    {
        return $this->settings;
    }
}

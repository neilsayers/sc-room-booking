<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Contracts\Hookable;

/**
 * The plain CSS behind [sc_rooms]/[sc_room] and DefaultTemplates'
 * bundled single-room.php/archive-room.php — every .scrb-listing- and
 * .scrb-detail- class RoomCard/RoomDetail print gets its default
 * look from here. A site with its own theme markup for rooms (this
 * one's content-single-room.blade.php, say) never prints those
 * classes, so this file never does anything on that site beyond
 * costing one small, cached request.
 */
final class FrontendAssets implements Hookable
{
    public function register(): void
    {
        \add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        \wp_enqueue_style('scrb-frontend', SCRB_URL.'assets/css/frontend.css', [], SCRB_VERSION);
    }
}

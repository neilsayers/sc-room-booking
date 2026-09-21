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
        \add_action('init', [$this, 'registerStyle']);
        \add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Registered (not enqueued) on init, deliberately — early enough
     * to exist in both the front end and the block editor's own admin
     * context, where wp_enqueue_scripts (below) never fires.
     * Blocks\Blocks' two block.json files reference this same handle
     * as their own "style", which is what actually gets it loaded
     * inside the editor's preview iframe for ServerSideRender —
     * without a style already registered by the time WordPress
     * resolves that, the handle simply doesn't exist there and
     * nothing loads, which is exactly why a block's editor preview
     * used to render with none of .scrb-listing- and .scrb-detail-'s
     * actual styling, just bare unstyled HTML.
     */
    public function registerStyle(): void
    {
        \wp_register_style('scrb-frontend', SCRB_URL.'assets/css/frontend.css', [], SCRB_VERSION);
    }

    public function enqueue(): void
    {
        \wp_enqueue_style('scrb-frontend');
    }
}

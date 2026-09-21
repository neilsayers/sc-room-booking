<?php

namespace SCRoomBookings\Blocks;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Frontend\RoomListing;
use SCRoomBookings\Settings\Settings;

/**
 * Registers this plugin's two Gutenberg blocks — the block-editor
 * equivalent of [sc_rooms]/[sc_room], for a site using the block
 * editor rather than (or alongside) Admin\ShortcodeButton's classic-
 * editor toolbar button. Both are dynamic (server-rendered) blocks
 * whose blocks/*\/render.php calls straight into
 * Frontend\RoomListingShortcode::render()/Frontend\RoomDetailShortcode::render()
 * — the exact same rendering the shortcodes themselves use, so a
 * block and its shortcode twin can never drift apart.
 *
 * No build step: each block.json's editorScript references an
 * already-registered script handle (registerEditorScripts(), below)
 * rather than a file: path needing a webpack-generated .asset.php,
 * and the editor JS itself is plain ES5 against the wp.* globals
 * WordPress already ships in the block editor — same "drop it in,
 * nothing to compile" philosophy as the rest of this plugin.
 */
final class Blocks implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('init', [$this, 'registerBlocks']);
        \add_filter('block_categories_all', [$this, 'addBlockCategory']);
    }

    public function registerBlocks(): void
    {
        if ($this->settings->allRoomTypes() === []) {
            return;
        }

        $this->registerEditorScripts();

        \register_block_type(SCRB_PATH.'blocks/room-listing');
        \register_block_type(SCRB_PATH.'blocks/room-detail');
    }

    /**
     * @param array<int, array<string, string>> $categories
     * @return array<int, array<string, string>>
     */
    public function addBlockCategory(array $categories): array
    {
        return \array_merge($categories, [
            ['slug' => 'sc-room-bookings', 'title' => \__('SC Room Bookings', 'sc-room-bookings')],
        ]);
    }

    private function registerEditorScripts(): void
    {
        $deps = ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'];

        \wp_register_script(
            'scrb-block-room-listing-editor',
            SCRB_URL.'blocks/room-listing/index.js',
            $deps,
            SCRB_VERSION,
            true
        );

        \wp_register_script(
            'scrb-block-room-detail-editor',
            SCRB_URL.'blocks/room-detail/index.js',
            $deps,
            SCRB_VERSION,
            true
        );

        // The room-detail block's own room-picker dropdown needs the
        // same {id, name} list Admin\ShortcodeButton's TinyMCE modal
        // uses — localized directly onto its editor script rather
        // than fetched over REST, so the list is available the instant
        // the block's Inspector panel opens, not after a round trip.
        $rooms = \array_map(
            static fn (array $room): array => ['id' => $room['id'], 'name' => $room['name']],
            RoomListing::query([], $this->settings)
        );

        \wp_localize_script('scrb-block-room-detail-editor', 'scrbBlockRooms', $rooms);
    }
}

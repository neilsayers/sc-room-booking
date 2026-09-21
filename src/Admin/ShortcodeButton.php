<?php

namespace SCRoomBookings\Admin;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Frontend\RoomListing;
use SCRoomBookings\Settings\Settings;

/**
 * A "SC Room Bookings" TinyMCE toolbar button that inserts
 * [sc_rooms]/[sc_room id="X"] at the caret — the classic-editor
 * equivalent of a block inserter, so an admin never has to remember
 * or hand-type either shortcode's syntax, let alone a specific room's
 * post ID.
 *
 * Registered on the same mce_external_plugins/mce_buttons filters
 * WordPress itself uses for the classic editor's own buttons, so this
 * also appears inside a "Classic" block's own mini toolbar in the
 * block editor — not full Gutenberg block support (see the dedicated
 * blocks this plugin also ships for that), just a bonus rather than
 * something specifically built for it.
 *
 * The button's own click handler only ever toggles a plain <dialog>
 * (assets/js/shortcode-modal.js) — same vanilla-JS, no-jQuery,
 * native-<dialog> approach as the front-end booking widget and
 * Admin\RoomTypesPage's own delete-confirmation dialog, rather than
 * TinyMCE's own constrained legacy windowManager dialog API, which
 * has no real radio-button field type to build the requested "all
 * rooms, or one room by ID" choice with.
 */
final class ShortcodeButton implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_init', [$this, 'maybeRegisterMceButton']);
        \add_action('admin_enqueue_scripts', [$this, 'maybeEnqueueModal']);
    }

    public function maybeRegisterMceButton(): void
    {
        if ($this->settings->allRoomTypes() === [] || ! \current_user_can('edit_posts')) {
            return;
        }

        if (\get_user_option('rich_editing') !== 'true') {
            return;
        }

        \add_filter('mce_external_plugins', [$this, 'addTinyMcePlugin']);
        \add_filter('mce_buttons', [$this, 'addTinyMceButton']);
    }

    /**
     * @param array<string, string> $plugins
     * @return array<string, string>
     */
    public function addTinyMcePlugin(array $plugins): array
    {
        $plugins['scrb_shortcode'] = SCRB_URL.'assets/js/tinymce-button.js';

        return $plugins;
    }

    /**
     * @param array<int, string> $buttons
     * @return array<int, string>
     */
    public function addTinyMceButton(array $buttons): array
    {
        $buttons[] = 'scrb_shortcode';

        return $buttons;
    }

    public function maybeEnqueueModal(string $hook): void
    {
        if (! \in_array($hook, ['post.php', 'post-new.php'], true) || $this->settings->allRoomTypes() === []) {
            return;
        }

        if (! \current_user_can('edit_posts') || \get_user_option('rich_editing') !== 'true') {
            return;
        }

        \wp_enqueue_style('scrb-admin', SCRB_URL.'assets/css/admin.css', [], SCRB_VERSION);
        \wp_enqueue_script('scrb-shortcode-modal', SCRB_URL.'assets/js/shortcode-modal.js', [], SCRB_VERSION, true);

        $rooms = \array_map(
            static fn (array $room): array => ['id' => $room['id'], 'name' => $room['name']],
            RoomListing::query([], $this->settings)
        );

        \wp_localize_script('scrb-shortcode-modal', 'scrbShortcodeRooms', $rooms);

        \add_action('admin_footer', [$this, 'renderModal']);
    }

    public function renderModal(): void
    {
        ?>
        <dialog id="scrb-shortcode-modal" class="scrb-shortcode-modal">
            <form method="dialog">
                <h2><?php echo \esc_html__('Insert a Room Bookings shortcode', 'sc-room-bookings'); ?></h2>

                <p>
                    <label>
                        <input type="radio" name="scrb_shortcode_type" value="rooms" checked>
                        <?php echo \esc_html__('Show all rooms', 'sc-room-bookings'); ?>
                    </label>
                </p>
                <p>
                    <label>
                        <input type="radio" name="scrb_shortcode_type" value="room">
                        <?php echo \esc_html__('Show one room by ID', 'sc-room-bookings'); ?>
                    </label>
                </p>
                <p>
                    <select id="scrb-shortcode-room-select" disabled>
                        <option value=""><?php echo \esc_html__('Select a room...', 'sc-room-bookings'); ?></option>
                    </select>
                </p>

                <p class="scrb-shortcode-modal-actions">
                    <button type="button" class="button" id="scrb-shortcode-cancel"><?php echo \esc_html__('Cancel', 'sc-room-bookings'); ?></button>
                    <button type="button" class="button button-primary" id="scrb-shortcode-insert"><?php echo \esc_html__('Insert Shortcode', 'sc-room-bookings'); ?></button>
                </p>
            </form>
        </dialog>
        <?php
    }
}

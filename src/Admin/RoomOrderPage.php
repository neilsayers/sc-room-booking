<?php

namespace SCRoomBookings\Admin;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;

/**
 * "Reorder rooms" under Room Bookings — a drag-and-drop list per
 * configured room type, saved automatically on drop rather than via a
 * submit button (there's nothing else on this screen to configure, so
 * a save button would just be one extra click every time).
 *
 * Drag order is stored in each room's own menu_order, the same column
 * WordPress already uses for Pages — no new postmeta, no new table.
 * Frontend\RoomListing::query() orders by menu_order first and falls
 * back to date for anything never dragged, so a site that never opens
 * this screen keeps exactly the ordering it always had.
 */
final class RoomOrderPage implements Hookable
{
    private const PAGE_SLUG = 'scrb-room-order';
    private const AJAX_ACTION = 'scrb_save_room_order';
    private const NONCE_ACTION = 'scrb_save_room_order';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu'], 40);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('wp_ajax_'.self::AJAX_ACTION, [$this, 'handleSaveOrder']);
    }

    public function registerMenu(): void
    {
        if (! $this->settings->hasRoomTypes()) {
            return;
        }

        \add_submenu_page(
            'scrb-settings',
            'Reorder rooms',
            'Reorder rooms',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_style('scrb-admin', SCRB_URL.'assets/css/admin.css', [], SCRB_VERSION);
        \wp_enqueue_script(
            'scrb-room-order',
            SCRB_URL.'assets/js/room-order.js',
            ['jquery-ui-sortable'],
            SCRB_VERSION,
            true
        );
        \wp_localize_script('scrb-room-order', 'scrbRoomOrder', [
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'action' => self::AJAX_ACTION,
            'nonce' => \wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Reorder rooms</h1>
            <p>Drag a room up or down to change the order it appears in on the front end (SC Room Bookings' room templates, and any theme code using <code>scrb_get_rooms()</code>). Saves automatically as you drop each one.</p>

            <?php foreach ($this->settings->allRoomTypes() as $roomType) : ?>
                <?php $rooms = $this->orderedRooms($roomType['post_type']); ?>

                <h2><?php echo \esc_html($roomType['label_plural']); ?></h2>

                <?php if ($rooms === []) : ?>
                    <p><em>No <?php echo \esc_html(\strtolower($roomType['label_plural'])); ?> yet.</em></p>
                <?php else : ?>
                    <ul class="scrb-order-list" data-post-type="<?php echo \esc_attr($roomType['post_type']); ?>">
                        <?php foreach ($rooms as $room) : ?>
                            <li class="scrb-order-row" data-id="<?php echo \esc_attr((string) $room->ID); ?>">
                                <span class="scrb-order-handle" aria-hidden="true">&#9776;</span>
                                <?php echo \get_the_post_thumbnail($room, [32, 32], ['class' => 'scrb-order-thumb']); ?>
                                <span class="scrb-order-title"><?php echo \esc_html(\get_the_title($room)); ?></span>
                                <span class="scrb-order-status <?php echo $room->post_status === 'publish' ? '' : 'scrb-order-status--muted'; ?>"><?php echo \esc_html(\get_post_status_object($room->post_status)?->label ?? $room->post_status); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @return \WP_Post[]
     */
    private function orderedRooms(string $postType): array
    {
        return \get_posts([
            'post_type' => $postType,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ]);
    }

    public function handleSaveOrder(): void
    {
        \check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (! \current_user_can('manage_options')) {
            \wp_send_json_error('forbidden', 403);
        }

        $postType = \sanitize_key($_POST['post_type'] ?? '');

        if ($this->settings->getRoomType($postType) === null) {
            \wp_send_json_error('unknown_room_type', 400);
        }

        $ids = \array_map('absint', (array) ($_POST['ids'] ?? []));

        foreach ($ids as $index => $id) {
            // Belt and braces: only ever reorders rooms of the type
            // this request claims to be reordering, regardless of
            // what the browser actually posted.
            if (\get_post_type($id) !== $postType) {
                continue;
            }

            \wp_update_post(['ID' => $id, 'menu_order' => $index]);
        }

        \wp_send_json_success();
    }
}

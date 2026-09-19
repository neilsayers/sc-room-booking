<?php

namespace SCRoomBookings\Admin;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\PostTypes\RoomPostTypes;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Setup\Activator;

/**
 * The plugin's one settings screen: lists every configured room type
 * (rename labels inline, all in one form) and, while under the limit,
 * a form to add another. First activation redirects here since the
 * list starts empty — see maybeRedirectToSetup().
 */
final class RoomTypesPage implements Hookable
{
    private const PAGE_SLUG = 'scrb-settings';
    private const ADD_ACTION = 'scrb_add_room_type';
    private const RELABEL_ACTION = 'scrb_relabel_room_types';
    private const DELETE_ACTION = 'scrb_delete_room_type';
    private const SIMPLE_MODE_ACTION = 'scrb_toggle_simple_mode';
    private const BOOKING_MODE_ACTION = 'scrb_set_booking_mode';

    /**
     * Post statuses counted as "existing" for the pre-delete warning
     * and actually moved to Trash — excludes 'trash' (already there)
     * and 'auto-draft' (never a real room an editor created).
     */
    private const DELETABLE_STATUSES = ['publish', 'future', 'draft', 'pending', 'private'];

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_menu', [$this, 'reorderMenu'], 999);
        \add_action('admin_init', [$this, 'maybeRedirectToSetup']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('admin_post_'.self::ADD_ACTION, [$this, 'handleAdd']);
        \add_action('admin_post_'.self::RELABEL_ACTION, [$this, 'handleRelabel']);
        \add_action('admin_post_'.self::DELETE_ACTION, [$this, 'handleDelete']);
        \add_action('admin_post_'.self::SIMPLE_MODE_ACTION, [$this, 'handleToggleSimpleMode']);
        \add_action('admin_post_'.self::BOOKING_MODE_ACTION, [$this, 'handleSetBookingMode']);
        \add_action('admin_notices', [$this, 'maybeShowCreatedNotice']);
    }

    public function enqueueAssets(): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_style('scrb-admin', SCRB_URL.'assets/css/admin.css', [], SCRB_VERSION);
        \wp_enqueue_script('scrb-room-types-delete', SCRB_URL.'assets/js/room-types-delete.js', [], SCRB_VERSION, true);
    }

    public function registerMenu(): void
    {
        \add_menu_page(
            'SC Room Bookings',
            'SC Room Bookings',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage'],
            'dashicons-admin-multisite',
            91
        );

        /*
         * Without an explicit submenu entry whose own slug matches the
         * parent, this settings page would have no menu link pointing
         * to it at all — see reorderMenu() for why, and for why this
         * alone isn't quite enough either.
         */
        \add_submenu_page(
            self::PAGE_SLUG,
            'SC Room Bookings',
            'Room Types',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * The top-level menu link's own href is whichever submenu item
     * was registered *first*, not whichever one shares its slug. Run
     * last (priority 999) and move this page's entry to the front so
     * the top-level link — and the visible submenu order — both point
     * here first. See SC Events Manager's identical EventTypesPage
     * ::reorderMenu() for the fuller version of this story.
     */
    public function reorderMenu(): void
    {
        global $submenu;

        if (empty($submenu[self::PAGE_SLUG])) {
            return;
        }

        $items = $submenu[self::PAGE_SLUG];

        foreach ($items as $index => $item) {
            if ($item[2] === self::PAGE_SLUG) {
                unset($items[$index]);
                \array_unshift($items, $item);
                $submenu[self::PAGE_SLUG] = \array_values($items);

                return;
            }
        }
    }

    /**
     * Sends a freshly-activated admin here once, the same way SC
     * Events Manager's own setup wizard does. Skips bulk/network
     * activation so it doesn't hijack that summary screen.
     */
    public function maybeRedirectToSetup(): void
    {
        if (! \get_transient(Activator::REDIRECT_TRANSIENT)) {
            return;
        }

        \delete_transient(Activator::REDIRECT_TRANSIENT);

        if (
            $this->settings->hasRoomTypes()
            || \wp_doing_ajax()
            || isset($_GET['activate-multi'])
            || ! \current_user_can('manage_options')
        ) {
            return;
        }

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG));
        exit;
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }

        $roomTypes = $this->settings->allRoomTypes();
        ?>
        <div class="wrap">
            <h1>SC Room Bookings</h1>

            <?php \settings_errors('scrb_settings'); ?>

            <h2>Simple mode</h2>
            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                <?php \wp_nonce_field(self::SIMPLE_MODE_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo \esc_attr(self::SIMPLE_MODE_ACTION); ?>">

                <p>
                    <label>
                        <input type="checkbox" name="simple_mode" value="1" <?php \checked($this->settings->simpleMode()); ?>>
                        Use SC Room Bookings in simple mode
                    </label>
                </p>
                <p class="description">
                    For sites only using this plugin to list rooms with basic info, not to take bookings. Trims each
                    room's edit screen down to just Capacity and Suitable for, and hides the Bookings, Documentation
                    and Facilities menu items below. Nothing already saved — pricing, availability, facilities — is
                    touched, so turning this off again brings it all straight back.
                </p>

                <?php \submit_button('Save'); ?>
            </form>

            <h2>Booking</h2>
            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                <?php \wp_nonce_field(self::BOOKING_MODE_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo \esc_attr(self::BOOKING_MODE_ACTION); ?>">

                <p>
                    <label>
                        <input type="radio" name="booking_mode" value="<?php echo \esc_attr(Settings::BOOKING_MODE_INTERNAL); ?>" <?php \checked($this->settings->bookingMode(), Settings::BOOKING_MODE_INTERNAL); ?>>
                        No online booking
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="booking_mode" value="<?php echo \esc_attr(Settings::BOOKING_MODE_REQUEST); ?>" <?php \checked($this->settings->bookingMode(), Settings::BOOKING_MODE_REQUEST); ?>>
                        Visitors can request a time and discuss by email
                    </label>
                    <br>
                    <label>
                        <input type="radio" name="booking_mode" value="<?php echo \esc_attr(Settings::BOOKING_MODE_EXTERNAL_LINK); ?>" <?php \checked($this->settings->bookingMode(), Settings::BOOKING_MODE_EXTERNAL_LINK); ?>>
                        Send visitors to a third-party website to book
                    </label>
                </p>
                <p class="description">
                    "Visitors can request a time" shows a "View availability" button on every room — a week view of
                    what's free, tap a start time then an end time, then a name/email form. Submitting it creates a
                    booking here exactly as if it came through the REST API, so nothing about approving/declining it
                    (Room Bookings -> Bookings) or its email notifications changes — this only adds the front-end
                    widget that was missing before.<br>
                    "Send visitors to a third party" adds a "Booking link" field to each room's edit screen instead —
                    every "Book now" shown on the front end for that room opens it in a new tab. Leave a room's link
                    blank and no "Book now" shows for it. (A built-in payment option, e.g. via SC Commerce, may be
                    added here in future.)
                </p>

                <?php \submit_button('Save'); ?>
            </form>

            <?php if ($roomTypes !== []) : ?>
                <h2>Room types</h2>
                <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                    <?php \wp_nonce_field(self::RELABEL_ACTION); ?>
                    <input type="hidden" name="action" value="<?php echo \esc_attr(self::RELABEL_ACTION); ?>">

                    <table class="widefat striped" style="max-width: 1100px;">
                        <thead>
                            <tr>
                                <th>Singular</th>
                                <th>Plural</th>
                                <th>URL slug</th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roomTypes as $roomType) : ?>
                                <tr>
                                    <td>
                                        <input
                                            type="text"
                                            name="labels[<?php echo \esc_attr($roomType['post_type']); ?>][singular]"
                                            value="<?php echo \esc_attr($roomType['label_singular']); ?>"
                                            class="regular-text"
                                            required
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="labels[<?php echo \esc_attr($roomType['post_type']); ?>][plural]"
                                            value="<?php echo \esc_attr($roomType['label_plural']); ?>"
                                            class="regular-text"
                                            required
                                        >
                                    </td>
                                    <td><code>/<?php echo \esc_html($roomType['slug']); ?>/</code></td>
                                    <td>
                                        <a
                                            href="<?php echo \esc_url(\admin_url('edit.php?post_type='.$roomType['post_type'])); ?>"
                                            class="button"
                                        >View <?php echo \esc_html($roomType['label_plural']); ?></a>
                                    </td>
                                    <td><?php $this->renderDeleteButton($roomType); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="description">
                        Renaming only changes the labels shown in wp-admin — the URL slug stays fixed so existing
                        links never break.
                    </p>

                    <?php \submit_button('Save changes'); ?>
                </form>

                <?php foreach ($roomTypes as $roomType) : ?>
                    <?php $this->renderDeleteDialog($roomType); ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <h2><?php echo $roomTypes === [] ? 'Create your first room type' : 'Add another room type'; ?></h2>

            <?php if ($roomTypes === []) : ?>
                <p>
                    Before SC Room Bookings can take a booking, tell it what to call one bookable space on this site
                    — for example <strong>Room</strong>, <strong>Court</strong>, or <strong>Desk</strong>.
                </p>
            <?php endif; ?>

            <?php if ($this->settings->canAddRoomType()) : ?>
                <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                    <?php \wp_nonce_field(self::ADD_ACTION); ?>
                    <input type="hidden" name="action" value="<?php echo \esc_attr(self::ADD_ACTION); ?>">

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="scrb_label_singular">Singular name</label></th>
                            <td>
                                <input name="label_singular" type="text" id="scrb_label_singular" class="regular-text" placeholder="e.g. Room" required>
                                <p class="description">Used for "Add New Room", "Edit Room", and so on.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="scrb_label_plural">Plural name</label></th>
                            <td>
                                <input name="label_plural" type="text" id="scrb_label_plural" class="regular-text" placeholder="e.g. Rooms" required>
                                <p class="description">Used for the admin menu and archive page, and to build its URL.</p>
                            </td>
                        </tr>
                    </table>

                    <?php \submit_button('Create room type'); ?>
                </form>
            <?php else : ?>
                <p>
                    <?php echo \esc_html(\sprintf(
                        'You\'ve reached the current limit of %d room types.',
                        $this->settings->maxRoomTypes()
                    )); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array{post_type: string, slug: string, label_singular: string, label_plural: string} $roomType
     */
    private function renderDeleteButton(array $roomType): void
    {
        ?>
        <button
            type="button"
            class="button button-link-delete"
            onclick="document.getElementById('scrb-delete-dialog-<?php echo \esc_attr($roomType['post_type']); ?>').showModal()"
        >Delete&hellip;</button>
        <?php
    }

    /**
     * The confirmation dialog for one room type's delete button —
     * typing the plural label (GitHub delete-repo style) is required
     * to enable the submit button; see assets/js/room-types-delete.js.
     * check_admin_referer() in handleDelete() is the real guard
     * against a forged request — the typed-label check only guards
     * against a misclick.
     *
     * @param array{post_type: string, slug: string, label_singular: string, label_plural: string} $roomType
     */
    private function renderDeleteDialog(array $roomType): void
    {
        $postType = $roomType['post_type'];
        $roomCount = $this->roomCountForType($postType);
        $bookingCount = $this->bookingCountForRooms($postType);
        $dialogId = 'scrb-delete-dialog-'.$postType;
        $inputId = 'scrb-delete-confirm-'.$postType;
        ?>
        <dialog id="<?php echo \esc_attr($dialogId); ?>" class="scrb-delete-dialog">
            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>">
                <?php \wp_nonce_field(self::DELETE_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo \esc_attr(self::DELETE_ACTION); ?>">
                <input type="hidden" name="post_type" value="<?php echo \esc_attr($postType); ?>">

                <h2>Delete <?php echo \esc_html($roomType['label_plural']); ?>?</h2>

                <p>
                    This will delete
                    <strong><?php echo \esc_html(\sprintf('%d %s', $roomCount, $roomType['label_plural'])); ?></strong>.
                    They'll be moved to Trash, where they can be restored (or permanently deleted) for 30 days.
                </p>

                <?php if ($bookingCount > 0) : ?>
                    <p>
                        It will also cancel
                        <?php echo \esc_html(\sprintf(
                            $bookingCount === 1 ? '%d booking' : '%d bookings',
                            $bookingCount
                        )); ?>
                        against <?php echo \esc_html(\strtolower($roomType['label_plural'])); ?> of this type — see
                        <a href="<?php echo \esc_url(\admin_url('admin.php?page=scrb-bookings')); ?>">Bookings</a>.
                    </p>
                <?php endif; ?>

                <p>
                    <label for="<?php echo \esc_attr($inputId); ?>">
                        Type <strong><?php echo \esc_html($roomType['label_plural']); ?></strong> to confirm:
                    </label>
                    <br>
                    <input
                        type="text"
                        id="<?php echo \esc_attr($inputId); ?>"
                        name="confirm_label"
                        class="regular-text scrb-delete-confirm-input"
                        data-confirm="<?php echo \esc_attr($roomType['label_plural']); ?>"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                    >
                </p>

                <p>
                    <button type="submit" class="button button-primary scrb-delete-confirm-submit" disabled>
                        Delete <?php echo \esc_html($roomType['label_plural']); ?>
                    </button>
                    <button type="button" class="button" onclick="document.getElementById('<?php echo \esc_attr($dialogId); ?>').close()">Cancel</button>
                </p>
            </form>
        </dialog>
        <?php
    }

    /**
     * Rooms that actually exist for this type right now — the same
     * statuses get_posts(['post_status' => 'any']) returns in
     * handleDelete(), so this count matches what's about to be
     * trashed.
     */
    private function roomCountForType(string $postType): int
    {
        $counts = \wp_count_posts($postType);
        $total = 0;

        foreach (self::DELETABLE_STATUSES as $status) {
            $total += (int) ($counts->$status ?? 0);
        }

        return $total;
    }

    /**
     * Bookings against any room of this type — shown so deleting a
     * type isn't a surprise for whoever's tracking upcoming bookings,
     * even though the bookings themselves are cancelled, not deleted
     * (see handleDelete()).
     */
    private function bookingCountForRooms(string $postType): int
    {
        $roomIds = \get_posts([
            'post_type' => $postType,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if ($roomIds === []) {
            return 0;
        }

        $query = new \WP_Query([
            'post_type' => \SCRoomBookings\PostTypes\BookingPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'meta_query' => [
                ['key' => '_scrb_room_id', 'value' => $roomIds, 'compare' => 'IN'],
            ],
        ]);

        return \count($query->posts);
    }

    public function handleAdd(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-room-bookings'));
        }

        \check_admin_referer(self::ADD_ACTION);

        $singular = \sanitize_text_field(\wp_unslash($_POST['label_singular'] ?? ''));
        $plural = \sanitize_text_field(\wp_unslash($_POST['label_plural'] ?? ''));

        $error = $this->settings->validateNewRoomType($singular, $plural);

        if ($error !== null) {
            \add_settings_error('scrb_settings', 'scrb_invalid', $error);
            \set_transient('settings_errors', \get_settings_errors(), 30);
            \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
            exit;
        }

        $postType = $this->settings->addRoomType($singular, $plural);

        // Deferred to the next request's init — see RoomPostTypes::FLUSH_TRANSIENT.
        \set_transient(RoomPostTypes::FLUSH_TRANSIENT, true, 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&scrb-created='.$postType));
        exit;
    }

    public function handleRelabel(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-room-bookings'));
        }

        \check_admin_referer(self::RELABEL_ACTION);

        $labels = \wp_unslash($_POST['labels'] ?? []);
        $hadError = false;

        foreach ($labels as $postType => $pair) {
            $singular = \sanitize_text_field($pair['singular'] ?? '');
            $plural = \sanitize_text_field($pair['plural'] ?? '');

            if ($singular === '' || $plural === '') {
                $hadError = true;
                continue;
            }

            $this->settings->relabelRoomType(\sanitize_key($postType), $singular, $plural);
        }

        if ($hadError) {
            \add_settings_error('scrb_settings', 'scrb_missing_labels', \__('Every room type needs both a singular and plural name — any left blank were skipped.', 'sc-room-bookings'));
        } else {
            \add_settings_error('scrb_settings', 'scrb_saved', \__('Settings saved.', 'sc-room-bookings'), 'success');
        }

        \set_transient('settings_errors', \get_settings_errors(), 30);
        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
        exit;
    }

    /**
     * Trashes the type's rooms, cancels bookings against them, then
     * removes the type itself — in that order, since the first two
     * steps need Settings::getRoomType() to still find it. The
     * rewrite flush is deferred to the next request for the same
     * reason as handleAdd() — see RoomPostTypes::FLUSH_TRANSIENT.
     *
     * Bookings are *cancelled* (status set to 'cancelled'), not
     * trashed/deleted — a booking is effectively a historical record
     * once it's happened or been requested, and Admin\BookingsPage's
     * own filters already hide cancelled ones from the default view.
     */
    public function handleDelete(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-room-bookings'));
        }

        \check_admin_referer(self::DELETE_ACTION);

        $postType = \sanitize_key(\wp_unslash($_POST['post_type'] ?? ''));
        $confirmLabel = \sanitize_text_field(\wp_unslash($_POST['confirm_label'] ?? ''));

        $roomType = $this->settings->getRoomType($postType);

        if ($roomType === null) {
            \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG));
            exit;
        }

        // Belt-and-braces alongside the JS-disabled submit button —
        // that only stops an accidental click, not a resubmitted or
        // hand-crafted request.
        if (\trim($confirmLabel) !== $roomType['label_plural']) {
            \add_settings_error(
                'scrb_settings',
                'scrb_delete_mismatch',
                \sprintf(
                    'Typed confirmation didn\'t match "%s" — nothing was deleted.',
                    $roomType['label_plural']
                )
            );
            \set_transient('settings_errors', \get_settings_errors(), 30);
            \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
            exit;
        }

        $roomIds = \get_posts([
            'post_type' => $postType,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        if ($roomIds !== []) {
            $bookingIds = (new \WP_Query([
                'post_type' => \SCRoomBookings\PostTypes\BookingPostType::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'no_found_rows' => true,
                'fields' => 'ids',
                'meta_query' => [
                    ['key' => '_scrb_room_id', 'value' => $roomIds, 'compare' => 'IN'],
                ],
            ]))->posts;

            foreach ($bookingIds as $bookingId) {
                \SCRoomBookings\Booking\Booking::setStatus($bookingId, \SCRoomBookings\PostTypes\BookingPostType::STATUS_CANCELLED);
            }
        }

        foreach ($roomIds as $roomId) {
            \wp_trash_post($roomId);
        }

        $this->settings->deleteRoomType($postType);

        // Deferred to the next request's init — see RoomPostTypes::FLUSH_TRANSIENT.
        \set_transient(RoomPostTypes::FLUSH_TRANSIENT, true, 30);

        \add_settings_error(
            'scrb_settings',
            'scrb_deleted',
            \sprintf(
                '"%s" deleted — %d moved to Trash.',
                $roomType['label_plural'],
                \count($roomIds)
            ),
            'success'
        );
        \set_transient('settings_errors', \get_settings_errors(), 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
        exit;
    }

    public function handleToggleSimpleMode(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-room-bookings'));
        }

        \check_admin_referer(self::SIMPLE_MODE_ACTION);

        $this->settings->setSimpleMode(! empty($_POST['simple_mode']));

        \add_settings_error('scrb_settings', 'scrb_saved', \__('Settings saved.', 'sc-room-bookings'), 'success');
        \set_transient('settings_errors', \get_settings_errors(), 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
        exit;
    }

    public function handleSetBookingMode(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-room-bookings'));
        }

        \check_admin_referer(self::BOOKING_MODE_ACTION);

        $this->settings->setBookingMode(\sanitize_key(\wp_unslash($_POST['booking_mode'] ?? '')));

        \add_settings_error('scrb_settings', 'scrb_saved', \__('Settings saved.', 'sc-room-bookings'), 'success');
        \set_transient('settings_errors', \get_settings_errors(), 30);

        \wp_safe_redirect(\admin_url('admin.php?page='.self::PAGE_SLUG.'&settings-updated=true'));
        exit;
    }

    public function maybeShowCreatedNotice(): void
    {
        $postType = \sanitize_key(\wp_unslash($_GET['scrb-created'] ?? ''));

        if ($postType === '') {
            return;
        }

        $roomType = $this->settings->getRoomType($postType);

        if ($roomType === null) {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            \esc_html(\sprintf('"%s" is ready — start adding your first one below.', $roomType['label_plural']))
        );
    }
}

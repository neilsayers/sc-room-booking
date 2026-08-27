<?php

namespace SCRoomBookings\Admin;

use SCRoomBookings\Booking\Booking;
use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\PostTypes\BookingPostType;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\BookingMeta;

/**
 * "Bookings" — every request against every room type, one list
 * rather than wp-admin's native per-post-type screens (a booking
 * doesn't have "content" worth a post-edit screen, just a handful of
 * fields best scanned as a table — see PostTypes\BookingPostType's
 * docblock for why show_ui is false there).
 */
final class BookingsPage implements Hookable
{
    private const PAGE_SLUG = 'scrb-bookings';
    private const STATUS_ACTION = 'scrb_update_booking_status';

    /**
     * Statuses shown when no ?status filter is in the URL — the ones
     * still awaiting something, rather than history. Declined/
     * cancelled bookings aren't hidden forever, just one click away
     * via the filter links instead of cluttering the default view.
     */
    private const DEFAULT_VISIBLE_STATUSES = [BookingPostType::STATUS_PENDING, BookingPostType::STATUS_CONFIRMED];

    private const STATUS_COLOURS = [
        BookingPostType::STATUS_PENDING => '#dba617',
        BookingPostType::STATUS_CONFIRMED => '#00854a',
        BookingPostType::STATUS_DECLINED => '#d63638',
        BookingPostType::STATUS_CANCELLED => '#787c82',
    ];

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        \add_action('admin_post_'.self::STATUS_ACTION, [$this, 'handleStatusUpdate']);
    }

    public function enqueueAssets(): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_style('scrb-admin', SCRB_URL.'assets/css/admin.css', [], SCRB_VERSION);
    }

    public function registerMenu(): void
    {
        // Simple mode (Settings::simpleMode()) is for sites only using
        // this plugin to list rooms, not to take bookings — see
        // Admin\RoomTypesPage's own checkbox for the fuller reasoning.
        // This only hides the menu entry; the booking system itself
        // stays fully registered and working underneath.
        if ($this->settings->simpleMode()) {
            return;
        }

        \add_submenu_page(
            'scrb-settings',
            'Bookings',
            'Bookings',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }

        $roomTypes = $this->settings->allRoomTypes();

        if ($roomTypes === []) {
            ?>
            <div class="wrap">
                <h1>Bookings</h1>
                <p>
                    <a href="<?php echo \esc_url(\admin_url('admin.php?page=scrb-settings')); ?>">Create a room type</a>
                    and add a room first — bookings are made against one.
                </p>
            </div>
            <?php

            return;
        }

        $statusFilter = \sanitize_key(\wp_unslash($_GET['status'] ?? ''));
        $bookings = $this->fetchBookings($statusFilter);
        ?>
        <div class="wrap">
            <h1>Bookings</h1>

            <?php \settings_errors('scrb_bookings'); ?>

            <ul class="subsubsub">
                <li><a href="<?php echo \esc_url(\admin_url('admin.php?page='.self::PAGE_SLUG)); ?>" <?php echo $statusFilter === '' ? 'class="current"' : ''; ?>>Upcoming</a> |</li>
                <?php foreach (BookingPostType::STATUSES as $status => $label) : ?>
                    <li>
                        <a href="<?php echo \esc_url(\admin_url('admin.php?page='.self::PAGE_SLUG.'&status='.$status)); ?>" <?php echo $statusFilter === $status ? 'class="current"' : ''; ?>><?php echo \esc_html($label); ?></a>
                        <?php if ($status !== \array_key_last(BookingPostType::STATUSES)) : ?> |<?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <li>| <a href="<?php echo \esc_url(\admin_url('admin.php?page='.self::PAGE_SLUG.'&status=all')); ?>" <?php echo $statusFilter === 'all' ? 'class="current"' : ''; ?>>All</a></li>
            </ul>
            <br class="clear">

            <?php if ($bookings === []) : ?>
                <p>No bookings here.</p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>When</th>
                            <th>Requested by</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking) : ?>
                            <?php $this->renderRow($booking); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function renderRow(array $booking): void
    {
        $room = \get_post($booking['room_id']);
        $roomName = $room ? \get_the_title($room) : \__('(room deleted)', 'sc-room-bookings');
        $start = new \DateTimeImmutable($booking['start_datetime']);
        $end = new \DateTimeImmutable($booking['end_datetime']);
        $colour = self::STATUS_COLOURS[$booking['status']] ?? '#787c82';
        ?>
        <tr>
            <td><?php echo \esc_html($roomName); ?></td>
            <td>
                <?php echo \esc_html($start->format('D j M Y, g:ia')); ?> &ndash; <?php echo \esc_html($end->format('g:ia')); ?>
            </td>
            <td>
                <?php echo \esc_html($booking['requester_name']); ?><br>
                <a href="mailto:<?php echo \esc_attr($booking['requester_email']); ?>"><?php echo \esc_html($booking['requester_email']); ?></a>
                <?php if ($booking['requester_phone'] !== '') : ?>
                    <br><?php echo \esc_html($booking['requester_phone']); ?>
                <?php endif; ?>
                <?php if ($booking['requester_message'] !== '') : ?>
                    <p class="description"><?php echo \esc_html($booking['requester_message']); ?></p>
                <?php endif; ?>
            </td>
            <td>
                <span class="scrb-status-badge" style="background: <?php echo \esc_attr($colour); ?>">
                    <?php echo \esc_html(BookingPostType::STATUSES[$booking['status']] ?? $booking['status']); ?>
                </span>
            </td>
            <td>
                <?php if ($booking['status'] === BookingPostType::STATUS_PENDING) : ?>
                    <?php $this->renderStatusForm($booking['id'], BookingPostType::STATUS_CONFIRMED, 'Confirm', 'button-primary'); ?>
                    <?php $this->renderStatusForm($booking['id'], BookingPostType::STATUS_DECLINED, 'Decline', 'button'); ?>
                <?php elseif ($booking['status'] === BookingPostType::STATUS_CONFIRMED) : ?>
                    <?php $this->renderStatusForm($booking['id'], BookingPostType::STATUS_CANCELLED, 'Cancel', 'button'); ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function renderStatusForm(int $bookingId, string $status, string $label, string $buttonClass): void
    {
        ?>
        <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" style="display:inline;">
            <?php \wp_nonce_field(self::STATUS_ACTION); ?>
            <input type="hidden" name="action" value="<?php echo \esc_attr(self::STATUS_ACTION); ?>">
            <input type="hidden" name="booking_id" value="<?php echo \esc_attr((string) $bookingId); ?>">
            <input type="hidden" name="status" value="<?php echo \esc_attr($status); ?>">
            <button type="submit" class="button <?php echo \esc_attr($buttonClass); ?> button-small"><?php echo \esc_html($label); ?></button>
        </form>
        <?php
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchBookings(string $statusFilter): array
    {
        $args = [
            'post_type' => BookingPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'no_found_rows' => true,
            'fields' => 'ids',
        ];

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $args['meta_query'] = [
                ['key' => '_scrb_status', 'value' => \sanitize_key($statusFilter), 'compare' => '='],
            ];
        }

        $ids = (new \WP_Query($args))->posts;
        $bookings = \array_map(static fn (int $id): array => ['id' => $id, ...BookingMeta::read($id)], $ids);

        if ($statusFilter === '') {
            $bookings = \array_values(\array_filter(
                $bookings,
                static fn (array $b): bool => \in_array($b['status'], self::DEFAULT_VISIBLE_STATUSES, true)
            ));
        }

        \usort($bookings, static fn (array $a, array $b): int => $a['start_datetime'] <=> $b['start_datetime']);

        return $bookings;
    }

    public function handleStatusUpdate(): void
    {
        if (! \current_user_can('manage_options')) {
            \wp_die(\esc_html__('You are not allowed to do this.', 'sc-room-bookings'));
        }

        \check_admin_referer(self::STATUS_ACTION);

        $bookingId = \absint($_POST['booking_id'] ?? 0);
        $status = \sanitize_key(\wp_unslash($_POST['status'] ?? ''));

        if ($bookingId > 0 && \array_key_exists($status, BookingPostType::STATUSES)) {
            Booking::setStatus($bookingId, $status);
        }

        \wp_safe_redirect(\wp_get_referer() ?: \admin_url('admin.php?page='.self::PAGE_SLUG));
        exit;
    }
}

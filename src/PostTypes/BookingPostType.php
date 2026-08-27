<?php

namespace SCRoomBookings\PostTypes;

use SCRoomBookings\Contracts\Hookable;

/**
 * A single global "Booking" post type shared across every room type —
 * a booking always belongs to exactly one room, but isn't a kind of
 * room itself, so unlike room types this one is fixed rather than
 * user-named, and always registered. Mirrors SC Events Manager's
 * Venue post type for the same reason: a request/room-details/booking
 * for it, not something the site should be naming/relabelling.
 *
 * Not public — a booking has no front-end page of its own. It's
 * managed entirely through Admin\BookingsPage and created via
 * Frontend\template-functions.php's scrb_request_booking() (form
 * submission, REST, wherever), never through wp-admin's native
 * post-new.php screen.
 */
final class BookingPostType implements Hookable
{
    public const POST_TYPE = 'scrb_booking';

    /**
     * Every status a booking can be in. Stored in postmeta
     * (_scrb_status) rather than as a custom post_status: a booking
     * is always a "real" post (never itself trashed until the whole
     * request is deleted), so there's no need for WordPress's own
     * publish/draft/trash machinery to also track this — see
     * Booking\Booking for the class that reads/writes it.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_DECLINED => 'Declined',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public function register(): void
    {
        \add_action('init', [$this, 'registerPostType']);
    }

    public function registerPostType(): void
    {
        \register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Bookings',
                'singular_name' => 'Booking',
                'edit_item' => 'Edit Booking',
                'view_item' => 'View Booking',
                'search_items' => 'Search Bookings',
                'not_found' => 'No bookings found',
                'not_found_in_trash' => 'No bookings found in Trash',
                'all_items' => 'Bookings',
                'menu_name' => 'Bookings',
                'name_admin_bar' => 'Booking',
            ],
            'public' => false,
            'show_ui' => false, // Admin\BookingsPage is the only UI for these — no native post-new/edit screens.
            'show_in_rest' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }
}

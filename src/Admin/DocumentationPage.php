<?php

namespace SCRoomBookings\Admin;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;

/**
 * "Documentation" — a plain reference for every way this plugin's
 * room/availability/booking data can be used: the PHP functions and
 * the REST API. Mirrors SC Events Manager's own Documentation page —
 * same shape, same reasoning (see that plugin's DocumentationPage for
 * the fuller version of this docblock).
 */
final class DocumentationPage implements Hookable
{
    private const PAGE_SLUG = 'scrb-documentation';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
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
        // See Admin\RoomTypesPage's simple-mode checkbox and
        // Admin\BookingsPage::registerMenu() for the fuller reasoning.
        if ($this->settings->simpleMode()) {
            return;
        }

        \add_submenu_page(
            'scrb-settings',
            'Documentation',
            'Documentation',
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
        ?>
        <div class="wrap scrb-docs">
            <h1>SC Room Bookings — Documentation</h1>

            <p>
                Two ways to check availability and take a booking, both backed by the same
                <code>Booking\AvailabilityChecker</code> and <code>Booking\Booking</code> classes — a slot that's free
                (or taken) reads the same whichever one you use.
            </p>

            <h2>PHP: <code>scrb_get_rooms()</code> / <code>scrb_check_availability()</code> / <code>scrb_request_booking()</code></h2>
            <p>For theme code on this same site — no HTTP round trip.</p>
            <pre class="scrb-docs-code">$rooms = scrb_get_rooms(); // every room; scrb_get_rooms(['type' => 'room']) narrows to one type

$result = scrb_check_availability($roomId, '2026-09-12 14:00', '2026-09-12 16:00');
// true, or a human-readable reason it's not available

$booking = scrb_request_booking([
    'room_id' =&gt; $roomId,
    'start'   =&gt; '2026-09-12 14:00',
    'end'     =&gt; '2026-09-12 16:00',
    'name'    =&gt; 'Jane Smith',
    'email'   =&gt; 'jane@example.com',
]);
// int (the new booking's post ID) or WP_Error</pre>
            <p class="description">
                Both are the plugin's stable PHP contract: <code>Booking\AvailabilityChecker</code> and
                <code>Booking\Booking</code> underneath them can change between versions, but these two functions'
                parameters and return shapes won't, within a major version.
            </p>

            <h2>REST: <code>scrb/v1</code></h2>
            <p>For anything outside this site's own PHP. All three are public and unauthenticated — same trust model as a plain HTML form; see <code>Frontend\BookingsRestController</code>'s own docblock for why.</p>

            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Method</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code><?php echo \esc_html(\rest_url('scrb/v1/rooms')); ?></code></td>
                        <td>GET</td>
                        <td>Every room, across every configured type. Optional <code>?type=</code> to narrow to one.</td>
                    </tr>
                    <tr>
                        <td><code><?php echo \esc_html(\rest_url('scrb/v1/availability')); ?></code></td>
                        <td>GET</td>
                        <td>Params: <code>room_id</code>, <code>start</code>, <code>end</code>. Returns <code>{"available": bool, "reason": string|null}</code>.</td>
                    </tr>
                    <tr>
                        <td><code><?php echo \esc_html(\rest_url('scrb/v1/bookings')); ?></code></td>
                        <td>POST</td>
                        <td>Params: <code>room_id</code>, <code>start</code>, <code>end</code>, <code>name</code>, <code>email</code>, optional <code>phone</code>/<code>message</code>. Returns <code>{"booking_id": int, "status": string}</code> (201) or a 422 with an error reason.</td>
                    </tr>
                </tbody>
            </table>

            <p>
                <strong>v1 is a promise:</strong> existing fields won't be renamed or removed within it. A field can
                be added; a genuinely breaking change gets its own <code>scrb/v2</code> route instead.
            </p>

            <h2>Hooks</h2>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th>Hook</th>
                        <th>Fires when&hellip;</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>do_action('scrb_booking_created', int $bookingId, array $data)</code></td>
                        <td>A booking has just been persisted (any status). This plugin's own email notifications hook here — see <code>Notifications\BookingNotifications</code> for the reference implementation if you want to replace or add to it (Slack, SMS, a calendar sync, &hellip;).</td>
                    </tr>
                    <tr>
                        <td><code>do_action('scrb_booking_status_changed', int $bookingId, string $status, string $previous)</code></td>
                        <td>An existing booking's status actually changed (not fired if set to the same status it already had).</td>
                    </tr>
                    <tr>
                        <td><code>apply_filters('scrb_max_room_types', int $max)</code></td>
                        <td>Raise the cap on how many room types a site can configure. Default 4.</td>
                    </tr>
                    <tr>
                        <td><code>apply_filters('scrb_min_notice_hours', int $hours, int $roomId)</code></td>
                        <td>Override the site-wide minimum notice period for one room specifically.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}

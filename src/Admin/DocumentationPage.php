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
                Three ways to show rooms and take a booking on the front end — a bundled default template, a
                shortcode, or your own theme markup against the plugin's data API — plus two ways to check
                availability and submit a booking, all backed by the same <code>Booking\AvailabilityChecker</code>
                and <code>Booking\Booking</code> classes underneath, so a slot that's free (or taken) reads the same
                whichever one you use.
            </p>

            <h2>Front end: default templates, shortcodes, or your own markup</h2>
            <p>
                Every room type gets a working single/archive page the moment it's created — no theme changes
                needed. <code>Frontend\DefaultTemplates</code> serves its own single-room.php/archive-room.php the
                moment a room type has no <code>single-&#123;post_type&#125;.php</code>/<code>archive-&#123;post_type&#125;.php</code>
                of the theme's own (and never on a Sage/Acorn theme, which always owns its whole template hierarchy
                itself) — that's what a brand new install looks like straight after creating a room type.
            </p>
            <p>To place a room (or a grid of them) anywhere else — a page, a widget area, mixed in with other content:</p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[sc_rooms]</code></td>
                        <td>
                            A grid of every configured room type. <code>type="room"</code> (or a comma list) narrows
                            to specific types; <code>limit="6"</code> caps how many show. Also callable directly from
                            a theme template as <code>Frontend\RoomListingShortcode::render(['type' =&gt; 'room'])</code>.
                        </td>
                    </tr>
                    <tr>
                        <td><code>[sc_room id="123"]</code></td>
                        <td>
                            One room's full detail — image, description, price table, facilities, accessibility.
                            <code>id</code> defaults to the current post, so a bare <code>[sc_room]</code> also works
                            from inside a room's own content.
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="description">
                Adding either shortcode to a specific page doesn't need typing it by hand: a "SC Room Bookings"
                button in the classic editor's toolbar (<code>Admin\ShortcodeButton</code>) opens a small dialog to
                build and insert one, and the block editor has the same two options as proper blocks (Room Listing,
                Room Detail — <code>Blocks\Blocks</code>) with a live preview while editing. Both call the exact same
                <code>RoomListingShortcode::render()</code>/<code>RoomDetailShortcode::render()</code> the shortcodes
                themselves use, so a block, a shortcode, and this button's output can never drift apart.
            </p>
            <p class="description">
                Want your own look instead? Add your theme's own <code>single-&#123;post_type&#125;.php</code>/
                <code>archive-&#123;post_type&#125;.php</code> (or, on a Sage theme, a
                <code>content-single-&#123;post_type&#125;.blade.php</code> partial) and build it against the PHP
                functions below — <code>Frontend\DefaultTemplates</code> steps aside automatically the moment it
                sees one.
            </p>

            <h2>PHP: <code>scrb_get_rooms()</code> / <code>scrb_get_room()</code> / <code>scrb_check_availability()</code> / <code>scrb_request_booking()</code></h2>
            <p>For theme code on this same site — no HTTP round trip.</p>
            <pre class="scrb-docs-code">$rooms = scrb_get_rooms(); // every room; scrb_get_rooms(['type' => 'room']) narrows to one type
$room  = scrb_get_room($roomId); // one room, same shape as a scrb_get_rooms() row; defaults to the current post

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
                All four are the plugin's stable PHP contract: <code>Booking\AvailabilityChecker</code> and
                <code>Booking\Booking</code> underneath them can change between versions, but these functions'
                parameters and return shapes won't, within a major version.
            </p>

            <h2>Booking mode: how a visitor actually books</h2>
            <p>
                Set on Room Bookings &rarr; Room Types &rarr; Booking, site-wide. Three options:
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th>Mode</th>
                        <th>What happens</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>No online booking</td>
                        <td>The default. Nothing books-related shows on the front end at all.</td>
                    </tr>
                    <tr>
                        <td>Visitors can request a time</td>
                        <td>
                            Every room gets a "View availability" button (<code>Frontend\BookingWidget</code>, also
                            callable directly as <code>scrb_render_booking_widget($roomId)</code>): a week-view
                            calendar (tap a start time, then an end time — deliberately not press-and-drag, which
                            fights page scroll on a phone), then a name/email form. Submitting it calls
                            <code>scrb_request_booking()</code> exactly as shown above, so it lands in Room Bookings
                            &rarr; Bookings and fires the same notifications as any other request.
                        </td>
                    </tr>
                    <tr>
                        <td>Send visitors to a third-party website</td>
                        <td>
                            Adds a "Booking link" field to each room's edit screen (<code>booking_url</code>, also in
                            <code>scrb_get_rooms()</code>/<code>scrb_get_room()</code>/REST). Every "Book now" for
                            that room opens the link in a new tab instead. A room with no link set shows no "Book
                            now" at all.
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2>REST: <code>scrb/v1</code></h2>
            <p>For anything outside this site's own PHP. All four are public and unauthenticated — same trust model as a plain HTML form; see <code>Frontend\BookingsRestController</code>'s own docblock for why.</p>

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
                        <td>Every room, across every configured type. Optional <code>?type=</code> to narrow to one (or a comma list).</td>
                    </tr>
                    <tr>
                        <td><code><?php echo \esc_html(\rest_url('scrb/v1/availability')); ?></code></td>
                        <td>GET</td>
                        <td>Params: <code>room_id</code>, <code>start</code>, <code>end</code>. Returns <code>{"available": bool, "reason": string|null}</code> for that one candidate slot.</td>
                    </tr>
                    <tr>
                        <td><code><?php echo \esc_html(\rest_url('scrb/v1/rooms/123/schedule')); ?></code></td>
                        <td>GET</td>
                        <td>
                            Params: <code>start</code>, <code>end</code> (a date range, not one candidate slot). Returns
                            the room's opening days/hours/minimum booking length/buffer plus every existing booking's
                            already-buffered blocked range in that window — what the booking widget's calendar uses
                            to shade a week view. Backed by <code>AvailabilityChecker::blockedRanges()</code>.
                        </td>
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

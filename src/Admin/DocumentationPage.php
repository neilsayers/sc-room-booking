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
                SC Room Bookings turns a WordPress site into a simple booking system for any kind of bookable space
                — a meeting room, a sports court, a hot desk, whatever suits your site. Set up one or more types of
                space, add photos, pricing and facilities to each one, and choose whether visitors can request a
                time online, get sent to a booking site you already use, or just browse without booking at all.
                The guide below covers everyday set-up first; further down there's more technical detail for
                developers extending or customising the plugin.
            </p>

            <h2>Getting started</h2>

            <h3>Creating your first room type</h3>
            <p>
                Head to <strong>SC Room Bookings</strong> in the wp-admin menu. The first time you visit, you'll be
                asked what to call the kind of space you're managing — for example <strong>Room</strong>,
                <strong>Court</strong>, or <strong>Desk</strong> (WordPress needs both a singular name, like "Room",
                and a plural one, like "Rooms"). Once created, a new menu item appears where you can add as many
                individual rooms as you like. You can set up a few different types of space side by side too — Rooms
                and Desks together, say — up to a limit of four.
            </p>

            <h3>Adding rooms and facilities</h3>
            <p>
                Each room gets its own edit screen — a photo, description, capacity, price, accessibility notes,
                opening days and hours, and more. Facilities (Wi-Fi, wheelchair access, a projector, and so on) are
                shared across every room, so you only add "Wi-Fi" once from
                <strong>Room Bookings &rarr; Facilities</strong>, then simply tick it on for any room that has it.
            </p>

            <h3>Reordering rooms</h3>
            <p>
                Rooms are listed in the order they were created by default. If you'd like a particular one to show
                first — your most popular room, say — visit <strong>Room Bookings &rarr; Reorder rooms</strong> and
                drag them into the order you'd like. This saves automatically as you drag, no need to click Save.
            </p>

            <h3>How visitors book</h3>
            <p>
                Under <strong>Room Bookings &rarr; Room Types</strong>, the Booking section controls what a visitor
                can actually do:
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th>Mode</th>
                        <th>What visitors see</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>No online booking</td>
                        <td>The default. Rooms are shown for browsing only — nothing booking-related appears.</td>
                    </tr>
                    <tr>
                        <td>Visitors can request a time</td>
                        <td>
                            Adds a "View availability" button to each room, showing a simple calendar of what's
                            free. A visitor picks a time, fills in their name and email, and their request lands in
                            <strong>Room Bookings &rarr; Bookings</strong> for you to approve.
                        </td>
                    </tr>
                    <tr>
                        <td>Send visitors to a third-party website</td>
                        <td>
                            Adds a "Booking link" field to each room's edit screen. "Book now" then simply opens
                            that link — a booking system you already use elsewhere, say — in a new tab. Leave a
                            room's link blank and no "Book now" shows for it.
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Simple mode</h3>
            <p>
                If you only want to showcase your rooms — with no booking at all, not even a link — turn on
                <strong>Simple mode</strong> at the top of <strong>Room Bookings &rarr; Room Types</strong>. This
                hides the Bookings, Documentation and Facilities menus, and trims each room's edit screen down to
                just the basics. Nothing already saved (pricing, availability, facilities) is lost — turning it off
                again brings it all straight back.
            </p>

            <h3>Showing rooms on your site</h3>
            <p>
                The moment a room type is created, it already has its own pages on your site — a listing page and a
                page for each individual room — with no extra work needed.
            </p>
            <p>To show a room, or a grid of rooms, somewhere else too — your homepage, a page, wherever:</p>
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
                            A grid of every room. <code>type="room"</code> (or a comma list) narrows it to specific
                            types; <code>limit="6"</code> caps how many show.
                        </td>
                    </tr>
                    <tr>
                        <td><code>[sc_room id="123"]</code></td>
                        <td>
                            One room's full detail — image, description, price table, facilities, accessibility.
                            <code>id</code> defaults to the current room, so a bare <code>[sc_room]</code> also works
                            when placed on that room's own page.
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="description">
                You don't need to type either of these by hand: a "SC Room Bookings" button in the classic editor's
                toolbar opens a small dialog to build and insert one, and the block editor has the same two options
                as proper blocks (Room Listing, Room Detail) with a live preview while you're editing.
            </p>

            <p>
                Now we'll go into more technical detail below — you might need to contact a web developer for help
                with the following.
            </p>

            <h2>Technical detail</h2>

            <h3>Front end: templates and your own markup</h3>
            <p>
                Every room type gets a working single/archive page the moment it's created — no theme changes
                needed. <code>Frontend\DefaultTemplates</code> serves its own single-room.php/archive-room.php the
                moment a room type has no <code>single-&#123;post_type&#125;.php</code>/<code>archive-&#123;post_type&#125;.php</code>
                of the theme's own — that's what a brand new install looks like straight after creating a room type.
            </p>
            <p class="description">
                Want your own look instead? Add your theme's own <code>single-&#123;post_type&#125;.php</code>/
                <code>archive-&#123;post_type&#125;.php</code> and build it against the PHP functions below —
                <code>Frontend\DefaultTemplates</code> steps aside automatically the moment it sees one.
            </p>
            <p class="description">
                The shortcode button (<code>Admin\ShortcodeButton</code>) and the two Gutenberg blocks
                (<code>Blocks\Blocks</code>) covered above both call the exact same
                <code>RoomListingShortcode::render()</code>/<code>RoomDetailShortcode::render()</code> the shortcodes
                themselves use, so a block, a shortcode, and the toolbar button's output can never drift apart.
            </p>

            <h3>PHP: <code>scrb_get_rooms()</code> / <code>scrb_get_room()</code> / <code>scrb_check_availability()</code> / <code>scrb_request_booking()</code></h3>
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

            <h3>Booking mode: implementation reference</h3>
            <p>
                Set on Room Bookings &rarr; Room Types &rarr; Booking (see "How visitors book" above for the
                friendlier version of this). The underlying classes and data for each of the three options:
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

            <h3>REST: <code>scrb/v1</code></h3>
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

            <h3>Hooks</h3>
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

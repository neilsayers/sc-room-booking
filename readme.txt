=== SC Room Bookings ===
Contributors: screencandy
Tags: bookings, rooms, availability, calendar
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A site-agnostic room/space booking manager. Guides you through naming your own bookable post types before anything
is registered.

== Description ==

SC Room Bookings is built to be dropped into any WordPress site as-is — no build step, no Composer install. Rather
than hard-coding a single "Room" post type, it's managed from one "Room Bookings" settings screen where you create
your own room types with their own singular/plural labels (e.g. "Room" / "Rooms", "Court" / "Courts", "Desk" /
"Desks") — up to 4 by default. That cap is filterable, e.g.:

    add_filter('scrb_max_room_types', fn () => 8);

For each type, the underlying post type key and URL slug are locked in the moment it's created — renaming its labels
afterwards never orphans existing rooms or breaks their links. Types can be deleted from the screen too, but only
after typing the type's plural label to confirm — doing so moves every one of its rooms to Trash (a normal 30-day
recovery window, nothing is hard-deleted) and cancels any bookings made against them (bookings themselves are never
deleted, only marked Cancelled — see "Data model" below).

Each room gets a hand-written "Room Details" box: capacity, what it's suitable for, accessibility notes, one or more
alternate layout options (each with its own capacity and photo — e.g. a hall that's 300 cleared, 220 theatre-style,
150 cabaret), one or more price options (a default price plus, optionally, further tiers like "Off-peak
non-commercial"/"Peak commercial"), which days of the week it's available, its daily opening/closing time, a minimum
booking length, and a buffer required between bookings. Facilities are a proper taxonomy (Taxonomies\FacilityTaxonomy)
rather than free text per room — manage the shared list once from Room Bookings -> Facilities (WordPress's own
term-manager screen, nothing custom-built), then just tick which apply on each room.

A booking always has a room, a start/end date-time, and a requester's name/email (phone and a free-text message are
optional). Availability checking (Booking\AvailabilityChecker) weighs up the room's own opening days/hours, its
minimum booking length, the site's minimum notice period, and — critically — every *pending* booking against that
room as well as confirmed ones, so two people racing to request the same slot both see it as taken rather than the
first approval silently bumping the second request.

Whether a booking needs an admin to confirm it before it's binding (the default) or is auto-confirmed on submission
is a site-wide setting. Either way, new requests and status changes fire plain `wp_mail()` notifications
(Notifications\BookingNotifications) — no queue, no third-party mail service, and easy to replace or extend by
hooking `scrb_booking_created` / `scrb_booking_status_changed` directly.

Every room and every availability/booking action is available three ways — plain PHP functions
(`scrb_check_availability()`, `scrb_request_booking()`), a public/versioned REST API (`scrb/v1`), or by reading the
source directly — see Room Bookings -> Documentation in wp-admin for the full reference, including every hook.

Out of the box — no theme customisation needed — every room type gets its own listing page (`[sc_rooms]`, or its own
`/{slug}/` archive URL) and single-room page (`[sc_room id="123"]`, or the room's own permalink), so a site that's
never touched a template file still has something worth looking at the moment it activates this plugin. A theme that
wants its own look just adds a `single-{post_type}.php`/`archive-{post_type}.php` (or, on a Sage/Acorn theme, its own
Blade partial) and this plugin's own version steps aside automatically — see Frontend\DefaultTemplates.

Some sites only want the room listing — not bookings at all. Ticking "Use SC Room Bookings in simple mode" on the
main Room Bookings screen trims each room's edit screen down to just Capacity and Suitable for, and hides the
Bookings, Documentation and Facilities menu items. Everything else (accessibility, layout options, pricing,
availability, facilities, the booking system itself) stays exactly as it was, untouched, ready to reappear the
moment the box is unticked again.

== Data model ==

Rooms are regular posts (one post type per configured room type). Bookings are their own fixed `scrb_booking` post
type — never renamed, never shown on wp-admin's native post-list/edit screens (manage them from Room Bookings ->
Bookings instead), and never trashed by a room-type deletion, only marked Cancelled — a booking is a historical
record of a request once it's been made, not disposable content.

== Changelog ==

= 0.7.3 =
* The dialog's "Book {Room}" title is now a real `<h2>` (was a styled `<p>`), with `aria-labelledby` wired up on
  the `<dialog>` itself — gives assistive tech a proper heading/landmark for the dialog's purpose. Purely markup;
  the CSS already fully controlled its look, so nothing changes visually.

= 0.7.2 =
* Added a small "You have selected..." label above the chosen date/time in the request form, so it reads as an
  answer to a question rather than just a fact dropped on the page.

= 0.7.1 =
* Fixed the request-a-time widget's dialog not being centred on screen — a Tailwind-based theme's global margin
  reset was silently breaking the `<dialog>` element's own default centring, restored with an explicit `margin:
  auto`.
* Fixed a real booking-blocking bug: after "Change times" (or completing one selection), the calendar's day columns
  rendered collapsed on top of each other, and picking a second start/end time afterwards would silently fail —
  visible to users as "I can't seem to book more than 30 minutes". Root cause was the same in both cases: hiding
  the calendar's container element and showing it again leaves FullCalendar's own internal size measurement stale,
  since it has no way to know the container's visibility changed. Fixed by calling `calendar.updateSize()`
  immediately after un-hiding it.
* The provisional "you tapped a start time" marker no longer intercepts clicks near it (rendered as a background
  event, like blocked ranges already were, instead of a normal foreground one) — a smaller contributing cause of
  the same "can't pick an end time near the start" symptom above.
* Added a fade-in/out transition to the dialog and its backdrop (respects prefers-reduced-motion), and restyled
  "Change times" as a bordered secondary button with a back arrow, and the calendar's prev/next/today buttons with
  slightly more rounded corners — all pure CSS/JS polish, no behaviour change.

= 0.7.0 =
* Added a third Booking option (Room Bookings -> Room Types): "Visitors can request a time and discuss by email". Every room gets a "View availability" button showing a week view (FullCalendar, vendored at assets/vendor/fullcalendar the same way SC Events Manager vendors Leaflet) — tap a start time, then an end time (deliberately not press-and-drag, which fights page scroll on a phone), fill in name/email (phone and a message are optional), submit. That request goes through scrb_request_booking() exactly as if it came through the REST API directly, so every existing piece — availability checking, the admin/pending/confirmed/declined workflow (Room Bookings -> Bookings), both email notifications — needed zero changes; this only adds the front-end widget that was missing (see scrb_request_booking()'s own long-standing "No public-facing booking form/calendar UI yet" note, now out of date).
* Added `GET /rooms/{id}/schedule` (scrb/v1) and `AvailabilityChecker::blockedRanges()`: a room's opening days/hours/minimum booking length/buffer plus every existing booking's already-buffered blocked range in a date window — what the new calendar widget needs to shade a week view, as opposed to `GET /availability`'s existing one-candidate-slot-at-a-time check.
* Added `Frontend\BookingWidget` and the `scrb_render_booking_widget(?int $postId = null)` template tag — same "plugin draws a sensible default, theme can call the same building block directly" split as scrb_get_room()/RoomDetail.

= 0.6.0 =
* Added a "Booking" section to the main Room Bookings settings screen: "No online booking link" (the previous,
  only behaviour) or "Send visitors to a third-party website to book". The second option adds a "Booking link"
  field to every room's edit screen (Room Bookings -> Room Types), and RoomDetail/`[sc_room]`/DefaultTemplates'
  bundled single-room.php all show a "Book now" button opening it in a new tab whenever a room has one set — a
  room without a link set just shows no "Book now" at all. Exposed as the new `booking_url` field via
  scrb_get_rooms()/scrb_get_room()/REST, so a theme can build its own "Book now" against it too (e.g.
  civic-centre-uckfield's own content-single-room.blade.php/room-accordion.blade.php). This is deliberately just
  a link-out, not a payment integration — see Settings::bookingMode()'s own docblock for why a future gateway
  (e.g. via SC Commerce) would be a new mode here, not a rename of this one.

= 0.5.0 =
* Added front-end shortcodes: `[sc_rooms]` (a grid of every configured room type, narrow with `type="room"` or a
  comma list, limit with `limit="6"`) and `[sc_room id="123"]` (one room's full detail — image, price table,
  facilities, accessibility). Both also callable directly from a theme template via
  `Frontend\RoomListingShortcode::render()`/`RoomDetailShortcode`.
* Added `scrb_get_room(?int $postId = null)` alongside the existing `scrb_get_rooms()` — one room, shaped the same
  way, defaulting to the current post. Backed by the new `Frontend\RoomListing::find()`.
* Added `Frontend\DefaultTemplates`: a single-room/archive-room view served automatically the moment this plugin's
  activated on a site whose theme hasn't customised that room type's template yet, via the plain CSS in
  `assets/css/frontend.css`. Steps aside the instant a theme adds its own `single-{post_type}.php`/
  `archive-{post_type}.php`, and never engages at all on a Sage/Acorn theme (which always owns its whole template
  hierarchy itself) — see that class's own docblock for the reasoning.
* `RoomListing::query()`'s `type` argument (and so `[sc_rooms]`'s own `type` attribute) now accepts a comma-separated
  list of room type keys, not just one.

= 0.4.2 =
* featured_image_url (scrb_get_rooms()/REST) now requests WordPress's 'large' image size instead of 'medium' — it's
  used by full-width slideshow/card displays in more than one theme template now, and 'medium' (300px max) was
  visibly soft stretched that wide.

= 0.4.1 =
* Added a "Gallery images" field to the Room Details box — up to 10 extra photos an admin can attach to a room on
  top of its Featured Image, picked via the same media modal Layout options already uses (multi-select this time).
  Exposed as the new `gallery_image_urls` array via scrb_get_rooms()/REST, for a theme to build a slideshow or
  gallery from — doesn't include the Featured Image itself, which a consumer is expected to show first.

= 0.4.0 =
* Capacity can now have one or more "layout options" alongside the plain Capacity figure — each with its own
  seating/configuration style (Clear, Theatre-style, Cabaret, Classroom, Boardroom, U-shape, Banquet, Standing
  reception — Support\LayoutTypes), its own capacity number, and an optional photo. A room that only ever needs one
  number can leave this alone entirely; scrb_get_rooms()/REST expose it as the new `layout_variants` array.
* Price restructured again: what was a single amount+unit+"contact for pricing" is now up to 6 price options per
  room, each with its own optional label (e.g. "Off-peak non-commercial", "Peak commercial") — added and removed in
  the browser via the room-edit screen's new "Add price option" button. The first row is always a room's plain
  default price. Exposed as the new `price_options` array (replacing the old flat `price_amount`/`price_unit`/
  `contact_for_pricing` keys) via scrb_get_rooms() and the REST rooms endpoint — a breaking change to that data
  contract, acceptable for now as this plugin isn't yet in use on more than one site. Rooms priced before this
  version have their old single price read as that first row automatically (Support\RoomMeta::priceOptions()), so
  nothing already entered is lost, only left for an admin to re-save into the new shape.
* Added an Accessibility field (free text) to the Room Details box.
* The Amenities taxonomy is renamed Facilities — a better fit for venue-hire spaces like a hall or conference room.
  Every existing term and every room's existing selections carry over automatically on upgrade (Setup\Upgrader
  renames the taxonomy at the DB level rather than starting a new one) — nothing needs re-ticking. `amenities` is
  renamed `facilities` in scrb_get_rooms()/REST output for the same reason the price fields changed shape above.
* Added Room Bookings -> Reorder rooms: a drag-and-drop list (one per configured room type) that saves automatically
  on drop. Stored in each room's own menu_order — no new postmeta — and scrb_get_rooms()/REST order by it first,
  falling back to the previous oldest-first-by-date order for any room never dragged, so a site that never opens
  this screen sees no change at all.

= 0.3.1 =
* The "SC Room Bookings" settings menu now reads "SC Room Bookings" in the admin sidebar (not just the page title)
  and has moved down near the bottom of the menu, alongside SC Events Manager and SC Maps's own settings screens —
  keeps the admin sidebar's top area for actual content rather than plugin settings.

= 0.3.0 =
* Added "Simple mode" (Room Bookings -> Room Types): a checkbox for sites that only want SC Room Bookings to list
  rooms, not take bookings. Trims the Room Details box down to Capacity and Suitable for, hides Amenities' checkbox
  meta box on the room-edit screen, and hides the Bookings/Documentation/Amenities admin menu items. Nothing already
  saved is touched either way, so it's fully reversible.

= 0.2.0 =
* Room Details box now renders above the content editor (edit_form_after_title, the same technique SC Events
  Manager's own Event Details box uses) as one postbox split into "Details" and "Availability" headings, rather
  than two separate boxes below the editor — a common look across both plugins' admin screens.
* Added a "Suitable for" field to rooms (e.g. "Events and shows").
* Amenities are now a proper taxonomy with their own Room Bookings -> Amenities screen, tickable per room, instead
  of free text.
* Price restructured: a "Contact us for pricing" checkbox alongside a numeric amount + per-hour/session/day/person
  unit, rather than one free-text field.
* Added Frontend\RoomListing as the single shared query behind scrb_get_rooms() and the REST rooms endpoint, and
  fixed room ordering to oldest-first.
* Settings::addRoomType() no longer overwrites an existing room type of the same key — it now returns the existing
  type instead, so a second, unrelated call can never silently clobber real room data.

= 0.1.0 =
* Initial framework: room types (add/relabel/delete, mirroring — and starting with the delete action SC Events
  Manager had to add later), the Room Details meta box, the Booking data model and AvailabilityChecker, the
  Bookings admin screen (confirm/decline/cancel), email notifications, and the PHP/REST integration surfaces.
* No public-facing booking form/calendar UI yet — scrb_request_booking()/POST scrb/v1/bookings are ready for a
  theme or headless front end to build one against.

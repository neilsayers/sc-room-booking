=== SC Room Bookings ===
Contributors: screencandy
Tags: bookings, rooms, availability, calendar
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.3.0
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

Each room gets a hand-written "Room Details" box: capacity, what it's suitable for, price, which days of
the week it's available, its daily opening/closing time, a minimum booking length, and a buffer required between
bookings. Amenities are a proper taxonomy (Taxonomies\AmenityTaxonomy) rather than free text per room — manage the
shared list once from Room Bookings -> Amenities (WordPress's own term-manager screen, nothing custom-built), then
just tick which apply on each room.

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

Some sites only want the room listing — not bookings at all. Ticking "Use SC Room Bookings in simple mode" on the
main Room Bookings screen trims each room's edit screen down to just Capacity and Suitable for, and hides the
Bookings, Documentation and Amenities menu items. Everything else (pricing, availability, amenities, the booking
system itself) stays exactly as it was, untouched, ready to reappear the moment the box is unticked again.

== Data model ==

Rooms are regular posts (one post type per configured room type). Bookings are their own fixed `scrb_booking` post
type — never renamed, never shown on wp-admin's native post-list/edit screens (manage them from Room Bookings ->
Bookings instead), and never trashed by a room-type deletion, only marked Cancelled — a booking is a historical
record of a request once it's been made, not disposable content.

== Changelog ==

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

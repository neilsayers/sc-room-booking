<?php

namespace SCRoomBookings\Settings;

/**
 * Reads/writes the plugin's single options-table row: a collection of
 * bookable "room types" keyed by their post_type slug, plus a handful
 * of site-wide booking preferences.
 *
 * For each type, only the post_type key and URL slug are fixed for
 * the lifetime of the site — both derived once from the labels given
 * when the type is created (see addRoomType()). Renaming a type
 * afterwards (relabelRoomType()) only ever touches its display
 * labels; changing the key or slug later would orphan existing rooms
 * or break their permalinks.
 *
 * Unlike SC Events Manager's first release, deleteRoomType() exists
 * from day one — that plugin had to add it later once real sites hit
 * the "I created one by mistake" problem, so this one starts with it.
 * Same contract either way: this only removes the settings entry —
 * Admin\RoomTypesPage is responsible for trashing the type's rooms
 * (and, transitively, their bookings) before calling it.
 */
final class Settings
{
    private const OPTION_KEY = 'scrb_settings';

    private const DEFAULTS = [
        'room_types' => [],
        // Whether a submitted booking needs an admin to confirm it
        // before it counts toward availability, or is auto-confirmed
        // on submission. Pending bookings still block the slot either
        // way — see Booking\AvailabilityChecker — this only controls
        // whether a human has to look at it first.
        'require_approval' => true,
        // Where "new booking request" notifications are sent. Empty
        // string (the default) resolves to get_option('admin_email')
        // at send time rather than freezing today's admin email into
        // settings — see notificationEmail().
        'notification_email' => '',
        // How much advance notice a booking must give, in hours.
        // Filterable per-request too — see Booking\AvailabilityChecker.
        'min_notice_hours' => 24,
        // When true, the plugin is being used purely as a way to list
        // rooms (custom post types) with basic info — not to take
        // bookings. Trims the room-edit screen down to capacity/
        // suitable-for and hides the Bookings/Documentation/Facilities
        // admin menu items. Existing price/availability/facilities data
        // is left untouched either way — see MetaBoxes\
        // RoomDetailsMetaBox::saveMetaBox() — so turning this off
        // again later doesn't lose anything.
        'simple_mode' => false,
        // 'internal' (the default — this plugin doesn't draw its own
        // booking form, so nothing site-wide changes) or
        // 'external_link', which lets each room set its own booking_url
        // (MetaBoxes\RoomDetailsMetaBox) for the front end to send
        // visitors to instead. A future gateway (e.g. an SC Commerce
        // integration) would be a further value here, not a rename of
        // this one — see BOOKING_MODES.
        'booking_mode' => 'internal',
    ];

    public const BOOKING_MODE_INTERNAL = 'internal';
    public const BOOKING_MODE_EXTERNAL_LINK = 'external_link';

    private const BOOKING_MODES = [self::BOOKING_MODE_INTERNAL, self::BOOKING_MODE_EXTERNAL_LINK];

    private array $values;

    public function __construct()
    {
        $stored = \get_option(self::OPTION_KEY, []);
        $this->values = \is_array($stored) ? \array_merge(self::DEFAULTS, $stored) : self::DEFAULTS;
    }

    /**
     * @return array<string, array{post_type: string, slug: string, label_singular: string, label_plural: string}>
     */
    public function allRoomTypes(): array
    {
        return $this->values['room_types'];
    }

    public function hasRoomTypes(): bool
    {
        return $this->values['room_types'] !== [];
    }

    public function getRoomType(string $postType): ?array
    {
        return $this->values['room_types'][$postType] ?? null;
    }

    /**
     * Filterable so a site can raise the cap later — e.g.
     * add_filter('scrb_max_room_types', fn () => 8) — without
     * touching the plugin itself.
     */
    public function maxRoomTypes(): int
    {
        return (int) \apply_filters('scrb_max_room_types', 4);
    }

    public function canAddRoomType(): bool
    {
        return \count($this->values['room_types']) < $this->maxRoomTypes();
    }

    /**
     * A WordPress post type name is capped at 20 characters and must
     * be lowercase alphanumeric/underscore/dash — sanitize_key() plus
     * a hard truncate keeps register_post_type() from silently
     * failing on a long or oddly-punctuated singular label.
     */
    public static function derivePostTypeKey(string $label): string
    {
        return \substr(\sanitize_key($label), 0, 20);
    }

    public static function deriveSlug(string $label): string
    {
        return \sanitize_title($label);
    }

    /**
     * Checked before addRoomType() so the admin screen can show one
     * error message covering every failure mode.
     */
    public function validateNewRoomType(string $labelSingular, string $labelPlural): ?string
    {
        if ($labelSingular === '' || $labelPlural === '') {
            return \__('Please enter both a singular and plural name.', 'sc-room-bookings');
        }

        if (! $this->canAddRoomType()) {
            return \sprintf(
                \__('You already have the maximum of %d room types.', 'sc-room-bookings'),
                $this->maxRoomTypes()
            );
        }

        $postType = self::derivePostTypeKey($labelSingular);

        if ($postType === '') {
            return \__('That singular name can\'t be turned into a valid post type — please use letters or numbers.', 'sc-room-bookings');
        }

        if (\post_type_exists($postType) || isset($this->values['room_types'][$postType])) {
            return \__('That singular name is already in use — please try a different one.', 'sc-room-bookings');
        }

        return null;
    }

    /**
     * validateNewRoomType() already stops the *admin form* from
     * creating a colliding type, but nothing previously stopped a
     * direct call to this method (from a script, a REST route, a
     * future integration) from silently overwriting an existing
     * entry — which is exactly what let one throwaway "Room" type
     * created outside the admin screen clobber a site's real one
     * during development. Existing entries now win outright: this
     * returns the type that's already there rather than touching it.
     *
     * @return string The room type's post_type key — newly created, or the existing one if this collided with it.
     */
    public function addRoomType(string $labelSingular, string $labelPlural): string
    {
        $postType = self::derivePostTypeKey($labelSingular);

        if (isset($this->values['room_types'][$postType])) {
            return $postType;
        }

        $this->values['room_types'][$postType] = [
            'post_type' => $postType,
            'slug' => self::deriveSlug($labelPlural),
            'label_singular' => $labelSingular,
            'label_plural' => $labelPlural,
        ];

        \update_option(self::OPTION_KEY, $this->values);

        return $postType;
    }

    /**
     * Safe to call any time — only the display labels change; see
     * class docblock for why post_type/slug are never touched again.
     */
    public function relabelRoomType(string $postType, string $labelSingular, string $labelPlural): void
    {
        if (! isset($this->values['room_types'][$postType])) {
            return;
        }

        $this->values['room_types'][$postType]['label_singular'] = $labelSingular;
        $this->values['room_types'][$postType]['label_plural'] = $labelPlural;

        \update_option(self::OPTION_KEY, $this->values);
    }

    /**
     * Removes this room type from settings only — it does not touch
     * the type's rooms or their bookings. Callers (Admin\RoomTypesPage
     * ::handleDelete()) are expected to trash both first, since they
     * need the type to still be a real, registered post type to do
     * their own work correctly.
     */
    public function deleteRoomType(string $postType): void
    {
        unset($this->values['room_types'][$postType]);

        \update_option(self::OPTION_KEY, $this->values);
    }

    public function requireApproval(): bool
    {
        return (bool) $this->values['require_approval'];
    }

    public function setRequireApproval(bool $requireApproval): void
    {
        $this->values['require_approval'] = $requireApproval;

        \update_option(self::OPTION_KEY, $this->values);
    }

    /**
     * Resolves to the site's admin_email unless a specific address has
     * been set — not stored as the default in settings itself, so
     * this always reflects an unset admin_email change rather than
     * freezing whatever it was when the plugin was first configured.
     */
    public function notificationEmail(): string
    {
        return $this->values['notification_email'] !== ''
            ? $this->values['notification_email']
            : (string) \get_option('admin_email');
    }

    public function setNotificationEmail(string $email): void
    {
        $this->values['notification_email'] = $email;

        \update_option(self::OPTION_KEY, $this->values);
    }

    public function minNoticeHours(): int
    {
        return (int) $this->values['min_notice_hours'];
    }

    public function setMinNoticeHours(int $hours): void
    {
        $this->values['min_notice_hours'] = \max(0, $hours);

        \update_option(self::OPTION_KEY, $this->values);
    }

    public function simpleMode(): bool
    {
        return (bool) $this->values['simple_mode'];
    }

    public function setSimpleMode(bool $simpleMode): void
    {
        $this->values['simple_mode'] = $simpleMode;

        \update_option(self::OPTION_KEY, $this->values);
    }

    public function bookingMode(): string
    {
        $mode = $this->values['booking_mode'];

        return \in_array($mode, self::BOOKING_MODES, true) ? $mode : self::BOOKING_MODE_INTERNAL;
    }

    /**
     * Whether each room can be given its own external booking_url
     * (MetaBoxes\RoomDetailsMetaBox) for the front end to send visitors
     * to instead of anything this plugin draws itself. Only gates
     * whether that admin field is shown — a room that already has a
     * booking_url saved keeps working on the front end even if this is
     * later switched back off, same "nothing already saved is lost"
     * rule simpleMode() follows.
     */
    public function bookingIsExternalLink(): bool
    {
        return $this->bookingMode() === self::BOOKING_MODE_EXTERNAL_LINK;
    }

    public function setBookingMode(string $mode): void
    {
        $this->values['booking_mode'] = \in_array($mode, self::BOOKING_MODES, true) ? $mode : self::BOOKING_MODE_INTERNAL;

        \update_option(self::OPTION_KEY, $this->values);
    }
}

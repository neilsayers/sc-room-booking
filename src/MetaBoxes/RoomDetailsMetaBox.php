<?php

namespace SCRoomBookings\MetaBoxes;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\MetaField;
use SCRoomBookings\Support\RoomMeta;

/**
 * Hand-written "Room Details" box rendered above the content editor
 * via edit_form_after_title — add_meta_box() can only place things in
 * the columns below the editor, not above it. Same technique SC
 * Events Manager's own EventDetailsMetaBox uses.
 *
 * Covers Details (capacity, what it's suitable for, price) and
 * Availability (days/hours a room can be booked, minimum booking
 * length, gap between bookings) as two <h3>-divided sections inside
 * one postbox, rather than two separate boxes — matching how the
 * events plugin groups its own related fields under one "Event
 * Details" box with internal headings, so both plugins' admin screens
 * read as a matched pair. One save handler for the whole "scrb" field
 * set, for the same reason EventDetailsMetaBox uses one: a meta box
 * is just a visual container, every field posts through the same
 * <form> regardless of which section draws it.
 */
final class RoomDetailsMetaBox implements Hookable
{
    private const NONCE_ACTION = 'scrb_save_room_details';
    private const NONCE_NAME = 'scrb_room_details_nonce';

    /**
     * The unit a price is quoted per — kept short and generic rather
     * than trying to anticipate every venue's billing model.
     */
    private const PRICE_UNITS = [
        'hour' => 'per hour',
        'session' => 'per session',
        'day' => 'per day',
        'person' => 'per person',
    ];

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('edit_form_after_title', [$this, 'renderMetaBox']);
        \add_action('save_post', [$this, 'saveMetaBox']);
    }

    private function isRoomPostType(string $postType): bool
    {
        return $this->settings->getRoomType($postType) !== null;
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        if (! $this->isRoomPostType($post->post_type)) {
            return;
        }

        $meta = RoomMeta::read($post->ID);
        $simpleMode = $this->settings->simpleMode();
        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <div class="postbox scrb-room-details">
            <h2 class="hndle"><span>Room Details</span></h2>
            <div class="inside">

                <?php if (! $simpleMode) : ?><h3>Details</h3><?php endif; ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="scrb_capacity">Capacity</label></th>
                        <td>
                            <input type="number" id="scrb_capacity" name="scrb[capacity]" min="0" class="small-text" value="<?php echo \esc_attr((string) $meta['capacity']); ?>">
                            people
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scrb_suitable_for">Suitable for</label></th>
                        <td>
                            <input type="text" id="scrb_suitable_for" name="scrb[suitable_for]" class="regular-text" placeholder="e.g. Events and shows, Receptions and fairs, Boardroom style" value="<?php echo \esc_attr($meta['suitable_for']); ?>">
                        </td>
                    </tr>
                    <?php if (! $simpleMode) : ?>
                    <tr>
                        <th scope="row">Price</th>
                        <td>
                            <label>
                                <input type="checkbox" id="scrb_contact_for_pricing" name="scrb[contact_for_pricing]" value="1" <?php \checked($meta['contact_for_pricing']); ?>>
                                Contact us for pricing
                            </label>
                            <p class="description">Ticking this shows "Contact us for pricing" instead of the amount below, wherever a room's price is displayed — the amount stays saved underneath, so unticking it later doesn't lose anything typed in.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scrb_price_amount">Amount</label></th>
                        <td>
                            <input type="number" id="scrb_price_amount" name="scrb[price_amount]" min="0" step="0.01" class="small-text" value="<?php echo \esc_attr($meta['price_amount']); ?>">
                            <label for="scrb_price_unit">per</label>
                            <select id="scrb_price_unit" name="scrb[price_unit]">
                                <?php foreach (self::PRICE_UNITS as $key => $label) : ?>
                                    <option value="<?php echo \esc_attr($key); ?>" <?php \selected($meta['price_unit'], $key); ?>><?php echo \esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Ignored if "Contact us for pricing" above is ticked. No currency symbol here — that's a display concern for whatever's showing the price, not this plugin's to assume.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php if (! $simpleMode) : ?>
                <h3>Availability</h3>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Available days</th>
                        <td>
                            <?php foreach (RoomMeta::WEEKDAYS as $day) : ?>
                                <label style="margin-right: 12px; text-transform: capitalize;">
                                    <input type="checkbox" name="scrb[available_days][]" value="<?php echo \esc_attr($day); ?>" <?php \checked(\in_array($day, $meta['available_days'], true)); ?>>
                                    <?php echo \esc_html($day); ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scrb_available_start_time">Available from</label></th>
                        <td>
                            <input type="time" id="scrb_available_start_time" name="scrb[available_start_time]" value="<?php echo \esc_attr($meta['available_start_time']); ?>">
                            <label for="scrb_available_end_time">until</label>
                            <input type="time" id="scrb_available_end_time" name="scrb[available_end_time]" value="<?php echo \esc_attr($meta['available_end_time']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scrb_min_booking_minutes">Minimum booking length</label></th>
                        <td>
                            <input type="number" id="scrb_min_booking_minutes" name="scrb[min_booking_minutes]" min="0" step="15" class="small-text" value="<?php echo \esc_attr((string) $meta['min_booking_minutes']); ?>">
                            minutes
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="scrb_buffer_minutes">Gap required between bookings</label></th>
                        <td>
                            <input type="number" id="scrb_buffer_minutes" name="scrb[buffer_minutes]" min="0" step="5" class="small-text" value="<?php echo \esc_attr((string) $meta['buffer_minutes']); ?>">
                            minutes
                            <p class="description">e.g. 15 minutes to turn a room around between bookings. Applied on both sides of every existing booking when checking a new request.</p>
                        </td>
                    </tr>
                </table>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }

    public function saveMetaBox(int $postId): void
    {
        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        if (\defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $post = \get_post($postId);

        if (! $post || ! $this->isRoomPostType($post->post_type) || ! \current_user_can('edit_post', $postId)) {
            return;
        }

        $data = \wp_unslash($_POST['scrb'] ?? []);

        MetaField::saveText($postId, '_scrb_capacity', (string) \absint($data['capacity'] ?? 0));
        MetaField::saveText($postId, '_scrb_suitable_for', (string) ($data['suitable_for'] ?? ''));

        // Simple mode's form (see renderMetaBox()) only has the two
        // fields above — price/availability inputs don't exist on the
        // page at all, so nothing for them shows up in $data either.
        // Skipping them here (rather than saving whatever absent-key
        // defaults MetaField::saveText() would fall back to) is what
        // stops a save while simple mode is on from silently wiping
        // out real pricing/availability data underneath.
        if ($this->settings->simpleMode()) {
            return;
        }

        MetaField::saveText($postId, '_scrb_contact_for_pricing', ! empty($data['contact_for_pricing']) ? '1' : '');

        $priceAmount = \is_numeric($data['price_amount'] ?? null) ? (float) $data['price_amount'] : null;
        MetaField::saveText($postId, '_scrb_price_amount', $priceAmount !== null ? (string) $priceAmount : '');

        $priceUnit = \sanitize_key($data['price_unit'] ?? '');
        MetaField::saveText($postId, '_scrb_price_unit', \array_key_exists($priceUnit, self::PRICE_UNITS) ? $priceUnit : '');

        $availableDays = \is_array($data['available_days'] ?? null) ? $data['available_days'] : [];
        $availableDays = \array_values(\array_intersect($availableDays, RoomMeta::WEEKDAYS));
        MetaField::saveArray($postId, '_scrb_available_days', $availableDays);

        MetaField::saveText($postId, '_scrb_available_start_time', $this->sanitizeTime($data['available_start_time'] ?? ''));
        MetaField::saveText($postId, '_scrb_available_end_time', $this->sanitizeTime($data['available_end_time'] ?? ''));
        MetaField::saveText($postId, '_scrb_min_booking_minutes', (string) \absint($data['min_booking_minutes'] ?? 0));
        MetaField::saveText($postId, '_scrb_buffer_minutes', (string) \absint($data['buffer_minutes'] ?? 0));

        // Amenities themselves are saved by WordPress's own taxonomy
        // meta box handling (Taxonomies\AmenityTaxonomy) — nothing to
        // do here for them.
    }

    private function sanitizeTime(string $time): string
    {
        return \preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time) ? $time : '';
    }
}

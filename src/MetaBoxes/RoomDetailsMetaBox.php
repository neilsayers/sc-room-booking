<?php

namespace SCRoomBookings\MetaBoxes;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\LayoutTypes;
use SCRoomBookings\Support\MetaField;
use SCRoomBookings\Support\PriceUnits;
use SCRoomBookings\Support\RoomMeta;

/**
 * Hand-written "Room Details" box rendered above the content editor
 * via edit_form_after_title — add_meta_box() can only place things in
 * the columns below the editor, not above it. Same technique SC
 * Events Manager's own EventDetailsMetaBox uses.
 *
 * Covers Details (capacity, what it's suitable for, accessibility,
 * layout options, price options) and Availability (days/hours a room
 * can be booked, minimum booking length, gap between bookings) as two
 * <h3>-divided sections inside one postbox, rather than two separate
 * boxes — matching how the events plugin groups its own related
 * fields under one "Event Details" box with internal headings, so
 * both plugins' admin screens read as a matched pair. One save
 * handler for the whole "scrb" field set, for the same reason
 * EventDetailsMetaBox uses one: a meta box is just a visual container,
 * every field posts through the same <form> regardless of which
 * section draws it.
 *
 * Layout options and price options are both small user-managed
 * repeaters (add/remove rows in the browser via assets/js/
 * room-details-repeaters.js) — there's no ACF or other field-builder
 * dependency here, so this plugin still needs zero build step or
 * Composer install to drop into a site.
 */
final class RoomDetailsMetaBox implements Hookable
{
    private const NONCE_ACTION = 'scrb_save_room_details';
    private const NONCE_NAME = 'scrb_room_details_nonce';

    /**
     * A room can offer at most this many price options (the default
     * row plus however many "Add price option" adds) — enforced both
     * here and by the "Add" button disabling itself client-side, so a
     * room's pricing table stays skimmable rather than growing without
     * bound.
     */
    private const MAX_PRICE_OPTIONS = 6;

    /**
     * A room can attach at most this many gallery images (on top of
     * its own Featured Image, which the front-end slideshow always
     * shows first) — enough for a real slideshow without an editor
     * needing to hunt through dozens of thumbnails to manage it.
     */
    private const MAX_GALLERY_IMAGES = 10;

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('edit_form_after_title', [$this, 'renderMetaBox']);
        \add_action('save_post', [$this, 'saveMetaBox']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (! \in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = \get_current_screen();

        if (! $screen || ! $this->isRoomPostType($screen->post_type)) {
            return;
        }

        // Layout options let an admin attach a photo per layout — the
        // repeater's own JS opens the same media modal core post
        // editing uses, rather than this plugin building its own
        // upload UI from scratch.
        \wp_enqueue_media();
        \wp_enqueue_style('scrb-admin', SCRB_URL.'assets/css/admin.css', [], SCRB_VERSION);
        \wp_enqueue_script('scrb-room-details-repeaters', SCRB_URL.'assets/js/room-details-repeaters.js', [], SCRB_VERSION, true);
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
        $priceOptions = RoomMeta::priceOptions($post->ID);
        $layoutVariants = RoomMeta::layoutVariants($post->ID);
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
                        <th scope="row"><label for="scrb_accessibility">Accessibility</label></th>
                        <td>
                            <textarea id="scrb_accessibility" name="scrb[accessibility]" rows="3" class="large-text" placeholder="e.g. Ground floor, step-free access. Wheelchair lift to the stage and an induction loop built into the sound system."><?php echo \esc_textarea($meta['accessibility']); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gallery images</th>
                        <td>
                            <div id="scrb-gallery-images" class="scrb-gallery" data-max="<?php echo \esc_attr((string) self::MAX_GALLERY_IMAGES); ?>">
                                <?php foreach (RoomMeta::galleryImages($post->ID) as $imageId) : ?>
                                    <?php echo self::renderGalleryImageItem($imageId); ?>
                                <?php endforeach; ?>
                            </div>
                            <p><button type="button" class="button scrb-add-gallery-images" <?php \disabled(\count(RoomMeta::galleryImages($post->ID)) >= self::MAX_GALLERY_IMAGES); ?>>Add images</button></p>
                            <p class="description">Used for the front-end image slideshow, alongside the Featured Image above (which always shows first). Up to <?php echo \esc_html((string) self::MAX_GALLERY_IMAGES); ?> images.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Layout options</th>
                        <td>
                            <div id="scrb-layout-variants" class="scrb-repeater">
                                <?php foreach ($layoutVariants === [] ? [['layout' => 'clear', 'capacity' => '', 'image_id' => 0]] : $layoutVariants as $index => $variant) : ?>
                                    <?php echo self::renderLayoutVariantRow((string) $index, $variant); ?>
                                <?php endforeach; ?>
                            </div>
                            <p><button type="button" class="button scrb-add-layout-variant">Add layout option</button></p>
                            <p class="description">Some rooms can be set out more than one way, each with its own capacity and (optionally) its own photo — e.g. 300 capacity Clear, 220 Theatre-style, 150 Cabaret. Leave this as a single blank row if the Capacity figure above is all this room needs.</p>
                            <template id="scrb-layout-variant-template"><?php echo self::renderLayoutVariantRow('__INDEX__', ['layout' => 'clear', 'capacity' => '', 'image_id' => 0]); ?></template>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Price options</th>
                        <td>
                            <div id="scrb-price-options" class="scrb-repeater" data-max="<?php echo \esc_attr((string) self::MAX_PRICE_OPTIONS); ?>">
                                <?php foreach ($priceOptions as $index => $row) : ?>
                                    <?php echo self::renderPriceOptionRow((string) $index, $row); ?>
                                <?php endforeach; ?>
                            </div>
                            <p><button type="button" class="button scrb-add-price-option" <?php \disabled(\count($priceOptions) >= self::MAX_PRICE_OPTIONS); ?>>Add price option</button></p>
                            <p class="description">The first row is this room's default price — leave its label blank to show just the amount, no label prefix. Add up to <?php echo \esc_html((string) self::MAX_PRICE_OPTIONS); ?> rows total for tiered pricing, e.g. "Off-peak non-commercial" and "Peak commercial" alongside it. "Contact us for pricing" shows in place of a row's amount wherever it's displayed, without losing whatever's typed in underneath. No currency symbol here — that's a display concern for whatever's showing the price, not this plugin's to assume.</p>
                            <template id="scrb-price-option-template"><?php echo self::renderPriceOptionRow('__INDEX__', ['label' => '', 'amount' => '', 'unit' => 'hour', 'contact_for_pricing' => false]); ?></template>
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
        // fields above — accessibility/layout/price/availability
        // inputs don't exist on the page at all, so nothing for them
        // shows up in $data either. Skipping them here (rather than
        // saving whatever absent-key defaults would fall back to) is
        // what stops a save while simple mode is on from silently
        // wiping out real data underneath.
        if ($this->settings->simpleMode()) {
            return;
        }

        MetaField::saveText($postId, '_scrb_accessibility', \sanitize_textarea_field((string) ($data['accessibility'] ?? '')));

        $galleryImages = \array_slice(
            \array_values(\array_unique(\array_filter(\array_map('absint', (array) ($data['gallery_images'] ?? []))))),
            0,
            self::MAX_GALLERY_IMAGES
        );
        MetaField::saveArray($postId, '_scrb_gallery_images', $galleryImages);

        $layoutVariants = [];

        foreach ((array) ($data['layout_variants'] ?? []) as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $layout = \sanitize_key($row['layout'] ?? '');
            $capacity = $row['capacity'] ?? '';

            // A row with no layout picked or no capacity typed isn't a
            // real layout option yet — dropped rather than saved as a
            // zero-capacity row, same "blank means nothing to save"
            // rule MetaField::saveText()/saveArray() apply everywhere
            // else in this box.
            if (! \array_key_exists($layout, LayoutTypes::TYPES) || $capacity === '') {
                continue;
            }

            $layoutVariants[] = [
                'layout' => $layout,
                'capacity' => \absint($capacity),
                'image_id' => \absint($row['image_id'] ?? 0),
            ];
        }

        MetaField::saveArray($postId, '_scrb_layout_variants', $layoutVariants);

        $priceOptions = [];

        foreach ((array) ($data['price_options'] ?? []) as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $unit = \sanitize_key($row['unit'] ?? '');
            $amount = \is_numeric($row['amount'] ?? null) ? (string) (float) $row['amount'] : '';
            $contactForPricing = ! empty($row['contact_for_pricing']);

            // An untouched extra row (no amount typed, "contact for
            // pricing" left unticked) isn't a real price option —
            // dropped rather than saved as a blank row, other than the
            // very first row, which always represents this room's
            // default price even if an admin hasn't filled it in yet.
            if ($priceOptions !== [] && $amount === '' && ! $contactForPricing) {
                continue;
            }

            $priceOptions[] = [
                'label' => \sanitize_text_field((string) ($row['label'] ?? '')),
                'amount' => $amount,
                'unit' => \array_key_exists($unit, PriceUnits::UNITS) ? $unit : 'hour',
                'contact_for_pricing' => $contactForPricing,
            ];

            if (\count($priceOptions) >= self::MAX_PRICE_OPTIONS) {
                break;
            }
        }

        MetaField::saveArray($postId, '_scrb_price_options', $priceOptions);

        // The old flat price fields are superseded by _scrb_price_options
        // above (see RoomMeta::priceOptions()'s legacy fallback) —
        // cleared here so a room re-saved on this version doesn't keep
        // two disagreeing copies of its own price sitting in postmeta.
        \delete_post_meta($postId, '_scrb_contact_for_pricing');
        \delete_post_meta($postId, '_scrb_price_amount');
        \delete_post_meta($postId, '_scrb_price_unit');

        $availableDays = \is_array($data['available_days'] ?? null) ? $data['available_days'] : [];
        $availableDays = \array_values(\array_intersect($availableDays, RoomMeta::WEEKDAYS));
        MetaField::saveArray($postId, '_scrb_available_days', $availableDays);

        MetaField::saveText($postId, '_scrb_available_start_time', $this->sanitizeTime($data['available_start_time'] ?? ''));
        MetaField::saveText($postId, '_scrb_available_end_time', $this->sanitizeTime($data['available_end_time'] ?? ''));
        MetaField::saveText($postId, '_scrb_min_booking_minutes', (string) \absint($data['min_booking_minutes'] ?? 0));
        MetaField::saveText($postId, '_scrb_buffer_minutes', (string) \absint($data['buffer_minutes'] ?? 0));

        // Facilities themselves are saved by WordPress's own taxonomy
        // meta box handling (Taxonomies\FacilityTaxonomy) — nothing to
        // do here for them.
    }

    private function sanitizeTime(string $time): string
    {
        return \preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time) ? $time : '';
    }

    private static function renderGalleryImageItem(int $imageId): string
    {
        $imageUrl = \wp_get_attachment_image_url($imageId, 'thumbnail');

        \ob_start();
        ?>
        <span class="scrb-gallery-item">
            <img src="<?php echo \esc_url((string) $imageUrl); ?>" alt="">
            <input type="hidden" name="scrb[gallery_images][]" value="<?php echo \esc_attr((string) $imageId); ?>">
            <button type="button" class="button-link scrb-remove-gallery-image" aria-label="Remove image">&times;</button>
        </span>
        <?php
        return (string) \ob_get_clean();
    }

    /**
     * @param array{layout?: string, capacity?: int|string, image_id?: int} $variant
     */
    private static function renderLayoutVariantRow(string $index, array $variant): string
    {
        $imageId = (int) ($variant['image_id'] ?? 0);
        $imageUrl = $imageId > 0 ? \wp_get_attachment_image_url($imageId, 'thumbnail') : '';

        \ob_start();
        ?>
        <div class="scrb-repeater-row scrb-layout-variant-row">
            <select name="scrb[layout_variants][<?php echo \esc_attr($index); ?>][layout]">
                <?php foreach (LayoutTypes::TYPES as $key => $label) : ?>
                    <option value="<?php echo \esc_attr($key); ?>" <?php \selected($variant['layout'] ?? '', $key); ?>><?php echo \esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" name="scrb[layout_variants][<?php echo \esc_attr($index); ?>][capacity]" min="0" class="small-text" placeholder="Capacity" value="<?php echo \esc_attr((string) ($variant['capacity'] ?? '')); ?>">
            people
            <span class="scrb-image-preview"><?php echo $imageUrl ? '<img src="'.\esc_url($imageUrl).'" alt="">' : ''; ?></span>
            <button type="button" class="button scrb-pick-image"><?php echo $imageId > 0 ? 'Change image' : 'Select image'; ?></button>
            <input type="hidden" class="scrb-image-id" name="scrb[layout_variants][<?php echo \esc_attr($index); ?>][image_id]" value="<?php echo \esc_attr((string) $imageId); ?>">
            <button type="button" class="button-link scrb-remove-row" aria-label="Remove layout option">&times;</button>
        </div>
        <?php
        return (string) \ob_get_clean();
    }

    /**
     * @param array{label?: string, amount?: string, unit?: string, contact_for_pricing?: bool} $row
     */
    private static function renderPriceOptionRow(string $index, array $row): string
    {
        \ob_start();
        ?>
        <div class="scrb-repeater-row scrb-price-option-row">
            <input type="text" name="scrb[price_options][<?php echo \esc_attr($index); ?>][label]" class="regular-text" placeholder="e.g. Off-peak non-commercial" value="<?php echo \esc_attr($row['label'] ?? ''); ?>">
            <input type="number" name="scrb[price_options][<?php echo \esc_attr($index); ?>][amount]" min="0" step="0.01" class="small-text" value="<?php echo \esc_attr((string) ($row['amount'] ?? '')); ?>">
            <label for="scrb_price_option_unit_<?php echo \esc_attr($index); ?>">per</label>
            <select id="scrb_price_option_unit_<?php echo \esc_attr($index); ?>" name="scrb[price_options][<?php echo \esc_attr($index); ?>][unit]">
                <?php foreach (PriceUnits::UNITS as $key => $label) : ?>
                    <option value="<?php echo \esc_attr($key); ?>" <?php \selected($row['unit'] ?? 'hour', $key); ?>><?php echo \esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <label>
                <input type="checkbox" name="scrb[price_options][<?php echo \esc_attr($index); ?>][contact_for_pricing]" value="1" <?php \checked(! empty($row['contact_for_pricing'])); ?>>
                Contact us for pricing
            </label>
            <button type="button" class="button-link scrb-remove-row" aria-label="Remove price option">&times;</button>
        </div>
        <?php
        return (string) \ob_get_clean();
    }
}

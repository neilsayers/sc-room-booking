<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Settings\Settings;

/**
 * Renders one room's full detail — image, description, price table,
 * facilities, accessibility notes. Shared by the [sc_room] shortcode
 * and Frontend\DefaultTemplates' bundled single-room.php, so a room
 * dropped into an arbitrary page via the shortcode and a room viewed
 * at its own default URL look the same.
 *
 * A site that wants its own look (civic-centre-uckfield's own
 * content-single-room.blade.php, say) doesn't use this at all — it's
 * only what a site sees until/unless it customises the single room
 * template itself, same relationship RoomListing has to a theme's own
 * markup.
 */
final class RoomDetail
{
    /**
     * @param array<string, mixed> $room One row of RoomListing::query()/find().
     * @param array{heading?: string} $args 'heading' is the tag wrapping the room name — 'h1' from the
     *                                      plugin's own single-room.php template, 'h2' (the default) from
     *                                      [sc_room], so it never collides with a host page's own <h1>.
     */
    public static function render(array $room, array $args = []): void
    {
        $headingTag = $args['heading'] ?? 'h2';
        $post = \get_post($room['id']);
        $content = $post instanceof \WP_Post ? \apply_filters('the_content', $post->post_content) : '';
        ?>
        <div class="scrb-detail">
            <<?php echo \tag_escape($headingTag); ?> class="scrb-detail-title"><?php echo \esc_html($room['name']); ?></<?php echo \tag_escape($headingTag); ?>>

            <?php if ($room['capacity'] > 0 || $room['suitable_for'] !== '') : ?>
                <p class="scrb-detail-summary">
                    <?php if ($room['capacity'] > 0) : ?>
                        <span class="scrb-detail-capacity">
                            <?php
                            /* translators: %d: maximum number of people the room holds. */
                            echo \esc_html(\sprintf(__('Capacity: up to %d', 'sc-room-bookings'), $room['capacity']));
                            ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($room['suitable_for'] !== '') : ?>
                        <span class="scrb-detail-suitable-for"><?php echo \esc_html($room['suitable_for']); ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (($room['booking_url'] ?? '') !== '') : ?>
                <p class="scrb-detail-cta">
                    <a href="<?php echo \esc_url($room['booking_url']); ?>" target="_blank" rel="noopener">
                        <?php echo \esc_html__('Book now', 'sc-room-bookings'); ?>
                    </a>
                </p>
            <?php elseif ((new Settings())->bookingIsRequestForm()) : ?>
                <div class="scrb-detail-cta">
                    <?php echo BookingWidget::render($room); ?>
                </div>
            <?php endif; ?>

            <?php if ($room['featured_image_url'] !== '') : ?>
                <div class="scrb-detail-image">
                    <img src="<?php echo \esc_url($room['featured_image_url']); ?>" alt="">
                </div>
            <?php endif; ?>

            <?php if ($content !== '') : ?>
                <div class="scrb-detail-content"><?php echo $content; // phpcs:ignore -- already through the_content filter. ?></div>
            <?php endif; ?>

            <?php if ($room['gallery_image_urls'] !== []) : ?>
                <div class="scrb-detail-gallery">
                    <?php foreach ($room['gallery_image_urls'] as $url) : ?>
                        <img src="<?php echo \esc_url($url); ?>" alt="">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($room['layout_variants'] !== []) : ?>
                <div class="scrb-detail-section">
                    <p class="scrb-detail-label"><?php echo \esc_html__('Capacity options', 'sc-room-bookings'); ?></p>
                    <ul class="scrb-detail-layouts">
                        <?php foreach ($room['layout_variants'] as $variant) : ?>
                            <li>
                                <strong><?php echo \esc_html((string) $variant['capacity']); ?></strong>
                                <?php echo \esc_html($variant['label']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (self::hasVisiblePrice($room['price_options'])) : ?>
                <div class="scrb-detail-section">
                    <p class="scrb-detail-label"><?php echo \esc_html__('Room hire prices', 'sc-room-bookings'); ?></p>
                    <table class="scrb-detail-prices">
                        <tbody>
                            <?php foreach ($room['price_options'] as $price) : ?>
                                <?php if ($price['amount'] === '' && ! $price['contact_for_pricing']) {
                                    continue;
                                } ?>
                                <tr>
                                    <td><?php echo \esc_html($price['label'] !== '' ? $price['label'] : __('Price', 'sc-room-bookings')); ?></td>
                                    <td>
                                        <?php if ($price['contact_for_pricing']) : ?>
                                            <?php echo \esc_html__('Contact us', 'sc-room-bookings'); ?>
                                        <?php else : ?>
                                            &pound;<?php echo \esc_html($price['amount']); ?> <?php echo \esc_html($price['unit_label']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($room['facilities'] !== []) : ?>
                <div class="scrb-detail-section">
                    <p class="scrb-detail-label"><?php echo \esc_html__('Facilities', 'sc-room-bookings'); ?></p>
                    <ul class="scrb-detail-facilities">
                        <?php foreach ($room['facilities'] as $facility) : ?>
                            <li><?php echo \esc_html($facility); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($room['accessibility'] !== '') : ?>
                <div class="scrb-detail-section">
                    <p class="scrb-detail-label"><?php echo \esc_html__('Accessibility', 'sc-room-bookings'); ?></p>
                    <p><?php echo \esc_html($room['accessibility']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<int, array{amount: string, contact_for_pricing: bool}> $priceOptions
     */
    private static function hasVisiblePrice(array $priceOptions): bool
    {
        foreach ($priceOptions as $option) {
            if ($option['amount'] !== '' || $option['contact_for_pricing']) {
                return true;
            }
        }

        return false;
    }
}

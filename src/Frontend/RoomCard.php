<?php

namespace SCRoomBookings\Frontend;

/**
 * Renders one room as a listing "card" — the [sc_rooms] shortcode's
 * grid item, and Frontend\DefaultTemplates' bundled archive-room.php's
 * grid item too, so both read identically. Deliberately not
 * configurable field-by-field the way SC Events Manager's
 * OccurrenceCard is (Settings::listingFields()) — a room has far fewer
 * optional facts than an event does, so a fixed, sensible default
 * card is enough here rather than another admin screen to build.
 */
final class RoomCard
{
    /**
     * @param array<string, mixed> $room One row of RoomListing::query()/find().
     */
    public static function render(array $room): void
    {
        ?>
        <div class="scrb-listing-item">
            <?php if ($room['featured_image_url'] !== '') : ?>
                <div class="scrb-listing-image">
                    <a href="<?php echo \esc_url($room['view_url']); ?>">
                        <img src="<?php echo \esc_url($room['featured_image_url']); ?>" alt="">
                    </a>
                </div>
            <?php endif; ?>

            <div class="scrb-listing-content">
                <h3 class="scrb-listing-title">
                    <a href="<?php echo \esc_url($room['view_url']); ?>"><?php echo \esc_html($room['name']); ?></a>
                </h3>

                <?php if ($room['capacity'] > 0) : ?>
                    <p class="scrb-listing-capacity">
                        <?php
                        /* translators: %d: maximum number of people the room holds. */
                        echo \esc_html(\sprintf(__('Capacity: up to %d', 'sc-room-bookings'), $room['capacity']));
                        ?>
                    </p>
                <?php endif; ?>

                <?php $price = self::cheapestPrice($room['price_options']); ?>
                <?php if ($price !== null) : ?>
                    <p class="scrb-listing-price"><?php echo \esc_html($price); ?></p>
                <?php endif; ?>

                <?php if ($room['excerpt'] !== '') : ?>
                    <p class="scrb-listing-excerpt"><?php echo \esc_html($room['excerpt']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * The cheapest priced (non-"contact us") option, formatted for a
     * card — "From £14.50 per hour" — or null when every option is
     * contact-for-pricing/unset, in which case the card just omits the
     * price line rather than showing a misleading "From £0".
     *
     * @param array<int, array{label: string, amount: string, unit_label: string, contact_for_pricing: bool}> $priceOptions
     */
    private static function cheapestPrice(array $priceOptions): ?string
    {
        $cheapest = null;

        foreach ($priceOptions as $option) {
            if ($option['contact_for_pricing'] || $option['amount'] === '') {
                continue;
            }

            $amount = (float) $option['amount'];

            if ($cheapest === null || $amount < $cheapest['amount']) {
                $cheapest = ['amount' => $amount, 'unit_label' => $option['unit_label']];
            }
        }

        if ($cheapest === null) {
            return null;
        }

        $formatted = \rtrim(\rtrim(\number_format($cheapest['amount'], 2), '0'), '.');

        /* translators: 1: price, 2: unit (e.g. "per hour"). */
        return \sprintf(__('From £%1$s %2$s', 'sc-room-bookings'), $formatted, $cheapest['unit_label']);
    }
}

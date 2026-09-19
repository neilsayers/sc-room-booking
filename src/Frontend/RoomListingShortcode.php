<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;

/**
 * [sc_rooms] — a grid/list of every configured room type by default
 * (narrow with type="room" or a comma list for a site with several),
 * also callable directly from a theme template via the static
 * render() method. Same shape as SC Events Manager's
 * EventListingShortcode — see that class's own docblock.
 */
final class RoomListingShortcode implements Hookable
{
    public const SHORTCODE_TAG = 'sc_rooms';

    private const DEFAULT_ATTS = [
        'type' => '',
        'limit' => '',
    ];

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_shortcode(self::SHORTCODE_TAG, [$this, 'renderShortcode']);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderShortcode($atts): string
    {
        $atts = \shortcode_atts(self::DEFAULT_ATTS, (array) $atts, self::SHORTCODE_TAG);

        return self::renderWith($atts, $this->settings);
    }

    /**
     * Callable directly from a theme template, e.g.:
     *   echo RoomListingShortcode::render(['type' => 'room', 'limit' => 6]);
     *
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts): string
    {
        return self::renderWith($atts + self::DEFAULT_ATTS, new Settings());
    }

    /**
     * @param array<string, mixed> $atts
     */
    private static function renderWith(array $atts, Settings $settings): string
    {
        $rooms = RoomListing::query(['type' => (string) $atts['type']], $settings);

        $limit = (int) $atts['limit'];

        if ($limit > 0) {
            $rooms = \array_slice($rooms, 0, $limit);
        }

        if ($rooms === []) {
            return '<p class="scrb-listing-empty">'.\esc_html__('No rooms found.', 'sc-room-bookings').'</p>';
        }

        \ob_start();
        ?>
        <div class="scrb-listing">
            <?php foreach ($rooms as $room) : ?>
                <?php RoomCard::render($room); ?>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) \ob_get_clean();
    }
}

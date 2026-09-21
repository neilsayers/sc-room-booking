<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;

/**
 * [sc_room id="123"] — one room's full detail (image, description,
 * prices, facilities, accessibility), for dropping a specific room
 * into an arbitrary page rather than linking out to its own URL. id
 * defaults to the current post, so [sc_room] with no attribute also
 * works from inside a room's own content if a site wants that.
 */
final class RoomDetailShortcode implements Hookable
{
    public const SHORTCODE_TAG = 'sc_room';

    private const DEFAULT_ATTS = [
        'id' => '',
    ];

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

        return self::render($atts);
    }

    /**
     * Callable directly from a theme template or a Gutenberg block's
     * render.php, e.g.:
     *   echo RoomDetailShortcode::render(['id' => $roomId]);
     *
     * Same "shortcode method delegates to a static render()" split as
     * RoomListingShortcode.
     *
     * @param array<string, mixed> $atts
     */
    public static function render(array $atts): string
    {
        $atts += self::DEFAULT_ATTS;
        $postId = (int) $atts['id'] ?: (int) \get_the_ID();

        $room = RoomListing::find($postId, new Settings());

        if ($room === null) {
            return '<p class="scrb-listing-empty">'.\esc_html__('Room not found.', 'sc-room-bookings').'</p>';
        }

        \ob_start();
        RoomDetail::render($room);

        return (string) \ob_get_clean();
    }
}

<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;

/**
 * The "View availability" button, week-view calendar, and request form
 * shown on a room's own page when Settings::bookingIsRequestForm() is
 * on — the front end scrb_request_booking()/the REST API never had
 * (see that function's own docblock: "No public-facing booking form/
 * calendar UI yet" was true right up until this class). The calendar
 * itself is FullCalendar (assets/vendor/fullcalendar), vendored the
 * same way SC Events Manager vendors Leaflet for its own venue map —
 * see Frontend\VenueMapAssets there for the identical reasoning.
 *
 * render() only ever prints empty containers — every bit of real
 * behaviour (fetching a room's schedule, the tap-a-start/tap-an-end
 * interaction, submitting the request) lives in assets/js/
 * booking-widget.js against this plugin's own REST API, so nothing
 * here needs to know about calendar internals at all.
 */
final class BookingWidget implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
    }

    /**
     * Only on a page that could actually render this widget — same
     * reasoning as Frontend\VenueMapAssets's own conditional enqueue.
     */
    public function maybeEnqueueAssets(): void
    {
        if (! $this->settings->bookingIsRequestForm()) {
            return;
        }

        $roomTypes = \array_keys($this->settings->allRoomTypes());

        if ($roomTypes === [] || ! \is_singular($roomTypes)) {
            return;
        }

        \wp_enqueue_style('scrb-frontend', SCRB_URL.'assets/css/frontend.css', [], SCRB_VERSION);

        \wp_enqueue_script('scrb-fullcalendar', SCRB_URL.'assets/vendor/fullcalendar/index.global.min.js', [], '6.1.15', true);
        \wp_enqueue_script('scrb-booking-widget', SCRB_URL.'assets/js/booking-widget.js', ['scrb-fullcalendar'], SCRB_VERSION, true);

        \wp_localize_script('scrb-booking-widget', 'scrbBookingWidget', [
            'restUrl' => \esc_url_raw(\rest_url('scrb/v1')),
        ]);
    }

    /**
     * @param array<string, mixed> $room One row of RoomListing::query()/find().
     */
    public static function render(array $room): string
    {
        \ob_start();
        ?>
        <div class="scrb-booking-widget" data-room-id="<?php echo \esc_attr((string) $room['id']); ?>">
            <button type="button" class="scrb-booking-widget-trigger">
                <?php echo \esc_html__('View availability', 'sc-room-bookings'); ?>
            </button>

            <dialog class="scrb-booking-widget-dialog">
                <div class="scrb-booking-widget-header">
                    <p class="scrb-booking-widget-title">
                        <?php echo \esc_html(\sprintf(\__('Book %s', 'sc-room-bookings'), $room['name'])); ?>
                    </p>
                    <button type="button" class="scrb-booking-widget-close" aria-label="<?php echo \esc_attr__('Close', 'sc-room-bookings'); ?>">&times;</button>
                </div>

                <div class="scrb-booking-widget-body">
                    <p class="scrb-booking-widget-hint" data-step="pick-start"><?php echo \esc_html__('Tap a start time.', 'sc-room-bookings'); ?></p>
                    <p class="scrb-booking-widget-hint" data-step="pick-end" hidden><?php echo \esc_html__('Now tap an end time.', 'sc-room-bookings'); ?></p>

                    <div class="scrb-booking-widget-calendar"></div>

                    <form class="scrb-booking-widget-form" hidden>
                        <p class="scrb-booking-widget-selection"></p>

                        <p>
                            <label>
                                <?php echo \esc_html__('Name', 'sc-room-bookings'); ?>
                                <input type="text" name="name" required>
                            </label>
                        </p>
                        <p>
                            <label>
                                <?php echo \esc_html__('Email', 'sc-room-bookings'); ?>
                                <input type="email" name="email" required>
                            </label>
                        </p>
                        <p>
                            <label>
                                <?php echo \esc_html__('Phone (optional)', 'sc-room-bookings'); ?>
                                <input type="tel" name="phone">
                            </label>
                        </p>
                        <p>
                            <label>
                                <?php echo \esc_html__('Anything we should know? (optional)', 'sc-room-bookings'); ?>
                                <textarea name="message" rows="3"></textarea>
                            </label>
                        </p>

                        <p class="scrb-booking-widget-error" role="alert" hidden></p>

                        <p class="scrb-booking-widget-actions">
                            <button type="button" class="scrb-booking-widget-change-times"><?php echo \esc_html__('Change times', 'sc-room-bookings'); ?></button>
                            <button type="submit" class="scrb-booking-widget-submit"><?php echo \esc_html__('Request this time', 'sc-room-bookings'); ?></button>
                        </p>
                    </form>

                    <div class="scrb-booking-widget-success" hidden>
                        <p><?php echo \esc_html__('Thanks — we\'ve received your request. We\'ll be in touch by email to confirm.', 'sc-room-bookings'); ?></p>
                        <button type="button" class="scrb-booking-widget-done"><?php echo \esc_html__('Close', 'sc-room-bookings'); ?></button>
                    </div>
                </div>
            </dialog>
        </div>
        <?php
        return (string) \ob_get_clean();
    }
}

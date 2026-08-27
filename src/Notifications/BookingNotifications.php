<?php

namespace SCRoomBookings\Notifications;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\PostTypes\BookingPostType;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\BookingMeta;

/**
 * Plain wp_mail() on the two events Booking\Booking already fires —
 * no queue, no third-party mail service, matching SC Events Manager's
 * own bias toward the simplest thing that works on any host. A site
 * that wants richer notifications (SMS, Slack, a proper transactional
 * email service) hooks scrb_booking_created / scrb_booking_status_
 * changed directly rather than this plugin growing integrations for
 * services it can't know its buyers will use.
 */
final class BookingNotifications implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('scrb_booking_created', [$this, 'notifyAdminOfNewBooking'], 10, 1);
        \add_action('scrb_booking_status_changed', [$this, 'notifyRequesterOfStatusChange'], 10, 2);
    }

    public function notifyAdminOfNewBooking(int $bookingId): void
    {
        $booking = BookingMeta::read($bookingId);
        $room = \get_post($booking['room_id']);
        $roomName = $room ? \get_the_title($room) : \__('(room deleted)', 'sc-room-bookings');

        $subject = \sprintf(\__('New booking request: %s', 'sc-room-bookings'), $roomName);
        $body = $this->formatBookingSummary($booking, $roomName);
        $body .= "\n\n".\sprintf(
            \__('Review it: %s', 'sc-room-bookings'),
            \admin_url('admin.php?page=scrb-bookings')
        );

        \wp_mail($this->settings->notificationEmail(), $subject, $body);
    }

    public function notifyRequesterOfStatusChange(int $bookingId, string $status): void
    {
        // Only these two are worth a "here's what happened" email —
        // cancelled bookings are almost always cancelled *by* the
        // requester's own later action elsewhere (a phone call, a
        // separate cancellation flow), not something they need told
        // about themselves.
        if (! \in_array($status, [BookingPostType::STATUS_CONFIRMED, BookingPostType::STATUS_DECLINED], true)) {
            return;
        }

        $booking = BookingMeta::read($bookingId);

        if ($booking['requester_email'] === '' || ! \is_email($booking['requester_email'])) {
            return;
        }

        $room = \get_post($booking['room_id']);
        $roomName = $room ? \get_the_title($room) : \__('(room deleted)', 'sc-room-bookings');

        $subject = $status === BookingPostType::STATUS_CONFIRMED
            ? \sprintf(\__('Your booking is confirmed: %s', 'sc-room-bookings'), $roomName)
            : \sprintf(\__('About your booking request: %s', 'sc-room-bookings'), $roomName);

        $body = $this->formatBookingSummary($booking, $roomName);

        \wp_mail($booking['requester_email'], $subject, $body);
    }

    /**
     * @param array<string, mixed> $booking
     */
    private function formatBookingSummary(array $booking, string $roomName): string
    {
        $start = new \DateTimeImmutable($booking['start_datetime']);
        $end = new \DateTimeImmutable($booking['end_datetime']);

        $lines = [
            \sprintf(\__('Room: %s', 'sc-room-bookings'), $roomName),
            \sprintf(\__('When: %s', 'sc-room-bookings'), $start->format('l j F Y, g:ia').' – '.$end->format('g:ia')),
            \sprintf(\__('Name: %s', 'sc-room-bookings'), $booking['requester_name']),
        ];

        if ($booking['requester_message'] !== '') {
            $lines[] = \sprintf(\__('Message: %s', 'sc-room-bookings'), $booking['requester_message']);
        }

        return \implode("\n", $lines);
    }
}

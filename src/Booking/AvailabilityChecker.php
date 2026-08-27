<?php

namespace SCRoomBookings\Booking;

use SCRoomBookings\PostTypes\BookingPostType;
use SCRoomBookings\Settings\Settings;
use SCRoomBookings\Support\BookingMeta;
use SCRoomBookings\Support\RoomMeta;

/**
 * Whether a room can actually take a given start/end — the room's own
 * opening days/hours and minimum-booking-length, the site's minimum
 * notice period, and no overlap with an existing pending/confirmed
 * booking (plus that room's buffer either side).
 *
 * Deliberately checks against pending bookings, not just confirmed
 * ones — two people racing to request the same slot should see it as
 * unavailable the moment the first request lands, not just once an
 * admin has approved it. Settings::requireApproval() controls whether
 * a human looks at a request before it's binding; it was never meant
 * to control whether a second person can double-book the same gap in
 * the meantime.
 */
final class AvailabilityChecker
{
    /**
     * Statuses that occupy a slot — see the class docblock for why
     * pending counts alongside confirmed.
     */
    private const BLOCKING_STATUSES = [BookingPostType::STATUS_PENDING, BookingPostType::STATUS_CONFIRMED];

    public function __construct(private Settings $settings)
    {
    }

    /**
     * @return string|null A human-readable reason it's unavailable, or null if it's free.
     */
    public function checkAvailability(
        int $roomId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $excludeBookingId = null
    ): ?string {
        if ($end <= $start) {
            return \__('The end time must be after the start time.', 'sc-room-bookings');
        }

        $room = RoomMeta::read($roomId);

        $lengthMinutes = ($end->getTimestamp() - $start->getTimestamp()) / 60;

        if ($lengthMinutes < $room['min_booking_minutes']) {
            return \sprintf(
                \__('This room needs at least %d minutes per booking.', 'sc-room-bookings'),
                $room['min_booking_minutes']
            );
        }

        $weekday = \strtolower($start->format('D')); // 'mon', 'tue', ...

        if (! \in_array($weekday, $room['available_days'], true)) {
            return \__('This room isn\'t available on that day.', 'sc-room-bookings');
        }

        if ($start->format('H:i') < $room['available_start_time'] || $end->format('H:i') > $room['available_end_time']) {
            return \sprintf(
                \__('This room is only available between %s and %s.', 'sc-room-bookings'),
                $room['available_start_time'],
                $room['available_end_time']
            );
        }

        $minNoticeHours = (int) \apply_filters('scrb_min_notice_hours', $this->settings->minNoticeHours(), $roomId);
        $earliestAllowed = new \DateTimeImmutable(\sprintf('+%d hours', $minNoticeHours));

        if ($start < $earliestAllowed) {
            return $minNoticeHours > 0
                ? \sprintf(\__('Bookings need at least %d hours\' notice.', 'sc-room-bookings'), $minNoticeHours)
                : \__('That time has already passed.', 'sc-room-bookings');
        }

        $buffer = new \DateInterval(\sprintf('PT%dM', $room['buffer_minutes']));
        $bufferedStart = $start->sub($buffer);
        $bufferedEnd = $end->add($buffer);

        foreach ($this->blockingBookingsForRoom($roomId, $excludeBookingId) as $existing) {
            $existingStart = new \DateTimeImmutable($existing['start_datetime']);
            $existingEnd = new \DateTimeImmutable($existing['end_datetime']);

            // Classic interval overlap: two ranges overlap unless one
            // ends before the other starts. Compared against the
            // *buffered* requested range so the room's own gap
            // requirement is honoured on both sides, not just between
            // two bookings that are each individually buffer-compliant
            // against everything except this new one.
            if ($existingStart < $bufferedEnd && $existingEnd > $bufferedStart) {
                return \__('That slot is no longer available — please choose another time.', 'sc-room-bookings');
            }
        }

        return null;
    }

    public function isAvailable(int $roomId, \DateTimeImmutable $start, \DateTimeImmutable $end, ?int $excludeBookingId = null): bool
    {
        return $this->checkAvailability($roomId, $start, $end, $excludeBookingId) === null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blockingBookingsForRoom(int $roomId, ?int $excludeBookingId): array
    {
        $query = new \WP_Query([
            'post_type' => BookingPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'post__not_in' => $excludeBookingId ? [$excludeBookingId] : [],
            'meta_query' => [
                'relation' => 'AND',
                ['key' => '_scrb_room_id', 'value' => $roomId, 'compare' => '='],
                ['key' => '_scrb_status', 'value' => self::BLOCKING_STATUSES, 'compare' => 'IN'],
            ],
        ]);

        return \array_map(static fn (int $id): array => BookingMeta::read($id), $query->posts);
    }
}

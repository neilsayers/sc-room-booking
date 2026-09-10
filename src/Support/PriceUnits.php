<?php

namespace SCRoomBookings\Support;

/**
 * The unit a price option is quoted per — kept short and generic
 * rather than trying to anticipate every venue's billing model. Lives
 * here (not inside MetaBoxes\RoomDetailsMetaBox, where it started) so
 * Frontend\RoomListing can label each price option's unit for theme/
 * REST consumers too, not just the admin dropdown.
 */
final class PriceUnits
{
    public const UNITS = [
        'hour' => 'per hour',
        'session' => 'per session',
        'day' => 'per day',
        'person' => 'per person',
    ];

    public static function label(string $key): string
    {
        return self::UNITS[$key] ?? $key;
    }
}

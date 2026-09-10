<?php

namespace SCRoomBookings\Support;

/**
 * The seating/configuration styles a room's "layout options" (see
 * MetaBoxes\RoomDetailsMetaBox) can be set out in — e.g. a hall that's
 * 300 capacity cleared, 220 theatre-style, 150 cabaret. A fixed list
 * rather than free text per row, same reasoning as Support\PriceUnits:
 * a small admin-managed enum both the meta box's dropdown and
 * Frontend\RoomListing's output can share one definition of.
 */
final class LayoutTypes
{
    public const TYPES = [
        'clear' => 'Clear',
        'theatre' => 'Theatre-style',
        'cabaret' => 'Cabaret',
        'classroom' => 'Classroom',
        'boardroom' => 'Boardroom',
        'u_shape' => 'U-shape',
        'banquet' => 'Banquet',
        'reception' => 'Standing reception',
    ];

    public static function label(string $key): string
    {
        return self::TYPES[$key] ?? $key;
    }
}

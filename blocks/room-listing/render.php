<?php

/**
 * Dynamic (server-rendered) block — this file's job is only to shape
 * $attributes into what RoomListingShortcode::render() already
 * expects, the exact same rendering [sc_rooms] itself uses, so a
 * block and its shortcode twin can never drift apart.
 *
 * @var array<string, mixed> $attributes
 */

echo \SCRoomBookings\Frontend\RoomListingShortcode::render([
    'type' => (string) ($attributes['roomType'] ?? ''),
    'limit' => (int) ($attributes['limit'] ?? 0),
]);

<?php

/**
 * Dynamic (server-rendered) block — reuses RoomDetailShortcode::render(),
 * the exact same rendering [sc_room] itself uses, so a block and its
 * shortcode twin can never drift apart.
 *
 * @var array<string, mixed> $attributes
 */

$roomId = (int) ($attributes['roomId'] ?? 0);

if ($roomId <= 0) {
    echo '<p class="scrb-listing-empty">'.\esc_html__('Select a room in the block\'s sidebar.', 'sc-room-bookings').'</p>';

    return;
}

echo \SCRoomBookings\Frontend\RoomDetailShortcode::render(['id' => $roomId]);

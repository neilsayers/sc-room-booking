<?php

/**
 * Dynamic (server-rendered) block — reuses RoomDetailShortcode::render(),
 * the exact same rendering [sc_room] itself uses, so a block and its
 * shortcode twin can never drift apart.
 *
 * @var array<string, mixed> $attributes
 */

$roomId = (int) ($attributes['roomId'] ?? 0);

// A dynamic block's save() returns null (see index.js), so WordPress
// never gets the chance to add the usual wp-block-* wrapper the way it
// would for a static block's own save() markup — get_block_wrapper_attributes()
// is how a render.php is expected to add that itself. Without it the
// block had no wp-block class at all, so nothing constrained it to the
// theme's own content width the way every other block already was.
if ($roomId <= 0) {
    printf(
        '<div %s><p class="scrb-listing-empty">%s</p></div>',
        \get_block_wrapper_attributes(),
        \esc_html__('Select a room in the block\'s sidebar.', 'sc-room-bookings')
    );

    return;
}

printf(
    '<div %s>%s</div>',
    \get_block_wrapper_attributes(),
    \SCRoomBookings\Frontend\RoomDetailShortcode::render(['id' => $roomId])
);

<?php

/**
 * Dynamic (server-rendered) block — this file's job is only to shape
 * $attributes into what RoomListingShortcode::render() already
 * expects, the exact same rendering [sc_rooms] itself uses, so a
 * block and its shortcode twin can never drift apart.
 *
 * @var array<string, mixed> $attributes
 */

// A dynamic block's save() returns null (see index.js), so WordPress
// never gets the chance to add the usual wp-block-* wrapper the way it
// would for a static block's own save() markup — get_block_wrapper_attributes()
// is how a render.php is expected to add that itself. Without it the
// block had no wp-block class at all, so nothing constrained it to the
// theme's own content width the way every other block already was.
printf(
    '<div %s>%s</div>',
    \get_block_wrapper_attributes(),
    \SCRoomBookings\Frontend\RoomListingShortcode::render([
        'type' => (string) ($attributes['roomType'] ?? ''),
        'limit' => (int) ($attributes['limit'] ?? 0),
    ])
);

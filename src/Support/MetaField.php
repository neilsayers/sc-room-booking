<?php

namespace SCRoomBookings\Support;

/**
 * Tiny postmeta read/write helpers shared by every hand-written meta
 * box in this plugin — an empty value always means "delete the row"
 * rather than storing an empty string, so postmeta stays tidy.
 */
final class MetaField
{
    public static function saveText(int $postId, string $key, string $value): void
    {
        self::saveValue($postId, $key, \sanitize_text_field($value));
    }

    public static function saveValue(int $postId, string $key, string $value): void
    {
        if ($value === '') {
            \delete_post_meta($postId, $key);

            return;
        }

        \update_post_meta($postId, $key, $value);
    }

    /**
     * Handles both a flat list (e.g. available_days) and a repeater's
     * array-of-rows (e.g. price_options, layout_variants) — WordPress
     * serializes either shape into postmeta the same way, so one
     * method covers both rather than needing a separate helper per
     * shape.
     *
     * @param array<int|string, mixed> $value
     */
    public static function saveArray(int $postId, string $key, array $value): void
    {
        if ($value === []) {
            \delete_post_meta($postId, $key);

            return;
        }

        \update_post_meta($postId, $key, $value);
    }
}

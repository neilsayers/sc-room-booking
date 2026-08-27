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
     * @param string[] $value
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

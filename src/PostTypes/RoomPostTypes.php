<?php

namespace SCRoomBookings\PostTypes;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;

/**
 * Registers every configured room type as its own post type. Nothing
 * is registered until at least one type has been created via the
 * admin screen — see Admin\RoomTypesPage.
 */
final class RoomPostTypes implements Hookable
{
    /**
     * A flush must happen on the request *after* a new type is first
     * registered — flushing during the same request that saves it is
     * too early, since register_post_type() for it hasn't run yet at
     * that point in the request lifecycle (init fires before the
     * admin-post.php handler that saves it). Admin\RoomTypesPage sets
     * this transient instead of flushing directly.
     */
    public const FLUSH_TRANSIENT = 'scrb_flush_rewrite_rules';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('init', [$this, 'registerPostTypes']);
    }

    public function registerPostTypes(): void
    {
        foreach ($this->settings->allRoomTypes() as $roomType) {
            \register_post_type($roomType['post_type'], [
                'labels' => $this->buildLabels($roomType['label_singular'], $roomType['label_plural']),
                'public' => true,
                'has_archive' => true,
                'show_in_rest' => false, // Classic editor, matching SC Events Manager's editor choice.
                'menu_icon' => 'dashicons-admin-multisite',
                'rewrite' => ['slug' => $roomType['slug']],
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            ]);
        }

        if (\get_transient(self::FLUSH_TRANSIENT)) {
            \delete_transient(self::FLUSH_TRANSIENT);
            \flush_rewrite_rules();
        }
    }

    private function buildLabels(string $singular, string $plural): array
    {
        return [
            'name' => $plural,
            'singular_name' => $singular,
            'add_new' => \sprintf('Add New %s', $singular),
            'add_new_item' => \sprintf('Add New %s', $singular),
            'edit_item' => \sprintf('Edit %s', $singular),
            'new_item' => \sprintf('New %s', $singular),
            'view_item' => \sprintf('View %s', $singular),
            'view_items' => \sprintf('View %s', $plural),
            'search_items' => \sprintf('Search %s', $plural),
            'not_found' => \sprintf('No %s found', \strtolower($plural)),
            'not_found_in_trash' => \sprintf('No %s found in Trash', \strtolower($plural)),
            'all_items' => \sprintf('All %s', $plural),
            'archives' => \sprintf('%s Archives', $singular),
            'menu_name' => $plural,
            'name_admin_bar' => $singular,
        ];
    }
}

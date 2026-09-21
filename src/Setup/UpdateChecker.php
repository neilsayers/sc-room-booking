<?php

namespace SCRoomBookings\Setup;

use SCRoomBookings\Contracts\Hookable;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * This plugin isn't on WordPress.org (it's sold directly, not given
 * away), so the wp-admin Plugins screen has nothing to check it
 * against out of the box — WordPress's own update cron only ever
 * queries api.wordpress.org. This points that same "Update available"
 * UI at this plugin's own GitHub repo instead, via the (vendored,
 * build-step-free) Plugin Update Checker library — see vendor/
 * plugin-update-checker/README.md for the library's own docs.
 *
 * Releasing an update is just tagging the repo `vX.Y.Z` and pushing —
 * no separate zip to build or upload, matching this plugin's existing
 * "no build step" approach: PUC downloads the tag's own source archive
 * directly, which is already a complete, ready-to-run copy.
 */
final class UpdateChecker implements Hookable
{
    private const REPO_URL = 'https://github.com/neilsayers/sc-room-booking/';

    public function register(): void
    {
        \add_action('init', [$this, 'boot']);
    }

    public function boot(): void
    {
        require_once SCRB_PATH.'vendor/plugin-update-checker/plugin-update-checker.php';

        PucFactory::buildUpdateChecker(self::REPO_URL, SCRB_FILE, 'sc-room-bookings');
    }
}

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
 * Releasing an update is just bumping the plugin header's Version and
 * pushing: .github/workflows/release.yml tags vX.Y.Z and attaches a
 * built sc-room-bookings.zip to the GitHub Release. enableReleaseAssets()
 * makes sites install that zip, whose folder is named correctly, rather
 * than GitHub's source archive.
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

        PucFactory::buildUpdateChecker(self::REPO_URL, SCRB_FILE, 'sc-room-bookings')
            ->getVcsApi()
            ->enableReleaseAssets();
    }
}

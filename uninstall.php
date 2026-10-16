<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Rooms, facilities and bookings are left in place on uninstall — they're
// the site's own records (bookings include requesters' contact details),
// not plugin config, and deleting them silently on a WordPress "uninstall"
// click would be a nasty surprise. Only this plugin's own settings,
// version marker, transients and update-checker state are removed.
delete_option('scrb_settings');
delete_option('scrb_version');

delete_transient('scrb_flush_rewrite_rules');
delete_transient('scrb_activation_redirect');

// Plugin Update Checker's stored state and cron, keyed by the slug passed
// to buildUpdateChecker() in src/Setup/UpdateChecker.php.
delete_site_option('external_updates-sc-room-bookings');
wp_clear_scheduled_hook('puc_cron_check_updates-sc-room-bookings');

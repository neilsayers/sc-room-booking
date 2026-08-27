<?php

/**
 * Plugin Name:       SC Room Bookings
 * Plugin URI:        https://screencandy.co.uk
 * Description:       A site-agnostic room/space booking manager. On first activation, guides you through naming your own bookable post type (e.g. "Room", "Court", "Desk") before anything is registered.
 * Version:           0.3.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Neil Sayers
 * Author URI:        https://screencandy.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sc-room-bookings
 */

namespace SCRoomBookings;

if (! defined('ABSPATH')) {
    exit;
}

define('SCRB_VERSION', '0.3.0');
define('SCRB_FILE', __FILE__);
define('SCRB_PATH', \plugin_dir_path(__FILE__));
define('SCRB_URL', \plugin_dir_url(__FILE__));

/**
 * Minimal PSR-4-style autoloader so this plugin has zero build step
 * or Composer dependency — it just needs to be copied into any site's
 * wp-content/plugins and activated. Same shape as SC Events Manager's
 * own autoloader, deliberately — the two are meant to read as a
 * matched pair, not independently-invented siblings.
 */
\spl_autoload_register(function (string $class): void {
    $prefix = __NAMESPACE__.'\\';

    if (! \str_starts_with($class, $prefix)) {
        return;
    }

    $relative = \substr($class, \strlen($prefix));
    $path = SCRB_PATH.'src/'.\str_replace('\\', '/', $relative).'.php';

    if (\is_file($path)) {
        require $path;
    }
});

\register_activation_hook(__FILE__, [Setup\Activator::class, 'activate']);
\register_deactivation_hook(__FILE__, [Setup\Activator::class, 'deactivate']);

require SCRB_PATH.'src/Frontend/template-functions.php';

Plugin::instance()->boot();

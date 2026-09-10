<?php

namespace SCRoomBookings\Setup;

use SCRoomBookings\Contracts\Hookable;

/**
 * One-time data migrations that need to run when an already-installed
 * site updates to a newer copy of this plugin — as opposed to
 * Activator, which only ever runs on a fresh activation. Hooked at
 * 'plugins_loaded' priority 5, ahead of every other feature's own
 * 'init' registration, so a migration a class like Taxonomies\
 * FacilityTaxonomy depends on (the DB rename below) has already
 * happened by the time that class runs.
 */
final class Upgrader implements Hookable
{
    private const VERSION_OPTION = 'scrb_version';

    public function register(): void
    {
        \add_action('plugins_loaded', [$this, 'maybeUpgrade'], 5);
    }

    public function maybeUpgrade(): void
    {
        $installed = (string) \get_option(self::VERSION_OPTION, '0.0.0');

        if (\version_compare($installed, SCRB_VERSION, '>=')) {
            return;
        }

        if (\version_compare($installed, '0.4.0', '<')) {
            $this->renameFacilitiesTaxonomy();
        }

        \update_option(self::VERSION_OPTION, SCRB_VERSION);
    }

    /**
     * The Amenities taxonomy became Facilities in 0.4.0 (see readme's
     * changelog) — renames it in place at the DB level so every term
     * and every room's existing selections survive untouched, rather
     * than the rename silently orphaning whatever's already ticked on
     * a live room. A plain $wpdb->update() rather than unregistering/
     * re-registering the taxonomy: WordPress has no built-in "rename a
     * taxonomy" operation, and term relationships are stored against
     * the taxonomy's own slug in wp_term_taxonomy, not against
     * anything derived from its labels.
     */
    private function renameFacilitiesTaxonomy(): void
    {
        global $wpdb;

        $renamed = $wpdb->update(
            $wpdb->term_taxonomy,
            ['taxonomy' => 'scrb_facility'],
            ['taxonomy' => 'scrb_amenity']
        );

        if ($renamed) {
            \wp_cache_flush();
        }
    }
}

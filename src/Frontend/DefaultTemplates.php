<?php

namespace SCRoomBookings\Frontend;

use SCRoomBookings\Contracts\Hookable;
use SCRoomBookings\Settings\Settings;

/**
 * A single/archive view for every configured room type, so a site
 * that's just activated this plugin — and never told its theme
 * anything about "room", "court", or whatever it named its bookable
 * type — still gets a page worth looking at, not the theme's bare
 * title-and-content fallback. This is what makes the plugin's "drop
 * it into any site" claim (see the main plugin file's own description)
 * true for the front end too, not just the admin/booking side.
 *
 * Deliberately conservative about *when* it steps in — the moment a
 * site's theme has taken any position on how to show a room, this
 * gets out of the way entirely rather than guessing whether the
 * theme's version is "good enough":
 *
 *  - A classic theme's own single-{post_type}.php/archive-{post_type}.php
 *    (found via locate_template(), the normal WP override mechanism)
 *    always wins.
 *  - A Sage/Acorn theme (detected via Roots\Acorn\Application) is
 *    skipped entirely, full stop. Sage themes never have a
 *    locate_template()-visible single-{post_type}.php — their own
 *    per-post-type customisation lives in a Blade partial
 *    (content-single-{post_type}.blade.php) that this filter has no
 *    way to see, so locate_template() alone can't tell "customised"
 *    from "not yet customised" on one of these themes. Getting that
 *    wrong the other way — silently overriding a Sage theme's actual
 *    Blade output — is worse than never stepping in on one, so this
 *    plugin just never tries; see civic-centre-uckfield's own
 *    content-single-room.blade.php for how a Sage site does this
 *    itself instead.
 */
final class DefaultTemplates implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_filter('template_include', [$this, 'maybeServeDefault']);
    }

    public function maybeServeDefault(string $template): string
    {
        if (\class_exists(\Roots\Acorn\Application::class)) {
            return $template;
        }

        $roomTypes = \array_keys($this->settings->allRoomTypes());

        if ($roomTypes === []) {
            return $template;
        }

        if (\is_singular($roomTypes)) {
            $postType = \get_post_type();

            if ($postType !== false && \locate_template(["single-{$postType}.php"]) === '') {
                return SCRB_PATH.'templates/single-room.php';
            }
        }

        if (\is_post_type_archive($roomTypes)) {
            $postType = (string) \get_query_var('post_type');

            if ($postType !== '' && \locate_template(["archive-{$postType}.php"]) === '') {
                return SCRB_PATH.'templates/archive-room.php';
            }
        }

        return $template;
    }
}

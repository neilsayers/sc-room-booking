<?php

/**
 * This plugin's own single-room view — served by
 * Frontend\DefaultTemplates only when the active theme has no
 * single-{post_type}.php of its own (and isn't a Sage/Acorn theme,
 * which always owns its whole template hierarchy itself — see
 * DefaultTemplates::maybeServeDefault()'s own docblock for why). A
 * site that wants full control just adds its own
 * single-{post_type}.php (or, on a Sage-based theme, a
 * content-single-{post_type}.blade.php) and never reaches this file
 * again.
 */

if (! \defined('ABSPATH')) {
    exit;
}

\get_header();
?>
<main class="scrb-single-room">
    <?php while (\have_posts()) : \the_post(); ?>
        <?php $room = \SCRoomBookings\Frontend\RoomListing::find(\get_the_ID()); ?>
        <?php if ($room !== null) : ?>
            <?php \SCRoomBookings\Frontend\RoomDetail::render($room, ['heading' => 'h1']); ?>
        <?php endif; ?>
    <?php endwhile; ?>
</main>
<?php
\get_footer();

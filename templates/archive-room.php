<?php

/**
 * This plugin's own archive-room view — see single-room.php's own
 * docblock for exactly when Frontend\DefaultTemplates serves this
 * instead of the active theme's.
 */

if (! \defined('ABSPATH')) {
    exit;
}

\get_header();
?>
<main class="scrb-archive-room">
    <h1 class="scrb-archive-title"><?php echo \esc_html(\post_type_archive_title('', false)); ?></h1>

    <?php if (\have_posts()) : ?>
        <div class="scrb-listing">
            <?php while (\have_posts()) : \the_post(); ?>
                <?php $room = \SCRoomBookings\Frontend\RoomListing::find(\get_the_ID()); ?>
                <?php if ($room !== null) : ?>
                    <?php \SCRoomBookings\Frontend\RoomCard::render($room); ?>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>

        <?php \the_posts_pagination(); ?>
    <?php else : ?>
        <p class="scrb-listing-empty"><?php echo \esc_html__('No rooms found.', 'sc-room-bookings'); ?></p>
    <?php endif; ?>
</main>
<?php
\get_footer();

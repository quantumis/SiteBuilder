<?php
/**
 * Footer variant: Simple.
 * Single line of centered copyright. No menu, no extras.
 * Good for landing pages and minimalist sites.
 */
if (!defined('ABSPATH')) exit;
?>
<footer class="sb-footer sb-footer-simple">
    <div class="sb-footer-inner">
        <span class="sb-footer-copyright">
            &copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>
        </span>
    </div>
</footer>

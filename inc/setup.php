<?php
/**
 * DeviceHub — Theme Setup
 *
 * Theme supports, nav menus, image sizes.
 * No enqueue, no hooks, no output here.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

add_action('after_setup_theme', 'devhub_theme_setup');

function devhub_theme_setup(): void
{
    load_theme_textdomain('devicehub-theme', DEVHUB_DIR . '/languages');

    add_theme_support('automatic-feed-links');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('custom-header');
    add_theme_support('customize-selective-refresh-widgets');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ]);

    add_theme_support('custom-background', [
        'default-color' => 'ffffff',
        'default-image' => '',
    ]);

    register_nav_menus([
        'primary_menu' => esc_html__('Primary Menu', 'devicehub-theme'),
        'secondary_menu' => esc_html__('Secondary Navigation', 'devicehub-theme'),
        'secondary_offers_menu' => esc_html__('Secondary Offers Dropdown', 'devicehub-theme'),
    ]);

    // Thumbnail sizes used by product cards and sections
    add_image_size('devhub-card', 400, 400, true);
    add_image_size('devhub-banner', 800, 600, false);

    if (!isset($content_width)) {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
        $GLOBALS['content_width'] = 1280;
    }
}

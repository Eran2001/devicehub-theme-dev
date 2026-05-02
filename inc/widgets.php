<?php
/**
 * DeviceHub — Widgets
 *
 * Register all sidebar areas.
 * No logic, no output — only register_sidebar calls.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

add_action('widgets_init', 'devhub_register_sidebars');

function devhub_register_sidebars(): void
{

    if (class_exists('WooCommerce')) {
        register_sidebar([
            'name' => __('WooCommerce Widget Area', 'devicehub-theme'),
            'id' => 'shopire-woocommerce-sidebar',
            'before_widget' => '<aside id="%1$s" class="widget %2$s">',
            'after_widget' => '</aside>',
            'before_title' => '<h5 class="widget-title">',
            'after_title' => '</h5>',
        ]);
    }

    register_sidebar([
        'name' => __('Sidebar Widget Area', 'devicehub-theme'),
        'id' => 'shopire-sidebar-primary',
        'before_widget' => '<aside id="%1$s" class="widget %2$s">',
        'after_widget' => '</aside>',
        'before_title' => '<h5 class="widget-title"><span></span>',
        'after_title' => '</h5>',
    ]);

    register_sidebar([
        'name' => __('Header Top Bar Widget Area', 'devicehub-theme'),
        'id' => 'shopire-header-top-sidebar',
        'before_widget' => '<aside id="%1$s" class="widget %2$s">',
        'after_widget' => '</aside>',
    ]);

    register_sidebar([
        'name' => __('Header Side Docker Area', 'devicehub-theme'),
        'id' => 'shopire-header-docker-sidebar',
        'before_widget' => '<aside id="%1$s" class="widget %2$s">',
        'after_widget' => '</aside>',
        'before_title' => '<h5 class="widget-title"><span></span>',
        'after_title' => '</h5>',
    ]);

    // Footer widget columns — count comes from Customizer
    $footer_columns = (int) get_theme_mod('shopire_footer_widget_column', 4);
    for ($i = 1; $i <= $footer_columns; $i++) {
        register_sidebar([
            'name' => __('Footer ', 'devicehub-theme') . $i,
            'id' => 'shopire-footer-widget-' . $i,
            'before_widget' => '<aside id="%1$s" class="widget %2$s">',
            'after_widget' => '</aside>',
            'before_title' => '<h5 class="widget-title">',
            'after_title' => '</h5>',
        ]);
    }
}
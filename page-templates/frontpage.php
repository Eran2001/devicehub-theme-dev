<?php
/**
 * Template Name: Frontpage
 *
 * @package DeviceHub
 */

get_header();

do_action('devhub_hero_section');
do_action('devhub_flash_section');
do_action('devhub_categories_section');
do_action('devhub_home_product_sections');

get_footer();

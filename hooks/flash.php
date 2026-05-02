<?php
/**
 * DeviceHub — Flash Sale Section
 *
 * Queries WooCommerce products that have an active sale price
 * with a future sale end date. Countdown reads data-countdown.
 * Image: always the dummy SVG (build phase).
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

add_action('devhub_flash_section', 'devhub_render_flash_section');

function devhub_render_flash_section(): void
{
    if (!devhub_has_catalog_data()) {
        return;
    }

    $img = DEVHUB_URI . '/assets/images/Original-Img.svg';
    $modifiers = ['green', 'yellow'];

    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 2,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => '_sale_price',
                'value' => '',
                'compare' => '!=',
            ],
            [
                'key' => '_sale_price_dates_to',
                'value' => time(),
                'compare' => '>=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return;
    }
    ?>
    <section class="devhub-flash" aria-label="<?php esc_attr_e('Flash sale', 'devicehub-theme'); ?>">
        <div class="wf-container">
            <div class="devhub-flash__grid">
                <?php
                $idx = 0;
                while ($query->have_posts()):
                    $query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if (!$product) {
                        $idx++;
                        continue;
                    }

                    $sale_to = $product->get_date_on_sale_to();
                    $end_date = $sale_to ? gmdate('Y-m-d', $sale_to->getTimestamp()) . 'T23:59:59Z' : '';
                    $modifier = $modifiers[$idx % count($modifiers)];
                    $image_class = 'devhub-flash__img';

                    if ($product->get_name() === 'Apple iPhone 16 1TB Pink (MQ233)') {
                        $image_class .= ' devhub-flash__img--pink-iphone';
                    }
                    ?>
                    <a
                        class="devhub-flash__card devhub-flash__card--<?php echo esc_attr($modifier); ?>"
                        href="<?php echo esc_url(get_permalink($product->get_id())); ?>"
                        aria-label="<?php echo esc_attr($product->get_name()); ?>">

                        <div class="devhub-flash__img-wrap">
                            <div class="devhub-flash__img-circle">
                                <!-- <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($product->get_name()); ?>"> -->
                                <img class="<?php echo esc_attr($image_class); ?>"
                                    src="<?php echo esc_url(get_the_post_thumbnail_url($product->get_id(), 'woocommerce_single') ?: $img); ?>"
                                    alt="<?php echo esc_attr($product->get_name()); ?>">

                            </div>
                        </div>

                        <div class="devhub-flash__body">
                            <div class="devhub-flash__timer-wrap">
                                <div class="devhub-flash__timer-labels" aria-hidden="true">
                                    <span><?php esc_html_e('Days', 'devicehub-theme'); ?></span>
                                    <span><?php esc_html_e('Hours', 'devicehub-theme'); ?></span>
                                    <span><?php esc_html_e('Minutes', 'devicehub-theme'); ?></span>
                                    <span><?php esc_html_e('Seconds', 'devicehub-theme'); ?></span>
                                </div>
                                <div class="devhub-flash__timer" data-countdown="<?php echo esc_attr($end_date); ?>"
                                    aria-live="polite">
                                    <span class="devhub-flash__time-part" data-part="days">00</span>
                                    <span class="devhub-flash__sep" aria-hidden="true">:</span>
                                    <span class="devhub-flash__time-part" data-part="hours">00</span>
                                    <span class="devhub-flash__sep" aria-hidden="true">:</span>
                                    <span class="devhub-flash__time-part" data-part="minutes">00</span>
                                    <span class="devhub-flash__sep" aria-hidden="true">:</span>
                                    <span class="devhub-flash__time-part" data-part="seconds">00</span>
                                </div>
                            </div>

                            <div class="devhub-flash__info">
                                <p class="devhub-flash__name"><?php echo esc_html($product->get_name()); ?></p>
                                <p class="devhub-flash__price">
                                    <?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                </p>
                            </div>
                        </div>

                    </a>
                    <?php
                    $idx++;
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        </div>
    </section>
    <?php
}

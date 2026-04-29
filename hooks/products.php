<?php
/**
 * DeviceHub — Product Sections
 *
 * Real WooCommerce data, fixed local SVG images.
 * Brand tabs are hardcoded for UI — JS filters by data-brands attribute.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;


/**
 * Shared product section renderer.
 *
 * Queries WooCommerce products by category slug and renders
 * the section using devhub_render_product_card() with a fixed
 * local image override (no WC product image used).
 *
 * @param string $title          Section heading
 * @param string $section_id     HTML id attribute + JS hook
 * @param array  $brands         Brand tab labels (hardcoded for UI phase)
 * @param string $category_slug  WooCommerce product_cat slug to query
 * @param string $img            Absolute URL to the local SVG image
 * @param string $view_all_url   URL for the "View All" link
 */
function devhub_render_product_section(
    string $title,
    string $section_id,
    array $brands,
    string $category_slug,
    string $img,
    string $view_all_url = ''
): void {
    if (!devhub_has_catalog_data()) {
        return;
    }

    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 8,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => $category_slug,
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return;
    }

    if ($view_all_url === '') {
        $view_all_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    }
    ?>
    <section class="devhub-products" id="<?php echo esc_attr($section_id); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <div class="wf-container">

            <div class="devhub-products__header">
                <h2 class="devhub-products__title"><?php echo esc_html($title); ?></h2>
                <a href="<?php echo esc_url($view_all_url); ?>" class="devhub-products__view-all">
                    View All <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="devhub-products__grid" id="<?php echo esc_attr($section_id); ?>-grid">
                <?php
                while ($query->have_posts()) {
                    $query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if ($product) {
                        // devhub_render_product_card($product, $img);
                        devhub_render_product_card($product, get_the_post_thumbnail_url($product->get_id(), 'woocommerce_single') ?: $img);
                    }
                }
                wp_reset_postdata();
                ?>
            </div>

        </div>
    </section>
    <?php
}


// ── Mobile Phones ─────────────────────────────────────────────────────────────

add_action('devhub_mobile_phones_section', 'devhub_render_mobile_phones_section');

function devhub_render_mobile_phones_section(): void
{
    devhub_render_product_section(
        'Mobile Phones',
        'devhub-mobile-phones',
        ['Apple', 'Samsung', 'Vivo', 'Redmi', 'OnePlus'],
        'mobile-phones',
        DEVHUB_URI . '/assets/images/Original-Img.svg'
    );
}


// ── Broad Bands ───────────────────────────────────────────────────────────────

add_action('devhub_broadbands_section', 'devhub_render_broadbands_section');

function devhub_render_broadbands_section(): void
{
    devhub_render_product_section(
        'Broad Bands',
        'devhub-broad-bands',
        ['TP-Link', 'Huawei', 'ZTE'],
        'broad-bands',
        DEVHUB_URI . '/assets/images/Original-Router-Img.svg'
    );
}


// ── Electronics ───────────────────────────────────────────────────────────────

add_action('devhub_electronics_section', 'devhub_render_electronics_section');

function devhub_render_electronics_section(): void
{
    devhub_render_product_section(
        'Electronics',
        'devhub-electronics',
        [],
        'electronics',
        DEVHUB_URI . '/assets/images/Original-Img.svg'
    );
}


// ── Accessories ───────────────────────────────────────────────────────────────

add_action('devhub_accessories_section', 'devhub_render_accessories_section');

function devhub_render_accessories_section(): void
{
    devhub_render_product_section(
        'Accessories',
        'devhub-accessories',
        [],
        'accessories',
        DEVHUB_URI . '/assets/images/Original-Img.svg'
    );
}

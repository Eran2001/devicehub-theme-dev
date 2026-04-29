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
 * @param string $category_slug  WooCommerce product_cat slug to query
 * @param string $img            Absolute URL to the local SVG image
 * @param string $view_all_url   URL for the "View All" link
 */
function devhub_render_product_section(
    string $title,
    string $section_id,
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
        'posts_per_page' => 10,
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

    $section_brands = devhub_get_product_section_brands(wp_list_pluck($query->posts, 'ID'));

    if ($view_all_url === '') {
        $view_all_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
    }
    ?>
    <section class="devhub-products" id="<?php echo esc_attr($section_id); ?>" aria-label="<?php echo esc_attr($title); ?>">
        <div class="wf-container">

            <div class="devhub-products__header">
                <h2 class="devhub-products__title"><?php echo esc_html($title); ?></h2>
                <?php if (!empty($section_brands)): ?>
                    <div class="devhub-products__brands" aria-label="<?php echo esc_attr(sprintf(__('%s brands', 'devicehub-theme'), $title)); ?>">
                        <button type="button"
                            class="devhub-brand-tab devhub-brand-tab--active"
                            data-section="<?php echo esc_attr($section_id); ?>"
                            data-brand="all"
                            aria-pressed="true">
                            <?php esc_html_e('All', 'devicehub-theme'); ?>
                        </button>
                        <?php foreach ($section_brands as $brand): ?>
                            <button type="button"
                                class="devhub-brand-tab"
                                data-section="<?php echo esc_attr($section_id); ?>"
                                data-brand="<?php echo esc_attr($brand->slug); ?>"
                                aria-pressed="false">
                                <?php echo esc_html($brand->name); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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

            <div class="devhub-products__footer">
                <a href="<?php echo esc_url($view_all_url); ?>" class="devhub-products__view-all">
                    <?php esc_html_e('View All', 'devicehub-theme'); ?> <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
            </div>

        </div>
    </section>
    <?php
}

function devhub_get_product_section_brands(array $product_ids): array
{
    $product_ids = array_values(array_filter(array_map('absint', $product_ids)));

    if (empty($product_ids)) {
        return [];
    }

    $brands = [];

    foreach (['product_brand', 'pwb-brand', 'pa_brand'] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = wp_get_object_terms($product_ids, $taxonomy, [
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        if (empty($terms) || is_wp_error($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            if ($term instanceof WP_Term) {
                $brands[$term->slug] = $term;
            }
        }
    }

    return array_values($brands);
}


// ── Mobile Phones ─────────────────────────────────────────────────────────────

add_action('devhub_home_product_sections', 'devhub_render_home_product_sections');

function devhub_render_home_product_sections(): void
{
    if (!devhub_has_catalog_data() || !taxonomy_exists('product_cat')) {
        return;
    }

    $excluded_ids = [(int) get_option('default_product_cat')];
    $flash_sale = get_term_by('slug', 'flash-sale', 'product_cat');

    if ($flash_sale instanceof WP_Term) {
        $excluded_ids[] = (int) $flash_sale->term_id;
    }

    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'exclude' => array_filter($excluded_ids),
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    if (empty($categories) || is_wp_error($categories)) {
        return;
    }

    foreach ($categories as $category) {
        if (!$category instanceof WP_Term) {
            continue;
        }

        $section_id = 'devhub-' . sanitize_html_class($category->slug);
        $view_all_url = get_term_link($category);

        if (is_wp_error($view_all_url)) {
            $view_all_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
        }

        devhub_render_home_section_banner_before_category($category->slug);

        devhub_render_product_section(
            $category->name,
            $section_id,
            $category->slug,
            DEVHUB_URI . '/assets/images/Original-Img.svg',
            (string) $view_all_url
        );
    }
}

function devhub_render_home_section_banner_before_category(string $category_slug): void
{
    $banner_actions = [
        'broad-bands' => 'devhub_before_broadbands_banner_section',
        'broadbands' => 'devhub_before_broadbands_banner_section',
        'electronics' => 'devhub_before_electronics_banner_section',
        'accessories' => 'devhub_before_accessories_banner_section',
    ];

    if (!isset($banner_actions[$category_slug])) {
        return;
    }

    do_action($banner_actions[$category_slug]);
}

add_action('devhub_mobile_phones_section', 'devhub_render_mobile_phones_section');

function devhub_render_mobile_phones_section(): void
{
    devhub_render_product_section(
        'Mobile Phones',
        'devhub-mobile-phones',
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
        'accessories',
        DEVHUB_URI . '/assets/images/Original-Img.svg'
    );
}

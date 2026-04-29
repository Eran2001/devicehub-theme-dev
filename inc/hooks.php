<?php
/**
 * DeviceHub — Hooks
 *
 * All add_action / remove_action / add_filter overrides that
 * modify the parent Shopire theme and WooCommerce globally.
 *
 * Rules:
 *  - No markup output here — only hook registration
 *  - Page-section hooks (hero, flash, products) live in hooks/*.php
 *  - WooCommerce template overrides live in woocommerce/*.php
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;


// ── Page loader overlay ───────────────────────────────────────────────────────

add_action('wp_body_open', 'devhub_render_page_loader');

function devhub_render_page_loader(): void
{
    echo '<div id="devhub-page-loader" aria-hidden="true"><div id="devhub-page-loader__spinner"></div></div>';
}


// ── Logo ──────────────────────────────────────────────────────────────────────

remove_action('shopire_site_logo', 'shopire_site_logo');
add_action('shopire_site_logo', 'devhub_render_logo');

function devhub_render_logo(): void
{
    ?>
    <a href="<?php echo esc_url(home_url('/')); ?>" class="devhub-logo">
        <img src="<?php echo esc_url(DEVHUB_URI . '/assets/images/HUTCHMainLogo.svg'); ?>"
            alt="<?php esc_attr_e('HUTCH Device Hub', 'devicehub-theme'); ?>" height="36" width="auto">
    </a>
    <?php
}


// ── Header — remove Shopire elements not used in DeviceHub ───────────────────

// Top bar (social icons, free shipping text)
remove_action('shopire_site_header', 'shopire_site_header');
add_action('shopire_site_header', '__return_false');

// Nav links (Home, Cart, Checkout)
remove_action('shopire_site_header_navigation', 'shopire_site_header_navigation');
add_action('shopire_site_header_navigation', '__return_false');

// Flash sale button
remove_action('shopire_header_button', 'shopire_header_button');
add_action('shopire_header_button', '__return_false');

// Phone contact on right side
remove_action('shopire_header_contact', 'shopire_header_contact');
add_action('shopire_header_contact', '__return_false');


// ── Shopire page-title banner — suppress on WooCommerce pages ─────────────────
// The banner is replaced by compact inline breadcrumb bars in the WC templates.

add_filter('theme_mod_shopire_hs_site_breadcrumb', function ($val) {
    return (devhub_is_product_page() || devhub_is_product_category_page() || devhub_is_shop_page() || devhub_is_cart_page() || devhub_is_checkout_page() || devhub_is_account_context() || is_404()) ? '0' : $val;
}, 20);


// ── Page bar — inject on cart / checkout (account uses template override) ────

add_action('woocommerce_before_cart', 'devhub_render_page_bar', 5);
add_action('woocommerce_before_checkout_form', 'devhub_render_page_bar', 5);

function devhub_render_page_bar(): void
{
    static $rendered = false;
    if ($rendered)
        return;
    $rendered = true;

    $title = (devhub_is_cart_page() || devhub_is_checkout_page() || devhub_is_account_context())
        ? get_the_title()
        : woocommerce_page_title(false);
    ?>
    <div class="devhub-page-bar wf-container">
        <?php woocommerce_breadcrumb(); ?>
        <h1 class="devhub-page-bar__title"><?php echo esc_html($title); ?></h1>
    </div>
    <?php
}


// ── WooCommerce archive title — remove "Category:" prefix ─────────────────────

add_filter('woocommerce_page_title', function ($title) {
    if (is_product_category()) {
        return single_cat_title('', false);
    }
    return $title;
});


// ── Header — add Orders icon before cart ─────────────────────────────────────

add_action('shopire_woo_cart', 'devhub_render_orders_icon', 5);

function devhub_render_orders_icon(): void
{
    if (!class_exists('WooCommerce'))
        return;
    ?>
    <li class="wf_navbar-cart-item">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="wf_navbar-cart-icon"
            title="<?php esc_attr_e('Orders', 'devicehub-theme'); ?>">
            <span class="cart_icon">
                <i class="far fa-box-open" aria-hidden="true"></i>
            </span>
            <span class="screen-reader-text">
                <?php esc_html_e('Orders', 'devicehub-theme'); ?>
            </span>
        </a>
    </li>
    <?php
}


// ── WooCommerce — archive products per page ───────────────────────────────────

add_filter('loop_shop_per_page', fn() => 9, 20);


// ── WooCommerce — brand filter via URL param ?filter_brand=slug1,slug2 ────────
// pwb-brand is a custom taxonomy (PWB Brands plugin), not a pa_* attribute,
// so WooCommerce's built-in layered nav doesn't handle it — we do it here.

add_action('pre_get_posts', 'devhub_filter_archive_by_product_category', 9);
add_action('pre_get_posts', 'devhub_filter_archive_by_brand');

function devhub_filter_archive_by_product_category(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query())
        return;
    if (!devhub_is_shop_page() && !devhub_is_product_category_page() && !devhub_is_product_tag_page())
        return;

    $raw = sanitize_text_field(wp_unslash($_GET['filter_product_cat'] ?? ''));
    if ($raw === '')
        return;

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', explode(',', $raw)))));
    if (empty($slugs))
        return;

    $tax_query = (array) $query->get('tax_query');
    $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field' => 'slug',
        'terms' => $slugs,
        'operator' => 'IN',
    ];
    $query->set('tax_query', $tax_query);
}

function devhub_remove_taxonomy_from_tax_query(array $tax_query, string $taxonomy): array
{
    $cleaned_query = [];

    foreach ($tax_query as $key => $clause) {
        if ($key === 'relation') {
            $cleaned_query['relation'] = $clause;
            continue;
        }

        if (!is_array($clause)) {
            continue;
        }

        if (($clause['taxonomy'] ?? '') === $taxonomy) {
            continue;
        }

        $nested_clause = devhub_remove_taxonomy_from_tax_query($clause, $taxonomy);

        if (isset($nested_clause['taxonomy']) || count($nested_clause) > (isset($nested_clause['relation']) ? 1 : 0)) {
            $cleaned_query[] = $nested_clause;
        }
    }

    return $cleaned_query;
}

function devhub_filter_archive_by_brand(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query())
        return;
    if (!devhub_is_shop_page() && !devhub_is_product_category_page() && !devhub_is_product_tag_page())
        return;

    $raw = sanitize_text_field(wp_unslash($_GET['filter_brand'] ?? ''));
    if ($raw === '')
        return;

    $slugs = array_values(array_filter(array_map('sanitize_title', explode(',', $raw))));
    if (empty($slugs))
        return;

    $brand_tax_query = ['relation' => 'OR'];

    if (taxonomy_exists('product_brand')) {
        $brand_tax_query[] = [
            'taxonomy' => 'product_brand',
            'field' => 'slug',
            'terms' => $slugs,
            'operator' => 'IN',
        ];
    }

    if (taxonomy_exists('pwb-brand')) {
        $brand_tax_query[] = [
            'taxonomy' => 'pwb-brand',
            'field' => 'slug',
            'terms' => $slugs,
            'operator' => 'IN',
        ];
    }

    if (taxonomy_exists('pa_brand')) {
        $brand_tax_query[] = [
            'taxonomy' => 'pa_brand',
            'field' => 'slug',
            'terms' => $slugs,
            'operator' => 'IN',
        ];
    }

    if (count($brand_tax_query) === 1)
        return;

    $tax_query = (array) $query->get('tax_query');
    $tax_query = devhub_remove_taxonomy_from_tax_query($tax_query, 'product_brand');
    $tax_query = devhub_remove_taxonomy_from_tax_query($tax_query, 'pwb-brand');
    $tax_query = devhub_remove_taxonomy_from_tax_query($tax_query, 'pa_brand');
    $tax_query[] = $brand_tax_query;
    $query->set('tax_query', $tax_query);
}


// ── WooCommerce — force our archive-product template ─────────────────────────
// woocommerce_locate_template fires via wc_get_template() — used by archive.
// Single product uses wc_get_template_part() which calls locate_template()
// directly, so content-single-product.php is picked up from the theme
// woocommerce/ folder automatically — no filter needed for it.

add_action('pre_get_posts', 'devhub_search_products_only');
add_action('wp_ajax_devhub_header_search', 'devhub_handle_header_search');
add_action('wp_ajax_nopriv_devhub_header_search', 'devhub_handle_header_search');
add_filter('query_vars', 'devhub_add_product_search_query_vars');

function devhub_add_product_search_query_vars(array $vars): array
{
    $vars[] = 'devhub_product_search_term';
    return $vars;
}

function devhub_search_products_only(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    if (empty($query->get('post_type'))) {
        $query->set('post_type', ['product']);
    }

    $term = trim((string) $query->get('s'));
    if ($term === '') {
        return;
    }

    $matched_ids = devhub_collect_product_search_ids($term, 200);
    $query->set('devhub_product_search_term', $term);
    $query->set('s', '');
    $query->set('post_type', ['product']);
    $query->set('post__in', !empty($matched_ids) ? $matched_ids : [0]);
    $query->set('orderby', 'post__in');
}

function devhub_collect_product_search_ids(string $term, int $limit = 20): array
{
    if (!class_exists('WooCommerce')) {
        return [];
    }

    $term = trim(sanitize_text_field($term));
    if ($term === '') {
        return [];
    }

    $ids = [];

    $text_query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        's' => $term,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);
    $ids = array_merge($ids, array_map('absint', $text_query->posts));

    $sku_query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => '_sku',
                'value' => $term,
                'compare' => 'LIKE',
            ],
        ],
    ]);
    $ids = array_merge($ids, array_map('absint', $sku_query->posts));

    $tax_query = ['relation' => 'OR'];
    foreach (devhub_get_searchable_product_taxonomies() as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'name__like' => $term,
            'fields' => 'ids',
            'number' => 20,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field' => 'term_id',
            'terms' => array_map('absint', $terms),
            'operator' => 'IN',
        ];
    }

    if (count($tax_query) > 1) {
        $taxonomy_query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'tax_query' => $tax_query,
        ]);
        $ids = array_merge($ids, array_map('absint', $taxonomy_query->posts));
    }

    $ids = array_values(array_unique(array_filter($ids)));

    return array_slice($ids, 0, $limit);
}

function devhub_get_searchable_product_taxonomies(): array
{
    return [
        'product_cat',
        'product_tag',
        'product_brand',
        'pwb-brand',
        'pa_brand',
        'pa_color',
        'pa_storage',
    ];
}

function devhub_get_header_search_categories(string $term): array
{
    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'name__like' => $term,
        'number' => 6,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    $categories = [];
    foreach ($terms as $category) {
        $link = get_term_link($category);
        if (is_wp_error($link)) {
            continue;
        }

        $categories[] = [
            'name' => html_entity_decode($category->name, ENT_QUOTES, get_bloginfo('charset')),
            'url' => esc_url_raw($link),
        ];
    }

    return $categories;
}

function devhub_handle_header_search(): void
{
    if (!check_ajax_referer('devhub_header_search', 'nonce', false)) {
        wp_send_json_error(['message' => __('Invalid search request.', 'devicehub-theme')], 403);
    }

    $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
    $term = trim($term);

    if (strlen($term) < 2) {
        wp_send_json_success([
            'products' => [],
            'categories' => [],
        ]);
    }

    $products = [];
    foreach (devhub_collect_product_search_ids($term, 8) as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product || !$product->is_visible()) {
            continue;
        }

        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src('woocommerce_thumbnail');

        $products[] = [
            'name' => html_entity_decode($product->get_name(), ENT_QUOTES, get_bloginfo('charset')),
            'url' => esc_url_raw($product->get_permalink()),
            'image' => esc_url_raw($image_url),
        ];
    }

    wp_send_json_success([
        'products' => $products,
        'categories' => devhub_get_header_search_categories($term),
        'searchUrl' => esc_url_raw(add_query_arg([
            's' => $term,
            'post_type' => 'product',
        ], home_url('/'))),
    ]);
}

add_filter('woocommerce_locate_template', 'devhub_locate_template', 10, 3);

function devhub_locate_template(string $template, string $template_name, string $template_path): string
{
    if ($template_name !== 'archive-product.php')
        return $template;

    $custom = DEVHUB_DIR . '/woocommerce/archive-product.php';
    return file_exists($custom) ? $custom : $template;
}

add_filter('template_include', 'devhub_brand_taxonomy_template');

function devhub_brand_taxonomy_template(string $template): string
{
    if (!function_exists('devhub_is_brand_page') || !devhub_is_brand_page()) {
        return $template;
    }

    $custom = DEVHUB_DIR . '/woocommerce/archive-product.php';
    return file_exists($custom) ? $custom : $template;
}

add_action('wp_head', 'devhub_prime_motion_class', 0);

function devhub_prime_motion_class(): void
{
    ?>
    <script>
        (function () {
            if (!window.matchMedia || !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.documentElement.classList.add('devhub-motion-ready');
            }
        })();
    </script>
    <?php
}

function devhub_get_secondary_brand_image_url(WP_Term $brand): string
{
    $resolve_image = static function ($value): string {
        if (is_array($value)) {
            foreach (['id', 'ID', 'attachment_id', 'image_id', 'url', 'src'] as $array_key) {
                if (!array_key_exists($array_key, $value)) {
                    continue;
                }

                $resolved = $value[$array_key];
                if (is_numeric($resolved) && (int) $resolved > 0) {
                    $url = wp_get_attachment_image_url((int) $resolved, 'medium');
                    if ($url) {
                        return $url;
                    }
                }

                if (is_string($resolved) && filter_var($resolved, FILTER_VALIDATE_URL)) {
                    return $resolved;
                }
            }

            return '';
        }

        if (is_numeric($value) && (int) $value > 0) {
            $url = wp_get_attachment_image_url((int) $value, 'medium');
            return $url ?: '';
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return '';
    };

    foreach ([
        'thumbnail_id',
        'pwb_brand_image',
        'pwb_brand_logo',
        'pwb_brand_thumbnail_id',
        'product_brand_thumbnail_id',
        'brand_thumbnail_id',
        'brand_logo',
        'logo',
        'image',
    ] as $key) {
        $value = get_term_meta($brand->term_id, $key, true);
        $url = $resolve_image($value);

        if ($url !== '') {
            return $url;
        }
    }

    $all_meta = get_term_meta($brand->term_id);
    foreach ($all_meta as $meta_key => $values) {
        if (!preg_match('/(image|logo|thumb|thumbnail)/i', (string) $meta_key)) {
            continue;
        }

        foreach ((array) $values as $value) {
            $url = $resolve_image(maybe_unserialize($value));

            if ($url !== '') {
                return $url;
            }
        }
    }

    return '';
}

function devhub_get_secondary_brands(): array
{
    foreach (['product_brand', 'pwb-brand', 'pa_brand'] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
            'number' => 36,
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            return $terms;
        }
    }

    return [];
}

function devhub_get_secondary_categories(): array
{
    if (!taxonomy_exists('product_cat')) {
        return [];
    }

    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => 0,
        'exclude' => get_option('default_product_cat'),
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    return (!is_wp_error($terms) && !empty($terms)) ? $terms : [];
}

function devhub_get_secondary_category_visual(WP_Term $category): array
{
    $thumbnail_id = (int) get_term_meta($category->term_id, 'thumbnail_id', true);
    $image_url = $thumbnail_id > 0 ? wp_get_attachment_image_url($thumbnail_id, 'thumbnail') : '';
    $icon = (string) get_term_meta($category->term_id, 'shopire_product_cat_icon', true);

    if ($icon === '') {
        $slug_icon_map = [
            'accessories' => 'fas fa-headphones-alt',
            'broad-bands' => 'fas fa-wifi',
            'electronics' => 'fas fa-tv',
            'flash-sale' => 'fas fa-bolt',
            'mobile-phones' => 'fas fa-mobile-alt',
            'new-arrivals' => 'fas fa-star',
            'routers' => 'fas fa-router',
            'smart-watch' => 'fas fa-watch-smart',
            'wingle' => 'fas fa-wifi',
        ];

        $keyword_icon_map = [
            'accessor' => 'fas fa-headphones-alt',
            'broadband' => 'fas fa-wifi',
            'broad-band' => 'fas fa-wifi',
            'electronics' => 'fas fa-tv',
            'flash' => 'fas fa-bolt',
            'mobile' => 'fas fa-mobile-alt',
            'new' => 'fas fa-star',
            'phone' => 'fas fa-mobile-alt',
            'router' => 'fas fa-router',
            'sim' => 'fas fa-sim-card',
            'smart-watch' => 'fas fa-watch-smart',
            'watch' => 'fas fa-watch-smart',
            'wifi' => 'fas fa-wifi',
            'wingle' => 'fas fa-wifi',
        ];

        $category_key = sanitize_title($category->slug . ' ' . $category->name);
        $icon = $slug_icon_map[$category->slug] ?? '';

        if ($icon === '') {
            foreach ($keyword_icon_map as $keyword => $keyword_icon) {
                if (str_contains($category_key, $keyword)) {
                    $icon = $keyword_icon;
                    break;
                }
            }
        }

        if ($icon === '') {
            $icon = 'fas fa-tag';
        }
    }

    return [
        'image' => $image_url ?: '',
        'icon' => $icon,
    ];
}

function devhub_render_secondary_menu_links(): void
{
    if (has_nav_menu('secondary_menu')) {
        wp_nav_menu([
            'theme_location' => 'secondary_menu',
            'container' => false,
            'menu_class' => 'devhub-secondary-nav__links',
            'fallback_cb' => false,
            'depth' => 1,
        ]);
        return;
    }

    $fallback_links = [
        ['label' => __('Hire Purchase', 'devicehub-theme'), 'url' => home_url('/hire-purchase/')],
        ['label' => __('Gift Vouchers', 'devicehub-theme'), 'url' => home_url('/gift-vouchers/')],
        ['label' => __('Duty Free', 'devicehub-theme'), 'url' => home_url('/duty-free/')],
        ['label' => __('Hutch Loyalty', 'devicehub-theme'), 'url' => home_url('/loyalty/')],
    ];
    ?>
    <ul class="devhub-secondary-nav__links">
        <?php foreach ($fallback_links as $link): ?>
            <li><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php
}

function devhub_render_secondary_offers(): void
{
    if (has_nav_menu('secondary_offers_menu')) {
        wp_nav_menu([
            'theme_location' => 'secondary_offers_menu',
            'container' => false,
            'menu_class' => 'devhub-secondary-nav__offer-list',
            'fallback_cb' => false,
            'depth' => 1,
        ]);
        return;
    }

    $fallback_offers = [
        __('Flash Sale', 'devicehub-theme'),
        __('New Arrivals', 'devicehub-theme'),
        __('Bundle Deals', 'devicehub-theme'),
        __('Mobile Phone Offers', 'devicehub-theme'),
        __('Broadband Offers', 'devicehub-theme'),
    ];
    ?>
    <ul class="devhub-secondary-nav__offer-list">
        <?php foreach ($fallback_offers as $offer): ?>
            <li><a href="<?php echo esc_url(home_url('/shop/')); ?>"><?php echo esc_html($offer); ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php
}

function devhub_render_secondary_nav(): void
{
    $categories = devhub_get_secondary_categories();
    $brands = devhub_get_secondary_brands();
    ?>
    <div class="devhub-secondary-nav wf-d-none wf-d-lg-block">
        <div class="devhub-secondary-nav__inner">
            <div class="devhub-secondary-nav__item devhub-secondary-nav__item--categories">
                <button class="devhub-secondary-nav__button" type="button" aria-haspopup="true">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                    <span><?php esc_html_e('All Categories', 'devicehub-theme'); ?></span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
                <?php if (!empty($categories)): ?>
                    <div class="devhub-secondary-nav__dropdown devhub-secondary-nav__dropdown--categories">
                        <ul>
                            <?php foreach ($categories as $category):
                                $visual = devhub_get_secondary_category_visual($category);
                                ?>
                                <li>
                                    <a href="<?php echo esc_url(get_term_link($category)); ?>">
                                        <span class="devhub-secondary-nav__cat-icon" aria-hidden="true">
                                            <?php if ($visual['image'] !== ''): ?>
                                                <img src="<?php echo esc_url($visual['image']); ?>" alt=""
                                                    onerror="this.hidden=true;this.nextElementSibling.classList.remove('devhub-secondary-nav__fallback-icon--hidden');">
                                            <?php endif; ?>
                                            <i class="<?php echo esc_attr($visual['icon'] . ($visual['image'] !== '' ? ' devhub-secondary-nav__fallback-icon--hidden' : '')); ?>"></i>
                                        </span>
                                        <span><?php echo esc_html($category->name); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="devhub-secondary-nav__item devhub-secondary-nav__item--brands">
                <button class="devhub-secondary-nav__button" type="button" aria-haspopup="true">
                    <span><?php esc_html_e('Brands', 'devicehub-theme'); ?></span>
                </button>
                <?php if (!empty($brands)): ?>
                    <div class="devhub-secondary-nav__dropdown devhub-secondary-nav__dropdown--brands">
                        <div class="devhub-secondary-nav__brand-featured">
                            <h3><?php esc_html_e('Top Brands', 'devicehub-theme'); ?></h3>
                            <div class="devhub-secondary-nav__brand-grid">
                                <?php foreach (array_slice($brands, 0, 12) as $brand):
                                    $image_url = devhub_get_secondary_brand_image_url($brand);
                                    ?>
                                    <a href="<?php echo esc_url(get_term_link($brand)); ?>"
                                        class="devhub-secondary-nav__brand-logo">
                                        <?php if ($image_url !== ''): ?>
                                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($brand->name); ?>">
                                        <?php else: ?>
                                            <span><?php echo esc_html($brand->name); ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="devhub-secondary-nav__brand-list">
                            <h3><?php esc_html_e('Brands', 'devicehub-theme'); ?></h3>
                            <ul>
                                <?php foreach ($brands as $brand): ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_term_link($brand)); ?>">
                                            <?php echo esc_html($brand->name); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="devhub-secondary-nav__item devhub-secondary-nav__item--offers">
                <button class="devhub-secondary-nav__button" type="button" aria-haspopup="true">
                    <span><?php esc_html_e("Today's Offer", 'devicehub-theme'); ?></span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="devhub-secondary-nav__dropdown devhub-secondary-nav__dropdown--offers">
                    <?php devhub_render_secondary_offers(); ?>
                </div>
            </div>

            <nav class="devhub-secondary-nav__menu"
                aria-label="<?php esc_attr_e('Secondary navigation', 'devicehub-theme'); ?>">
                <?php devhub_render_secondary_menu_links(); ?>
            </nav>

            <div class="devhub-secondary-nav__contact">
                <a href="<?php echo esc_url(home_url('/my-account/orders/')); ?>">
                    <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                    <span><?php esc_html_e('Track your order', 'devicehub-theme'); ?></span>
                </a>
                <a href="tel:+94112222888">
                    <i class="fas fa-phone" aria-hidden="true"></i>
                    <span>+94 788 222 888</span>
                </a>
            </div>
        </div>
    </div>
    <?php
}


// ── Debug — template path comment in <head> (remove before production) ────────

add_action('wp_head', 'devhub_debug_template_comment');

function devhub_debug_template_comment(): void
{
    if (!devhub_is_shop_page() && !devhub_is_product_category_page())
        return;
    if (!current_user_can('administrator'))
        return; // Only show to admins

    global $template;
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '<!-- DEVHUB TEMPLATE: ' . esc_html($template) . ' -->' . PHP_EOL;
}

// ── WooCommerce — Buy Now redirect to checkout ────────────────────────────────
// product.js adds devhub_buy_now=1 to the cart form before submitting.
// We catch it here and redirect to checkout instead of back to the product page.

add_filter('woocommerce_add_to_cart_redirect', 'devhub_buy_now_redirect');

function devhub_buy_now_redirect(string $url): string
{
    if (!empty($_POST['devhub_buy_now'])) {
        return wc_get_checkout_url();
    }
    return $url;
}



// ── Price display — sale price first, regular price (strikethrough) second ────
// Two complementary hooks cover every render path:
//   woocommerce_format_sale_price  — fires mid-stack inside wc_format_sale_price();
//                                    catches cart/checkout/mini-cart line items.
//   woocommerce_get_price_html     — fires at the top after get_price_html() is
//                                    fully assembled; catches archive cards, single
//                                    product page, and Shopire's cross-sell cards.
// Both run the same idempotent swap: regex only matches when <del> precedes <ins>,
// so running both never double-flips. Range prices (no del/ins) pass through unchanged.

$devhub_swap_price = static function (string $price): string {
    if (preg_match('/(<del[\s>].*?<\/del>)\s*(<ins[\s>].*?<\/ins>)/s', $price, $m)) {
        return $m[2] . ' ' . $m[1];
    }
    return $price;
};

add_filter('woocommerce_format_sale_price', $devhub_swap_price, 20);
add_filter('woocommerce_get_price_html', $devhub_swap_price, 999);


// Override LKR currency symbol to display as 'LKR' instead of රු
add_filter('woocommerce_currency_symbol', function (string $symbol, string $currency): string {
    if ($currency === 'LKR')
        return 'Rs.';
    return $symbol;
}, 10, 2);


// ── Cart / Checkout / Account — force no sidebar (full container width) ────────
add_filter('woocommerce_price_format', function (string $format): string {
    if (get_woocommerce_currency() === 'LKR') {
        return '%1$s %2$s';
    }

    return $format;
});

add_filter('theme_mod_shopire_default_pg_sidebar_option', function ($value) {
    if (is_string($value) && (devhub_is_cart_page() || devhub_is_checkout_page() || devhub_is_account_context())) {
        return 'no_sidebar';
    }
    return $value;
});


// ── Custom account endpoints: Wishlist, Coupons, Points, Dispute ──────────────
// NOTE: After activating, go to Settings > Permalinks and click Save to flush rewrite rules.

add_action('init', 'devhub_register_account_endpoints');
add_action('after_switch_theme', 'devhub_schedule_rewrite_flush');
add_action('init', 'devhub_maybe_flush_rewrite_rules', 20);

function devhub_register_account_endpoints(): void
{
    add_rewrite_endpoint('wishlist', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('coupons', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('points', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('dispute', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('gift-cards', EP_ROOT | EP_PAGES);
}

function devhub_schedule_rewrite_flush(): void
{
    update_option('devhub_flush_rewrite_rules', '1');
}

function devhub_maybe_flush_rewrite_rules(): void
{
    if (get_option('devhub_flush_rewrite_rules') !== '1') {
        return;
    }

    devhub_register_account_endpoints();
    flush_rewrite_rules();
    delete_option('devhub_flush_rewrite_rules');
}

// Register endpoints with WooCommerce so wc_get_account_endpoint_url() works
add_filter('woocommerce_account_menu_items', 'devhub_custom_account_menu_items');

function devhub_custom_account_menu_items(array $items): array
{
    $items['wishlist'] = __('Wishlist', 'devicehub-theme');
    $items['coupons'] = __('Coupons', 'devicehub-theme');
    $items['points'] = __('Points Collected', 'devicehub-theme');
    $items['dispute'] = __('Dispute', 'devicehub-theme');
    $items['gift-cards'] = __('Your Gift Cards', 'devicehub-theme');
    return $items;
}

// Wire content for each endpoint
add_action('woocommerce_account_wishlist_endpoint', function (): void {
    include DEVHUB_DIR . '/woocommerce/myaccount/wishlist.php';
});

add_action('woocommerce_account_coupons_endpoint', function (): void {
    include DEVHUB_DIR . '/woocommerce/myaccount/coupons.php';
});

add_action('woocommerce_account_points_endpoint', function (): void {
    include DEVHUB_DIR . '/woocommerce/myaccount/points.php';
});

add_action('woocommerce_account_dispute_endpoint', function (): void {
    include DEVHUB_DIR . '/woocommerce/myaccount/dispute.php';
});

add_action('woocommerce_account_gift-cards_endpoint', function (): void {
    include DEVHUB_DIR . '/woocommerce/myaccount/gift-cards.php';
});


// ── Social login — suppress "temporary password" notice for OAuth users ────────
// Fires for both new registrations and existing users signing in via social.
// OAuth users never have a real password, so the WC nag is confusing/wrong.

function devhub_clear_social_password_nag($user_id): void
{
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return;
    }
    delete_user_meta($user_id, 'default_password_nag');
    delete_user_meta($user_id, 'woocommerce_force_password_reset');
    update_user_option($user_id, 'default_password_nag', false);
}

add_action('nsl_register_new_user', 'devhub_clear_social_password_nag');
add_action('nsl_login', 'devhub_clear_social_password_nag');


// ── Social login — honour checkout redirect after OAuth ───────────────────────
// Nextend exposes per-provider filters for the final redirect URL. Using these
// is more reliable than hooking login_redirect, which Nextend may bypass.

function devhub_nsl_checkout_redirect($redirect_to, $requested_redirect_to)
{
    if (!empty($requested_redirect_to) && str_starts_with((string) $requested_redirect_to, home_url())) {
        return $requested_redirect_to;
    }
    return $redirect_to;
}

add_filter('nsl_facebooklast_location_redirect', 'devhub_nsl_checkout_redirect', 10, 2);
add_filter('nsl_googlelast_location_redirect', 'devhub_nsl_checkout_redirect', 10, 2);

// Bundle package: make package selection part of WooCommerce cart identity.
add_filter('woocommerce_add_cart_item_data', 'devhub_add_bundle_identity_to_cart_item', 5, 4);
add_filter('woocommerce_get_cart_item_from_session', 'devhub_restore_bundle_identity_from_session', 10, 2);
add_action('woocommerce_checkout_create_order_line_item', 'devhub_add_bundle_identity_to_order_line', 10, 4);

function devhub_get_requested_bundle_selection(int $product_id): ?array
{
    if (!function_exists('devhub_get_product_bundle_context')) {
        return null;
    }

    $context = devhub_get_product_bundle_context($product_id);
    if (empty($context['enabled']) || empty($context['packages'])) {
        return null;
    }

    $input_name = isset($context['input_name']) && '' !== (string) $context['input_name']
        ? (string) $context['input_name']
        : 'devicehub_package_id';

    $posted_package_id = null;
    if (isset($_POST[$input_name])) {
        $posted_package_id = absint(wp_unslash($_POST[$input_name]));
    }

    $package_id = null !== $posted_package_id
        ? $posted_package_id
        : (int) ($context['default_id'] ?? 0);

    if ($package_id <= 0) {
        return [
            'bundle_id' => 'none',
            'bundle_code' => 'none',
            'bundle_name' => __('No Bundle', 'devicehub-theme'),
            'bundle_price' => '',
            'bundle_key' => 'none',
        ];
    }

    foreach ($context['packages'] as $package) {
        if ((int) ($package['id'] ?? 0) !== $package_id) {
            continue;
        }

        $bundle_code = trim((string) ($package['package_code'] ?? ''));
        $bundle_key = '' !== $bundle_code ? $bundle_code : (string) $package_id;

        return [
            'bundle_id' => (string) $package_id,
            'bundle_code' => $bundle_code,
            'bundle_name' => (string) ($package['name'] ?? ''),
            'bundle_price' => (string) ($package['price_display'] ?? ''),
            'billing_label' => (string) ($package['billing_label'] ?? ''),
            'bundle_key' => sanitize_key($bundle_key),
        ];
    }

    return [
        'bundle_id' => 'none',
        'bundle_code' => 'none',
        'bundle_name' => __('No Bundle', 'devicehub-theme'),
        'bundle_price' => '',
        'bundle_key' => 'none',
    ];
}

function devhub_add_bundle_identity_to_cart_item(array $cart_item_data, int $product_id, int $variation_id, int $quantity): array
{
    $selection = devhub_get_requested_bundle_selection($product_id);
    if (null === $selection) {
        return $cart_item_data;
    }

    $bundle_key = (string) ($selection['bundle_key'] ?? 'none');
    $product_key_id = $variation_id > 0 ? $variation_id : $product_id;

    $cart_item_data['selected_bundle'] = [
        'bundle_id' => (string) ($selection['bundle_id'] ?? 'none'),
        'bundle_code' => (string) ($selection['bundle_code'] ?? ''),
        'bundle_name' => (string) ($selection['bundle_name'] ?? ''),
        'bundle_price' => (string) ($selection['bundle_price'] ?? ''),
        'billing_label' => (string) ($selection['billing_label'] ?? ''),
    ];
    $cart_item_data['bundle_key'] = $bundle_key;
    $cart_item_data['unique_key'] = md5($product_key_id . '|' . $bundle_key);

    return $cart_item_data;
}

function devhub_restore_bundle_identity_from_session(array $cart_item, array $values): array
{
    foreach (['selected_bundle', 'bundle_key', 'unique_key'] as $key) {
        if (isset($values[$key])) {
            $cart_item[$key] = $values[$key];
        }
    }

    return $cart_item;
}

function devhub_add_bundle_identity_to_order_line($item, string $cart_item_key, array $values, $order): void
{
    if (empty($values['selected_bundle']) || !is_array($values['selected_bundle'])) {
        return;
    }

    $bundle = $values['selected_bundle'];

    $item->add_meta_data('devicehub_bundle_id', (string) ($bundle['bundle_id'] ?? 'none'), true);
    $item->add_meta_data('devicehub_bundle_code', (string) ($bundle['bundle_code'] ?? ''), true);
    $item->add_meta_data('devicehub_bundle_name', (string) ($bundle['bundle_name'] ?? ''), true);
    $item->add_meta_data('devicehub_bundle_price', (string) ($bundle['bundle_price'] ?? ''), true);
    $item->add_meta_data('devicehub_bundle_key', (string) ($values['bundle_key'] ?? 'none'), true);
}



// ── Bundle package — add package price as a separate fee line in cart totals ──
// The bundle plugin stores the selected package price as informational metadata
// on the cart item but intentionally omits price mutation. This hook fills that
// gap: each cart item with a bundle package gets a dedicated fee line (like the
// delivery fee) so the package price is visible and included in the order total.
// Uses raw meta key strings so the theme has no hard dependency on the plugin.

// ── Bundle package — simplify cart item display to "Bundle Package: Plan Name" ─
// The plugin outputs the full "Plan Name — price LKR / billing label" line plus
// a second description row. We intercept at priority 20 (after the plugin's 10)
// and reduce it to just the display name, dropping price and description rows.

add_filter('woocommerce_get_item_data', function (array $item_data, array $cart_item): array {
    $has_bundle = isset($cart_item['devicehub_package_id']);
    $display_name = $cart_item['devicehub_package_display_name'] ?? '';
    $bundle_key = __('Bundle Package', 'devicehub-bundlepackage');
    $other_rows = [];
    $bundle_rows = [];

    foreach ($item_data as $row) {
        $key = $row['key'] ?? null;

        if ($key === $bundle_key) {
            // Simplify to just the plan name; always move to end.
            if ('' !== $display_name) {
                $bundle_rows[] = ['key' => $bundle_key, 'value' => $display_name];
            }
        } elseif ($has_bundle && '' === $key) {
            // Drop the keyless description row added by the plugin.
            continue;
        } else {
            $other_rows[] = $row;
        }
    }

    return array_merge($other_rows, $bundle_rows);
}, 20, 2);

// ── Bundle package — clean up order detail display (UI only, data untouched) ──
// The plugin's OrderPackageHandler renders its own dl.dh-order-pkg block via
// woocommerce_order_item_meta_end. We hide that block via CSS and instead show
// one clean "Bundle Package: Name" line via the standard WC meta
// system. Raw devicehub_* keys are hidden so they don't appear as extra rows.

add_filter('woocommerce_hidden_order_itemmeta', function (array $hidden): array {
    return array_merge($hidden, [
        'devicehub_package_id',
        'devicehub_package_external_id',
        'devicehub_package_code',
        'devicehub_package_name',
        'devicehub_package_display_name',
        'devicehub_package_description',
        'devicehub_package_price_amount',
        'devicehub_package_currency',
        'devicehub_package_billing_label',
        'devicehub_package_was_required',
        'devicehub_bundle_id',
        'devicehub_bundle_code',
        'devicehub_bundle_name',
        'devicehub_bundle_price',
        'devicehub_bundle_key',
    ]);
});

add_filter('woocommerce_order_item_get_formatted_meta_data', function (array $meta_data, $item): array {
    $bundle_label = __('Bundle Package', 'devicehub-bundlepackage');
    $clean_meta = [];

    foreach ($meta_data as $meta) {
        $key = isset($meta->key) ? (string) $meta->key : '';
        $display_key = isset($meta->display_key) ? wp_strip_all_tags((string) $meta->display_key) : '';

        if (
            str_starts_with($key, 'devicehub_package_')
            || str_starts_with($key, 'devicehub_bundle_')
            || $display_key === $bundle_label
        ) {
            continue;
        }

        $clean_meta[] = $meta;
    }

    $bundle_id = (string) ($item->get_meta('devicehub_package_id') ?: $item->get_meta('devicehub_bundle_id'));
    $bundle_key = (string) $item->get_meta('devicehub_bundle_key');

    if ('' === $bundle_id || 'none' === $bundle_id || 'none' === $bundle_key) {
        return $clean_meta;
    }

    $display_name = (string) $item->get_meta('devicehub_package_name');
    if ('' === $display_name) {
        $display_name = (string) $item->get_meta('devicehub_bundle_name');
    }
    if ('' === $display_name) {
        $display_name = (string) $item->get_meta('devicehub_package_display_name');
    }

    $display_name = trim(preg_replace('/\s+[—-]\s+[\d,.]+\s*[A-Z]{3}\s*$/', '', $display_name) ?? $display_name);

    if ('' === $display_name || __('No Bundle', 'devicehub-theme') === $display_name) {
        return $clean_meta;
    }

    return $clean_meta;
}, 10, 2);


add_action('woocommerce_cart_calculate_fees', 'devhub_add_bundle_package_fees');

function devhub_add_bundle_package_fees($cart): void
{
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }

    // WooCommerce uses the fee label as a unique key (sanitized to generate the
    // fee ID). Two products with the same bundle plan produce identical labels,
    // so calling add_fee() twice overwrites instead of adding. Aggregate first,
    // then register each unique label once with the combined amount.
    $fees = [];

    foreach ($cart->get_cart() as $cart_item) {
        $price = isset($cart_item['devicehub_package_price_amount'])
            ? (float) $cart_item['devicehub_package_price_amount']
            : 0.0;

        if ($price <= 0.0) {
            continue;
        }

        $display_name = isset($cart_item['devicehub_package_display_name'])
            ? (string) $cart_item['devicehub_package_display_name']
            : '';
        $billing_label = isset($cart_item['devicehub_package_billing_label'])
            ? (string) $cart_item['devicehub_package_billing_label']
            : '';
        $quantity = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;

        $fee_label = '' !== $display_name ? $display_name : __('Bundle Package', 'devicehub-theme');
        if ('' !== $billing_label) {
            $fee_label .= ' (' . $billing_label . ')';
        }

        $fees[$fee_label] = ($fees[$fee_label] ?? 0.0) + ($price * $quantity);
    }

    foreach ($fees as $label => $amount) {
        $cart->add_fee($label, $amount, false);
    }
}


// ── Checkout — Terms & Conditions checkbox text (FR-13) ───────────────────────
// WooCommerce Blocks (block checkout) stores its terms text as page content, so
// the classic `woocommerce_get_terms_and_conditions_checkbox_text` filter is
// ignored. We use `render_block` to post-process the rendered HTML and inject a
// Privacy Policy link wherever the plain text appears unlinked.

function devhub_get_order_item_bundle_name($item): string
{
    $bundle_id = (string) ($item->get_meta('devicehub_package_id') ?: $item->get_meta('devicehub_bundle_id'));
    $bundle_key = (string) $item->get_meta('devicehub_bundle_key');

    if ('' === $bundle_id || 'none' === $bundle_id || 'none' === $bundle_key) {
        return '';
    }

    $display_name = (string) $item->get_meta('devicehub_package_name');
    if ('' === $display_name) {
        $display_name = (string) $item->get_meta('devicehub_bundle_name');
    }
    if ('' === $display_name) {
        $display_name = (string) $item->get_meta('devicehub_package_display_name');
    }

    $display_name = trim(wp_strip_all_tags($display_name));

    if ('' === $display_name || __('No Bundle', 'devicehub-theme') === $display_name) {
        return '';
    }

    return $display_name;
}

function devhub_get_order_item_bundle_amount($item): float
{
    $raw_price = $item->get_meta('devicehub_package_price_amount');
    if ('' === $raw_price) {
        $raw_price = $item->get_meta('devicehub_bundle_price');
    }

    $price = (float) wc_format_decimal($raw_price);

    if ($price <= 0.0) {
        return 0.0;
    }

    $quantity = method_exists($item, 'get_quantity') ? max(1, (int) $item->get_quantity()) : 1;

    return $price * $quantity;
}

function devhub_get_order_bundle_rows_by_item(WC_Order $order): array
{
    $bundle_rows = [];

    foreach ($order->get_items('line_item') as $item_id => $item) {
        $name = devhub_get_order_item_bundle_name($item);
        $amount = devhub_get_order_item_bundle_amount($item);

        if ('' === $name || $amount <= 0.0) {
            continue;
        }

        $bundle_rows[$item_id] = [
            'name' => $name,
            'amount' => $amount,
        ];
    }

    return $bundle_rows;
}

function devhub_get_order_bundle_rows_total(array $bundle_rows): float
{
    $total = 0.0;

    foreach ($bundle_rows as $bundle_row) {
        $total += isset($bundle_row['amount']) ? (float) $bundle_row['amount'] : 0.0;
    }

    return $total;
}

function devhub_is_order_total_bundle_fee_row(string $key, array $total, array $bundle_rows): bool
{
    if (!str_starts_with($key, 'fee_')) {
        return false;
    }

    $label = isset($total['label']) ? wp_strip_all_tags((string) $total['label']) : '';
    $label = trim(rtrim($label, ':'));

    foreach ($bundle_rows as $bundle_row) {
        $name = isset($bundle_row['name']) ? (string) $bundle_row['name'] : '';

        if ('' === $name) {
            continue;
        }

        if ($label === $name || str_starts_with($label, $name . ' (')) {
            return true;
        }
    }

    return false;
}

add_filter('render_block_woocommerce/checkout-terms-block', 'devhub_terms_block_inject_pp_link');

function devhub_terms_block_inject_pp_link(string $block_content): string
{
    $pp_page_id = (int) get_option('wp_page_for_privacy_policy');

    if ($pp_page_id <= 0) {
        return $block_content;
    }

    $pp_url = get_permalink($pp_page_id);

    if (empty($pp_url)) {
        return $block_content;
    }

    // Only replace plain-text "Privacy Policy" that is NOT already inside an <a>.
    $replacement = '<a href="' . esc_url($pp_url) . '" target="_blank" rel="noopener noreferrer">Privacy Policy</a>';

    return preg_replace(
        '/(?<!">)(?<!\/>)Privacy Policy(?!<\/a>)/',
        $replacement,
        $block_content
    );
}


// ── Mobile drawer categories — exclude "Uncategorized" ───────────────────────
// Must defer removal: child functions.php loads before parent's, so the parent's
// add_action hasn't run yet when hooks.php is first included.

add_action('after_setup_theme', function () {
    remove_action('shopire_header_bcat_base', 'shopire_header_bcat_base');
}, 20);
add_action('shopire_header_bcat_base', 'devhub_mobile_drawer_categories');

function devhub_mobile_drawer_categories(): void
{
    $shopire_hs_hdr_bcat = get_theme_mod('shopire_hs_hdr_bcat', '1');
    if ($shopire_hs_hdr_bcat !== '1' || !class_exists('woocommerce')) {
        return;
    }

    $product_cat = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'parent' => 0,
        'exclude' => get_term_by('slug', 'uncategorized', 'product_cat')->term_id ?? 0,
    ]);

    if (empty($product_cat) || is_wp_error($product_cat)) {
        return;
    }

    echo '<ul class="wf_navbar-mainmenu">';
    foreach ($product_cat as $cat) {
        $child_cats = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $cat->term_id,
        ]);
        $icon = get_term_meta($cat->term_id, 'shopire_product_cat_icon', true);
        $icon_html = $icon ? "<i class='" . esc_attr($icon) . " wf-mr-2'></i>" : '';
        $link = '<a title="' . esc_attr($cat->name) . '" href="' . esc_url(get_term_link($cat->term_id)) . '" class="nav-link">' . $icon_html . esc_html($cat->name) . '</a>';

        if (!empty($child_cats) && !is_wp_error($child_cats)) {
            echo '<li class="menu-item menu-item-has-children" style="display:list-item;">' . $link;
            echo '<ul class="dropdown-menu">';
            foreach ($child_cats as $child) {
                echo '<li class="menu-item" style="display:list-item;"><a title="' . esc_attr($child->name) . '" href="' . esc_url(get_term_link($child->term_id)) . '" class="dropdown-item">' . esc_html($child->name) . '</a></li>';
            }
            echo '</ul></li>';
        } else {
            echo '<li class="menu-item" style="display:list-item;">' . $link . '</li>';
        }
    }
    echo '</ul>';
}

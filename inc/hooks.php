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
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            return devhub_get_product_category_display_name($term);
        }
    }
    return $title;
});

add_filter('woocommerce_get_breadcrumb', function ($crumbs) {
    if (is_admin() || empty($crumbs) || !is_array($crumbs)) {
        return $crumbs;
    }

    foreach ($crumbs as &$crumb) {
        if (!isset($crumb[0]) || !is_string($crumb[0])) {
            continue;
        }

        if ($crumb[0] === 'Uncategorized') {
            $crumb[0] = devhub_get_product_category_display_name('uncategorized');
        }
    }

    return $crumbs;
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

add_filter('loop_shop_per_page', fn() => 12, 20);


// ── WooCommerce — brand filter via URL param ?filter_brand=slug1,slug2 ────────
// pwb-brand is a custom taxonomy (PWB Brands plugin), not a pa_* attribute,
// so WooCommerce's built-in layered nav doesn't handle it — we do it here.

add_action('pre_get_posts', 'devhub_filter_archive_by_product_category', 9);
add_action('pre_get_posts', 'devhub_filter_archive_by_brand');
add_action('pre_get_posts', 'devhub_filter_archive_by_product_tag');

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

function devhub_filter_archive_by_product_tag(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }
    if (!devhub_is_shop_page() && !devhub_is_product_category_page() && !devhub_is_product_tag_page()) {
        return;
    }

    $raw = sanitize_text_field(wp_unslash($_GET['filter_product_tag'] ?? ''));
    if ($raw === '') {
        return;
    }

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', explode(',', $raw)))));
    if (empty($slugs)) {
        return;
    }

    $tax_query = (array) $query->get('tax_query');
    $tax_query = devhub_remove_taxonomy_from_tax_query($tax_query, 'product_tag');
    $tax_query[] = [
        'taxonomy' => 'product_tag',
        'field' => 'slug',
        'terms' => $slugs,
        'operator' => 'IN',
    ];
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
            'name' => html_entity_decode(devhub_get_product_category_display_name($category), ENT_QUOTES, get_bloginfo('charset')),
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

function devhub_get_secondary_brand_taxonomy(): string
{
    foreach (['product_brand', 'pwb-brand', 'pa_brand'] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        return $taxonomy;
    }

    return '';
}

function devhub_get_secondary_brands(int $parent = -1): array
{
    $taxonomy = devhub_get_secondary_brand_taxonomy();

    if ($taxonomy === '') {
        return [];
    }

    $args = [
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'number' => 36,
    ];

    if ($parent >= 0) {
        $args['parent'] = $parent;
    }

    $terms = get_terms($args);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    $has_explicit_order = false;

    foreach ($terms as $term) {
        if ($term instanceof WP_Term && devhub_get_term_admin_order($term) !== 0) {
            $has_explicit_order = true;
            break;
        }
    }

    if ($has_explicit_order) {
        usort($terms, static function (WP_Term $a, WP_Term $b): int {
            $a_order = devhub_get_term_admin_order($a);
            $b_order = devhub_get_term_admin_order($b);

            if ($a_order === $b_order) {
                return strcasecmp($a->name, $b->name);
            }

            return $a_order <=> $b_order;
        });
    }

    return $terms;
}

function devhub_get_term_admin_order(WP_Term $term): int
{
    $order_keys = [
        'order',
        'menu_order',
        'term_order',
        'product_brand_order',
        'pwb_brand_order',
    ];

    foreach ($order_keys as $key) {
        $value = get_term_meta($term->term_id, $key, true);

        if ($value !== '' && is_numeric($value)) {
            return (int) $value;
        }
    }

    return isset($term->term_order) && is_numeric($term->term_order)
        ? (int) $term->term_order
        : 0;
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
        'orderby' => 'menu_order',
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

function devhub_get_pricing_rule_reference_url(array $rule): string
{
    $term_candidates = [];
    $product_candidates = [];

    if (!empty($rule['custom_pl_status']) && !empty($rule['custom_pl']) && is_array($rule['custom_pl'])) {
        foreach ($rule['custom_pl'] as $group) {
            if (empty($group['rules']) || !is_array($group['rules'])) {
                continue;
            }

            foreach ($group['rules'] as $item) {
                if (!is_array($item) || empty($item['rule']['value']) || !is_array($item['rule']['value'])) {
                    continue;
                }

                $taxonomy = $item['rule']['item'] ?? '';
                if ($taxonomy === 'product_selection') {
                    $product_candidates = array_merge($product_candidates, $item['rule']['value']);
                    continue;
                }

                $term_candidates[] = [
                    'taxonomy' => $taxonomy,
                    'terms' => $item['rule']['value'],
                ];
            }
        }
    } elseif (!empty($rule['product_list'])) {
        $product_list_id = (int) $rule['product_list'];
        $list_type = get_post_meta($product_list_id, 'list_type', true);
        $list_config = get_post_meta($product_list_id, 'product_list_config', true);
        $list_config = is_array($list_config) ? $list_config : [];

        if ($list_type === 'dynamic_request' && !empty($list_config['rules']) && is_array($list_config['rules'])) {
            foreach ($list_config['rules'] as $group) {
                if (empty($group['rules']) || !is_array($group['rules'])) {
                    continue;
                }

                foreach ($group['rules'] as $item) {
                    if (!is_array($item) || empty($item['rule']['value']) || !is_array($item['rule']['value'])) {
                        continue;
                    }

                    $term_candidates[] = [
                        'taxonomy' => $item['rule']['item'] ?? '',
                        'terms' => $item['rule']['value'],
                    ];
                }
            }
        } elseif (!empty($list_config['selectedProducts']) && is_array($list_config['selectedProducts'])) {
            $product_candidates = $list_config['selectedProducts'];
        }
    }

    $product_candidates = array_values(array_unique(array_map('absint', $product_candidates)));

    if (count($product_candidates) === 1) {
        $product_link = get_permalink($product_candidates[0]);
        if ($product_link) {
            return $product_link;
        }
    }

    $category_slugs = [];
    $brand_slugs = [];
    $tag_slugs = [];

    foreach ($term_candidates as $candidate) {
        $taxonomy = $candidate['taxonomy'] ?? '';
        if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
            continue;
        }

        foreach ($candidate['terms'] as $term_id) {
            $term = get_term((int) $term_id, $taxonomy);
            if (!$term instanceof WP_Term || is_wp_error($term)) {
                continue;
            }

            if ($taxonomy === 'product_cat') {
                $category_slugs[] = $term->slug;
                continue;
            }

            if (in_array($taxonomy, ['product_brand', 'pwb-brand', 'pa_brand'], true)) {
                $brand_slugs[] = $term->slug;
                continue;
            }

            if ($taxonomy === 'product_tag') {
                $tag_slugs[] = $term->slug;
            }
        }
    }

    $category_slugs = array_values(array_unique(array_filter($category_slugs)));
    $brand_slugs = array_values(array_unique(array_filter($brand_slugs)));
    $tag_slugs = array_values(array_unique(array_filter($tag_slugs)));

    if (!empty($category_slugs) || !empty($brand_slugs)) {
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        $query_args = [];

        if (!empty($category_slugs)) {
            $query_args['filter_product_cat'] = implode(',', $category_slugs);
        }

        if (!empty($brand_slugs)) {
            $query_args['filter_brand'] = implode(',', $brand_slugs);
            $query_args['query_type_brand'] = 'or';
        }

        if (!empty($tag_slugs)) {
            $query_args['filter_product_tag'] = implode(',', $tag_slugs);
        }

        return add_query_arg($query_args, $shop_url);
    }

    if (!empty($tag_slugs)) {
        $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
        return add_query_arg('filter_product_tag', implode(',', $tag_slugs), $shop_url);
    }

    $preferred_taxonomies = ['product_cat', 'pwb-brand', 'product_tag'];

    foreach ($preferred_taxonomies as $preferred_taxonomy) {
        foreach ($term_candidates as $candidate) {
            if (($candidate['taxonomy'] ?? '') !== $preferred_taxonomy) {
                continue;
            }

            foreach ($candidate['terms'] as $term_id) {
                $term = get_term((int) $term_id, $preferred_taxonomy);
                if ($term instanceof WP_Term && !is_wp_error($term)) {
                    $link = get_term_link($term);
                    if (!is_wp_error($link)) {
                        return $link;
                    }
                }
            }
        }
    }

    foreach ($term_candidates as $candidate) {
        $taxonomy = $candidate['taxonomy'] ?? '';
        if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
            continue;
        }

        foreach ($candidate['terms'] as $term_id) {
            $term = get_term((int) $term_id, $taxonomy);
            if ($term instanceof WP_Term && !is_wp_error($term)) {
                $link = get_term_link($term);
                if (!is_wp_error($link)) {
                    return $link;
                }
            }
        }
    }

    foreach ($product_candidates as $product_id) {
        $product_link = get_permalink((int) $product_id);
        if ($product_link) {
            return $product_link;
        }
    }

    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
}

function devhub_get_active_pricing_offer_items(): array
{
    $items = [];

    foreach (devhub_get_active_pricing_rule_rows() as $rule) {
        $items[] = [
            'label' => $rule['label'],
            'url' => devhub_get_pricing_rule_reference_url($rule),
        ];
    }

    return $items;
}

function devhub_pricing_rule_schedule_is_active(int $rule_id): bool
{
    $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
    $now = new DateTimeImmutable('now', $tz);

    $parse_date = static function ($value) use ($tz): ?DateTimeImmutable {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        try {
            return new DateTimeImmutable($value, $tz);
        } catch (Exception $e) {
            return null;
        }
    };

    $schedules = get_post_meta($rule_id, 'discount_schedules', true);
    if (is_string($schedules) && $schedules !== '') {
        $schedules = maybe_unserialize($schedules);
    }

    if (is_array($schedules) && !empty($schedules)) {
        foreach ($schedules as $schedule) {
            if (!is_array($schedule)) {
                continue;
            }

            $start = $parse_date($schedule['start_date'] ?? '');
            $end = $parse_date($schedule['end_date'] ?? '');

            if ($start instanceof DateTimeImmutable && $now < $start) {
                continue;
            }

            if ($end instanceof DateTimeImmutable && $now > $end) {
                continue;
            }

            return true;
        }

        return false;
    }

    $legacy_start = $parse_date(get_post_meta($rule_id, 'discount_start_date', true));
    $legacy_end = $parse_date(get_post_meta($rule_id, 'discount_end_date', true));

    if ($legacy_start instanceof DateTimeImmutable && $now < $legacy_start) {
        return false;
    }

    if ($legacy_end instanceof DateTimeImmutable && $now > $legacy_end) {
        return false;
    }

    return true;
}

function devhub_get_active_pricing_rule_rows(): array
{
    static $rows = null;

    if ($rows !== null) {
        return $rows;
    }

    $rows = [];

    global $wpdb;

    $rule_ids = $wpdb->get_col(
        $wpdb->prepare(
            "
            SELECT posts.ID
            FROM {$wpdb->posts} posts
            INNER JOIN {$wpdb->postmeta} status_meta
                ON status_meta.post_id = posts.ID
                AND status_meta.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} priority_meta
                ON priority_meta.post_id = posts.ID
                AND priority_meta.meta_key = %s
            WHERE posts.post_type = %s
              AND posts.post_status = %s
              AND status_meta.meta_value IN ('1', 'true')
            ORDER BY CAST(COALESCE(priority_meta.meta_value, '999999') AS UNSIGNED) ASC, posts.post_title ASC
            ",
            'discount_status',
            'discount_priority',
            'awdp_pt_rules',
            'publish'
        )
    );

    if (empty($rule_ids)) {
        return $rows;
    }

    foreach ($rule_ids as $rule_id) {
        if (!devhub_pricing_rule_schedule_is_active((int) $rule_id)) {
            continue;
        }

        $quantity_rules = get_post_meta($rule_id, 'discount_quantityranges', true);

        if (is_string($quantity_rules) && $quantity_rules !== '') {
            $quantity_rules = maybe_unserialize($quantity_rules);
        }

        $rows[] = [
            'id' => (int) $rule_id,
            'label' => html_entity_decode(get_the_title($rule_id), ENT_QUOTES, get_bloginfo('charset')),
            'priority' => (int) get_post_meta($rule_id, 'discount_priority', true),
            'discount_type' => (string) get_post_meta($rule_id, 'discount_type', true),
            'discount_value' => (string) get_post_meta($rule_id, 'discount_value', true),
            'dynamic_value' => get_post_meta($rule_id, 'dynamic_value', true),
            'quantity_type' => (string) get_post_meta($rule_id, 'discount_quantity_type', true),
            'quantity_rules' => is_array($quantity_rules) ? $quantity_rules : [],
            'pricing_table' => (bool) get_post_meta($rule_id, 'discount_pricing_table', true),
            'table_layout' => (string) get_post_meta($rule_id, 'discount_table_layout', true),
            'product_list' => (int) get_post_meta($rule_id, 'discount_product_list', true),
            'custom_pl_status' => (bool) get_post_meta($rule_id, 'discount_custom_pl', true),
            'custom_pl' => get_post_meta($rule_id, 'custom_product_list', true),
        ];
    }

    return $rows;
}

function devhub_format_pricing_rule_admin_value(array $rule_item): string
{
    $type = (string) ($rule_item['discount_type'] ?? '');
    $raw_value = trim((string) ($rule_item['discount_value'] ?? ''));

    $format_money = static function (float $amount): string {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, get_bloginfo('charset'));
    };

    $format_number = static function (string $value): string {
        $formatted_value = trim($value);

        if ($formatted_value !== '' && strpos($formatted_value, '.') !== false) {
            $formatted_value = rtrim(rtrim($formatted_value, '0'), '.');
        }

        return $formatted_value;
    };

    if ($type === 'percent_product_price' || $type === 'percent_total_amount') {
        return $raw_value !== '' ? $format_number($raw_value) . '%' : '-';
    }

    if ($type === 'fixed_product_price' || $type === 'fixed_cart_amount') {
        return $raw_value !== '' ? $format_money((float) $raw_value) : '-';
    }

    if ($type === 'cart_quantity') {
        $rule_id = (int) ($rule_item['discount_id'] ?? $rule_item['id'] ?? 0);
        $quantity_rules = $rule_id > 0 ? get_post_meta($rule_id, 'discount_quantityranges', true) : [];

        if (is_string($quantity_rules) && $quantity_rules !== '') {
            $quantity_rules = maybe_unserialize($quantity_rules);
        }

        if (!is_array($quantity_rules) || empty($quantity_rules)) {
            return '-';
        }

        $formatted_values = [];

        foreach ($quantity_rules as $quantity_rule) {
            if (!is_array($quantity_rule)) {
                continue;
            }

            $rule_type = strtolower(trim((string) ($quantity_rule['dis_type'] ?? '')));
            $rule_value = trim((string) ($quantity_rule['dis_value'] ?? ''));

            if ($rule_value === '') {
                continue;
            }

            if ($rule_type === 'percentage') {
                $formatted_values[] = $format_number($rule_value) . '%';
                continue;
            }

            if ($rule_type === 'fixed') {
                $formatted_values[] = $format_money((float) $rule_value);
            }
        }

        $formatted_values = array_values(array_unique(array_filter($formatted_values)));

        return !empty($formatted_values) ? implode(', ', $formatted_values) : '-';
    }

    return $raw_value !== '' ? $raw_value : '-';
}

add_filter('rest_post_dispatch', function ($response, $server, $request) {
    if (!($response instanceof WP_REST_Response) || !($request instanceof WP_REST_Request)) {
        return $response;
    }

    if ($request->get_method() !== 'GET' || $request->get_route() !== '/awdp/v1/rules/') {
        return $response;
    }

    $data = $response->get_data();

    if (!is_array($data) || empty($data)) {
        return $response;
    }

    $updated = false;

    foreach ($data as $index => $row) {
        if (!is_array($row) || !array_key_exists('discount_type', $row) || !array_key_exists('discount_value', $row)) {
            continue;
        }

        $formatted_value = devhub_format_pricing_rule_admin_value($row);

        if ($formatted_value === '') {
            continue;
        }

        $data[$index]['discount_value'] = $formatted_value;
        $updated = true;
    }

    if ($updated) {
        $response->set_data($data);
    }

    return $response;
}, 10, 3);

function devhub_get_pricing_rule_product_ids(array $rule): array
{
    static $cache = [];

    $rule_id = (int) ($rule['id'] ?? 0);
    if ($rule_id <= 0) {
        return [];
    }

    if (array_key_exists($rule_id, $cache)) {
        return $cache[$rule_id];
    }

    $product_ids = [];
    $custom_pl = $rule['custom_pl'] ?? [];

    if (!empty($custom_pl) && is_array($custom_pl)) {
        $tax_query = ['relation' => 'OR'];
        $has_tax_filters = false;

        foreach ($custom_pl as $group) {
            if (empty($group['rules']) || !is_array($group['rules'])) {
                continue;
            }

            foreach ($group['rules'] as $item) {
                if (!is_array($item) || empty($item['rule']['item']) || empty($item['rule']['value']) || !is_array($item['rule']['value'])) {
                    continue;
                }

                $rule_item = (string) $item['rule']['item'];
                $values = array_values(array_filter(array_map('absint', $item['rule']['value'])));

                if (empty($values)) {
                    continue;
                }

                if ($rule_item === 'product_selection') {
                    $product_ids = array_merge($product_ids, $values);
                    continue;
                }

                if (!taxonomy_exists($rule_item)) {
                    continue;
                }

                $has_tax_filters = true;
                $tax_query[] = [
                    'taxonomy' => $rule_item,
                    'field' => 'term_id',
                    'terms' => $values,
                    'operator' => strtolower((string) ($item['rule']['condition'] ?? 'in')) === 'notin' ? 'NOT IN' : 'IN',
                ];
            }
        }

        if ($has_tax_filters) {
            $tax_query_product_ids = get_posts([
                'post_type' => 'product',
                'post_status' => ['publish', 'draft'],
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => $tax_query,
            ]);

            if (is_array($tax_query_product_ids)) {
                $product_ids = array_merge($product_ids, $tax_query_product_ids);
            }
        }

        $cache[$rule_id] = array_values(array_unique(array_map('absint', $product_ids)));
        return $cache[$rule_id];
    }

    $product_list_id = (int) ($rule['product_list'] ?? 0);
    if ($product_list_id <= 0) {
        $cache[$rule_id] = [];
        return $cache[$rule_id];
    }

    $list_type = (string) get_post_meta($product_list_id, 'list_type', true);
    $list_config = get_post_meta($product_list_id, 'product_list_config', true);
    $list_config = is_array($list_config) ? $list_config : [];

    if ($list_type !== 'dynamic_request') {
        $product_ids = array_map('absint', (array) ($list_config['selectedProducts'] ?? []));
        $cache[$rule_id] = array_values(array_unique(array_filter($product_ids)));
        return $cache[$rule_id];
    }

    $excluded_products = array_map('absint', (array) ($list_config['excludedProducts'] ?? []));
    $selected_products = array_map('absint', (array) ($list_config['selectedProducts'] ?? []));
    $tax_query = [];

    foreach ((array) ($list_config['rules'] ?? []) as $group) {
        if (empty($group['rules']) || !is_array($group['rules'])) {
            continue;
        }

        foreach ($group['rules'] as $item) {
            if (!is_array($item) || empty($item['rule']['item']) || empty($item['rule']['value']) || !is_array($item['rule']['value'])) {
                continue;
            }

            $rule_item = (string) $item['rule']['item'];
            if (!taxonomy_exists($rule_item)) {
                continue;
            }

            $values = array_values(array_filter(array_map('absint', $item['rule']['value'])));
            if (empty($values)) {
                continue;
            }

            $tax_query[] = [
                'taxonomy' => $rule_item,
                'field' => 'term_id',
                'terms' => $values,
                'operator' => strtolower((string) ($item['rule']['condition'] ?? 'in')) === 'notin' ? 'NOT IN' : 'IN',
            ];
        }
    }

    if (!empty($tax_query)) {
        array_unshift(
            $tax_query,
            [
                'relation' => strtolower((string) ($list_config['taxRelation'] ?? 'or')) === 'and' ? 'AND' : 'OR',
            ]
        );

        $dynamic_product_ids = get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post__not_in' => $excluded_products,
            'tax_query' => $tax_query,
        ]);

        if (is_array($dynamic_product_ids)) {
            $product_ids = array_merge($selected_products, $dynamic_product_ids);
        }
    } else {
        $product_ids = $selected_products;
    }

    if (!empty($excluded_products)) {
        $product_ids = array_diff($product_ids, $excluded_products);
    }

    $cache[$rule_id] = array_values(array_unique(array_map('absint', $product_ids)));
    return $cache[$rule_id];
}

function devhub_pricing_rule_matches_product(array $rule, WC_Product $product): bool
{
    $product_id = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();
    $target_product_ids = devhub_get_pricing_rule_product_ids($rule);

    if (!empty($target_product_ids)) {
        return in_array($product_id, $target_product_ids, true);
    }

    return empty($rule['custom_pl']) && (int) ($rule['product_list'] ?? 0) <= 0;
}

function devhub_get_matching_product_pricing_rules(WC_Product $product): array
{
    $matching_rules = [];

    foreach (devhub_get_active_pricing_rule_rows() as $rule) {
        if (devhub_pricing_rule_matches_product($rule, $product)) {
            $matching_rules[] = $rule;
        }
    }

    return $matching_rules;
}

function devhub_get_highest_priority_product_pricing_rule(WC_Product $product): array
{
    $matching_rules = devhub_get_matching_product_pricing_rules($product);

    return !empty($matching_rules) ? $matching_rules[0] : [];
}

function devhub_format_product_pricing_offer_rule(WC_Product $product, array $rule): array
{
    $format_money_text = static function (float $amount): string {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, get_bloginfo('charset'));
    };

    $format_number = static function (string $value): string {
        $formatted_value = trim($value);

        if ($formatted_value !== '' && strpos($formatted_value, '.') !== false) {
            $formatted_value = rtrim(rtrim($formatted_value, '0'), '.');
        }

        return $formatted_value;
    };

    $get_quantity_caption = static function (string $quantity_type): string {
        if ($quantity_type === 'type_cart') {
            return __('Cart Quantity', 'devicehub-theme');
        }

        if ($quantity_type === 'type_item') {
            return __('Cart Items', 'devicehub-theme');
        }

        return __('Quantity Offer', 'devicehub-theme');
    };

    if (empty($rule)) {
        return [];
    }

    $value = trim((string) ($rule['discount_value'] ?? ''));
    $type = (string) ($rule['discount_type'] ?? '');
    $badge_value = __('Sale', 'devicehub-theme');
    $badge_caption = __('Special Offer', 'devicehub-theme');
    $formatted_value = $format_number($value);
    $summary = __('A promotional pricing rule is active for this product.', 'devicehub-theme');

    if ($type === 'percent_product_price' && $value !== '') {
        $summary = sprintf(__('%s%% off is active for this product.', 'devicehub-theme'), $formatted_value);
        $badge_value = sprintf(__('%s%% OFF', 'devicehub-theme'), $formatted_value);
    } elseif ($type === 'fixed_product_price' && $value !== '') {
        $summary = sprintf(__('A fixed-price offer is active: %s.', 'devicehub-theme'), $format_money_text((float) $value));
        $badge_value = $format_money_text((float) $value);
        $badge_caption = __('Offer Price', 'devicehub-theme');
    } elseif ($type === 'percent_total_amount' && $value !== '') {
        $summary = sprintf(__('%s%% off is active on the cart total.', 'devicehub-theme'), $formatted_value);
        $badge_value = sprintf(__('%s%% OFF', 'devicehub-theme'), $formatted_value);
        $badge_caption = __('Cart Discount', 'devicehub-theme');
    } elseif ($type === 'fixed_cart_amount' && $value !== '') {
        $summary = sprintf(__('%s will be deducted from the cart total.', 'devicehub-theme'), $format_money_text((float) $value));
        $badge_value = $format_money_text((float) $value);
        $badge_caption = __('Cart Discount', 'devicehub-theme');
    } elseif ($type === 'cart_quantity') {
        $quantity_type = (string) ($rule['quantity_type'] ?? '');
        $quantity_rules = is_array($rule['quantity_rules'] ?? null) ? $rule['quantity_rules'] : [];
        $badge_caption = __('Quantity Offer', 'devicehub-theme');
        $summary = __('A quantity-based discount is active for this product.', 'devicehub-theme');

        $best_rule = null;
        $min_range_start = null;
        foreach ($quantity_rules as $quantity_rule) {
            if (!is_array($quantity_rule)) {
                continue;
            }

            $from = isset($quantity_rule['start_range']) ? (int) $quantity_rule['start_range'] : 0;
            $rule_value = isset($quantity_rule['dis_value']) ? (float) $quantity_rule['dis_value'] : null;
            if ($rule_value === null) {
                continue;
            }

            if ($from > 0 && ($min_range_start === null || $from < $min_range_start)) {
                $min_range_start = $from;
            }

            if ($best_rule === null || $rule_value > (float) ($best_rule['dis_value'] ?? 0)) {
                $best_rule = $quantity_rule;
            }
        }

        if (is_array($best_rule)) {
            $best_type = strtolower((string) ($best_rule['dis_type'] ?? ''));
            $best_value = isset($best_rule['dis_value']) ? (string) $best_rule['dis_value'] : '';
            $best_formatted_value = $format_number($best_value);

            $badge_value = $min_range_start !== null
                ? sprintf(__('From %s+', 'devicehub-theme'), (string) $min_range_start)
                : __('Qty Offer', 'devicehub-theme');

            if ($best_type === 'percentage' && $best_value !== '') {
                $summary = sprintf(__('A quantity-based discount of up to %s%% is active for this product.', 'devicehub-theme'), $best_formatted_value);
            } elseif ($best_type === 'fixed' && $best_value !== '') {
                $summary = sprintf(__('A quantity-based discount of up to %s is active for this product.', 'devicehub-theme'), $format_money_text((float) $best_value));
            }
        }
    } elseif ($type === 'bogo') {
        $badge_value = __('BOGO', 'devicehub-theme');
        $badge_caption = __('Buy X Get X', 'devicehub-theme');
        $summary = __('A buy-one-get-one style offer is active for this product.', 'devicehub-theme');
    } elseif ($type === 'gift') {
        $badge_value = __('Gift', 'devicehub-theme');
        $badge_caption = __('Gift Product', 'devicehub-theme');
        $summary = __('A gift-product offer is active for this product.', 'devicehub-theme');
    } elseif ($type === 'pay_method') {
        $badge_value = __('Pay Offer', 'devicehub-theme');
        $badge_caption = __('Payment Method', 'devicehub-theme');
        $summary = __('A payment-method offer is active for this product.', 'devicehub-theme');
    } elseif ($type === 'ship_method') {
        $badge_value = __('Ship Offer', 'devicehub-theme');
        $badge_caption = __('Shipping Method', 'devicehub-theme');
        $summary = __('A shipping-method offer is active for this product.', 'devicehub-theme');
    }

    if ($product->is_type('variable')) {
        $summary .= ' ' . __('This offer applies across the available variants matched by the rule.', 'devicehub-theme');
    }

    return [
        'id' => (int) ($rule['id'] ?? 0),
        'priority' => (int) ($rule['priority'] ?? 0),
        'label' => $rule['label'],
        'type' => $type,
        'summary' => $summary,
        'badge_value' => $badge_value,
        'badge_caption' => $badge_caption,
        'quantity_type' => (string) ($rule['quantity_type'] ?? ''),
        'quantity_rules' => is_array($rule['quantity_rules'] ?? null) ? $rule['quantity_rules'] : [],
        'discount_value' => $value,
        'pricing_table' => !empty($rule['pricing_table']),
    ];
}

function devhub_get_product_pricing_offer_candidates(WC_Product $product): array
{
    $candidates = [];

    foreach (devhub_get_matching_product_pricing_rules($product) as $rule) {
        $formatted = devhub_format_product_pricing_offer_rule($product, $rule);

        if (!empty($formatted)) {
            $candidates[] = $formatted;
        }
    }

    return $candidates;
}

function devhub_get_product_pricing_offer_data(WC_Product $product, int $quantity = 1): array
{
    $quantity = max(1, $quantity);

    foreach (devhub_get_product_pricing_offer_candidates($product) as $candidate) {
        if (($candidate['type'] ?? '') !== 'cart_quantity') {
            return $candidate;
        }

        $quantity_rules = is_array($candidate['quantity_rules'] ?? null) ? $candidate['quantity_rules'] : [];
        foreach ($quantity_rules as $quantity_rule) {
            if (!is_array($quantity_rule)) {
                continue;
            }

            $from = isset($quantity_rule['start_range']) ? (int) $quantity_rule['start_range'] : 0;
            $to = isset($quantity_rule['end_range']) && $quantity_rule['end_range'] !== '' ? (int) $quantity_rule['end_range'] : PHP_INT_MAX;

            if ($quantity >= $from && $quantity <= $to) {
                return $candidate;
            }
        }
    }

    return [];
}

function devhub_get_product_pricing_offer_preview_data(WC_Product $product): array
{
    $candidates = devhub_get_product_pricing_offer_candidates($product);

    if (empty($candidates)) {
        return [];
    }

    $offer = $candidates[0];

    if (($offer['type'] ?? '') !== 'cart_quantity') {
        return $offer;
    }

    $quantity_rules = is_array($offer['quantity_rules'] ?? null) ? $offer['quantity_rules'] : [];
    $min_range_start = null;

    foreach ($quantity_rules as $quantity_rule) {
        if (!is_array($quantity_rule)) {
            continue;
        }

        $from = isset($quantity_rule['start_range']) ? (int) $quantity_rule['start_range'] : 0;
        if ($from > 0 && ($min_range_start === null || $from < $min_range_start)) {
            $min_range_start = $from;
        }
    }

    $offer['badge_value'] = $min_range_start !== null
        ? sprintf(__('%s+', 'devicehub-theme'), (string) $min_range_start)
        : __('Qty Offer', 'devicehub-theme');
    $offer['badge_caption'] = __('Quantity Offer', 'devicehub-theme');

    return $offer;
}

function devhub_get_awdp_runtime_discounts(): array
{
    if (!class_exists('AWDP_Discount')) {
        return [];
    }

    try {
        $instance = AWDP_Discount::instance();
    } catch (Throwable $exception) {
        return [];
    }

    if (!is_object($instance)) {
        return [];
    }

    try {
        $reflection = new ReflectionObject($instance);
        if (!$reflection->hasProperty('discounts')) {
            return [];
        }

        $property = $reflection->getProperty('discounts');
        $property->setAccessible(true);
        $discounts = $property->getValue($instance);

        return is_array($discounts) ? $discounts : [];
    } catch (ReflectionException $exception) {
        return [];
    }
}

function devhub_get_cart_discount_summary_data(): array
{
    if (!function_exists('WC') || !WC()->cart) {
        return [];
    }

    $virtual_coupon_label = html_entity_decode((string) (get_option('awdp_fee_label') ?: 'Discount'), ENT_QUOTES, get_bloginfo('charset'));
    $applied_coupons = array_map('strval', (array) WC()->cart->get_applied_coupons());
    $matched_coupon_code = '';

    foreach ($applied_coupons as $coupon_code) {
        if (strcasecmp($coupon_code, $virtual_coupon_label) === 0) {
            $matched_coupon_code = $coupon_code;
            break;
        }
    }

    if ($matched_coupon_code === '') {
        return [];
    }

    $format_money_text = static function (float $amount): string {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, get_bloginfo('charset'));
    };

    $format_number = static function (string $value): string {
        $formatted_value = trim($value);

        if ($formatted_value !== '' && strpos($formatted_value, '.') !== false) {
            $formatted_value = rtrim(rtrim($formatted_value, '0'), '.');
        }

        return $formatted_value;
    };

    $build_rule_value_label = static function (string $type, string $value) use ($format_money_text, $format_number): string {
        $normalized_value = trim($value);

        if ($normalized_value === '') {
            return '';
        }

        if ($type === 'percent_product_price' || $type === 'percent_total_amount') {
            return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number($normalized_value));
        }

        if ($type === 'fixed_product_price' || $type === 'fixed_cart_amount') {
            return sprintf(__('%s OFF', 'devicehub-theme'), $format_money_text((float) $normalized_value));
        }

        return '';
    };

    $find_matching_quantity_rule = static function (array $quantity_rules, int $quantity): ?array {
        foreach ($quantity_rules as $quantity_rule) {
            if (!is_array($quantity_rule)) {
                continue;
            }

            $from = isset($quantity_rule['start_range']) ? (int) $quantity_rule['start_range'] : 0;
            $to = isset($quantity_rule['end_range']) && $quantity_rule['end_range'] !== '' ? (int) $quantity_rule['end_range'] : PHP_INT_MAX;

            if ($quantity >= $from && $quantity <= $to) {
                return $quantity_rule;
            }
        }

        return null;
    };

    $get_rule_chip_label = static function (array $applied_rule, float $actual_discount_amount) use ($format_money_text, $format_number, $find_matching_quantity_rule): string {
        $type = (string) ($applied_rule['type'] ?? '');
        $rule_row = is_array($applied_rule['row'] ?? null) ? $applied_rule['row'] : [];
        $value = trim((string) ($rule_row['discount_value'] ?? ''));
        $subtotal_amount = (float) WC()->cart->get_subtotal();
        $runtime_items = is_array($applied_rule['runtime']['discounts'] ?? null) ? $applied_rule['runtime']['discounts'] : [];

        $resolve_matching_rule_value = static function (string $expected_type) use ($runtime_items): string {
            if ($expected_type === '' || empty($runtime_items)) {
                return '';
            }

            $matched_values = [];
            $active_rules = devhub_get_active_pricing_rule_rows();

            foreach ($runtime_items as $runtime_item) {
                $product_id = (int) ($runtime_item['productid'] ?? 0);
                if ($product_id <= 0) {
                    continue;
                }

                $product = wc_get_product($product_id);
                if (!$product instanceof WC_Product) {
                    continue;
                }

                foreach ($active_rules as $candidate_rule) {
                    if (($candidate_rule['discount_type'] ?? '') !== $expected_type) {
                        continue;
                    }

                    $candidate_value = trim((string) ($candidate_rule['discount_value'] ?? ''));
                    if ($candidate_value === '') {
                        continue;
                    }

                    if (devhub_pricing_rule_matches_product($candidate_rule, $product)) {
                        $matched_values[] = $candidate_value;
                    }
                }
            }

            $matched_values = array_values(array_unique(array_filter($matched_values)));

            return count($matched_values) === 1 ? $matched_values[0] : '';
        };

        $infer_percentage_label = static function () use ($actual_discount_amount, $subtotal_amount, $format_number): string {
            if ($actual_discount_amount <= 0 || $subtotal_amount <= 0) {
                return '';
            }

            $percentage_value = ($actual_discount_amount / $subtotal_amount) * 100;
            $rounded_percentage = round($percentage_value, 2);

            if ($rounded_percentage <= 0) {
                return '';
            }

            return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number((string) $rounded_percentage));
        };

        $format_quantity_rule_label = static function (array $quantity_rule) use ($format_money_text, $format_number): string {
            $matched_type = strtolower((string) ($quantity_rule['dis_type'] ?? ''));
            $matched_value = trim((string) ($quantity_rule['dis_value'] ?? ''));

            if ($matched_value === '') {
                return '';
            }

            if ($matched_type === 'percentage') {
                return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number($matched_value));
            }

            if ($matched_type === 'fixed') {
                return sprintf(__('%s OFF', 'devicehub-theme'), $format_money_text((float) $matched_value));
            }

            return '';
        };

        if ($type === 'percent_product_price' && $value !== '') {
            return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number($value));
        }

        if ($type === 'percent_product_price') {
            $resolved_value = $resolve_matching_rule_value($type);
            if ($resolved_value !== '') {
                return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number($resolved_value));
            }

            $inferred_percentage_label = $infer_percentage_label();

            if ($inferred_percentage_label !== '') {
                return $inferred_percentage_label;
            }
        }

        if ($type === 'percent_total_amount' && $value !== '') {
            return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number($value));
        }

        if ($type === 'percent_total_amount') {
            $resolved_value = $resolve_matching_rule_value($type);
            if ($resolved_value !== '') {
                return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number($resolved_value));
            }

            $inferred_percentage_label = $infer_percentage_label();

            if ($inferred_percentage_label !== '') {
                return $inferred_percentage_label;
            }
        }

        if ($type === 'fixed_product_price' || $type === 'fixed_cart_amount') {
            return sprintf(__('%s OFF', 'devicehub-theme'), $format_money_text($actual_discount_amount));
        }

        if ($type === 'cart_quantity') {
            $quantity_rules = is_array($rule_row['quantity_rules'] ?? null) ? $rule_row['quantity_rules'] : [];
            $quantity_type = (string) ($rule_row['quantity_type'] ?? '');
            $matching_cart_items = [];

            foreach ((array) WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'] ?? null;
                if (!$product instanceof WC_Product || !devhub_pricing_rule_matches_product($rule_row, $product)) {
                    continue;
                }

                $matching_cart_items[] = $cart_item;
            }

            $candidate_labels = [];

            if ($quantity_type === 'type_cart') {
                $matched_quantity_rule = $find_matching_quantity_rule($quantity_rules, count($matching_cart_items));
                if (is_array($matched_quantity_rule)) {
                    $candidate_labels[] = $format_quantity_rule_label($matched_quantity_rule);
                }
            } elseif ($quantity_type === 'type_item') {
                $total_quantity = 0;
                foreach ($matching_cart_items as $cart_item) {
                    $total_quantity += max(0, (int) ($cart_item['quantity'] ?? 0));
                }

                $matched_quantity_rule = $find_matching_quantity_rule($quantity_rules, $total_quantity);
                if (is_array($matched_quantity_rule)) {
                    $candidate_labels[] = $format_quantity_rule_label($matched_quantity_rule);
                }
            } else {
                foreach ($matching_cart_items as $cart_item) {
                    $item_quantity = max(0, (int) ($cart_item['quantity'] ?? 0));
                    $matched_quantity_rule = $find_matching_quantity_rule($quantity_rules, $item_quantity);

                    if (is_array($matched_quantity_rule)) {
                        $candidate_labels[] = $format_quantity_rule_label($matched_quantity_rule);
                    }
                }
            }

            $candidate_labels = array_values(array_unique(array_filter($candidate_labels)));

            if (count($candidate_labels) === 1) {
                return $candidate_labels[0];
            }

            foreach ($quantity_rules as $quantity_rule) {
                $quantity_rule_label = $format_quantity_rule_label((array) $quantity_rule);

                if ($quantity_rule_label !== '' && strpos($quantity_rule_label, '%') !== false) {
                    $candidate_labels[] = $quantity_rule_label;
                }
            }

            $candidate_labels = array_values(array_unique(array_filter($candidate_labels)));

            if (count($candidate_labels) === 1) {
                return $candidate_labels[0];
            }

            $inferred_percentage_label = $infer_percentage_label();
            if ($inferred_percentage_label !== '') {
                return $inferred_percentage_label;
            }
        }

        return sprintf(__('%s OFF', 'devicehub-theme'), $format_money_text($actual_discount_amount));
    };

    $actual_discount_amount = (float) WC()->cart->get_coupon_discount_amount($matched_coupon_code, false);
    if ($actual_discount_amount <= 0) {
        return [];
    }

    $resolve_label_from_cart_rules = static function () use ($actual_discount_amount, $build_rule_value_label): string {
        $cart_items = WC()->cart ? WC()->cart->get_cart() : [];
        if (empty($cart_items)) {
            return '';
        }

        $cart_subtotal = (float) WC()->cart->get_subtotal();
        $candidate_labels = [];
        $tolerance = 0.01;

        foreach (devhub_get_active_pricing_rule_rows() as $rule) {
            $type = (string) ($rule['discount_type'] ?? '');
            $value = trim((string) ($rule['discount_value'] ?? ''));

            if ($value === '' || !in_array($type, ['percent_product_price', 'fixed_product_price', 'percent_total_amount', 'fixed_cart_amount'], true)) {
                continue;
            }

            $eligible_subtotal = 0.0;
            $expected_discount = 0.0;

            foreach ($cart_items as $cart_item) {
                $product = $cart_item['data'] ?? null;
                if (!$product instanceof WC_Product || !devhub_pricing_rule_matches_product($rule, $product)) {
                    continue;
                }

                $quantity = max(1, (int) ($cart_item['quantity'] ?? 1));
                $line_subtotal = isset($cart_item['line_subtotal']) ? (float) $cart_item['line_subtotal'] : 0.0;
                $unit_price = $quantity > 0 ? ($line_subtotal / $quantity) : 0.0;
                $eligible_subtotal += $line_subtotal;

                if ($type === 'percent_product_price') {
                    $expected_discount += $line_subtotal * (((float) $value) / 100);
                } elseif ($type === 'fixed_product_price') {
                    $expected_discount += max(0.0, ($unit_price - (float) $value)) * $quantity;
                }
            }

            if ($type === 'percent_total_amount') {
                $expected_discount = $cart_subtotal * (((float) $value) / 100);
            } elseif ($type === 'fixed_cart_amount') {
                $expected_discount = (float) $value;
            } elseif ($eligible_subtotal <= 0) {
                continue;
            }

            if (abs(round($expected_discount, 2) - round($actual_discount_amount, 2)) <= $tolerance) {
                $candidate_label = $build_rule_value_label($type, $value);
                if ($candidate_label !== '') {
                    $candidate_labels[] = $candidate_label;
                }
            }
        }

        $candidate_labels = array_values(array_unique(array_filter($candidate_labels)));

        return count($candidate_labels) === 1 ? $candidate_labels[0] : '';
    };

    $resolve_label_from_quantity_rules = static function () use ($actual_discount_amount, $format_money_text, $format_number, $find_matching_quantity_rule): string {
        $cart_items = WC()->cart ? WC()->cart->get_cart() : [];
        if (empty($cart_items)) {
            return '';
        }

        $candidate_labels = [];

        $format_quantity_rule_label = static function (array $quantity_rule) use ($format_money_text, $format_number): string {
            $matched_type = strtolower((string) ($quantity_rule['dis_type'] ?? ''));
            $matched_value = trim((string) ($quantity_rule['dis_value'] ?? ''));

            if ($matched_value === '') {
                return '';
            }

            if ($matched_type === 'percentage') {
                return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number($matched_value));
            }

            if ($matched_type === 'fixed') {
                return sprintf(__('%s OFF', 'devicehub-theme'), $format_money_text((float) $matched_value));
            }

            return '';
        };

        foreach (devhub_get_active_pricing_rule_rows() as $rule) {
            if (($rule['discount_type'] ?? '') !== 'cart_quantity') {
                continue;
            }

            $quantity_rules = is_array($rule['quantity_rules'] ?? null) ? $rule['quantity_rules'] : [];
            if (empty($quantity_rules)) {
                continue;
            }

            $quantity_type = (string) ($rule['quantity_type'] ?? '');
            $matching_cart_items = [];

            foreach ($cart_items as $cart_item) {
                $product = $cart_item['data'] ?? null;
                if (!$product instanceof WC_Product || !devhub_pricing_rule_matches_product($rule, $product)) {
                    continue;
                }

                $matching_cart_items[] = $cart_item;
            }

            if (empty($matching_cart_items)) {
                continue;
            }

            $matched_quantity_rule = null;

            if ($quantity_type === 'type_cart') {
                $matched_quantity_rule = $find_matching_quantity_rule($quantity_rules, count($matching_cart_items));
            } elseif ($quantity_type === 'type_item') {
                $total_quantity = 0;
                foreach ($matching_cart_items as $cart_item) {
                    $total_quantity += max(0, (int) ($cart_item['quantity'] ?? 0));
                }
                $matched_quantity_rule = $find_matching_quantity_rule($quantity_rules, $total_quantity);
            } else {
                $per_item_labels = [];

                foreach ($matching_cart_items as $cart_item) {
                    $item_quantity = max(0, (int) ($cart_item['quantity'] ?? 0));
                    $item_rule = $find_matching_quantity_rule($quantity_rules, $item_quantity);

                    if (is_array($item_rule)) {
                        $per_item_labels[] = $format_quantity_rule_label($item_rule);
                    }
                }

                $per_item_labels = array_values(array_unique(array_filter($per_item_labels)));

                if (count($per_item_labels) === 1) {
                    $candidate_labels[] = $per_item_labels[0];
                }

                continue;
            }

            if (is_array($matched_quantity_rule)) {
                $candidate_labels[] = $format_quantity_rule_label($matched_quantity_rule);
            }
        }

        $candidate_labels = array_values(array_unique(array_filter($candidate_labels)));

        if (count($candidate_labels) === 1) {
            return $candidate_labels[0];
        }

        $subtotal_amount = (float) WC()->cart->get_subtotal();
        if ($actual_discount_amount > 0 && $subtotal_amount > 0) {
            $percentage_value = ($actual_discount_amount / $subtotal_amount) * 100;
            $rounded_percentage = round($percentage_value, 2);

            if ($rounded_percentage > 0) {
                return sprintf(__('%s%% OFF', 'devicehub-theme'), $format_number((string) $rounded_percentage));
            }
        }

        return '';
    };

    $resolved_label_from_cart_rules = $resolve_label_from_cart_rules();
    if ($resolved_label_from_cart_rules !== '') {
        return [
            'type' => 'resolved_from_cart_rules',
            'chip_label' => $resolved_label_from_cart_rules,
        ];
    }

    $resolved_label_from_quantity_rules = $resolve_label_from_quantity_rules();
    if ($resolved_label_from_quantity_rules !== '') {
        return [
            'type' => 'resolved_from_quantity_rules',
            'chip_label' => $resolved_label_from_quantity_rules,
        ];
    }

    $runtime_discounts = devhub_get_awdp_runtime_discounts();
    if (empty($runtime_discounts)) {
        return [
            'type' => 'unknown',
            'chip_label' => sprintf(__('%s OFF', 'devicehub-theme'), $format_money_text($actual_discount_amount)),
        ];
    }

    $rule_rows_by_id = [];
    foreach (devhub_get_active_pricing_rule_rows() as $rule_row) {
        $rule_rows_by_id[(int) $rule_row['id']] = $rule_row;
    }

    $applied_rules = [];

    foreach ($runtime_discounts as $rule_id => $runtime_discount) {
        if (!is_array($runtime_discount) || empty($runtime_discount['discounts']) || !is_array($runtime_discount['discounts'])) {
            continue;
        }

        $rule_discount_total = 0.0;

        foreach ($runtime_discount['discounts'] as $discount_item) {
            if (!is_array($discount_item)) {
                continue;
            }

            $discount_value = isset($discount_item['discount']) ? (float) $discount_item['discount'] : 0.0;
            if ($discount_value > 0) {
                $rule_discount_total += $discount_value;
            }
        }

        if ($rule_discount_total <= 0) {
            continue;
        }

        $numeric_rule_id = (int) $rule_id;
        $rule_row = $rule_rows_by_id[$numeric_rule_id] ?? [];
        $applied_rules[] = [
            'id' => $numeric_rule_id,
            'type' => (string) (($rule_row['discount_type'] ?? '') ?: ($runtime_discount['discount_type'] ?? '')),
            'runtime' => $runtime_discount,
            'row' => $rule_row,
        ];
    }

    if (count($applied_rules) !== 1) {
        $candidate_chip_labels = [];

        foreach ($applied_rules as $applied_rule) {
            $candidate_label = $get_rule_chip_label($applied_rule, $actual_discount_amount);

            if (strpos($candidate_label, '%') !== false) {
                $candidate_chip_labels[] = $candidate_label;
            }
        }

        $candidate_chip_labels = array_values(array_unique(array_filter($candidate_chip_labels)));

        if (count($candidate_chip_labels) === 1) {
            return [
                'type' => 'multiple',
                'chip_label' => $candidate_chip_labels[0],
            ];
        }

        return [
            'type' => 'multiple',
            'chip_label' => sprintf(__('%s OFF', 'devicehub-theme'), $format_money_text($actual_discount_amount)),
        ];
    }

    $applied_rule = $applied_rules[0];
    $type = (string) ($applied_rule['type'] ?? '');

    return [
        'type' => $type ?: 'unknown',
        'chip_label' => $get_rule_chip_label($applied_rule, $actual_discount_amount),
    ];
}

function devhub_ajax_get_cart_discount_summary(): void
{
    wp_send_json_success([
        'discountSummary' => devhub_get_cart_discount_summary_data(),
        'virtualCouponLabel' => html_entity_decode((string) (get_option('awdp_fee_label') ?: 'Discount'), ENT_QUOTES, get_bloginfo('charset')),
    ]);
}

add_action('wp_ajax_devhub_cart_discount_summary', 'devhub_ajax_get_cart_discount_summary');
add_action('wp_ajax_nopriv_devhub_cart_discount_summary', 'devhub_ajax_get_cart_discount_summary');

function devhub_get_menu_items_by_location(string $location): array
{
    if (!has_nav_menu($location)) {
        return [];
    }

    $locations = get_nav_menu_locations();
    $menu_id = $locations[$location] ?? 0;

    if (!$menu_id) {
        return [];
    }

    $menu_items = wp_get_nav_menu_items($menu_id);
    if (empty($menu_items) || is_wp_error($menu_items)) {
        return [];
    }

    $items = [];

    foreach ($menu_items as $menu_item) {
        if ((int) $menu_item->menu_item_parent !== 0) {
            continue;
        }

        $items[] = [
            'label' => $menu_item->title,
            'url' => $menu_item->url,
        ];
    }

    return $items;
}

function devhub_get_fallback_offer_items(): array
{
    return [
        ['label' => __('Flash Sale', 'devicehub-theme'), 'url' => home_url('/shop/')],
        ['label' => __('New Arrivals', 'devicehub-theme'), 'url' => home_url('/shop/')],
        ['label' => __('Bundle Deals', 'devicehub-theme'), 'url' => home_url('/shop/')],
        ['label' => __('Mobile Phone Offers', 'devicehub-theme'), 'url' => home_url('/shop/')],
        ['label' => __('Broadband Offers', 'devicehub-theme'), 'url' => home_url('/shop/')],
    ];
}

function devhub_render_secondary_offers(): void
{
    $pricing_rule_items = devhub_get_active_pricing_offer_items();

    if (!empty($pricing_rule_items)) {
        ?>
        <ul class="devhub-secondary-nav__offer-list">
            <?php foreach ($pricing_rule_items as $item): ?>
                <li>
                    <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return;
    }

    $menu_items = devhub_get_menu_items_by_location('secondary_offers_menu');
    $offer_items = !empty($menu_items) ? $menu_items : devhub_get_fallback_offer_items();
    ?>
    <ul class="devhub-secondary-nav__offer-list">
        <?php foreach ($offer_items as $item): ?>
            <li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php
}

function devhub_render_secondary_nav(): void
{
    $categories = devhub_get_secondary_categories();
    $parent_brands = devhub_get_secondary_brands(0);
    $child_brands = devhub_get_secondary_brands();
    $child_brands = array_values(array_filter($child_brands, static function (WP_Term $brand): bool {
        return (int) $brand->parent > 0;
    }));
    $brand_list = !empty($child_brands) ? $child_brands : $parent_brands;
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
                    <?php $category_list_class = count($categories) > 10 ? ' devhub-secondary-nav__cat-list--scroll' : ''; ?>
                    <div class="devhub-secondary-nav__dropdown devhub-secondary-nav__dropdown--categories">
                        <ul class="devhub-secondary-nav__cat-list<?php echo esc_attr($category_list_class); ?>">
                            <?php foreach ($categories as $category):
                                $visual = devhub_get_secondary_category_visual($category);
                                $category_name = devhub_get_product_category_display_name($category);
                                $child_categories = get_terms([
                                    'taxonomy' => 'product_cat',
                                    'hide_empty' => false,
                                    'parent' => $category->term_id,
                                    'orderby' => 'menu_order',
                                    'order' => 'ASC',
                                ]);
                                $has_child_categories = !is_wp_error($child_categories) && !empty($child_categories);
                                ?>
                                <li class="<?php echo $has_child_categories ? 'devhub-secondary-nav__cat-has-children' : ''; ?>">
                                    <a href="<?php echo esc_url(get_term_link($category)); ?>">
                                        <span class="devhub-secondary-nav__cat-icon" aria-hidden="true">
                                            <?php if ($visual['image'] !== ''): ?>
                                                <img src="<?php echo esc_url($visual['image']); ?>" alt=""
                                                    onerror="this.hidden=true;this.nextElementSibling.classList.remove('devhub-secondary-nav__fallback-icon--hidden');">
                                            <?php endif; ?>
                                            <i class="<?php echo esc_attr($visual['icon'] . ($visual['image'] !== '' ? ' devhub-secondary-nav__fallback-icon--hidden' : '')); ?>"></i>
                                        </span>
                                        <span><?php echo esc_html($category_name); ?></span>
                                        <?php if ($has_child_categories): ?>
                                            <i class="fas fa-chevron-right devhub-secondary-nav__cat-arrow" aria-hidden="true"></i>
                                        <?php endif; ?>
                                    </a>
                                    <?php if ($has_child_categories): ?>
                                        <ul class="devhub-secondary-nav__cat-children">
                                            <?php foreach ($child_categories as $child_category):
                                                $child_category_name = devhub_get_product_category_display_name($child_category);
                                                ?>
                                                <li>
                                                    <a href="<?php echo esc_url(get_term_link($child_category)); ?>">
                                                        <?php echo esc_html($child_category_name); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="devhub-secondary-nav__item devhub-secondary-nav__item--brands">
                <button class="devhub-secondary-nav__button" type="button" aria-haspopup="true">
                    <span><?php esc_html_e('Brands', 'devicehub-theme'); ?></span>
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
                <?php if (!empty($parent_brands) || !empty($brand_list)): ?>
                    <div class="devhub-secondary-nav__dropdown devhub-secondary-nav__dropdown--brands">
                        <?php if (!empty($parent_brands)): ?>
                            <div class="devhub-secondary-nav__brand-featured">
                                <h3><?php esc_html_e('Top Brands', 'devicehub-theme'); ?></h3>
                                <div class="devhub-secondary-nav__brand-grid">
                                    <?php foreach (array_slice($parent_brands, 0, 12) as $brand):
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
                        <?php endif; ?>
                        <?php if (!empty($brand_list)): ?>
                            <div class="devhub-secondary-nav__brand-list">
                                <h3><?php esc_html_e('Brands', 'devicehub-theme'); ?></h3>
                                <ul>
                                    <?php foreach ($brand_list as $brand): ?>
                                        <li>
                                            <a href="<?php echo esc_url(get_term_link($brand)); ?>">
                                                <?php echo esc_html($brand->name); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
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

function devhub_render_mobile_secondary_link_list(array $items, string $class_name): void
{
    if (empty($items)) {
        return;
    }
    ?>
    <ul class="<?php echo esc_attr($class_name); ?>">
        <?php foreach ($items as $item):
            if (empty($item['label']) || empty($item['url'])) {
                continue;
            }
            ?>
            <li>
                <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}

function devhub_get_mobile_secondary_menu_items(): array
{
    $items = devhub_get_menu_items_by_location('secondary_menu');

    if (!empty($items)) {
        return $items;
    }

    return [
        ['label' => __('Hire Purchase', 'devicehub-theme'), 'url' => home_url('/hire-purchase/')],
        ['label' => __('Gift Vouchers', 'devicehub-theme'), 'url' => home_url('/gift-vouchers/')],
        ['label' => __('Duty Free', 'devicehub-theme'), 'url' => home_url('/duty-free/')],
        ['label' => __('Hutch Loyalty', 'devicehub-theme'), 'url' => home_url('/loyalty/')],
    ];
}

function devhub_get_mobile_secondary_offer_items(): array
{
    $pricing_rule_items = devhub_get_active_pricing_offer_items();

    if (!empty($pricing_rule_items)) {
        return $pricing_rule_items;
    }

    $items = devhub_get_menu_items_by_location('secondary_offers_menu');

    if (!empty($items)) {
        return $items;
    }

    return devhub_get_fallback_offer_items();
}

function devhub_render_mobile_secondary_nav_sections(): void
{
    $brands = devhub_get_secondary_brands();
    $brands = array_values(array_filter($brands, static function (WP_Term $brand): bool {
        return (int) $brand->parent > 0;
    }));

    if (empty($brands)) {
        $brands = devhub_get_secondary_brands(0);
    }

    $quick_links = devhub_get_mobile_secondary_menu_items();
    $offer_links = devhub_get_mobile_secondary_offer_items();
    ?>
    <div class="devhub-mobile-secondary-nav">
        <?php if (!empty($brands)): ?>
            <h5 class="title"><?php esc_html_e('Brands', 'devicehub-theme'); ?></h5>
            <ul class="devhub-mobile-secondary-nav__list devhub-mobile-secondary-nav__list--brands">
                <?php foreach ($brands as $brand): ?>
                    <li>
                        <a href="<?php echo esc_url(get_term_link($brand)); ?>">
                            <?php echo esc_html($brand->name); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h5 class="title"><?php esc_html_e("Today's Offer", 'devicehub-theme'); ?></h5>
        <?php devhub_render_mobile_secondary_link_list($offer_links, 'devhub-mobile-secondary-nav__list'); ?>

        <h5 class="title"><?php esc_html_e('Quick Links', 'devicehub-theme'); ?></h5>
        <?php devhub_render_mobile_secondary_link_list($quick_links, 'devhub-mobile-secondary-nav__list'); ?>

        <h5 class="title"><?php esc_html_e('Support', 'devicehub-theme'); ?></h5>
        <ul class="devhub-mobile-secondary-nav__list devhub-mobile-secondary-nav__list--support">
            <li>
                <a href="<?php echo esc_url(home_url('/my-account/orders/')); ?>">
                    <?php esc_html_e('Track your order', 'devicehub-theme'); ?>
                </a>
            </li>
            <li>
                <a href="tel:+94788222888">+94 788 222 888</a>
            </li>
        </ul>
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

// ── WooCommerce — Add-to-cart redirect (PRG pattern) ─────────────────────────
// After a POST add-to-cart, WooCommerce by default may not redirect at all,
// leaving the browser on the POST response. A reload then re-submits the POST
// and adds another item. We enforce the Post/Redirect/Get pattern by always
// returning a clean redirect URL:
//   • Buy Now → checkout
//   • Normal add to cart → the product's own permalink (no ?add-to-cart= params)
//
// The second arg ($product) is the WC_Product object WooCommerce passes to the filter.

add_filter('woocommerce_add_to_cart_redirect', 'devhub_add_to_cart_redirect', 99, 2);

function devhub_add_to_cart_redirect($url, $product)
{
    if (!empty($_POST['devhub_buy_now'])) {
        return wc_get_checkout_url();
    }

    // Only enforce PRG for POST-based add-to-cart (the single product form).
    // GET-based links (archive/shop loop buttons) go through here too but
    // they already redirect fine; stripping params from their URL is safe.
    if (!empty($_POST['add-to-cart']) && $product instanceof \WC_Product) {
        $parent_id = $product->get_parent_id();
        return get_permalink($parent_id ?: $product->get_id());
    }

    // For GET add-to-cart links, strip the params from whatever URL WC built.
    if (is_string($url) && $url !== '') {
        return remove_query_arg(['add-to-cart', 'variation_id'], $url);
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


// ── Mobile drawer categories ──────────────────────────────────────────────────
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
        'orderby' => 'menu_order',
        'order' => 'ASC',
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
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);
        $icon = get_term_meta($cat->term_id, 'shopire_product_cat_icon', true);
        $icon_html = $icon ? "<i class='" . esc_attr($icon) . " wf-mr-2'></i>" : '';
        $cat_name = devhub_get_product_category_display_name($cat);
        $link = '<a title="' . esc_attr($cat_name) . '" href="' . esc_url(get_term_link($cat->term_id)) . '" class="nav-link">' . $icon_html . esc_html($cat_name) . '</a>';

        if (!empty($child_cats) && !is_wp_error($child_cats)) {
            echo '<li class="menu-item menu-item-has-children" style="display:list-item;">' . $link;
            echo '<ul class="dropdown-menu">';
            foreach ($child_cats as $child) {
                $child_name = devhub_get_product_category_display_name($child);
                echo '<li class="menu-item" style="display:list-item;"><a title="' . esc_attr($child_name) . '" href="' . esc_url(get_term_link($child->term_id)) . '" class="dropdown-item">' . esc_html($child_name) . '</a></li>';
            }
            echo '</ul></li>';
        } else {
            echo '<li class="menu-item" style="display:list-item;">' . $link . '</li>';
        }
    }
    echo '</ul>';
}

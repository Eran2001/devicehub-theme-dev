<?php
/**
 * DeviceHub — Helpers
 *
 * Reusable utility functions.
 * Rules:
 *  - No output (no echo, no markup) — return values only
 *  - No add_action / add_filter calls — that's hooks.php
 *  - Exception: devhub_render_product_card() outputs markup
 *    because it's a template renderer called from hooks, not a data helper.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

/**
 * Check whether WooCommerce is available before calling its helpers/conditionals.
 */
function devhub_has_woocommerce(): bool
{
    return class_exists('WooCommerce');
}

function devhub_is_shop_page(): bool
{
    return devhub_has_woocommerce() && function_exists('is_shop') && is_shop();
}

function devhub_is_brand_page(): bool
{
    return devhub_has_woocommerce()
        && (is_tax('pwb-brand') || is_tax('product_brand') || is_tax('pa_brand'));
}

function devhub_show_secondary_nav(): bool
{
    return is_front_page()
        || devhub_is_shop_page()
        || devhub_is_product_page()
        || devhub_is_product_category_page()
        || devhub_is_brand_page();
}

function devhub_is_product_category_page(): bool
{
    return devhub_has_woocommerce() && function_exists('is_product_category') && is_product_category();
}

function devhub_is_product_tag_page(): bool
{
    return devhub_has_woocommerce() && function_exists('is_product_tag') && is_product_tag();
}

function devhub_is_product_page(): bool
{
    return devhub_has_woocommerce() && function_exists('is_product') && is_product();
}

function devhub_is_cart_page(): bool
{
    return devhub_has_woocommerce() && function_exists('is_cart') && is_cart();
}

function devhub_is_checkout_page(): bool
{
    return devhub_has_woocommerce() && function_exists('is_checkout') && is_checkout();
}

function devhub_is_account_context(): bool
{
    return devhub_has_woocommerce() && function_exists('is_account_page') && is_account_page();
}

function devhub_has_catalog_data(): bool
{
    return devhub_has_woocommerce() && post_type_exists('product') && taxonomy_exists('product_cat') && function_exists('wc_get_product');
}

function devhub_get_product_category_display_name($category): string
{
    if ($category instanceof WP_Term) {
        $slug = $category->slug;
        $name = $category->name;
    } elseif (is_string($category)) {
        $slug = sanitize_title($category);
        $name = $category;
    } else {
        return '';
    }

    if ($slug === 'uncategorized') {
        return 'Featured Products';
    }

    return (string) $name;
}


// ── Product helpers ───────────────────────────────────────────────────────────

/**
 * Get the discount percentage between regular and sale price.
 * Returns 0 if the product is not on sale or regular price is 0.
 */
function devhub_get_discount_percent(WC_Product $product): int
{
    if (!$product->is_on_sale())
        return 0;

    $regular = (float) $product->get_regular_price();
    $sale = (float) $product->get_sale_price();

    if ($regular <= 0)
        return 0;

    return (int) round((($regular - $sale) / $regular) * 100);
}

/**
 * Get the lowest price across all products in a WooCommerce category.
 * Used for "From Rs.X" display in category cards.
 *
 * @param int $term_id  product_cat term ID
 * @return float|null   null if no products found
 */
function devhub_get_category_min_price(int $term_id): ?float
{
    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'meta_value_num',
        'order' => 'ASC',
        'meta_key' => '_price',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id,
            ]
        ],
        'fields' => 'ids',
    ]);

    if (empty($query->posts))
        return null;

    $product = wc_get_product($query->posts[0]);
    return $product ? (float) $product->get_price() : null;
}

/**
 * Get brand slugs for a product as a space-separated string.
 * Used for JS-based brand filtering via data-brands attribute.
 */
function devhub_get_product_brand_slugs(int $product_id): string
{
    $slugs = [];

    foreach (['product_brand', 'pwb-brand', 'pa_brand'] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'slugs']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $slugs = array_merge($slugs, $terms);
        }
    }

    $slugs = array_values(array_unique(array_filter($slugs)));
    return !empty($slugs) ? implode(' ', $slugs) : '';
}

/**
 * Check whether a product has a bundle package configured.
 */
function devhub_product_has_bundle(int $product_id): bool
{
    if (devhub_has_bundle_package_plugin()) {
        $bundle_context = devhub_get_product_bundle_context($product_id);
        return !empty($bundle_context['packages']);
    }

    return !empty(get_post_meta($product_id, 'devhub_bundles', true));
}

/**
 * Local preview fallback so bundle badges are visible without the bundle API.
 */
/**
 * Check whether the synced bundle-package plugin is available.
 */
function devhub_has_bundle_package_plugin(): bool
{
    return class_exists('DeviceHub\\BundlePackage\\Infrastructure\\Database\\PackageRepository')
        && class_exists('DeviceHub\\BundlePackage\\Woo\\PackageMetaKeys');
}

/**
 * Return plugin-backed bundle-package data for a WooCommerce product.
 *
 * @return array{
 *   enabled: bool,
 *   required: bool,
 *   allow_opt_out: bool,
 *   clearable: bool,
 *   ui_label: string,
 *   help_text: string,
 *   input_name: string,
 *   default_id: int,
 *   packages: array<int, array{
 *     id: int,
 *     name: string,
 *     description: string,
 *     package_code: string,
 *     price_display: string,
 *     billing_label: string,
 *     currency: string,
 *     is_default: bool
 *   }>
 * }
 */
function devhub_get_product_bundle_context(int $product_id): array
{
    $context = [
        'enabled' => false,
        'required' => false,
        'allow_opt_out' => false,
        'clearable' => false,
        'ui_label' => '',
        'help_text' => '',
        'input_name' => 'devicehub_package_id',
        'default_id' => 0,
        'packages' => [],
    ];

    if (!devhub_has_bundle_package_plugin()) {
        return $context;
    }

    $meta_keys_class = 'DeviceHub\\BundlePackage\\Woo\\PackageMetaKeys';
    $repository_class = 'DeviceHub\\BundlePackage\\Infrastructure\\Database\\PackageRepository';

    $enabled_key = constant($meta_keys_class . '::PRODUCT_ENABLED');
    $required_key = constant($meta_keys_class . '::PRODUCT_BUNDLE_REQUIRED');
    $allow_opt_out_key = constant($meta_keys_class . '::PRODUCT_ALLOW_OPT_OUT');
    $ui_label_key = constant($meta_keys_class . '::PRODUCT_UI_LABEL');
    $help_text_key = constant($meta_keys_class . '::PRODUCT_HELP_TEXT');
    $default_package_key = constant($meta_keys_class . '::PRODUCT_DEFAULT_PACKAGE');
    $cart_package_key = constant($meta_keys_class . '::CART_PACKAGE_ID');

    if ('1' !== (string) get_post_meta($product_id, $enabled_key, true)) {
        return $context;
    }

    $context['enabled'] = true;
    $context['required'] = '1' === (string) get_post_meta($product_id, $required_key, true);
    $context['allow_opt_out'] = '1' === (string) get_post_meta($product_id, $allow_opt_out_key, true);
    $context['ui_label'] = (string) get_post_meta($product_id, $ui_label_key, true);
    $context['help_text'] = (string) get_post_meta($product_id, $help_text_key, true);
    $context['input_name'] = (string) $cart_package_key;

    $repository = new $repository_class();
    $records = array_values(array_filter(
        $repository->getPackagesForProduct($product_id),
        static fn($record) => !empty($record->isActive)
    ));

    if (empty($records)) {
        return $context;
    }

    $configured_default = (int) get_post_meta($product_id, $default_package_key, true);
    $active_ids = array_map(static fn($record) => (int) $record->id, $records);

    if ($configured_default > 0 && in_array($configured_default, $active_ids, true)) {
        $context['default_id'] = $configured_default;
    } elseif ($context['required']) {
        $context['default_id'] = (int) $records[0]->id;
    }

    $context['clearable'] = !$context['required']
        && ($context['allow_opt_out'] || 0 === $context['default_id']);

    foreach ($records as $record) {
        $price_display = __('Included', 'devicehub-theme');
        if (null !== $record->priceAmount) {
            $price_display = number_format((float) $record->priceAmount, 2);
            if (!empty($record->currency)) {
                $price_display .= ' ' . $record->currency;
            }
        }

        $context['packages'][] = [
            'id' => (int) $record->id,
            'name' => (string) $record->getDisplayName(),
            'description' => trim((string) ($record->description ?? '')),
            'package_code' => (string) ($record->packageCode ?? ''),
            'price_display' => $price_display,
            'billing_label' => trim((string) ($record->billingLabel ?? '')),
            'currency' => (string) ($record->currency ?? ''),
            'is_default' => (int) $record->id === $context['default_id'],
        ];
    }

    return $context;
}


/**
 * Return the local placeholder SVG URL for a product.
 * Broadband-category products get the router image; everything else gets the phone image.
 */
function devhub_get_product_placeholder_img(WC_Product $product): string
{
    if (has_term('broadband', 'product_cat', $product->get_id())) {
        return DEVHUB_URI . '/assets/images/Original-Router-Img.svg';
    }
    return DEVHUB_URI . '/assets/images/Original-Img.svg';
}

/**
 * Build normalized gallery image data for custom single-product galleries.
 */
function devhub_build_gallery_image_data(int $attachment_id, string $fallback_alt = '', string $placeholder_img = ''): ?array
{
    if ($attachment_id <= 0) {
        return null;
    }

    $full_src = wp_get_attachment_image_url($attachment_id, 'full') ?: $placeholder_img;

    $main_src = wp_get_attachment_image_url($attachment_id, 'woocommerce_single')
        ?: $full_src;

    $thumb_src = wp_get_attachment_image_url($attachment_id, 'woocommerce_thumbnail')
        ?: $main_src;

    if (!$main_src || !$thumb_src) {
        return null;
    }

    $alt = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
    if ($alt === '') {
        $alt = trim((string) get_the_title($attachment_id));
    }
    if ($alt === '') {
        $alt = $fallback_alt;
    }

    return [
        'id' => $attachment_id,
        'main_src' => $main_src,
        'full_src' => $full_src,
        'thumb_src' => $thumb_src,
        'alt' => $alt,
    ];
}

/**
 * Return the parent product gallery for the custom single-product template.
 */
function devhub_get_product_gallery_data(WC_Product $product, string $placeholder_img): array
{
    $image_ids = array_values(array_unique(array_filter(array_merge(
        [(int) $product->get_image_id()],
        array_map('intval', $product->get_gallery_image_ids())
    ))));

    $gallery = [];
    foreach ($image_ids as $image_id) {
        $image = devhub_build_gallery_image_data($image_id, $product->get_name(), $placeholder_img);
        if ($image) {
            $gallery[] = $image;
        }
    }

    if (!empty($gallery)) {
        return $gallery;
    }

    return [[
        'id' => 0,
        'main_src' => $placeholder_img,
        'thumb_src' => $placeholder_img,
        'alt' => $product->get_name(),
    ]];
}

/**
 * Normalize Woo/variation-gallery plugin image payloads for the custom gallery.
 */
function devhub_get_variation_gallery_data(array $variation, string $fallback_alt, string $placeholder_img): array
{
    $gallery = [];
    $seen = [];

    if (!empty($variation['variation_gallery_images']) && is_array($variation['variation_gallery_images'])) {
        foreach ($variation['variation_gallery_images'] as $image) {
            if (!is_array($image)) {
                continue;
            }

            $id = isset($image['image_id']) ? (int) $image['image_id'] : 0;
            $main_src = (string) ($image['full_src'] ?? $image['src'] ?? $placeholder_img);
            $thumb_src = (string) ($image['gallery_thumbnail_src'] ?? $image['src'] ?? $main_src);
            $alt = trim((string) ($image['alt'] ?? $image['title'] ?? $fallback_alt));
            $key = $id > 0 ? 'id:' . $id : 'src:' . $main_src;

            if ($main_src === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $gallery[] = [
                'id' => $id,
                'main_src' => $main_src,
                'thumb_src' => $thumb_src ?: $main_src,
                'alt' => $alt,
            ];
        }
    }

    if (!empty($gallery)) {
        return $gallery;
    }

    if (!empty($variation['image']) && is_array($variation['image'])) {
        $image = $variation['image'];
        $main_src = (string) ($image['full_src'] ?? $image['src'] ?? $placeholder_img);
        if ($main_src !== '') {
            return [[
                'id' => isset($image['id']) ? (int) $image['id'] : 0,
                'main_src' => $main_src,
                'thumb_src' => (string) ($image['gallery_thumbnail_src'] ?? $image['thumb_src'] ?? $image['src'] ?? $main_src),
                'alt' => trim((string) ($image['alt'] ?? $image['title'] ?? $fallback_alt)),
            ]];
        }
    }

    return [];
}

/**
 * Normalize a raw hex color string from term meta or term description.
 */
function devhub_normalize_hex_color(string $value, string $fallback = '#cccccc'): string
{
    $value = trim($value);

    if ($value === '') {
        return $fallback;
    }

    $with_hash = sanitize_hex_color($value);
    if ($with_hash) {
        return $with_hash;
    }

    $no_hash = sanitize_hex_color_no_hash($value);
    if ($no_hash) {
        return '#' . $no_hash;
    }

    return $fallback;
}

/**
 * Extract the first hex color value from a product color term description.
 */
function devhub_get_hex_color_from_description(string $description): string
{
    $description = wp_strip_all_tags($description);

    if (
        preg_match('/#(?:[0-9a-fA-F]{3}){1,2}\b/', $description, $matches)
        || preg_match('/\b[0-9a-fA-F]{6}\b/', $description, $matches)
        || preg_match('/\b[0-9a-fA-F]{3}\b/', $description, $matches)
    ) {
        return devhub_normalize_hex_color($matches[0], '');
    }

    return '';
}

/**
 * Fallback resolver for product color swatches when term meta is missing.
 *
 * Uses known color words first, then generates a stable color from the term
 * slug/name so newly-added marketing colors still get distinct swatches.
 */
function devhub_guess_color_hex(string $slug, string $name, string $fallback = '#cccccc'): string
{
    $normalize = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    };

    $generate_hex = static function (string $seed) use ($fallback): string {
        $seed = trim($seed);
        if ($seed === '') {
            return $fallback;
        }

        $hash = sprintf('%u', crc32($seed));
        $hue = (int) $hash % 360;
        $saturation = 58 + (int) ($hash % 12);
        $lightness = 46 + (int) (($hash >> 4) % 12);

        $s = $saturation / 100;
        $l = $lightness / 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($hue / 60, 2) - 1));
        $m = $l - ($c / 2);

        if ($hue < 60) {
            [$r, $g, $b] = [$c, $x, 0];
        } elseif ($hue < 120) {
            [$r, $g, $b] = [$x, $c, 0];
        } elseif ($hue < 180) {
            [$r, $g, $b] = [0, $c, $x];
        } elseif ($hue < 240) {
            [$r, $g, $b] = [0, $x, $c];
        } elseif ($hue < 300) {
            [$r, $g, $b] = [$x, 0, $c];
        } else {
            [$r, $g, $b] = [$c, 0, $x];
        }

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255)
        );
    };

    $exact_map = [
        'black' => '#2b2b2b',
        'white' => '#f5f5f5',
        'silver' => '#c0c0c0',
        'gray' => '#9aa0a6',
        'grey' => '#9aa0a6',
        'space gray' => '#6e6e73',
        'space grey' => '#6e6e73',
        'graphite' => '#54565a',
        'midnight' => '#2f3640',
        'starlight' => '#f8e7c9',
        'blue' => '#5b8def',
        'sky blue' => '#87ceeb',
        'navy' => '#274690',
        'green' => '#6ea77a',
        'mint' => '#a8d5ba',
        'pink' => '#f4b6c2',
        'purple' => '#8e6ccf',
        'violet' => '#7d5ba6',
        'cobalt violet' => '#6f63b8',
        'gold' => '#d4af37',
        'rose gold' => '#b76e79',
        'orange' => '#ff8a3d',
        'red' => '#d64545',
        'yellow' => '#f2c94c',
        'coral' => '#ff7f50',
        'lavender' => '#b8a9e0',
        'titanium' => '#8f8f8c',
        'natural titanium' => '#b8b3a8',
        'blue titanium' => '#6c7a89',
        'white titanium' => '#e5e4e2',
        'black titanium' => '#3c3c3d',
        'desert titanium' => '#b78b6a',
    ];

    $candidates = array_values(array_unique(array_filter([
        $normalize($slug),
        $normalize($name),
    ])));

    foreach ($candidates as $candidate) {
        if (isset($exact_map[$candidate])) {
            return $exact_map[$candidate];
        }
    }

    foreach ($candidates as $candidate) {
        if (str_contains($candidate, 'rose gold')) {
            return $exact_map['rose gold'];
        }
        if (str_contains($candidate, 'orange')) {
            return $exact_map['orange'];
        }
        if (str_contains($candidate, 'natural titanium')) {
            return $exact_map['natural titanium'];
        }
        if (str_contains($candidate, 'blue titanium')) {
            return $exact_map['blue titanium'];
        }
        if (str_contains($candidate, 'white titanium')) {
            return $exact_map['white titanium'];
        }
        if (str_contains($candidate, 'black titanium')) {
            return $exact_map['black titanium'];
        }
        if (str_contains($candidate, 'desert titanium')) {
            return $exact_map['desert titanium'];
        }
        if (str_contains($candidate, 'space gray') || str_contains($candidate, 'space grey')) {
            return $exact_map['space gray'];
        }
        if (str_contains($candidate, 'cobalt violet')) {
            return $exact_map['cobalt violet'];
        }
        if (str_contains($candidate, 'sky blue')) {
            return $exact_map['sky blue'];
        }
        if (str_contains($candidate, 'graphite')) {
            return $exact_map['graphite'];
        }
        if (str_contains($candidate, 'midnight')) {
            return $exact_map['midnight'];
        }
        if (str_contains($candidate, 'starlight')) {
            return $exact_map['starlight'];
        }
        if (str_contains($candidate, 'black')) {
            return $exact_map['black'];
        }
        if (str_contains($candidate, 'white')) {
            return $exact_map['white'];
        }
        if (str_contains($candidate, 'silver')) {
            return $exact_map['silver'];
        }
        if (str_contains($candidate, 'gray') || str_contains($candidate, 'grey')) {
            return $exact_map['gray'];
        }
        if (str_contains($candidate, 'blue')) {
            return $exact_map['blue'];
        }
        if (str_contains($candidate, 'green')) {
            return $exact_map['green'];
        }
        if (str_contains($candidate, 'mint')) {
            return $exact_map['mint'];
        }
        if (str_contains($candidate, 'pink')) {
            return $exact_map['pink'];
        }
        if (str_contains($candidate, 'purple')) {
            return $exact_map['purple'];
        }
        if (str_contains($candidate, 'violet')) {
            return $exact_map['violet'];
        }
        if (str_contains($candidate, 'gold')) {
            return $exact_map['gold'];
        }
        if (str_contains($candidate, 'red')) {
            return $exact_map['red'];
        }
        if (str_contains($candidate, 'yellow')) {
            return $exact_map['yellow'];
        }
        if (str_contains($candidate, 'coral')) {
            return $exact_map['coral'];
        }
        if (str_contains($candidate, 'lavender')) {
            return $exact_map['lavender'];
        }
        if (str_contains($candidate, 'titanium')) {
            return $exact_map['titanium'];
        }
    }

    return $generate_hex($candidates[0] ?? $name);
}

/**
 * Resolve Woo product color terms into swatch-ready UI data.
 *
 * Reads the real color value from term meta saved by Woo Variation Swatches,
 * then falls back to the first hex value found in the term description.
 */
function devhub_get_product_color_options(WC_Product $product): array
{
    $attributes = $product->get_attributes();

    if (
        !$product->is_type('variable')
        || !taxonomy_exists('pa_color')
        || !isset($attributes['pa_color'])
    ) {
        return [];
    }

    $variation_attributes = $product->get_variation_attributes();
    $color_slugs = $variation_attributes['pa_color'] ?? [];
    if (empty($color_slugs) || is_wp_error($color_slugs)) {
        return [];
    }

    $colors = [];
    foreach ($color_slugs as $slug) {
        $term = get_term_by('slug', $slug, 'pa_color');
        if (!$term instanceof WP_Term) {
            continue;
        }

        $raw_hex = (string) get_term_meta($term->term_id, 'product_attribute_color', true);
        $description_hex = devhub_get_hex_color_from_description((string) $term->description);

        if (trim($raw_hex) !== '') {
            $hex = devhub_normalize_hex_color($raw_hex);
        } elseif ($description_hex !== '') {
            $hex = $description_hex;
        } else {
            $hex = devhub_guess_color_hex($slug, $term->name);
        }

        $colors[] = [
            'slug' => $slug,
            'name' => $term->name,
            'hex' => $hex,
        ];
    }

    return $colors;
}


// ── Template renderer ─────────────────────────────────────────────────────────

/**
 * Format product-card prices so variable products don't show awkward min-max ranges.
 *
 * Cards look cleaner with a single starting price than a wrapped range.
 */
function devhub_get_product_card_price_html(WC_Product $product): string
{
    if (!$product->is_type('variable')) {
        return (string) $product->get_price_html();
    }

    $min_price   = (float) $product->get_variation_price('min', true);
    $min_regular = (float) $product->get_variation_regular_price('min', true);

    if ($min_price <= 0) {
        return (string) $product->get_price_html();
    }

    if ($min_regular > $min_price) {
        return '<ins>' . wc_price($min_price) . '</ins> <del>' . wc_price($min_regular) . '</del>';
    }

    return wc_price($min_price);
}

/**
 * Render a single product card.
 *
 * This is the one shared card template used on:
 *  - Home products section
 *  - Archive / shop grid
 *  - Search results
 *
 * Why here and not template-parts/?
 * Because it's called programmatically from within WP_Query loops
 * in hook files. get_template_part() doesn't accept arguments cleanly
 * without globals or set_query_var — passing a $product object directly
 * is simpler and explicit.
 *
 * @param WC_Product $product
 */
function devhub_render_product_card(WC_Product $product, string $img_override = ''): void
{
    $img_url = $img_override !== '' ? $img_override : wp_get_attachment_image_url($product->get_image_id(), 'devhub-card');
    $discount = devhub_get_discount_percent($product);
    $pricing_offer = function_exists('devhub_get_product_pricing_offer_data')
        ? devhub_get_product_pricing_offer_data($product)
        : [];
    $in_stock = $product->is_in_stock();
    $has_bundle = devhub_product_has_bundle($product->get_id());
    $show_bundle_badge = $has_bundle;
    $brand_slugs = devhub_get_product_brand_slugs($product->get_id());
    $permalink = $product->get_permalink();
    $name = $product->get_name();
    $card_action_url = $permalink;
    $card_action_label = __('View Product', 'devicehub-theme');
    $card_action_disabled = !$in_stock;

    if ($card_action_disabled) {
        $card_action_label = __('Out of Stock', 'devicehub-theme');
    } elseif ($product->is_purchasable()) {
        $card_action_label = __('Buy Now', 'devicehub-theme');
    }
    ?>
    <div class="devhub-product-card" data-brands="<?php echo esc_attr($brand_slugs); ?>">

        <?php if ($discount > 0): ?>
            <span class="devhub-product-card__badge" aria-label="<?php echo esc_attr($discount); ?> percent off">
                OFF
                <?php echo esc_html($discount); ?>%
            </span>
        <?php endif; ?>

        <span class="devhub-product-card__stock devhub-product-card__stock--<?php echo $in_stock ? 'in' : 'out'; ?>">
            <?php echo $in_stock ? esc_html__('In stock', 'devicehub-theme') : esc_html__('Out of stock', 'devicehub-theme'); ?>
        </span>

        <a href="<?php echo esc_url($permalink); ?>" class="devhub-product-card__img-wrap">
            <?php if ($img_url): ?>
                <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy">
            <?php else: ?>
                <div class="devhub-product-card__img-placeholder" aria-hidden="true"></div>
            <?php endif; ?>

            <?php if (!empty($pricing_offer['badge_value'])): ?>
                <span class="devhub-product-card__offer-badge" aria-label="<?php echo esc_attr($pricing_offer['badge_value']); ?>">
                    <span class="devhub-product-card__offer-value"><?php echo esc_html($pricing_offer['badge_value']); ?></span>
                    <span class="devhub-product-card__offer-caption"><?php echo esc_html($pricing_offer['badge_caption'] ?? __('Special Offer', 'devicehub-theme')); ?></span>
                </span>
            <?php endif; ?>
        </a>

        <div class="devhub-product-card__body">
            <a href="<?php echo esc_url($permalink); ?>" class="devhub-product-card__name"
                title="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($name); ?>
            </a>

            <div class="devhub-product-card__price">
                <?php echo wp_kses_post(devhub_get_product_card_price_html($product)); ?>
            </div>

            <?php if ($show_bundle_badge): ?>
                <span class="devhub-product-card__bundle">
                    <?php esc_html_e('Bundle Offer', 'devicehub-theme'); ?>
                </span>
            <?php endif; ?>

            <?php if ($card_action_disabled): ?>
                <span class="devhub-product-card__action devhub-product-card__action--disabled">
                    <?php echo esc_html($card_action_label); ?>
                </span>
            <?php else: ?>
                <a href="<?php echo esc_url($card_action_url); ?>" class="devhub-product-card__action">
                    <?php echo esc_html($card_action_label); ?>
                </a>
            <?php endif; ?>
        </div>

    </div>
    <?php
}

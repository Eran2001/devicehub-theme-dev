<?php
/**
 * Single Product — DeviceHub override
 *
 * WooCommerce loads this via wc_get_template_part('content', 'single-product').
 * locate_template() finds it at {child-theme}/woocommerce/content-single-product.php.
 *
 * Custom full-width layout: gallery left, info right, tabs below.
 * Image: always placeholder SVG (build phase).
 * Variations: pa_color (swatches) + pa_storage (pill buttons).
 * Bundles: loaded from the DeviceHub bundle-package plugin when available.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_single_product');

global $product;
$product = wc_get_product(get_the_ID());
if (!$product)
    return;

$stock_state = devhub_get_product_stock_state($product);
$stock_text = devhub_get_product_stock_text($product);

// ── 1. Data ───────────────────────────────────────────────────────────────────

$is_variable = $product->is_type('variable');
$attributes = $product->get_attributes();
$variation_attributes = $is_variable ? $product->get_variation_attributes() : [];
$storage_slugs = $variation_attributes['pa_storage'] ?? [];

$colors = devhub_get_product_color_options($product);

$storages = [];
foreach ($storage_slugs as $slug) {
    $term = get_term_by('slug', $slug, 'pa_storage');
    if (!$term)
        continue;
    $storages[] = ['slug' => $slug, 'name' => $term->name];
}

$storage_capacity_rank = static function ($storage) {
    $label = strtolower((string) ($storage['name'] ?? $storage['slug'] ?? ''));

    if (preg_match('/([\d.]+)\s*(tb|gb|mb)/i', $label, $matches)) {
        $multipliers = [
            'mb' => 1,
            'gb' => 1024,
            'tb' => 1024 * 1024,
        ];

        return (float) $matches[1] * ($multipliers[strtolower($matches[2])] ?? 1);
    }

    if (preg_match('/\d+/', $label, $matches)) {
        return (float) $matches[0];
    }

    return INF;
};

usort($storages, static function ($a, $b) use ($storage_capacity_rank) {
    $rank_a = $storage_capacity_rank($a);
    $rank_b = $storage_capacity_rank($b);

    if ($rank_a == $rank_b) {
        return strnatcasecmp($a['name'], $b['name']);
    }

    return $rank_a <=> $rank_b;
});

// All available variations serialised for JS resolution
$available_variations = '[]';
if ($is_variable) {
    $raw = array_map(function ($v) use ($product) {
        $variation_product = wc_get_product($v['variation_id']);
        $native_current_price = 0.0;
        $native_original_price = 0.0;

        if ($variation_product instanceof WC_Product) {
            $regular_price = (float) $variation_product->get_regular_price();
            $sale_price = (float) $variation_product->get_sale_price();
            $native_current_price = $variation_product->is_on_sale() && $sale_price > 0
                ? $sale_price
                : ($regular_price > 0 ? $regular_price : (float) $variation_product->get_price());
            $native_original_price = $regular_price > $native_current_price ? $regular_price : 0.0;
        }

        return [
            'id' => $v['variation_id'],
            'attributes' => $v['attributes'],
            'price' => $v['display_price'],
            'price_html' => $v['price_html'] ?? wc_price((float) $v['display_price']),
            'native_current_price' => $native_current_price,
            'native_original_price' => $native_original_price,
            'in_stock' => $v['is_in_stock'],
            'stock_state' => $variation_product instanceof WC_Product ? devhub_get_product_stock_state($variation_product) : ($v['is_in_stock'] ? 'in' : 'out'),
            'stock_text' => $variation_product instanceof WC_Product ? devhub_get_product_stock_text($variation_product) : ($v['is_in_stock'] ? __('In stock', 'devicehub-theme') : __('Out of stock', 'devicehub-theme')),
            'gallery_images' => devhub_get_variation_gallery_data($v, $product->get_name(), ''),
        ];
    }, $product->get_available_variations());
    $available_variations = wp_json_encode($raw);
}

$pricing_offer_candidates = function_exists('devhub_get_product_pricing_offer_candidates')
    ? devhub_get_product_pricing_offer_candidates($product)
    : [];
$active_pricing_offer = function_exists('devhub_get_product_pricing_offer_data')
    ? devhub_get_product_pricing_offer_data($product, 1)
    : [];

$base_current_price = 0.0;
$base_original_price = 0.0;

if (!$is_variable) {
    $base_regular_price = (float) $product->get_regular_price();
    $base_sale_price = (float) $product->get_sale_price();
    $base_current_price = $product->is_on_sale() && $base_sale_price > 0
        ? $base_sale_price
        : ($base_regular_price > 0 ? $base_regular_price : (float) $product->get_price());
    $base_original_price = $base_regular_price > $base_current_price ? $base_regular_price : 0.0;
}

$bundle_context = devhub_get_product_bundle_context($product->get_id());
$bundles = $bundle_context['packages'];
$bundle_required = $bundle_context['required'];
$bundle_clearable = $bundle_context['clearable'];
$bundle_input_name = $bundle_context['input_name'];
$bundle_default_id = $bundle_context['default_id'];
$bundle_ui_label = $bundle_context['ui_label'];
$bundle_help_text = $bundle_context['help_text'];

// Quick stats — pull from available product attributes
$quick_stats_config = [
    'pa_screen-diagonal' => ['label' => 'Screen size', 'icon' => 'fas fa-mobile-alt'],
    'pa_battery-capacity' => ['label' => 'Battery capacity', 'icon' => 'fas fa-battery-full'],
    'pa_built-in-memory' => ['label' => 'Built-in Memory', 'icon' => 'fas fa-memory'],
    'pa_brand' => ['label' => 'Brand', 'icon' => 'fas fa-tag'],
];

$quick_stats = [];
foreach ($quick_stats_config as $attr_key => $config) {
    if (!isset($attributes[$attr_key]))
        continue;
    $terms = $attributes[$attr_key]->get_terms();
    if (empty($terms))
        continue;
    $quick_stats[] = array_merge($config, [
        'value' => implode(', ', array_map(fn($t) => $t->name, $terms)),
    ]);
}

// Full specs table — attributes only under General (other groups come later)
$specs = [];
foreach ($attributes as $attr_key => $attribute) {
    $terms = $attribute->get_terms();
    if (empty($terms))
        continue;
    $attr_id = wc_attribute_taxonomy_id_by_name($attr_key);
    $attr_obj = $attr_id ? wc_get_attribute($attr_id) : null;
    $label = $attr_obj
        ? $attr_obj->name
        : ucwords(str_replace(['pa_', '-'], ['', ' '], $attr_key));
    $specs[] = [
        'label' => $label,
        'value' => implode(', ', array_map(fn($t) => $t->name, $terms)),
    ];
}

// Physical specs — weight and dimensions from the Shipping tab.
// For variable products, parent stores no shipping data — pull from first available variation.
$physical_specs = [];
$phys_source = $product;
if ($is_variable) {
    $variation_ids = $product->get_children();
    foreach ($variation_ids as $vid) {
        $v = wc_get_product($vid);
        if ($v && ($v->get_weight() !== '' || $v->get_length() !== '')) {
            $phys_source = $v;
            break;
        }
    }
}
$weight = $phys_source->get_weight();
if ($weight !== '' && $weight !== null && $weight !== false) {
    $physical_specs[] = [
        'label' => __('Weight', 'devicehub-theme'),
        'value' => $weight . ' ' . get_option('woocommerce_weight_unit', 'kg'),
    ];
}
$dim_length = $phys_source->get_length();
$dim_width  = $phys_source->get_width();
$dim_height = $phys_source->get_height();
$dim_parts  = array_filter([$dim_length, $dim_width, $dim_height], fn($v) => $v !== '' && $v !== null && $v !== false);
if (!empty($dim_parts)) {
    $physical_specs[] = [
        'label' => __('Dimensions (L×W×H)', 'devicehub-theme'),
        'value' => implode(' × ', $dim_parts) . ' ' . get_option('woocommerce_dimension_unit', 'cm'),
    ];
}

$placeholder_img = DEVHUB_URI . '/assets/images/Original-Img.svg';
$default_gallery = devhub_get_product_gallery_data($product, $placeholder_img);
$main_img        = $default_gallery[0]['main_src'];
$payment_methods = function_exists('devhub_get_payment_method_display_data') ? devhub_get_payment_method_display_data() : [];

$has_feature_content = static function (string $html): bool {
    return trim(wp_strip_all_tags($html)) !== '';
};

$format_feature_content = static function (string $html): string {
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (function_exists('do_blocks')) {
        $html = do_blocks($html);
    }

    $html = wpautop($html);
    $html = shortcode_unautop($html);
    $html = do_shortcode($html);

    return wp_kses_post($html);
};

$features_sections = [];
$description_html = (string) $product->get_description();
$terms_html = (string) get_post_meta($product->get_id(), 'dh_terms', true);
$warranty_html = (string) get_post_meta($product->get_id(), 'dh_warranty', true);
$returns_html = (string) get_post_meta($product->get_id(), 'dh_returns', true);

if ($has_feature_content($description_html)) {
    $features_sections[] = [
        'title' => __('Description', 'devicehub-theme'),
        'content' => $format_feature_content($description_html),
    ];
}

if ($has_feature_content($terms_html)) {
    $features_sections[] = [
        'title' => __('Terms & Conditions', 'devicehub-theme'),
        'content' => $format_feature_content($terms_html),
    ];
}

if ($has_feature_content($warranty_html)) {
    $features_sections[] = [
        'title' => __('Warranty Information', 'devicehub-theme'),
        'content' => $format_feature_content($warranty_html),
    ];
}

if ($has_feature_content($returns_html)) {
    $features_sections[] = [
        'title' => __('Return Policy / Support / Service Info', 'devicehub-theme'),
        'content' => $format_feature_content($returns_html),
    ];
}

$has_features_tab = !empty($features_sections);
$has_specs_tab = !empty($quick_stats) || !empty($specs) || !empty($physical_specs);
$has_reviews_tab = comments_open();
$review_count = $has_reviews_tab ? $product->get_review_count() : 0;
$features_is_active = $has_features_tab;
$specs_is_active = !$has_features_tab && $has_specs_tab;
$reviews_is_active = !$has_features_tab && !$has_specs_tab && $has_reviews_tab;
// ── 2. Markup ─────────────────────────────────────────────────────────────────
?>

<div class="devhub-single"
    data-variations="<?php echo esc_attr($available_variations); ?>"
    data-default-gallery="<?php echo esc_attr(wp_json_encode($default_gallery)); ?>"
    data-pricing-offers="<?php echo esc_attr(wp_json_encode($pricing_offer_candidates)); ?>"
    data-active-pricing-offer="<?php echo esc_attr(wp_json_encode($active_pricing_offer)); ?>"
    data-base-current-price="<?php echo esc_attr((string) $base_current_price); ?>"
    data-base-original-price="<?php echo esc_attr((string) $base_original_price); ?>">
    <div class="wf-container">

        <div class="devhub-page-bar">
            <?php woocommerce_breadcrumb(); ?>
            <?php /* devhub-page-bar__title intentionally hidden for now. */ ?>
        </div>

        <div class="devhub-single__layout">

            <!-- ── Gallery (left) ──────────────────────────────────────────── -->
            <div class="devhub-single__gallery">

                <div class="devhub-single__main-image">
                    <?php if (!empty($active_pricing_offer)): ?>
                        <aside class="devhub-single__offer-badge<?php echo in_array(($active_pricing_offer['type'] ?? ''), ['fixed_cart_amount', 'percent_total_amount'], true) ? ' devhub-single__offer-badge--cart' : ''; ?>" role="status" aria-live="polite">
                            <span class="devhub-single__offer-badge-kicker"><?php esc_html_e("Today's Offer", 'devicehub-theme'); ?></span>
                            <strong class="devhub-single__offer-badge-value"><?php echo esc_html($active_pricing_offer['badge_value']); ?></strong>
                            <span class="devhub-single__offer-badge-caption"><?php echo esc_html($active_pricing_offer['badge_caption']); ?></span>
                        </aside>
                    <?php endif; ?>
                    <img src="<?php echo esc_url($main_img); ?>"
                        alt="<?php echo esc_attr($default_gallery[0]['alt']); ?>"
                        data-full-src="<?php echo esc_url($default_gallery[0]['full_src'] ?? $main_img); ?>"
                        draggable="false">
                </div>

                <div class="devhub-single__thumbnails-slider" id="devhubGallerySlider">
                    <button class="devhub-single__bundle-arrow devhub-single__gallery-arrow devhub-single__gallery-arrow--prev"
                        id="devhubGalleryPrev" type="button" hidden
                        aria-label="<?php esc_attr_e('Previous product images', 'devicehub-theme'); ?>">
                        <i class="fas fa-chevron-up" aria-hidden="true"></i>
                    </button>
                    <div class="devhub-single__thumbnails-viewport" id="devhubGalleryViewport">
                        <div class="devhub-single__thumbnails" id="devhubGalleryTrack">
                            <?php foreach ($default_gallery as $i => $gallery_image): ?>
                                <button class="devhub-single__thumb<?php echo $i === 0 ? ' devhub-single__thumb--active' : ''; ?>"
                                    type="button"
                                    data-main-src="<?php echo esc_url($gallery_image['main_src']); ?>"
                                    data-alt="<?php echo esc_attr($gallery_image['alt']); ?>"
                                    aria-label="<?php echo esc_attr(sprintf(__('View image %d', 'devicehub-theme'), $i + 1)); ?>">
                                    <img src="<?php echo esc_url($gallery_image['thumb_src']); ?>" alt="" draggable="false">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button class="devhub-single__bundle-arrow devhub-single__gallery-arrow devhub-single__gallery-arrow--next"
                        id="devhubGalleryNext" type="button" hidden
                        aria-label="<?php esc_attr_e('Next product images', 'devicehub-theme'); ?>">
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                </div>

            </div>

            <!-- ── Info (right) ────────────────────────────────────────────── -->
            <div class="devhub-single__info">

                <div class="devhub-single__heading-row">
                    <h1 class="devhub-single__title">
                        <?php echo esc_html($product->get_name()); ?>
                    </h1>
                </div>

                <?php
                $is_price_range = $product->is_type('variable')
                    && abs((float) $product->get_variation_price('max', true) - (float) $product->get_variation_price('min', true)) >= 0.01;
                ?>
                <div class="devhub-single__price-row">
                    <div class="devhub-single__price<?php echo $is_price_range ? ' devhub-single__price--range' : ''; ?>">
                        <?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </div>
                    <span
                        class="devhub-single__stock devhub-single__stock--<?php echo esc_attr($stock_state); ?>">
                        <span class="devhub-single__stock-dot" aria-hidden="true"></span>
                        <?php echo esc_html($stock_text); ?>
                    </span>
                </div>

                <?php $short_desc = $product->get_short_description(); if ($short_desc): ?>
                    <div class="devhub-single__short-desc">
                        <?php echo wp_kses_post($short_desc); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($colors)): ?>
                    <div class="devhub-single__option-group">
                        <p class="devhub-single__option-label"><?php esc_html_e('Select color', 'devicehub-theme'); ?></p>
                        <div class="devhub-single__color-swatches" role="group"
                            aria-label="<?php esc_attr_e('Color options', 'devicehub-theme'); ?>">
                            <?php foreach ($colors as $color): ?>
                                <button class="devhub-single__color-swatch" type="button"
                                    data-value="<?php echo esc_attr($color['slug']); ?>"
                                    style="background-color:<?php echo esc_attr($color['hex']); ?>;"
                                    title="<?php echo esc_attr($color['name']); ?>"
                                    aria-label="<?php echo esc_attr($color['name']); ?>">
                                    <i class="fas fa-check" aria-hidden="true"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($storages)): ?>
                    <div class="devhub-single__option-group">
                        <p class="devhub-single__option-label">
                            <?php esc_html_e('Choose your storage', 'devicehub-theme'); ?>
                        </p>
                        <div class="devhub-single__storage-options" role="group"
                            aria-label="<?php esc_attr_e('Storage options', 'devicehub-theme'); ?>">
                            <?php foreach ($storages as $storage): ?>
                                <button class="devhub-single__storage-btn" type="button"
                                    data-value="<?php echo esc_attr($storage['slug']); ?>">
                                    <?php echo esc_html($storage['name']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ── Bundle packages ──────────────────────────────────── -->
                <?php if (!empty($bundles)): ?>
                    <div class="devhub-single__bundles">
                        <p class="devhub-single__option-label">
                            <?php
                            $default_bundle_label = $bundle_required
                                ? __('Bundle Packages', 'devicehub-theme')
                                : __('Optional Bundle Packages', 'devicehub-theme');
                            echo esc_html($bundle_ui_label ?: $default_bundle_label);
                            ?>
                        </p>
                        <?php if ($bundle_help_text !== ''): ?>
                            <p class="devhub-single__bundle-help"><?php echo esc_html($bundle_help_text); ?></p>
                        <?php endif; ?>
                        <div class="devhub-single__bundles-slider"
                            data-bundle-required="<?php echo $bundle_required ? '1' : '0'; ?>"
                            data-bundle-clearable="<?php echo $bundle_clearable ? '1' : '0'; ?>">
                            <button class="devhub-single__bundle-arrow devhub-single__bundle-arrow--prev"
                                id="devhubBundlePrev" type="button" hidden
                                aria-label="<?php esc_attr_e('Previous bundle', 'devicehub-theme'); ?>">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            <div class="devhub-single__bundles-viewport">
                                <div class="devhub-single__bundles-track" id="devhubBundlesTrack">
                                    <?php foreach ($bundles as $bundle): ?>
                                        <div
                                            class="devhub-single__bundle-card<?php echo $bundle['is_default'] ? ' devhub-single__bundle-card--active' : ''; ?>"
                                            data-package-id="<?php echo esc_attr((string) $bundle['id']); ?>">
                                            <div class="devhub-single__bundle-top">
                                                <div class="devhub-single__bundle-icon" aria-hidden="true">
                                                    <i class="fas fa-box-open"></i>
                                                </div>
                                                <span class="devhub-single__bundle-name"
                                                    title="<?php echo esc_attr($bundle['name']); ?>">
                                                    <?php echo esc_html($bundle['name']); ?>
                                                </span>
                                            </div>
                                            <?php if ($bundle['description'] !== ''): ?>
                                                <div class="devhub-single__bundle-meta">
                                                    <p class="devhub-single__bundle-desc"
                                                        title="<?php echo esc_attr($bundle['description']); ?>">
                                                        <?php echo esc_html($bundle['description']); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>
                                            <div class="devhub-single__bundle-footer">
                                                <div class="devhub-single__bundle-plan">
                                                    <?php if ($bundle['billing_label'] !== ''): ?>
                                                        <p class="devhub-single__bundle-plan-label">
                                                            <?php echo esc_html($bundle['billing_label']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <p class="devhub-single__bundle-price">
                                                        <?php echo esc_html($bundle['price_display']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button class="devhub-single__bundle-arrow devhub-single__bundle-arrow--next"
                                id="devhubBundleNext" type="button" hidden
                                aria-label="<?php esc_attr_e('Next bundle', 'devicehub-theme'); ?>">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ── Cart form ────────────────────────────────────────── -->
                <?php do_action('woocommerce_before_add_to_cart_form'); ?>

                <form class="devhub-single__cart-form cart" method="post" enctype="multipart/form-data"
                    action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>">

                    <?php if ($is_variable): ?>
                        <input type="hidden" name="variation_id" id="devhubVariationId" value="">
                        <?php foreach ($variation_attributes as $attr_name => $options): ?>
                            <input type="hidden" name="<?php echo esc_attr('attribute_' . sanitize_title($attr_name)); ?>"
                                id="devhubAttr_<?php echo esc_attr(sanitize_title($attr_name)); ?>" value="">
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($bundles)): ?>
                        <input type="hidden" name="<?php echo esc_attr($bundle_input_name); ?>"
                            id="devhubBundlePackageId" value="<?php echo esc_attr((string) $bundle_default_id); ?>">
                    <?php endif; ?>
                    <div class="devhub-single__pricing-table-slot">
                        <?php do_action('woocommerce_before_add_to_cart_button'); ?>
                    </div>

                    <div class="devhub-single__safe-checkout">
                        <p class="devhub-single__safe-checkout-label">
                            <i class="fas fa-shield-alt" aria-hidden="true"></i>
                            <?php esc_html_e('Guaranteed safe Checkout', 'devicehub-theme'); ?>
                        </p>
                        <div class="devhub-single__payment-slider devhub-single__safe-payment-slider">
                            <button class="devhub-single__bundle-arrow devhub-single__payment-arrow devhub-single__payment-arrow--prev"
                                id="devhubPaymentPrev" type="button" aria-label="<?php esc_attr_e('Previous payment option', 'devicehub-theme'); ?>" hidden>
                                <i class="fas fa-chevron-left" aria-hidden="true"></i>
                            </button>
                            <div class="devhub-single__payment-viewport" id="devhubPaymentViewport">
                                <div class="devhub-single__safe-payment-grid">
                                    <img src="<?php echo esc_url(DEVHUB_URI . '/assets/images/cash-on-delivery.jpg'); ?>"
                                        alt="<?php esc_attr_e('Cash on delivery', 'devicehub-theme'); ?>" loading="lazy">
                                    <img src="<?php echo esc_url(DEVHUB_URI . '/assets/images/visa-master-new.png'); ?>"
                                        alt="<?php esc_attr_e('Visa and Mastercard', 'devicehub-theme'); ?>" loading="lazy">
                                    <img src="<?php echo esc_url(DEVHUB_URI . '/assets/images/koko.svg'); ?>"
                                        alt="<?php esc_attr_e('KOKO payment option', 'devicehub-theme'); ?>" loading="lazy">
                                    <img src="<?php echo esc_url(DEVHUB_URI . '/assets/images/webx.svg'); ?>"
                                        alt="<?php esc_attr_e('WebX payment option', 'devicehub-theme'); ?>" loading="lazy">
                                </div>
                            </div>
                            <button class="devhub-single__bundle-arrow devhub-single__payment-arrow devhub-single__payment-arrow--next"
                                id="devhubPaymentNext" type="button" aria-label="<?php esc_attr_e('Next payment option', 'devicehub-theme'); ?>" hidden>
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div class="devhub-single__actions">
                        <div class="devhub-single__quantity" data-devhub-quantity>
                            <button type="button" class="devhub-single__qty-btn" data-devhub-qty-minus
                                aria-label="<?php esc_attr_e('Decrease quantity', 'devicehub-theme'); ?>">-</button>
                            <input type="number" name="quantity" class="devhub-single__qty-input" value="1" min="1"
                                <?php echo $product->get_max_purchase_quantity() > 0 ? 'max="' . esc_attr((string) $product->get_max_purchase_quantity()) . '"' : ''; ?>
                                inputmode="numeric" aria-label="<?php esc_attr_e('Product quantity', 'devicehub-theme'); ?>">
                            <button type="button" class="devhub-single__qty-btn" data-devhub-qty-plus
                                aria-label="<?php esc_attr_e('Increase quantity', 'devicehub-theme'); ?>">+</button>
                        </div>
                        <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>"
                            class="devhub-single__btn devhub-single__btn--cart"
                            <?php disabled(!$product->is_in_stock()); ?>>
                            <?php esc_html_e('Add to Cart', 'devicehub-theme'); ?>
                        </button>
                        <button type="button" class="devhub-single__btn devhub-single__btn--buy"
                            <?php disabled(!$product->is_in_stock()); ?>>
                            <?php esc_html_e('Buy Now', 'devicehub-theme'); ?>
                        </button>
                    </div>

                    <?php do_action('woocommerce_after_add_to_cart_button'); ?>

                </form>

                <?php do_action('woocommerce_after_add_to_cart_form'); ?>

            </div><!-- /.devhub-single__info -->

        </div><!-- /.devhub-single__layout -->

        <!-- ── Tabs ──────────────────────────────────────────────────────── -->
        <?php if ($has_features_tab || $has_specs_tab || $has_reviews_tab): ?>
            <div class="devhub-single__tabs">

                <div class="devhub-single__tab-nav" role="tablist">
                    <?php if ($has_features_tab): ?>
                        <button class="devhub-single__tab-btn<?php echo $features_is_active ? ' devhub-single__tab-btn--active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $features_is_active ? 'true' : 'false'; ?>"
                            aria-controls="devhubTabFeatures" data-tab="features">
                            <?php esc_html_e('Description', 'devicehub-theme'); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ($has_specs_tab): ?>
                        <button class="devhub-single__tab-btn<?php echo $specs_is_active ? ' devhub-single__tab-btn--active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $specs_is_active ? 'true' : 'false'; ?>"
                            aria-controls="devhubTabSpecs" data-tab="specs">
                            <?php esc_html_e('Specifications', 'devicehub-theme'); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ($has_reviews_tab): ?>
                        <button class="devhub-single__tab-btn<?php echo $reviews_is_active ? ' devhub-single__tab-btn--active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $reviews_is_active ? 'true' : 'false'; ?>"
                            aria-controls="devhubTabReviews" data-tab="reviews">
                            <?php esc_html_e('Reviews', 'devicehub-theme'); ?>
                            <?php if ($review_count > 0): ?>
                                <span class="devhub-single__tab-count"><?php echo esc_html($review_count); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($has_features_tab): ?>
                    <div class="devhub-single__tab-panel<?php echo $features_is_active ? ' devhub-single__tab-panel--active' : ''; ?>"
                        id="devhubTabFeatures" role="tabpanel"<?php echo $features_is_active ? '' : ' hidden'; ?>>
                        <div class="devhub-single__feature-cards">
                            <?php foreach ($features_sections as $section): ?>
                                <div class="devhub-single__desc-card devhub-single__feature-card">
                                    <div class="devhub-single__features-content">
                                        <?php echo $section['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($has_specs_tab): ?>
                    <div class="devhub-single__tab-panel<?php echo $specs_is_active ? ' devhub-single__tab-panel--active' : ''; ?>"
                        id="devhubTabSpecs" role="tabpanel"<?php echo $specs_is_active ? '' : ' hidden'; ?>>

                        <?php if (!empty($specs) || !empty($physical_specs)): ?>
                            <table class="devhub-single__specs-table">
                                <tbody>
                                    <?php if (!empty($specs)): ?>
                                        <?php foreach ($specs as $index => $spec): ?>
                                            <tr>
                                                <?php if ($index === 0): ?>
                                                    <td class="devhub-single__spec-group" rowspan="<?php echo count($specs); ?>">
                                                        <?php esc_html_e('General', 'devicehub-theme'); ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td class="devhub-single__spec-label"><?php echo esc_html($spec['label']); ?></td>
                                                <td class="devhub-single__spec-value"><?php echo esc_html($spec['value']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($physical_specs)): ?>
                                        <?php foreach ($physical_specs as $index => $spec): ?>
                                            <tr>
                                                <?php if ($index === 0): ?>
                                                    <td class="devhub-single__spec-group" rowspan="<?php echo count($physical_specs); ?>">
                                                        <?php esc_html_e('Physical', 'devicehub-theme'); ?>
                                                    </td>
                                                <?php endif; ?>
                                                <td class="devhub-single__spec-label"><?php echo esc_html($spec['label']); ?></td>
                                                <td class="devhub-single__spec-value"><?php echo esc_html($spec['value']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

                <?php if ($has_reviews_tab): ?>
                    <div class="devhub-single__tab-panel<?php echo $reviews_is_active ? ' devhub-single__tab-panel--active' : ''; ?>"
                        id="devhubTabReviews" role="tabpanel"<?php echo $reviews_is_active ? '' : ' hidden'; ?>>
                        <div class="devhub-single__reviews-wrap">
                            <?php comments_template('/woocommerce/single-product-reviews.php', true); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div><!-- /.devhub-single__tabs -->
        <?php endif; ?>

    </div><!-- /.wf-container -->
</div><!-- /.devhub-single -->

<?php do_action('woocommerce_after_single_product'); ?>

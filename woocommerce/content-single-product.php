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

// All available variations serialised for JS resolution
$available_variations = '[]';
if ($is_variable) {
    $raw = array_map(function ($v) use ($product) {
        return [
            'id' => $v['variation_id'],
            'attributes' => $v['attributes'],
            'price' => $v['display_price'],
            'price_html' => $v['price_html'] ?? wc_price((float) $v['display_price']),
            'in_stock' => $v['is_in_stock'],
            'gallery_images' => devhub_get_variation_gallery_data($v, $product->get_name(), ''),
        ];
    }, $product->get_available_variations());
    $available_variations = wp_json_encode($raw);
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
$features_is_active = $has_features_tab;
$specs_is_active = !$has_features_tab && $has_specs_tab;

// ── 2. Markup ─────────────────────────────────────────────────────────────────
?>

<div class="devhub-single"
    data-variations="<?php echo esc_attr($available_variations); ?>"
    data-default-gallery="<?php echo esc_attr(wp_json_encode($default_gallery)); ?>">
    <div class="wf-container">

        <div class="devhub-page-bar">
            <?php woocommerce_breadcrumb(); ?>
            <?php /* devhub-page-bar__title intentionally hidden for now. */ ?>
        </div>

        <div class="devhub-single__layout">

            <!-- ── Gallery (left) ──────────────────────────────────────────── -->
            <div class="devhub-single__gallery">

                <div class="devhub-single__main-image">
                    <img src="<?php echo esc_url($main_img); ?>"
                        alt="<?php echo esc_attr($default_gallery[0]['alt']); ?>"
                        data-full-src="<?php echo esc_url($default_gallery[0]['full_src'] ?? $main_img); ?>">
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
                                    <img src="<?php echo esc_url($gallery_image['thumb_src']); ?>" alt="">
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

                <div class="devhub-single__safe-checkout">
                    <p class="devhub-single__safe-checkout-label">
                        <i class="fas fa-shield-alt" aria-hidden="true"></i>
                        <?php esc_html_e('Guaranteed safe Checkout', 'devicehub-theme'); ?>
                    </p>
                    <div class="devhub-single__payment-slider">
                        <button class="devhub-single__bundle-arrow devhub-single__payment-arrow devhub-single__payment-arrow--prev"
                            id="devhubPaymentPrev" type="button" hidden
                            aria-label="<?php esc_attr_e('Previous payment methods', 'devicehub-theme'); ?>">
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                        </button>
                        <div class="devhub-single__payment-viewport" id="devhubPaymentViewport">
                            <div class="devhub-single__payment-icons">
                                <?php if (!empty($payment_methods)): ?>
                                    <?php foreach ($payment_methods as $payment_method): ?>
                                        <span
                                            class="devhub-single__payment-badge devhub-single__payment-badge--dynamic devhub-single__payment-badge--<?php echo esc_attr($payment_method['id']); ?>">
                                            <?php echo esc_html($payment_method['title']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="devhub-single__payment-badge devhub-single__payment-badge--dynamic">
                                        <?php esc_html_e('Payment methods available at checkout', 'devicehub-theme'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button class="devhub-single__bundle-arrow devhub-single__payment-arrow devhub-single__payment-arrow--next"
                            id="devhubPaymentNext" type="button" hidden
                            aria-label="<?php esc_attr_e('Next payment methods', 'devicehub-theme'); ?>">
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

            </div>

            <!-- ── Info (right) ────────────────────────────────────────────── -->
            <div class="devhub-single__info">

                <h1 class="devhub-single__title">
                    <?php echo esc_html($product->get_name()); ?>
                </h1>

                <?php
                $is_price_range = $product->is_type('variable')
                    && abs((float) $product->get_variation_price('max', true) - (float) $product->get_variation_price('min', true)) >= 0.01;
                ?>
                <div class="devhub-single__price-row">
                    <div class="devhub-single__price<?php echo $is_price_range ? ' devhub-single__price--range' : ''; ?>">
                        <?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                    </div>
                    <span
                        class="devhub-single__stock devhub-single__stock--<?php echo $product->is_in_stock() ? 'in' : 'out'; ?>">
                        <span class="devhub-single__stock-dot" aria-hidden="true"></span>
                        <?php echo $product->is_in_stock()
                            ? esc_html__('In stock', 'devicehub-theme')
                            : esc_html__('Out of stock', 'devicehub-theme'); ?>
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

                    <input type="hidden" name="quantity" value="1">

                    <div class="devhub-single__actions">
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

                </form>

                <?php do_action('woocommerce_after_add_to_cart_form'); ?>

            </div><!-- /.devhub-single__info -->

        </div><!-- /.devhub-single__layout -->

        <!-- ── Tabs ──────────────────────────────────────────────────────── -->
        <?php if ($has_features_tab || $has_specs_tab): ?>
            <div class="devhub-single__tabs">

                <div class="devhub-single__tab-nav" role="tablist">
                    <?php if ($has_features_tab): ?>
                        <button class="devhub-single__tab-btn<?php echo $features_is_active ? ' devhub-single__tab-btn--active' : ''; ?>"
                            role="tab"
                            aria-selected="<?php echo $features_is_active ? 'true' : 'false'; ?>"
                            aria-controls="devhubTabFeatures" data-tab="features">
                            <?php esc_html_e('Features', 'devicehub-theme'); ?>
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
                </div>

                <?php if ($has_features_tab): ?>
                    <div class="devhub-single__tab-panel<?php echo $features_is_active ? ' devhub-single__tab-panel--active' : ''; ?>"
                        id="devhubTabFeatures" role="tabpanel"<?php echo $features_is_active ? '' : ' hidden'; ?>>
                        <div class="devhub-single__feature-cards">
                            <?php foreach ($features_sections as $section): ?>
                                <div class="devhub-single__desc-card devhub-single__feature-card">
                                    <h3 class="devhub-single__feature-title"><?php echo esc_html($section['title']); ?></h3>
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

                        <?php if (!empty($quick_stats)): ?>
                            <div class="devhub-single__quick-stats">
                                <?php foreach ($quick_stats as $stat): ?>
                                    <div class="devhub-single__quick-stat">
                                        <span class="devhub-single__quick-stat-icon">
                                            <i class="<?php echo esc_attr($stat['icon']); ?>" aria-hidden="true"></i>
                                        </span>
                                        <div class="devhub-single__quick-stat-text">
                                            <span class="devhub-single__quick-stat-label"><?php echo esc_html($stat['label']); ?></span>
                                            <span class="devhub-single__quick-stat-value"><?php echo esc_html($stat['value']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

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

            </div><!-- /.devhub-single__tabs -->
        <?php endif; ?>

    </div><!-- /.wf-container -->
</div><!-- /.devhub-single -->

<?php do_action('woocommerce_after_single_product'); ?>

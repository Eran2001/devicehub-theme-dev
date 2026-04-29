<?php
/*
 * Archive Product — DeviceHub override
 *
 * Layout: sidebar filters (left) + product grid (right).
 * All product data from WooCommerce; images are local SVG placeholders.
 * Filtering: WooCommerce handles pa_* attribute params natively.
 *             product_brand/pwb-brand are handled by devhub_filter_archive_by_brand() in inc/hooks.php.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

function devhub_get_selected_archive_category_slugs(): array
{
    $raw = sanitize_text_field(wp_unslash($_GET['filter_product_cat'] ?? ''));

    if ($raw !== '') {
        return array_values(array_unique(array_filter(array_map('sanitize_title', explode(',', $raw)))));
    }

    $current_cat = is_product_category() ? get_queried_object() : null;

    if ($current_cat instanceof WP_Term) {
        return [$current_cat->slug];
    }

    return [];
}

function devhub_get_archive_filter_shop_url(): string
{
    $query_args = [];

    foreach ($_GET as $key => $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $query_args[sanitize_key((string) $key)] = sanitize_text_field(wp_unslash((string) $value));
    }

    unset($query_args['paged']);

    return add_query_arg($query_args, get_permalink(wc_get_page_id('shop')));
}


/**
 * Render category links as a filter group.
 * Clicking a category navigates to its archive URL — PHP then renders
 * the relevant attribute filters for that category automatically.
 */
function devhub_archive_category_group(): void
{
    // Slugs that are utility/internal categories, not browsable sections.
    // Add to this list whenever you create a category that shouldn't appear in the filter.
    $excluded_slugs = ['new-arrivals', 'test'];

    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'parent' => 0,
        'orderby' => 'menu_order',
        'order' => 'ASC',
        'exclude' => [get_option('default_product_cat')],
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return;
    }

    $terms = array_values(array_filter($terms, fn($t) => !in_array($t->slug, $excluded_slugs, true)));

    if (empty($terms)) {
        return;
    }

    $active_slugs = devhub_get_selected_archive_category_slugs();
    $all_products_url = remove_query_arg('filter_product_cat', devhub_get_archive_filter_shop_url());
    $base = $all_products_url;
    ?>
    <div class="devhub-filter-group">
        <button class="devhub-filter-group__toggle" type="button" aria-expanded="true">
            Category
            <i class="fas fa-chevron-up" aria-hidden="true"></i>
        </button>
        <ul class="devhub-filter-group__list">
            <li>
                <a href="<?php echo esc_url($all_products_url); ?>"
                    class="devhub-filter-option<?php echo empty($active_slugs) ? ' devhub-filter-option--active' : ''; ?>">
                    <span class="devhub-filter-option__check" aria-hidden="true">
                        <?php if (empty($active_slugs)): ?><i class="fas fa-check"></i><?php endif; ?>
                    </span>
                    <span class="devhub-filter-option__name">All Products</span>
                </a>
            </li>
            <?php foreach ($terms as $term):
                $is_active = in_array($term->slug, $active_slugs, true);
                $new_slugs = $is_active
                    ? array_values(array_diff($active_slugs, [$term->slug]))
                    : array_values(array_unique(array_merge($active_slugs, [$term->slug])));
                $href = !empty($new_slugs)
                    ? add_query_arg('filter_product_cat', implode(',', $new_slugs), $base)
                    : $all_products_url;
                ?>
                <li>
                    <a href="<?php echo esc_url($href); ?>"
                        class="devhub-filter-option<?php echo $is_active ? ' devhub-filter-option--active' : ''; ?>">
                        <span class="devhub-filter-option__check" aria-hidden="true">
                            <?php if ($is_active): ?><i class="fas fa-check"></i><?php endif; ?>
                        </span>
                        <span class="devhub-filter-option__name"><?php echo esc_html($term->name); ?></span>
                        <span class="devhub-filter-option__count"><?php echo esc_html($term->count); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}


/**
 * Render one collapsible filter group.
 * Uses link-based URL toggling — no JS required for filtering itself.
 *
 * @param string $label     Display label (e.g. "Brand")
 * @param string $taxonomy  Taxonomy slug (e.g. "product_brand", "pa_screen-type")
 * @param string $url_param GET param name (e.g. "filter_brand", "filter_screen-type")
 */
function devhub_archive_filter_group(string $label, string $taxonomy, string $url_param): void
{
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
        'orderby' => 'name',
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return;
    }

    $raw = sanitize_text_field(wp_unslash($_GET[$url_param] ?? ''));
    $active = $raw !== '' ? array_filter(array_map('sanitize_title', explode(',', $raw))) : [];
    ?>
    <div class="devhub-filter-group">
        <button class="devhub-filter-group__toggle" type="button" aria-expanded="true">
            <?php echo esc_html($label); ?>
            <i class="fas fa-chevron-up" aria-hidden="true"></i>
        </button>
        <ul class="devhub-filter-group__list">
            <?php foreach ($terms as $term):
                $is_active = in_array($term->slug, $active, true);
                $new_vals = $is_active
                    ? array_values(array_diff($active, [$term->slug]))
                    : array_merge($active, [$term->slug]);
                $base = remove_query_arg('paged');
                $href = $new_vals
                    ? add_query_arg($url_param, implode(',', $new_vals), $base)
                    : remove_query_arg($url_param, $base);
                ?>
                <li>
                    <a href="<?php echo esc_url($href); ?>"
                        class="devhub-filter-option<?php echo $is_active ? ' devhub-filter-option--active' : ''; ?>">
                        <span class="devhub-filter-option__check" aria-hidden="true">
                            <?php if ($is_active): ?><i class="fas fa-check"></i><?php endif; ?>
                        </span>
                        <span class="devhub-filter-option__name"><?php echo esc_html($term->name); ?></span>
                        <span class="devhub-filter-option__count"><?php echo esc_html($term->count); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Return product IDs for the current archive scope.
 *
 * Brand filters should reflect the current shop/category/tag view rather than
 * unrelated products elsewhere in the catalog.
 *
 * @return int[]
 */
function devhub_get_archive_scope_product_ids(): array
{
    static $product_ids = null;

    if (is_array($product_ids)) {
        return $product_ids;
    }

    $tax_query = [];

    $selected_categories = devhub_get_selected_archive_category_slugs();

    if (!empty($selected_categories)) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field' => 'slug',
            'terms' => $selected_categories,
            'operator' => 'IN',
        ];
    } elseif (is_product_category()) {
        $current_term = get_queried_object();

        if ($current_term instanceof WP_Term) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => [$current_term->term_id],
            ];
        }
    } elseif (is_product_tag()) {
        $current_term = get_queried_object();

        if ($current_term instanceof WP_Term) {
            $tax_query[] = [
                'taxonomy' => 'product_tag',
                'field' => 'term_id',
                'terms' => [$current_term->term_id],
            ];
        }
    }

    $query = new WP_Query([
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'tax_query' => $tax_query,
    ]);

    $product_ids = array_map('intval', (array) $query->posts);

    return $product_ids;
}

/**
 * Return archive-scoped filter terms with counts limited to the current view.
 *
 * @return array<int, array{name: string, slug: string, count: int}>
 */
function devhub_get_scoped_archive_terms(string $taxonomy): array
{
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $product_ids = devhub_get_archive_scope_product_ids();

    if (empty($product_ids)) {
        return [];
    }

    $object_terms = wp_get_object_terms($product_ids, $taxonomy, [
        'fields' => 'all_with_object_id',
    ]);

    if (is_wp_error($object_terms) || empty($object_terms)) {
        return [];
    }

    $terms = [];

    foreach ($object_terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $term_id = (int) $term->term_id;

        if (!isset($terms[$term_id])) {
            $terms[$term_id] = [
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => 0,
                'object_ids' => [],
            ];
        }

        $terms[$term_id]['object_ids'][(int) $term->object_id] = true;
    }

    foreach ($terms as $term_id => $term_data) {
        $terms[$term_id]['count'] = count($term_data['object_ids']);
        unset($terms[$term_id]['object_ids']);
    }

    uasort($terms, static function (array $left, array $right): int {
        return strcasecmp($left['name'], $right['name']);
    });

    return array_values($terms);
}

/**
 * Render the Brand filter from whichever brand taxonomy is actually populated
 * in the current archive scope.
 */
function devhub_archive_brand_filter_group(): void
{
    $brand_terms = [];

    foreach (['product_brand', 'pwb-brand', 'pa_brand'] as $taxonomy) {
        $brand_terms = devhub_get_scoped_archive_terms($taxonomy);

        if (!empty($brand_terms)) {
            break;
        }
    }

    if (empty($brand_terms)) {
        return;
    }

    $raw = sanitize_text_field(wp_unslash($_GET['filter_brand'] ?? ''));
    $active = $raw !== '' ? array_filter(array_map('sanitize_title', explode(',', $raw))) : [];
    ?>
    <div class="devhub-filter-group">
        <button class="devhub-filter-group__toggle" type="button" aria-expanded="true">
            Brand
            <i class="fas fa-chevron-up" aria-hidden="true"></i>
        </button>
        <ul class="devhub-filter-group__list">
            <?php foreach ($brand_terms as $term):
                $is_active = in_array($term['slug'], $active, true);
                $new_vals = $is_active
                    ? array_values(array_diff($active, [$term['slug']]))
                    : array_merge($active, [$term['slug']]);
                $base = remove_query_arg('paged');
                $href = $new_vals
                    ? add_query_arg([
                        'filter_brand' => implode(',', $new_vals),
                        'query_type_brand' => 'or',
                    ], $base)
                    : remove_query_arg(['filter_brand', 'query_type_brand'], $base);
                ?>
                <li>
                    <a href="<?php echo esc_url($href); ?>"
                        class="devhub-filter-option<?php echo $is_active ? ' devhub-filter-option--active' : ''; ?>">
                        <span class="devhub-filter-option__check" aria-hidden="true">
                            <?php if ($is_active): ?><i class="fas fa-check"></i><?php endif; ?>
                        </span>
                        <span class="devhub-filter-option__name"><?php echo esc_html($term['name']); ?></span>
                        <span class="devhub-filter-option__count"><?php echo esc_html((string) $term['count']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}
?>

<div class="devhub-archive">
    <div class="wf-container">

        <div class="devhub-page-bar">
            <?php woocommerce_breadcrumb(); ?>
            <?php if (apply_filters('woocommerce_show_page_title', true)): ?>
                <h1 class="devhub-page-bar__title"><?php woocommerce_page_title(); ?></h1>
            <?php endif; ?>
        </div>

        <div class="devhub-archive__layout">

            <!-- ── Sidebar ───────────────────────────────────────────────── -->
            <aside class="devhub-archive__sidebar">
                <div class="devhub-archive__filters">

                    <!-- Category links — clicking navigates to the category archive -->
                    <?php devhub_archive_category_group(); ?>

                    <!-- Brand (always shown — applies to all categories) -->
                    <?php devhub_archive_brand_filter_group(); ?>

                    <!-- Category-specific attribute filters -->
                    <?php
                    // Map category slug → relevant attribute filters.
                    // 'taxonomy' must match a registered pa_* taxonomy in WooCommerce.
                    // Adjust slugs to match what you've created under Products → Attributes.
                    $category_attribute_map = [
                        'mobile-phones' => [
                            ['label' => 'Built-in Memory', 'taxonomy' => 'pa_built-in-memory', 'param' => 'filter_built-in-memory'],
                            ['label' => 'Storage', 'taxonomy' => 'pa_storage', 'param' => 'filter_storage'],
                            ['label' => 'Battery Capacity', 'taxonomy' => 'pa_battery-capacity', 'param' => 'filter_battery-capacity'],
                            ['label' => 'Screen Type', 'taxonomy' => 'pa_screen-type', 'param' => 'filter_screen-type'],
                            ['label' => 'Screen Diagonal', 'taxonomy' => 'pa_screen-diagonal', 'param' => 'filter_screen-diagonal'],
                            ['label' => 'Color', 'taxonomy' => 'pa_color', 'param' => 'filter_color'],
                        ],
                        'broad-bands' => [
                            ['label' => 'Color', 'taxonomy' => 'pa_color', 'param' => 'filter_color'],
                            ['label' => 'Material', 'taxonomy' => 'pa_material', 'param' => 'filter_material'],
                        ],
                        'electronics' => [
                            ['label' => 'Color', 'taxonomy' => 'pa_color', 'param' => 'filter_color'],
                            ['label' => 'Material', 'taxonomy' => 'pa_material', 'param' => 'filter_material'],
                        ],
                        'accessories' => [
                            ['label' => 'Color', 'taxonomy' => 'pa_color', 'param' => 'filter_color'],
                            ['label' => 'Size', 'taxonomy' => 'pa_size', 'param' => 'filter_size'],
                            ['label' => 'Material', 'taxonomy' => 'pa_material', 'param' => 'filter_material'],
                        ],
                    ];

                    $active_category_slugs = devhub_get_selected_archive_category_slugs();
                    $visible_attribute_filters = [];

                    foreach ($active_category_slugs as $category_slug) {
                        if (!isset($category_attribute_map[$category_slug])) {
                            continue;
                        }

                        foreach ($category_attribute_map[$category_slug] as $attr) {
                            $visible_attribute_filters[$attr['param']] = $attr;
                        }
                    }

                    foreach ($visible_attribute_filters as $attr) {
                        devhub_archive_filter_group($attr['label'], $attr['taxonomy'], $attr['param']);
                    }
                    ?>

                </div>
            </aside>

            <!-- ── Main ──────────────────────────────────────────────────── -->
            <div class="devhub-archive__main">

                <div class="devhub-archive__toolbar">
                    <?php woocommerce_result_count(); ?>
                    <?php woocommerce_catalog_ordering(); ?>
                </div>

                <?php if (woocommerce_product_loop()): ?>

                    <div class="devhub-archive__grid">
                        <?php
                        while (have_posts()):
                            the_post();
                            $product = wc_get_product(get_the_ID());
                            if ($product) {
                                // $img = devhub_get_product_placeholder_img($product);
                                // devhub_render_product_card($product, $img);
                                $img = get_the_post_thumbnail_url($product->get_id(), 'woocommerce_single') ?: devhub_get_product_placeholder_img($product);
                                devhub_render_product_card($product, $img);
                            }
                        endwhile;
                        wp_reset_postdata();
                        ?>
                    </div>

                    <div class="devhub-archive__pagination">
                        <div class="woocommerce-pagination woocommerce-Pagination">
                            <?php
                            global $wp_query;

                            echo wp_kses_post(
                                paginate_links(
                                    [
                                        'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                                        'format'    => '',
                                        'current'   => max( 1, get_query_var( 'paged' ) ),
                                        'total'     => (int) $wp_query->max_num_pages,
                                        'prev_text' => '<i class="fa fa-angle-double-left" aria-hidden="true"></i>',
                                        'next_text' => '<i class="fa fa-angle-double-right" aria-hidden="true"></i>',
                                        'type'      => 'list',
                                    ]
                                )
                            );
                            ?>
                        </div>
                    </div>

                <?php else: ?>
                    <?php do_action('woocommerce_no_products_found'); ?>
                <?php endif; ?>

            </div>

        </div>

    </div>
</div>

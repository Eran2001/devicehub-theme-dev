<?php
/**
 * DeviceHub search results template.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

get_header();

$search_query = (string) get_query_var('devhub_product_search_term');
if ($search_query === '') {
    $search_query = get_search_query();
}
$results_count = (int) $wp_query->found_posts;
?>

<section class="devhub-search wf-py-default">
    <div class="wf-container">

        <?php if (have_posts()): ?>
            <div class="devhub-search__intro">
                <p class="devhub-search__eyebrow"><?php esc_html_e('Search Results', 'devicehub-theme'); ?></p>
                <h2 class="devhub-search__title">
                    <?php
                    printf(
                        /* translators: %s: search term */
                        esc_html__('Products matching "%s"', 'devicehub-theme'),
                        esc_html($search_query)
                    );
                    ?>
                </h2>
                <p class="devhub-search__meta">
                    <?php
                    printf(
                        /* translators: %d: result count */
                        esc_html(_n('%d product found', '%d products found', $results_count, 'devicehub-theme')),
                        $results_count
                    );
                    ?>
                </p>
            </div>

            <div class="devhub-search__grid">
                <?php while (have_posts()):
                    the_post();
                    $product = wc_get_product(get_the_ID());
                    if ($product instanceof WC_Product) {
                        devhub_render_product_card($product);
                    }
                endwhile; ?>
            </div>

            <?php the_posts_navigation(); ?>

        <?php else: ?>
            <div class="devhub-search__empty-card">
                <span class="devhub-search__empty-kicker"><?php esc_html_e('No products found', 'devicehub-theme'); ?></span>
                <h2 class="devhub-search__empty-title">
                    <?php
                    printf(
                        /* translators: %s: search term */
                        esc_html__('Nothing matched "%s"', 'devicehub-theme'),
                        esc_html($search_query)
                    );
                    ?>
                </h2>
                <p class="devhub-search__empty-text">
                    <?php esc_html_e('Try a different keyword and search again.', 'devicehub-theme'); ?>
                </p>

                <form class="devhub-search__form" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                    <label class="screen-reader-text" for="devhub-search-page-field">
                        <?php esc_html_e('Search for products', 'devicehub-theme'); ?>
                    </label>
                    <input
                        id="devhub-search-page-field"
                        type="search"
                        name="s"
                        value="<?php echo esc_attr($search_query); ?>"
                        placeholder="<?php esc_attr_e('Search for products...', 'devicehub-theme'); ?>">
                    <input type="hidden" name="post_type" value="product">
                    <button type="submit"><?php esc_html_e('Search', 'devicehub-theme'); ?></button>
                </form>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php get_footer(); ?>

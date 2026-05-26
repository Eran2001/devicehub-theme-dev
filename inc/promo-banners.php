<?php
/**
 * DeviceHub - Promo Banners
 *
 * Admin-managed homepage promo banners with required placement and image.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

const DEVHUB_PROMO_BANNER_PLACEMENT_META = '_devhub_promo_banner_placement';
const DEVHUB_PROMO_BANNER_LINK_META = '_devhub_promo_banner_link';
const DEVHUB_PROMO_BANNER_MOBILE_IMAGE_META = '_devhub_promo_banner_mobile_image_id';

add_action('init', 'devhub_register_promo_banners');

function devhub_register_promo_banners(): void
{
    register_post_type('devhub_promo_banner', [
        'labels' => [
            'name'                  => __('Promo Banners', 'devicehub-theme'),
            'singular_name'         => __('Promo Banner', 'devicehub-theme'),
            'menu_name'             => __('Promo Banners', 'devicehub-theme'),
            'add_new'               => __('Add Banner', 'devicehub-theme'),
            'add_new_item'          => __('Add New Promo Banner', 'devicehub-theme'),
            'edit_item'             => __('Edit Promo Banner', 'devicehub-theme'),
            'new_item'              => __('New Promo Banner', 'devicehub-theme'),
            'view_item'             => __('View Promo Banner', 'devicehub-theme'),
            'search_items'          => __('Search Promo Banners', 'devicehub-theme'),
            'not_found'             => __('No promo banners found.', 'devicehub-theme'),
            'not_found_in_trash'    => __('No promo banners found in Trash.', 'devicehub-theme'),
            'featured_image'        => __('Banner Image', 'devicehub-theme'),
            'set_featured_image'    => __('Set Banner Image', 'devicehub-theme'),
            'remove_featured_image' => __('Remove Banner Image', 'devicehub-theme'),
            'use_featured_image'    => __('Use as Banner Image', 'devicehub-theme'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'menu_position'       => 59,
        'menu_icon'           => 'dashicons-format-image',
        'supports'            => ['title', 'thumbnail', 'page-attributes'],
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => false,
    ]);
}

function devhub_get_promo_banner_placements(): array
{
    $placements = [];

    foreach (devhub_get_promo_banner_categories() as $category) {
        $category_label = function_exists('devhub_get_product_category_display_name')
            ? devhub_get_product_category_display_name($category)
            : $category->name;

        $placements[devhub_get_promo_banner_category_placement($category, 'before')] = sprintf(
            __('Before %s', 'devicehub-theme'),
            $category_label
        );

        $placements[devhub_get_promo_banner_category_placement($category, 'after')] = sprintf(
            __('After %s', 'devicehub-theme'),
            $category_label
        );
    }

    return $placements;
}

function devhub_get_promo_banner_categories(): array
{
    if (!taxonomy_exists('product_cat')) {
        return [];
    }

    $excluded_ids = [];

    foreach (['flash-sale'] as $slug) {
        $term = get_term_by('slug', $slug, 'product_cat');

        if ($term instanceof WP_Term) {
            $excluded_ids[] = (int) $term->term_id;
        }
    }

    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'parent' => 0,
        'exclude' => array_values(array_unique(array_filter($excluded_ids))),
        'orderby' => 'menu_order',
        'order' => 'ASC',
    ]);

    if (empty($categories) || is_wp_error($categories)) {
        return [];
    }

    return array_values(array_filter($categories, static function ($category): bool {
        return $category instanceof WP_Term && (int) $category->count > 0;
    }));
}

function devhub_get_promo_banner_category_placement(WP_Term $category, string $position = 'after'): string
{
    $position = $position === 'before' ? 'before' : 'after';

    return $position . '_' . sanitize_key($category->slug);
}

function devhub_get_legacy_promo_banner_placements(string $placement): array
{
    $legacy_placements = [
        'before_broad-bands' => ['before_broadbands'],
        'before_broadbands' => ['before_broadbands'],
    ];

    return $legacy_placements[$placement] ?? [];
}

function devhub_get_promo_banner_link(int $post_id): string
{
    return (string) get_post_meta($post_id, DEVHUB_PROMO_BANNER_LINK_META, true);
}

function devhub_get_promo_banners_by_placement(string $placement): array
{
    if (!array_key_exists($placement, devhub_get_promo_banner_placements())) {
        return [];
    }

    $placement_values = array_merge([$placement], devhub_get_legacy_promo_banner_placements($placement));

    return get_posts([
        'post_type'      => 'devhub_promo_banner',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
        'meta_key'       => DEVHUB_PROMO_BANNER_PLACEMENT_META,
        'meta_value'     => count($placement_values) > 1 ? $placement_values : $placement,
        'meta_compare'   => count($placement_values) > 1 ? 'IN' : '=',
        'no_found_rows'  => true,
    ]);
}

add_action('add_meta_boxes_devhub_promo_banner', 'devhub_add_promo_banner_meta_boxes');

function devhub_add_promo_banner_meta_boxes(): void
{
    add_meta_box(
        'devhub-promo-banner-settings',
        __('Banner Settings', 'devicehub-theme'),
        'devhub_render_promo_banner_settings_box',
        'devhub_promo_banner',
        'normal',
        'high'
    );

    add_meta_box(
        'devhub-promo-banner-help',
        __('Promo Banner Guide', 'devicehub-theme'),
        'devhub_render_promo_banner_help_box',
        'devhub_promo_banner',
        'side',
        'high'
    );

    add_meta_box(
        'devhub-promo-banner-mobile-image',
        __('Mobile Image', 'devicehub-theme'),
        'devhub_render_promo_banner_mobile_image_box',
        'devhub_promo_banner',
        'side',
        'default'
    );
}

function devhub_render_promo_banner_settings_box(WP_Post $post): void
{
    wp_nonce_field('devhub_save_promo_banner', 'devhub_promo_banner_nonce');

    $placement = (string) get_post_meta($post->ID, DEVHUB_PROMO_BANNER_PLACEMENT_META, true);
    $link = devhub_get_promo_banner_link($post->ID);
    ?>
    <p>
        <label for="devhub-promo-banner-placement"><strong><?php esc_html_e('Banner Area', 'devicehub-theme'); ?></strong></label><br>
        <select id="devhub-promo-banner-placement" name="devhub_promo_banner_placement" required style="width:100%;margin-top:8px;">
            <option value=""><?php esc_html_e('Select where this banner should appear', 'devicehub-theme'); ?></option>
            <?php foreach (devhub_get_promo_banner_placements() as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($placement, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="devhub-promo-banner-link"><strong><?php esc_html_e('Banner Link', 'devicehub-theme'); ?></strong></label><br>
        <input id="devhub-promo-banner-link" type="url" name="devhub_promo_banner_link"
            value="<?php echo esc_attr($link); ?>" placeholder="https://example.com/page"
            style="width:100%;margin-top:8px;">
    </p>
    <?php
}

function devhub_render_promo_banner_help_box(): void
{
    echo '<p>' . esc_html__('Required before publish:', 'devicehub-theme') . '</p>';
    echo '<ul style="list-style:disc;padding-left:18px;margin:0;">';
    echo '<li>' . esc_html__('Banner Area', 'devicehub-theme') . '</li>';
    echo '<li>' . esc_html__('Banner Image (Featured Image)', 'devicehub-theme') . '</li>';
    echo '</ul>';
    echo '<p style="margin-top:12px;">' . esc_html__('Recommended desktop image size: 2560 x 276 px.', 'devicehub-theme') . '</p>';
    echo '<p style="margin-top:12px;">' . esc_html__('Optional: add a taller Mobile Image for screens 767px and below.', 'devicehub-theme') . '</p>';
    echo '<p style="margin-top:12px;">' . esc_html__('Recommended mobile image size: 2560 x 559 px.', 'devicehub-theme') . '</p>';
    echo '<p style="margin-top:12px;">' . esc_html__('Use the Order field in Page Attributes to control banner order inside the same area.', 'devicehub-theme') . '</p>';
}

function devhub_render_promo_banner_mobile_image_box(WP_Post $post): void
{
    $mobile_image_id = (int) get_post_meta($post->ID, DEVHUB_PROMO_BANNER_MOBILE_IMAGE_META, true);
    $has_image = $mobile_image_id > 0;
    ?>
    <div id="devhub-promo-mobile-image-wrap">
        <div id="devhub-promo-mobile-image-preview" <?php echo !$has_image ? 'style="display:none;"' : ''; ?>>
            <?php if ($has_image): ?>
                <?php echo wp_get_attachment_image($mobile_image_id, [200, 120], false, ['style' => 'width:100%;height:auto;border-radius:4px;margin-bottom:8px;display:block;']); ?>
            <?php endif; ?>
        </div>
        <input type="hidden" name="devhub_promo_banner_mobile_image_id" id="devhub-promo-mobile-image-id" value="<?php echo esc_attr($has_image ? (string) $mobile_image_id : ''); ?>">
        <button type="button" id="devhub-promo-mobile-image-upload" class="button button-primary" style="width:100%;margin-bottom:4px;">
            <?php echo $has_image ? esc_html__('Change Mobile Image', 'devicehub-theme') : esc_html__('Upload Mobile Image', 'devicehub-theme'); ?>
        </button>
        <button type="button" id="devhub-promo-mobile-image-remove" class="button" style="width:100%;<?php echo !$has_image ? 'display:none;' : ''; ?>">
            <?php esc_html_e('Remove Mobile Image', 'devicehub-theme'); ?>
        </button>
    </div>
    <p class="description" style="margin-top:8px;font-size:11px;">
        <?php esc_html_e('Optional. Shown on screens ≤767px. If not set, the desktop banner image is used.', 'devicehub-theme'); ?>
    </p>
    <script>
    (function () {
        var frame;
        var uploadBtn = document.getElementById('devhub-promo-mobile-image-upload');
        var removeBtn = document.getElementById('devhub-promo-mobile-image-remove');
        var input = document.getElementById('devhub-promo-mobile-image-id');
        var preview = document.getElementById('devhub-promo-mobile-image-preview');

        if (!uploadBtn || !removeBtn || !input || !preview) {
            return;
        }

        uploadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: '<?php echo esc_js(__('Select Mobile Promo Banner Image', 'devicehub-theme')); ?>',
                button: { text: '<?php echo esc_js(__('Use this image', 'devicehub-theme')); ?>' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                input.value = att.id;
                preview.innerHTML = '<img src="' + att.url + '" style="width:100%;height:auto;border-radius:4px;margin-bottom:8px;display:block;">';
                preview.style.display = '';
                removeBtn.style.display = '';
                uploadBtn.textContent = '<?php echo esc_js(__('Change Mobile Image', 'devicehub-theme')); ?>';
            });

            frame.open();
        });

        removeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = '';
            preview.innerHTML = '';
            preview.style.display = 'none';
            removeBtn.style.display = 'none';
            uploadBtn.textContent = '<?php echo esc_js(__('Upload Mobile Image', 'devicehub-theme')); ?>';
        });
    })();
    </script>
    <?php
}

add_action('save_post_devhub_promo_banner', 'devhub_save_promo_banner_meta', 10, 3);

function devhub_save_promo_banner_meta(int $post_id, WP_Post $post, bool $update): void
{
    if (!isset($_POST['devhub_promo_banner_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['devhub_promo_banner_nonce'])), 'devhub_save_promo_banner')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $placement = sanitize_key(wp_unslash($_POST['devhub_promo_banner_placement'] ?? ''));
    $valid_placements = devhub_get_promo_banner_placements();

    if (array_key_exists($placement, $valid_placements)) {
        update_post_meta($post_id, DEVHUB_PROMO_BANNER_PLACEMENT_META, $placement);
    } else {
        delete_post_meta($post_id, DEVHUB_PROMO_BANNER_PLACEMENT_META);
    }

    $link = esc_url_raw(wp_unslash($_POST['devhub_promo_banner_link'] ?? ''));
    if ($link !== '') {
        update_post_meta($post_id, DEVHUB_PROMO_BANNER_LINK_META, $link);
    } else {
        delete_post_meta($post_id, DEVHUB_PROMO_BANNER_LINK_META);
    }

    $mobile_image_id = isset($_POST['devhub_promo_banner_mobile_image_id']) ? absint($_POST['devhub_promo_banner_mobile_image_id']) : 0;
    if ($mobile_image_id > 0) {
        update_post_meta($post_id, DEVHUB_PROMO_BANNER_MOBILE_IMAGE_META, $mobile_image_id);
    } else {
        delete_post_meta($post_id, DEVHUB_PROMO_BANNER_MOBILE_IMAGE_META);
    }

    $thumbnail_id = absint($_POST['_thumbnail_id'] ?? get_post_thumbnail_id($post_id));
    $requested_status = sanitize_key(wp_unslash($_POST['post_status'] ?? $post->post_status));
    $is_trying_to_publish = in_array($requested_status, ['publish', 'future'], true);
    $missing_placement = !array_key_exists($placement, $valid_placements);
    $missing_image = $thumbnail_id <= 0;

    if (!$is_trying_to_publish || (!$missing_placement && !$missing_image)) {
        return;
    }

    set_transient(
        'devhub_promo_banner_error_' . get_current_user_id(),
        [
            'missing_placement' => $missing_placement,
            'missing_image' => $missing_image,
        ],
        60
    );

    remove_action('save_post_devhub_promo_banner', 'devhub_save_promo_banner_meta', 10);
    wp_update_post([
        'ID' => $post_id,
        'post_status' => 'draft',
    ]);
    add_action('save_post_devhub_promo_banner', 'devhub_save_promo_banner_meta', 10, 3);
}

add_action('admin_notices', 'devhub_promo_banner_admin_notice');

function devhub_promo_banner_admin_notice(): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'devhub_promo_banner') {
        return;
    }

    $error = get_transient('devhub_promo_banner_error_' . get_current_user_id());
    if (!is_array($error)) {
        return;
    }

    delete_transient('devhub_promo_banner_error_' . get_current_user_id());

    $parts = [];
    if (!empty($error['missing_placement'])) {
        $parts[] = __('Banner Area', 'devicehub-theme');
    }
    if (!empty($error['missing_image'])) {
        $parts[] = __('Banner Image', 'devicehub-theme');
    }

    if (empty($parts)) {
        return;
    }
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <?php
            echo esc_html(
                sprintf(
                    __('Promo banner saved as draft. Add the required field(s): %s.', 'devicehub-theme'),
                    implode(', ', $parts)
                )
            );
            ?>
        </p>
    </div>
    <?php
}

add_filter('enter_title_here', 'devhub_promo_banner_title_placeholder', 10, 2);

function devhub_promo_banner_title_placeholder(string $placeholder, WP_Post $post): string
{
    if ($post->post_type === 'devhub_promo_banner') {
        return __('Banner name for admin only', 'devicehub-theme');
    }

    return $placeholder;
}

add_filter('manage_devhub_promo_banner_posts_columns', 'devhub_promo_banner_columns');

function devhub_promo_banner_columns(array $columns): array
{
    return [
        'cb' => $columns['cb'] ?? '',
        'thumbnail' => __('Image', 'devicehub-theme'),
        'title' => __('Banner Name', 'devicehub-theme'),
        'placement' => __('Area', 'devicehub-theme'),
        'menu_order' => __('Order', 'devicehub-theme'),
        'date' => __('Date', 'devicehub-theme'),
    ];
}

add_action('manage_devhub_promo_banner_posts_custom_column', 'devhub_render_promo_banner_column', 10, 2);

function devhub_render_promo_banner_column(string $column, int $post_id): void
{
    if ($column === 'thumbnail') {
        if (has_post_thumbnail($post_id)) {
            echo get_the_post_thumbnail($post_id, [96, 48], ['style' => 'width:96px;height:48px;object-fit:cover;border-radius:6px;']);
        } else {
            esc_html_e('No image', 'devicehub-theme');
        }
    }

    if ($column === 'placement') {
        $placements = devhub_get_promo_banner_placements();
        $placement = (string) get_post_meta($post_id, DEVHUB_PROMO_BANNER_PLACEMENT_META, true);
        echo esc_html($placements[$placement] ?? '—');
    }

    if ($column === 'menu_order') {
        echo esc_html((string) get_post_field('menu_order', $post_id));
    }
}

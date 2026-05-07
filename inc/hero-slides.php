<?php
/**
 * DeviceHub - Hero Slides
 *
 * Registers an admin-managed post type used by the homepage hero slider.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

add_action('init', 'devhub_register_hero_slides');

function devhub_register_hero_slides(): void
{
    register_post_type('devhub_hero_slide', [
        'labels' => [
            'name'                  => __('Hero Slides', 'devicehub-theme'),
            'singular_name'         => __('Hero Slide', 'devicehub-theme'),
            'menu_name'             => __('Hero Slides', 'devicehub-theme'),
            'add_new'               => __('Add Slide', 'devicehub-theme'),
            'add_new_item'          => __('Add New Hero Slide', 'devicehub-theme'),
            'edit_item'             => __('Edit Hero Slide', 'devicehub-theme'),
            'new_item'              => __('New Hero Slide', 'devicehub-theme'),
            'view_item'             => __('View Hero Slide', 'devicehub-theme'),
            'search_items'          => __('Search Hero Slides', 'devicehub-theme'),
            'not_found'             => __('No hero slides found.', 'devicehub-theme'),
            'not_found_in_trash'    => __('No hero slides found in Trash.', 'devicehub-theme'),
            'featured_image'        => __('Hero Banner Image', 'devicehub-theme'),
            'set_featured_image'    => __('Set Hero Banner Image', 'devicehub-theme'),
            'remove_featured_image' => __('Remove Hero Banner Image', 'devicehub-theme'),
            'use_featured_image'    => __('Use as Hero Banner Image', 'devicehub-theme'),
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 58,
        'menu_icon'          => 'dashicons-images-alt2',
        'supports'           => ['title', 'thumbnail', 'page-attributes'],
        'exclude_from_search'=> true,
        'publicly_queryable' => false,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => false,
    ]);
}

add_action('add_meta_boxes_devhub_hero_slide', 'devhub_add_hero_slide_help_box');
add_action('add_meta_boxes_devhub_hero_slide', 'devhub_add_hero_slide_mobile_image_box');

function devhub_add_hero_slide_help_box(): void
{
    add_meta_box(
        'devhub-hero-slide-help',
        __('Hero Slide Guide', 'devicehub-theme'),
        'devhub_render_hero_slide_help_box',
        'devhub_hero_slide',
        'side',
        'high'
    );
}

function devhub_render_hero_slide_help_box(): void
{
    echo '<p>' . esc_html__('Upload the slide image using the Hero Banner Image box.', 'devicehub-theme') . '</p>';
    echo '<p>' . esc_html__('Recommended desktop image size: 1920 x 493 px.', 'devicehub-theme') . '</p>';
    echo '<p>' . esc_html__('Upload a portrait/square image in the Mobile Image box for better mobile display.', 'devicehub-theme') . '</p>';
    echo '<p>' . esc_html__('Recommended mobile image size: 2560 x 1261 px.', 'devicehub-theme') . '</p>';
    echo '<p>' . esc_html__('Use the title only as an internal admin label.', 'devicehub-theme') . '</p>';
    echo '<p>' . esc_html__('Use the Order field in Page Attributes to control slide order.', 'devicehub-theme') . '</p>';
}

function devhub_add_hero_slide_mobile_image_box(): void
{
    add_meta_box(
        'devhub-hero-slide-mobile-image',
        __('Mobile Image', 'devicehub-theme'),
        'devhub_render_hero_slide_mobile_image_box',
        'devhub_hero_slide',
        'side',
        'default'
    );
}

function devhub_render_hero_slide_mobile_image_box(WP_Post $post): void
{
    wp_nonce_field('devhub_hero_mobile_image', 'devhub_hero_mobile_image_nonce');
    $mobile_image_id = (int) get_post_meta($post->ID, '_devhub_mobile_image_id', true);
    $has_image = $mobile_image_id > 0;
    ?>
    <div id="devhub-mobile-image-wrap">
        <div id="devhub-mobile-image-preview" <?php echo !$has_image ? 'style="display:none;"' : ''; ?>>
            <?php if ($has_image): ?>
                <?php echo wp_get_attachment_image($mobile_image_id, [200, 120], false, ['style' => 'width:100%;height:auto;border-radius:4px;margin-bottom:8px;display:block;']); ?>
            <?php endif; ?>
        </div>
        <input type="hidden" name="devhub_mobile_image_id" id="devhub-mobile-image-id" value="<?php echo esc_attr($has_image ? (string) $mobile_image_id : ''); ?>">
        <button type="button" id="devhub-mobile-image-upload" class="button button-primary" style="width:100%;margin-bottom:4px;">
            <?php echo $has_image ? esc_html__('Change Mobile Image', 'devicehub-theme') : esc_html__('Upload Mobile Image', 'devicehub-theme'); ?>
        </button>
        <button type="button" id="devhub-mobile-image-remove" class="button" style="width:100%;<?php echo !$has_image ? 'display:none;' : ''; ?>">
            <?php esc_html_e('Remove Mobile Image', 'devicehub-theme'); ?>
        </button>
    </div>
    <p class="description" style="margin-top:8px;font-size:11px;">
        <?php esc_html_e('Optional. Shown on screens ≤767px. If not set, the desktop image is used.', 'devicehub-theme'); ?>
    </p>
    <script>
    (function () {
        var frame;
        var uploadBtn  = document.getElementById('devhub-mobile-image-upload');
        var removeBtn  = document.getElementById('devhub-mobile-image-remove');
        var input      = document.getElementById('devhub-mobile-image-id');
        var preview    = document.getElementById('devhub-mobile-image-preview');

        uploadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title: '<?php echo esc_js(__('Select Mobile Banner Image', 'devicehub-theme')); ?>',
                button: { text: '<?php echo esc_js(__('Use this image', 'devicehub-theme')); ?>' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                input.value       = att.id;
                preview.innerHTML = '<img src="' + att.url + '" style="width:100%;height:auto;border-radius:4px;margin-bottom:8px;display:block;">';
                preview.style.display = '';
                removeBtn.style.display = '';
                uploadBtn.textContent = '<?php echo esc_js(__('Change Mobile Image', 'devicehub-theme')); ?>';
            });
            frame.open();
        });

        removeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            input.value             = '';
            preview.innerHTML       = '';
            preview.style.display   = 'none';
            removeBtn.style.display = 'none';
            uploadBtn.textContent   = '<?php echo esc_js(__('Upload Mobile Image', 'devicehub-theme')); ?>';
        });
    })();
    </script>
    <?php
}

add_action('save_post', 'devhub_save_hero_slide_mobile_image', 10, 2);

function devhub_save_hero_slide_mobile_image(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== 'devhub_hero_slide') {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!isset($_POST['devhub_hero_mobile_image_nonce'])) {
        return;
    }
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['devhub_hero_mobile_image_nonce'])), 'devhub_hero_mobile_image')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $mobile_image_id = isset($_POST['devhub_mobile_image_id']) ? absint($_POST['devhub_mobile_image_id']) : 0;

    if ($mobile_image_id > 0) {
        update_post_meta($post_id, '_devhub_mobile_image_id', $mobile_image_id);
    } else {
        delete_post_meta($post_id, '_devhub_mobile_image_id');
    }
}

add_filter('enter_title_here', 'devhub_hero_slide_title_placeholder', 10, 2);

function devhub_hero_slide_title_placeholder(string $placeholder, WP_Post $post): string
{
    if ($post->post_type === 'devhub_hero_slide') {
        return __('Slide name for admin only', 'devicehub-theme');
    }

    return $placeholder;
}

add_filter('manage_devhub_hero_slide_posts_columns', 'devhub_hero_slide_columns');

function devhub_hero_slide_columns(array $columns): array
{
    $columns = [
        'cb'         => $columns['cb'] ?? '',
        'thumbnail'  => __('Image', 'devicehub-theme'),
        'title'      => __('Slide Name', 'devicehub-theme'),
        'menu_order' => __('Order', 'devicehub-theme'),
        'date'       => __('Date', 'devicehub-theme'),
    ];

    return $columns;
}

add_action('manage_devhub_hero_slide_posts_custom_column', 'devhub_render_hero_slide_column', 10, 2);

function devhub_render_hero_slide_column(string $column, int $post_id): void
{
    if ($column === 'thumbnail') {
        if (has_post_thumbnail($post_id)) {
            echo get_the_post_thumbnail($post_id, [80, 48], ['style' => 'width:80px;height:48px;object-fit:cover;border-radius:6px;']);
        } else {
            esc_html_e('No image', 'devicehub-theme');
        }
    }

    if ($column === 'menu_order') {
        echo esc_html((string) get_post_field('menu_order', $post_id));
    }
}

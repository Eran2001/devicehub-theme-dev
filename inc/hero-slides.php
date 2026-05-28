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
add_action('pre_get_posts', 'devhub_set_hero_slide_admin_order');
add_action('admin_enqueue_scripts', 'devhub_enqueue_hero_slide_admin_assets', 20);
add_action('wp_ajax_devhub_save_hero_slide_order', 'devhub_save_hero_slide_order');

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
        'supports'           => ['thumbnail'],
        'exclude_from_search'=> true,
        'publicly_queryable' => false,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => false,
    ]);
}

add_action('add_meta_boxes_devhub_hero_slide', 'devhub_add_hero_slide_mobile_image_box');
add_action('add_meta_boxes', 'devhub_reposition_hero_slide_meta_boxes', 100, 2);

function devhub_add_hero_slide_mobile_image_box(): void
{
    add_meta_box(
        'devhub-hero-slide-mobile-image',
        __('Mobile Image', 'devicehub-theme'),
        'devhub_render_hero_slide_mobile_image_box',
        'devhub_hero_slide',
        'normal',
        'default'
    );
}

function devhub_reposition_hero_slide_meta_boxes(string $post_type, WP_Post $post): void
{
    if ($post_type !== 'devhub_hero_slide') {
        return;
    }

    $post_type_object = get_post_type_object('devhub_hero_slide');
    $featured_image_title = $post_type_object && isset($post_type_object->labels->featured_image)
        ? $post_type_object->labels->featured_image
        : __('Hero Banner Image', 'devicehub-theme');

    remove_meta_box('postimagediv', 'devhub_hero_slide', 'side');
    add_meta_box(
        'devhub-hero-slide-image',
        $featured_image_title,
        'devhub_render_hero_slide_image_box',
        'devhub_hero_slide',
        'normal',
        'high'
    );

}

function devhub_render_hero_slide_image_box(WP_Post $post): void
{
    wp_nonce_field('devhub_hero_image', 'devhub_hero_image_nonce');
    $image_id = (int) get_post_thumbnail_id($post);
    $has_image = $image_id > 0;
    ?>
    <div id="devhub-hero-image-wrap">
        <div id="devhub-hero-image-preview" <?php echo !$has_image ? 'style="display:none;"' : ''; ?>>
            <?php if ($has_image): ?>
                <?php echo wp_get_attachment_image($image_id, [200, 120], false, ['style' => 'width:100%;height:auto;border-radius:4px;margin-bottom:8px;display:block;']); ?>
            <?php endif; ?>
        </div>
        <input type="hidden" name="devhub_hero_image_id" id="devhub-hero-image-id" value="<?php echo esc_attr($has_image ? (string) $image_id : ''); ?>">
        <button type="button" id="devhub-hero-image-upload" class="button button-primary" style="width:100%;margin-bottom:4px;">
            <?php echo $has_image ? esc_html__('Change Hero Banner Image', 'devicehub-theme') : esc_html__('Upload Hero Banner Image', 'devicehub-theme'); ?>
        </button>
        <button type="button" id="devhub-hero-image-remove" class="button" style="width:100%;<?php echo !$has_image ? 'display:none;' : ''; ?>">
            <?php esc_html_e('Remove Hero Banner Image', 'devicehub-theme'); ?>
        </button>
    </div>
    <p class="description" style="margin-top:8px;font-size:13px;font-weight:600;color:#1d2327;">
        <?php esc_html_e('Recommended desktop image size: 1920 x 493 px.', 'devicehub-theme'); ?>
    </p>
    <script>
    (function () {
        var frame;
        var uploadBtn = document.getElementById('devhub-hero-image-upload');
        var removeBtn = document.getElementById('devhub-hero-image-remove');
        var input = document.getElementById('devhub-hero-image-id');
        var preview = document.getElementById('devhub-hero-image-preview');

        if (!uploadBtn || !removeBtn || !input || !preview) {
            return;
        }

        var publishBtn = document.getElementById('publish');

        function togglePublishState() {
            if (!publishBtn) {
                return;
            }

            var hasHeroImage = input.value.trim() !== '';
            publishBtn.disabled = !hasHeroImage;
            publishBtn.setAttribute('aria-disabled', hasHeroImage ? 'false' : 'true');
            publishBtn.title = hasHeroImage
                ? ''
                : '<?php echo esc_js(__('Add a hero banner image before publishing.', 'devicehub-theme')); ?>';
        }

        uploadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: '<?php echo esc_js(__('Select Hero Banner Image', 'devicehub-theme')); ?>',
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
                uploadBtn.textContent = '<?php echo esc_js(__('Change Hero Banner Image', 'devicehub-theme')); ?>';
                togglePublishState();
            });

            frame.open();
        });

        removeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = '';
            preview.innerHTML = '';
            preview.style.display = 'none';
            removeBtn.style.display = 'none';
            uploadBtn.textContent = '<?php echo esc_js(__('Upload Hero Banner Image', 'devicehub-theme')); ?>';
            togglePublishState();
        });

        togglePublishState();
    })();
    </script>
    <?php
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
    <p class="description" style="margin-top:8px;font-size:13px;font-weight:600;color:#1d2327;">
        <?php esc_html_e('Recommended mobile image size: 2560 x 1261 px.', 'devicehub-theme'); ?>
    </p>
    <p class="description" style="font-size:11px;">
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

add_action('save_post', 'devhub_save_hero_slide_media', 10, 2);
add_filter('wp_insert_post_data', 'devhub_validate_hero_slide_publish_requirements', 10, 2);

function devhub_save_hero_slide_media(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== 'devhub_hero_slide') {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['devhub_hero_image_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['devhub_hero_image_nonce'])), 'devhub_hero_image')) {
        $hero_image_id = isset($_POST['devhub_hero_image_id']) ? absint($_POST['devhub_hero_image_id']) : 0;

        if ($hero_image_id > 0) {
            set_post_thumbnail($post_id, $hero_image_id);
        } else {
            delete_post_thumbnail($post_id);
        }
    }

    if (isset($_POST['devhub_hero_mobile_image_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['devhub_hero_mobile_image_nonce'])), 'devhub_hero_mobile_image')) {
        $mobile_image_id = isset($_POST['devhub_mobile_image_id']) ? absint($_POST['devhub_mobile_image_id']) : 0;

        if ($mobile_image_id > 0) {
            update_post_meta($post_id, '_devhub_mobile_image_id', $mobile_image_id);
        } else {
            delete_post_meta($post_id, '_devhub_mobile_image_id');
        }
    }
}

function devhub_validate_hero_slide_publish_requirements(array $data, array $postarr): array
{
    if (($data['post_type'] ?? '') !== 'devhub_hero_slide') {
        return $data;
    }

    if (wp_doing_ajax() && (($_POST['action'] ?? '') === 'devhub_save_hero_slide_order')) {
        return $data;
    }

    $requested_status = $data['post_status'] ?? '';
    if (!in_array($requested_status, ['publish', 'future'], true)) {
        return $data;
    }

    $post_id = isset($postarr['ID']) ? absint($postarr['ID']) : 0;
    $has_hero_nonce = isset($postarr['devhub_hero_image_nonce']) && wp_verify_nonce(
        sanitize_text_field(wp_unslash($postarr['devhub_hero_image_nonce'])),
        'devhub_hero_image'
    );

    if ($has_hero_nonce) {
        $hero_image_id = isset($postarr['devhub_hero_image_id']) ? absint($postarr['devhub_hero_image_id']) : 0;
    } else {
        $hero_image_id = $post_id > 0 ? (int) get_post_thumbnail_id($post_id) : 0;
    }

    if ($hero_image_id <= 0) {
        $data['post_status'] = 'draft';
    }

    return $data;
}

function devhub_set_hero_slide_admin_order(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if (($query->get('post_type') ?? '') !== 'devhub_hero_slide') {
        return;
    }

    if ($query->get('orderby')) {
        return;
    }

    $query->set('orderby', [
        'menu_order' => 'ASC',
        'date' => 'DESC',
    ]);
    $query->set('order', 'ASC');
}

function devhub_enqueue_hero_slide_admin_assets(string $hook_suffix): void
{
    if ($hook_suffix !== 'edit.php') {
        return;
    }

    $screen = get_current_screen();

    if (!$screen || $screen->post_type !== 'devhub_hero_slide') {
        return;
    }

    $is_default_listing = empty($_GET['s'])
        && empty($_GET['m'])
        && empty($_GET['orderby'])
        && empty($_GET['order'])
        && empty($_GET['paged'])
        && empty($_GET['post_status']);

    if (!$is_default_listing) {
        return;
    }

    wp_enqueue_script('jquery-ui-sortable');

    wp_register_script('devhub-hero-slide-admin-order', '', ['jquery', 'jquery-ui-sortable'], DEVHUB_VERSION, true);
    wp_enqueue_script('devhub-hero-slide-admin-order');
    wp_add_inline_script('devhub-hero-slide-admin-order', "
        jQuery(function ($) {
            var \$tableBody = $('#the-list');
            if (!\$tableBody.length) {
                return;
            }

            \$tableBody.sortable({
                items: 'tr',
                axis: 'y',
                handle: '.devhub-hero-slide-order-handle',
                helper: function (event, ui) {
                    ui.children().each(function () {
                        $(this).width($(this).width());
                    });
                    return ui;
                },
                update: function () {
                    var orderedIds = \$tableBody.sortable('toArray', { attribute: 'id' })
                        .map(function (rowId) {
                            return parseInt(String(rowId).replace('post-', ''), 10);
                        })
                        .filter(function (id) {
                            return !isNaN(id);
                        });

                    $.post(ajaxurl, {
                        action: 'devhub_save_hero_slide_order',
                        nonce: '" . esc_js(wp_create_nonce('devhub_save_hero_slide_order')) . "',
                        ordered_ids: orderedIds
                    });
                }
            });
        });
    ");

    wp_add_inline_style('devhub-admin', '
        .post-type-devhub_hero_slide #the-list tr { cursor: default; }
        .post-type-devhub_hero_slide .column-sort_handle { width: 52px; text-align: center; }
        .post-type-devhub_hero_slide .devhub-hero-slide-order-handle {
            cursor: move;
            color: #8c8f94;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
        }
        .post-type-devhub_hero_slide .devhub-hero-slide-order-handle:hover {
            color: #1d2327;
        }
        .post-type-devhub_hero_slide .ui-sortable-helper {
            background: #fff;
            box-shadow: 0 8px 20px rgba(16, 24, 40, 0.12);
        }
    ');
}

function devhub_save_hero_slide_order(): void
{
    check_ajax_referer('devhub_save_hero_slide_order', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('You are not allowed to reorder hero slides.', 'devicehub-theme')], 403);
    }

    $ordered_ids = isset($_POST['ordered_ids']) ? array_map('absint', (array) $_POST['ordered_ids']) : [];

    if (empty($ordered_ids)) {
        wp_send_json_error(['message' => __('No hero slides were provided for ordering.', 'devicehub-theme')], 400);
    }

    foreach ($ordered_ids as $index => $post_id) {
        if (get_post_type($post_id) !== 'devhub_hero_slide') {
            continue;
        }

        if (!current_user_can('edit_post', $post_id)) {
            continue;
        }

        wp_update_post([
            'ID' => $post_id,
            'menu_order' => $index + 1,
        ]);
    }

    wp_send_json_success();
}

function devhub_get_hero_slide_admin_label(int $post_id): string
{
    return sprintf(__('Hero Slide #%d', 'devicehub-theme'), $post_id);
}

function devhub_get_hero_slide_alt_text(int $desktop_image_id, int $mobile_image_id = 0): string
{
    $desktop_alt = trim((string) get_post_meta($desktop_image_id, '_wp_attachment_image_alt', true));

    if ($desktop_alt !== '') {
        return $desktop_alt;
    }

    if ($mobile_image_id > 0) {
        $mobile_alt = trim((string) get_post_meta($mobile_image_id, '_wp_attachment_image_alt', true));

        if ($mobile_alt !== '') {
            return $mobile_alt;
        }
    }

    return __('Hero banner', 'devicehub-theme');
}

add_filter('manage_devhub_hero_slide_posts_columns', 'devhub_hero_slide_columns');

function devhub_hero_slide_columns(array $columns): array
{
    $columns = [
        'cb'         => $columns['cb'] ?? '',
        'thumbnail'  => __('Image', 'devicehub-theme'),
        'slide_label'=> __('Slide', 'devicehub-theme'),
        'sort_handle'=> '',
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

    if ($column === 'slide_label') {
        echo esc_html(devhub_get_hero_slide_admin_label($post_id));
    }

    if ($column === 'sort_handle') {
        echo '<span class="dashicons dashicons-menu devhub-hero-slide-order-handle" aria-hidden="true"></span>';
    }
}

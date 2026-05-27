<?php
/**
 * Customize WordPress admin UI visibility for Shop Manager role.
 *
 * This function hides unnecessary admin notices, plugin meta boxes,
 * and technical UI components to provide a cleaner and simplified
 * product management experience for Shop Managers.
 */
function customize_shop_manager_admin_ui() {

    $current_user = wp_get_current_user();

    if (in_array('shop_manager', $current_user->roles)) {

        echo '<style>

            #devicehub-guidance-panel,
            .notice-warning,
            #litespeed,
            .litespeed-post-options {
                display: none !important;
            }

        </style>';
    }
}

add_action('admin_head', 'customize_shop_manager_admin_ui');

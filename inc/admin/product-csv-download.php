<?php
/**
 * Admin product CSV download helper.
 *
 * Adds a download button to the WooCommerce Products screen and serves the
 * template CSV from the theme.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', 'devhub_enqueue_product_csv_download_button_script');
add_action('admin_post_devhub_download_product_csv', 'devhub_handle_product_csv_download');

function devhub_enqueue_product_csv_download_button_script(): void
{
    if (!current_user_can('edit_products')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || 'edit-product' !== $screen->id) {
        return;
    }

    $download_url = add_query_arg(
        array(
            'action'   => 'devhub_download_product_csv',
            '_wpnonce' => wp_create_nonce('devhub_download_product_csv'),
        ),
        admin_url('admin-post.php')
    );

    $script = sprintf(
        "(function () {\n" .
        "    var downloadUrl = %s;\n" .
        "    var buttonLabel = %s;\n" .
        "\n" .
        "    function injectDownloadButton() {\n" .
        "        var exportButton = document.querySelector('.edit-php.post-type-product .page-title-action[href*=\"product_exporter\"]');\n" .
        "        if (!exportButton || document.querySelector('.edit-php.post-type-product a.page-title-action[href*=\"devhub_download_product_csv\"]')) {\n" .
        "            return false;\n" .
        "        }\n" .
        "\n" .
        "        var downloadButton = document.createElement('a');\n" .
        "        downloadButton.href = downloadUrl;\n" .
        "        downloadButton.className = 'page-title-action';\n" .
        "        downloadButton.textContent = buttonLabel;\n" .
        "\n" .
        "        exportButton.insertAdjacentElement('afterend', downloadButton);\n" .
        "        return true;\n" .
        "    }\n" .
        "\n" .
        "    function boot() {\n" .
        "        if (injectDownloadButton()) {\n" .
        "            return;\n" .
        "        }\n" .
        "\n" .
        "        var observer = new MutationObserver(function () {\n" .
        "            if (injectDownloadButton()) {\n" .
        "                observer.disconnect();\n" .
        "            }\n" .
        "        });\n" .
        "\n" .
        "        observer.observe(document.body, { childList: true, subtree: true });\n" .
        "        window.setTimeout(function () {\n" .
        "            observer.disconnect();\n" .
        "        }, 10000);\n" .
        "    }\n" .
        "\n" .
        "    if (document.readyState === 'loading') {\n" .
        "        document.addEventListener('DOMContentLoaded', boot);\n" .
        "    } else {\n" .
        "        boot();\n" .
        "    }\n" .
        "}());",
        wp_json_encode($download_url),
        wp_json_encode(__('Download CSV', 'devicehub-theme'))
    );

    wp_add_inline_script('woocommerce_admin', $script, 'after');
}

function devhub_handle_product_csv_download(): void
{
    if (!current_user_can('edit_products')) {
        wp_die(
            esc_html__('You do not have permission to download this file.', 'devicehub-theme'),
            esc_html__('Forbidden', 'devicehub-theme'),
            array('response' => 403)
        );
    }

    check_admin_referer('devhub_download_product_csv');

    $csv_path = devhub_get_product_csv_download_path();

    if (!file_exists($csv_path) || !is_readable($csv_path)) {
        wp_die(
            esc_html__('The CSV file could not be found.', 'devicehub-theme'),
            esc_html__('File not found', 'devicehub-theme'),
            array('response' => 404)
        );
    }

    nocache_headers();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="wc-import-template.csv"');
    header('Content-Length: ' . (string) filesize($csv_path));

    readfile($csv_path);
    exit;
}

function devhub_get_product_csv_download_path(): string
{
    $candidates = array(
        trailingslashit(DEVHUB_DIR) . 'wc-import-template.csv',
        trailingslashit(DEVHUB_DIR) . 'assets/files/wc-import-template.csv',
        trailingslashit(DEVHUB_DIR) . 'assets/downloads/wc-import-template.csv',
    );

    $path = '';

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            $path = $candidate;
            break;
        }
    }

    return (string) apply_filters('devhub_product_csv_download_path', $path, $candidates);
}

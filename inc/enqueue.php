<?php
/**
 * DeviceHub — Enqueue
 *
 * All wp_enqueue_style and wp_enqueue_script calls live here.
 * Nothing else.
 *
 * CSS naming rules:
 *  - components/   → no prefix  (footer.css, product-card.css)
 *  - everything else → devhub-  (devhub-hero-section.css, devhub-cart.css)
 *
 * Loading strategy:
 *  - style.css     → always (WP requirement + tokens + Shopire overrides)
 *  - components/   → always (header/footer on every page, product-card when needed)
 *  - page-specific → only on that page type
 *  - JS modules    → only on the page that needs them, in footer
 *
 * devhub_style() and devhub_script() silently skip missing files —
 * no fatal errors if a file hasn't been created yet during development.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;


// ── Block parent theme enqueue — run at priority 5, before Shopire's 10 ──────
// Shopire's functions.php registers shopire_scripts and shopire_google_fonts_scripts_styles
// at default priority 10. Without this, both parent and child styles load together,
// causing mixed/conflicting styles when DeviceHub theme is active.
add_action('wp_enqueue_scripts', function () {
    remove_action('wp_enqueue_scripts', 'shopire_scripts', 10);
    remove_action('wp_enqueue_scripts', 'shopire_google_fonts_scripts_styles', 10);
}, 5);


// ── Styles ───────────────────────────────────────────────────────────────────

add_action('wp_enqueue_scripts', 'devhub_enqueue_styles', 20);
add_action('wp_enqueue_scripts', 'devhub_footer_option_styles', 30);

function devhub_enqueue_styles(): void
{

    // ── Google Fonts ──────────────────────────────────────────────────────────
    // Plus Jakarta Sans — entire UI
    wp_enqueue_style(
        'devhub-google-fonts',
        'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    // ── Vendor ────────────────────────────────────────────────────────────────
    wp_enqueue_style('owl-carousel-min', DEVHUB_URI . '/assets/vendors/css/owl.carousel.min.css');
    wp_enqueue_style('font-awesome', DEVHUB_URI . '/assets/vendors/css/all.min.css');
    wp_enqueue_style('animate', DEVHUB_URI . '/assets/vendors/css/animate.css');
    wp_enqueue_style('fancybox', DEVHUB_URI . '/assets/vendors/css/jquery.fancybox.min.css');

    // ── Shopire base (keep as separate files — do not merge) ──────────────────
    wp_enqueue_style('shopire-core', DEVHUB_URI . '/assets/css/core.css');
    wp_enqueue_style('shopire-theme', DEVHUB_URI . '/assets/css/themes.css');
    wp_enqueue_style('shopire-woocommerce', DEVHUB_URI . '/assets/css/woo-styles.css');

    // ── style.css — always loaded ─────────────────────────────────────────────
    // Contains: design tokens + reset + shared utilities + Shopire overrides.
    // All devhub component CSS depends on the tokens defined here.
    wp_enqueue_style('devhub-style', get_stylesheet_uri(), [], DEVHUB_VERSION);

    // ── Page loader — critical CSS inlined so it renders before external files ─
    wp_add_inline_style('devhub-style', '
        #devhub-page-loader {
            position: fixed;
            inset: 0;
            z-index: 999999;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        #devhub-page-loader.is-ready {
            opacity: 0;
            visibility: hidden;
        }
        #devhub-page-loader__spinner {
            width: 42px;
            height: 42px;
            border: 3px solid #f0f0f0;
            border-top-color: #ff6600;
            border-radius: 50%;
            animation: devhub-loader-spin 0.7s linear infinite;
        }
        @keyframes devhub-loader-spin {
            to { transform: rotate(360deg); }
        }
    ');

    // ── Components — always loaded ────────────────────────────────────────────
    devhub_style('devhub-header', '/components/header.css', ['devhub-style']);
    devhub_style('devhub-footer', '/components/footer.css', ['devhub-style']);
    devhub_style('devhub-product-card', '/components/product-card.css', ['devhub-style']);
    devhub_style('devhub-toast', '/components/toast.css', ['devhub-style']);
    wp_add_inline_style('devhub-header', '
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu > li > a,
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu li > a {
            position: relative;
            min-height: 54px;
            line-height: 1.3 !important;
            padding-top: 14px !important;
            padding-bottom: 14px !important;
            font-size: var(--devhub-text-md) !important;
            font-weight: 600 !important;
            color: var(--devhub-dark) !important;
            background: var(--devhub-white) !important;
        }
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu > li.menu-item-has-children > a,
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu li.menu-item-has-children > a {
            padding-right: 44px !important;
        }
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu > li.menu-item-has-children > a::after,
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu li.menu-item-has-children > a::after {
            right: 18px !important;
            top: 50% !important;
            padding: 4px !important;
            border-width: 0 2px 2px 0 !important;
            border-color: var(--devhub-primary) !important;
            transform: translateY(-50%) rotate(-45deg) !important;
        }
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu > li:hover > a,
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu > li:focus-within > a,
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu li:hover > a,
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu li:focus-within > a {
            color: var(--devhub-primary) !important;
            background: rgba(255, 107, 0, 0.08) !important;
        }
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu {
            min-width: 220px !important;
            padding: 10px !important;
            border: 1px solid rgba(228, 231, 236, 0.95) !important;
            border-radius: 0 !important;
            background: var(--devhub-white) !important;
            box-shadow: 0 18px 40px rgba(16, 24, 40, 0.12) !important;
        }
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu::before {
            top: 20px !important;
            left: -8px !important;
            width: 16px !important;
            height: 16px !important;
            border-left: 1px solid rgba(228, 231, 236, 0.95) !important;
            border-bottom: 1px solid rgba(228, 231, 236, 0.95) !important;
            background: var(--devhub-white) !important;
        }
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu li,
        #wf_header .product-categories .wf_navbar-nav .wf_navbar-mainmenu .dropdown-menu li > a {
            margin: 0 !important;
            border-bottom: 0 !important;
        }
    ');

    // ── Home page ─────────────────────────────────────────────────────────────
    if (is_front_page()) {
        devhub_style('devhub-hero', '/home/devhub-hero-section.css', ['devhub-style']);
        devhub_style('devhub-flash', '/home/devhub-flash-section.css', ['devhub-style']);
        devhub_style('devhub-products', '/home/devhub-products-section.css', ['devhub-style', 'devhub-product-card']);
        devhub_style('devhub-categories', '/home/devhub-categories.css', ['devhub-style']);
        devhub_style('devhub-preorder', '/home/devhub-preorder.css', ['devhub-style']);
        devhub_style('devhub-broadbands', '/home/devhub-broadbands.css', ['devhub-style', 'devhub-product-card']);
        devhub_script('devhub-hero-slider', '/modules/hero-slider.js', [], true);
    }

    // ── Shop / Archive / Brand taxonomy ──────────────────────────────────────
    if (devhub_is_shop_page() || devhub_is_product_category_page() || devhub_is_brand_page()) {
        devhub_style('devhub-archive', '/archive/devhub-archive.css', ['devhub-style', 'devhub-product-card']);
        devhub_style('devhub-filters', '/archive/devhub-filters.css', ['devhub-style']);
    }

    // ── Single product ────────────────────────────────────────────────────────
    if (is_search()) {
        devhub_style('devhub-search', '/archive/devhub-search.css', ['devhub-style', 'devhub-product-card']);
    }

    if (devhub_is_product_page()) {
        devhub_style('devhub-single', '/single/devhub-single.css', ['devhub-style']);
        wp_add_inline_style('devhub-single', '
            .devhub-single__price ins,
            .devhub-single__price ins * { text-decoration: none !important; border-bottom: none !important; }
            .devhub-single__price ins,
            .devhub-single__price ins .woocommerce-Price-amount,
            .devhub-single__price ins .woocommerce-Price-amount bdi,
            .devhub-single__price ins .woocommerce-Price-amount bdi * { font-size: var(--devhub-text-4xl) !important; font-weight: 800 !important; }
            .devhub-single__price--range,
            .devhub-single__price--range .woocommerce-Price-amount,
            .devhub-single__price--range .woocommerce-Price-amount bdi,
            .devhub-single__price--range .woocommerce-Price-amount bdi * { font-size: var(--devhub-text-4xl) !important; font-weight: 800 !important; }
            .devhub-single__price del,
            .devhub-single__price del .woocommerce-Price-amount,
            .devhub-single__price del .woocommerce-Price-amount bdi,
            .devhub-single__price del .woocommerce-Price-amount bdi * { font-size: 1.6rem !important; font-weight: 600 !important; opacity: 0.75; text-decoration: line-through !important; }
            .devhub-single__price > .woocommerce-Price-amount,
            .devhub-single__price > .woocommerce-Price-amount bdi,
            .devhub-single__price > .woocommerce-Price-amount bdi * { font-size: var(--devhub-text-4xl) !important; font-weight: 800 !important; }
        ');
    }

    // ── Cart ──────────────────────────────────────────────────────────────────
    if (devhub_is_cart_page()) {
        devhub_style('devhub-cart', '/cart/devhub-cart.css', ['devhub-style']);
    }

    // ── Checkout ──────────────────────────────────────────────────────────────
    if (devhub_is_checkout_page()) {
        devhub_style('devhub-checkout', '/checkout/devhub-checkout.css', ['devhub-style', 'wc-blocks-style', 'wc-blocks-packages-style']);
        if (!is_user_logged_in()) {
            devhub_style('devhub-account', '/account/devhub-account.css', ['devhub-style']);
        }
    }

    // ── My Account ────────────────────────────────────────────────────────────
    if (devhub_is_account_context()) {
        devhub_style('devhub-account', '/account/devhub-account.css', ['devhub-style']);
    }
}


// ── Scripts ───────────────────────────────────────────────────────────────────

function devhub_footer_option_styles(): void
{
    if (!wp_style_is('devhub-footer', 'enqueued') || !function_exists('devhub_get_footer_settings')) {
        return;
    }

    $settings = devhub_get_footer_settings();
    $background_color = sanitize_hex_color((string) ($settings['background_color'] ?? '#ff6600'));

    if (!$background_color) {
        return;
    }

    wp_add_inline_style(
        'devhub-footer',
        "#wf_footer.wf_footer--one{background-color:{$background_color};}"
    );
}

add_action('wp_enqueue_scripts', 'devhub_enqueue_scripts', 20);

function devhub_enqueue_scripts(): void
{

    // ── Vendor / Shopire base ─────────────────────────────────────────────────
    wp_enqueue_script('jquery');
    wp_enqueue_script('imagesloaded');
    wp_enqueue_script('owl-carousel', DEVHUB_URI . '/assets/vendors/js/owl.carousel.js', ['jquery'], null, true);
    wp_enqueue_script('wow', DEVHUB_URI . '/assets/vendors/js/wow.min.js', ['jquery'], null, true);
    wp_enqueue_script('fancybox', DEVHUB_URI . '/assets/vendors/js/jquery.fancybox.js', ['jquery'], null, true);
    wp_enqueue_script('shopire-theme', DEVHUB_URI . '/assets/js/theme.js', ['jquery'], null, true);
    wp_enqueue_script('shopire-custom', DEVHUB_URI . '/assets/js/custom.js', ['jquery'], null, true);
    devhub_script('devhub-page-loader', '/modules/page-loader.js', [], true);
    devhub_script('devhub-mobile-menu', '/modules/mobile-menu.js', [], true);
    devhub_script('devhub-toast', '/modules/toast.js', [], true);
    // ── DeviceHub API utility — always loaded ─────────────────────────────────
    // Exposes devhubConfig to all JS modules: nonce, restUrl, cartUrl, isLoggedIn
    devhub_script('devhub-utils', '/utils/api.js', [], true);
    wp_localize_script('devhub-utils', 'devhubConfig', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => esc_url_raw(rest_url(devhub_has_woocommerce() ? 'wc/v3/' : '')),
        'nonce' => wp_create_nonce('wp_rest'),
        'searchNonce' => wp_create_nonce('devhub_header_search'),
        'cartUrl' => devhub_has_woocommerce() && function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/'),
        'isLoggedIn' => is_user_logged_in(),
    ]);
    devhub_script('devhub-header-search', '/modules/header-search.js', ['devhub-utils'], true);
    if (function_exists('devhub_show_secondary_nav') && devhub_show_secondary_nav()) {
        devhub_script('devhub-secondary-nav', '/modules/secondary-nav.js', [], true);
    }
    devhub_script('devhub-motion', '/modules/motion.js', [], true);

    // ── Home ──────────────────────────────────────────────────────────────────
    if (is_front_page()) {
        devhub_script('devhub-flash-countdown', '/modules/flash-countdown.js', ['devhub-utils'], true);
        devhub_script('devhub-brand-filter', '/modules/brand-filter.js', [], true);
    }

    // ── Archive ───────────────────────────────────────────────────────────────
    if (devhub_is_shop_page() || devhub_is_product_category_page() || devhub_is_brand_page()) {
        devhub_script('devhub-filters', '/modules/filters.js', [], true);
    }

    // ── Single product ────────────────────────────────────────────────────────
    if (devhub_is_product_page()) {
        devhub_script('devhub-product', '/modules/product.js', ['devhub-toast'], true);
    }

    // ── Cart ──────────────────────────────────────────────────────────────────
    if (devhub_is_cart_page()) {
        devhub_script('devhub-cart', '/modules/cart.js', ['jquery', 'wc-cart-fragments', 'devhub-utils'], true);
    }

    // ── Checkout ──────────────────────────────────────────────────────────────
    if (devhub_is_checkout_page()) {
        devhub_script('devhub-checkout', '/modules/checkout.js', ['devhub-utils'], true);
        if (!is_user_logged_in()) {
            devhub_script('devhub-login', '/modules/login.js', [], true);
            devhub_localize_login_script();
        }
        wp_localize_script('devhub-checkout', 'devhubCheckoutData', [
            'accountEmail' => is_user_logged_in() ? wp_get_current_user()->user_email : '',
            'billingEmail' => WC()->customer ? WC()->customer->get_billing_email() : '',
            'fields' => [
                'deliveryMethod' => defined('DEVHUB_CHECKOUT_DELIVERY_METHOD_FIELD') ? DEVHUB_CHECKOUT_DELIVERY_METHOD_FIELD : 'devicehub/delivery_method',
                'pickupStore'    => defined('DEVHUB_CHECKOUT_PICKUP_STORE_FIELD') ? DEVHUB_CHECKOUT_PICKUP_STORE_FIELD : 'devicehub/pickup_store',
                'billingEmail'   => defined('DEVHUB_CHECKOUT_BILLING_EMAIL_FIELD') ? DEVHUB_CHECKOUT_BILLING_EMAIL_FIELD : 'devicehub/billing_email',
            ],
            'pickupLocations' => function_exists('devhub_get_checkout_pickup_locations') ? devhub_get_checkout_pickup_locations() : [],
            'messages' => [
                'title'             => __('Your Delivery Method', 'devicehub-theme'),
                'pickupTitle'       => __('Pick up at store', 'devicehub-theme'),
                'pickupSubtitle'    => __('Select the Hutch location for collection.', 'devicehub-theme'),
                'searchPlaceholder' => __('Search stores', 'devicehub-theme'),
                'searchHelp'        => __('Search for your nearest Hutch store.', 'devicehub-theme'),
                'pickupLabel'       => __('Pick Up at Store', 'devicehub-theme'),
                'pickupHint'        => __('Collect from a Hutch service location.', 'devicehub-theme'),
                'deliveryLabel'     => __('Home Delivery', 'devicehub-theme'),
                'deliveryHint'      => __('Delivery via courier to the billing address.', 'devicehub-theme'),
                'pickupRequired'    => __('Please select a pickup store to continue.', 'devicehub-theme'),
                'emptySearch'       => __('No stores match your search.', 'devicehub-theme'),
                'pickupUnavailable' => __('Pickup is currently unavailable.', 'devicehub-theme'),
            ],
        ]);
    }

    // ── My Account ────────────────────────────────────────────────────────────
    if (devhub_is_account_context()) {
        devhub_script('devhub-login', '/modules/login.js', [], true);
        devhub_localize_login_script();
        devhub_script('devhub-account', '/modules/account.js', [], true);
    }

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}

/**
 * Localize the auth-panel script with mobile OTP config.
 */
function devhub_localize_login_script(): void
{
    if (!wp_script_is('devhub-login', 'enqueued')) {
        return;
    }

    wp_localize_script('devhub-login', 'devhubLoginData', [
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('devhub_mobile_auth'),
        'redirectUrl' => function_exists('devhub_get_auth_success_redirect_url')
            ? devhub_get_auth_success_redirect_url()
            : home_url('/'),
        'messages'    => [
            'requestError' => __('We could not send your OTP right now. Please try again.', 'devicehub-theme'),
            'verifyError'  => __('We could not verify that OTP. Please try again.', 'devicehub-theme'),
            'sendEmailOtp' => __('Send OTP', 'devicehub-theme'),
            'resendEmailOtp' => __('Resend OTP', 'devicehub-theme'),
            'emailOtpInvalidEmail' => __('Enter a valid email address first.', 'devicehub-theme'),
            'emailOtpSent' => __('Verification code sent. Check your email.', 'devicehub-theme'),
            'emailOtpRequestError' => __('We could not send your verification email right now. Please try again.', 'devicehub-theme'),
        ],
    ]);
}


// ── Admin ─────────────────────────────────────────────────────────────────────

add_action('admin_enqueue_scripts', 'devhub_admin_enqueue_scripts');

function devhub_admin_enqueue_scripts(): void
{
    wp_enqueue_style('devhub-admin', DEVHUB_URI . '/inc/admin/assets/css/admin.css');
    wp_enqueue_script('devhub-admin', DEVHUB_URI . '/inc/admin/assets/js/shopire-admin-script.js', ['jquery'], null, true);
    wp_localize_script('devhub-admin', 'devhubAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('devhub_nonce'),
    ]);
}


// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Enqueue a DeviceHub stylesheet with automatic cache-busting.
 * Path is relative to assets/css/.
 * Silently skips missing files — safe to call before the file is created.
 */
function devhub_style(string $handle, string $path, array $deps = []): void
{
    $full_path = DEVHUB_DIR . '/assets/css' . $path;
    if (!file_exists($full_path))
        return;

    wp_enqueue_style(
        $handle,
        DEVHUB_URI . '/assets/css' . $path,
        $deps,
        filemtime($full_path)
    );
}

/**
 * Enqueue a DeviceHub script with automatic cache-busting.
 * Path is relative to assets/js/.
 * Silently skips missing files.
 */
function devhub_script(string $handle, string $path, array $deps = [], bool $in_footer = true): void
{
    $full_path = DEVHUB_DIR . '/assets/js' . $path;
    if (!file_exists($full_path))
        return;

    wp_enqueue_script(
        $handle,
        DEVHUB_URI . '/assets/js' . $path,
        $deps,
        filemtime($full_path),
        $in_footer
    );
}

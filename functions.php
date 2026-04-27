<?php
/**
 * DeviceHub Theme — functions.php
 *
 * This file is an orchestrator only.
 * No logic lives here. All logic lives in inc/.
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

// ── Constants ────────────────────────────────────────────────────────────────

define('DEVHUB_VERSION', wp_get_theme()->get('Version'));
define('DEVHUB_DIR', get_template_directory());
define('DEVHUB_URI', get_template_directory_uri());
define('DEVHUB_INC_DIR', DEVHUB_DIR . '/inc');
define('DEVHUB_INC_URI', DEVHUB_URI . '/inc');

// Shopire legacy constants — required by inc/customizer/ files which reference
// SHOPIRE_THEME_INC_DIR directly. Do not remove until those files are rewritten.
define('SHOPIRE_THEME_VERSION', DEVHUB_VERSION);
define('SHOPIRE_THEME_DIR', DEVHUB_DIR);
define('SHOPIRE_THEME_URI', DEVHUB_URI);
define('SHOPIRE_THEME_INC_DIR', DEVHUB_INC_DIR);
define('SHOPIRE_THEME_INC_URI', DEVHUB_INC_URI);

// ── Core (load order matters) ────────────────────────────────────────────────

require_once DEVHUB_INC_DIR . '/setup.php';       // Theme supports, nav menus, image sizes
require_once DEVHUB_INC_DIR . '/widgets.php';     // register_sidebar calls
require_once DEVHUB_INC_DIR . '/enqueue.php';     // All wp_enqueue_style / wp_enqueue_script
require_once DEVHUB_INC_DIR . '/helpers.php';     // Reusable utility functions (no output)
require_once DEVHUB_INC_DIR . '/hooks.php';       // All add_action / remove_action overrides
require_once DEVHUB_INC_DIR . '/checkout-delivery.php'; // Block checkout delivery method UI/data
require_once DEVHUB_INC_DIR . '/checkout-auth.php'; // Block checkout auth gate for unauthenticated users
require_once DEVHUB_INC_DIR . '/checkout-receipt.php'; // Order-received page invoice download button
require_once DEVHUB_INC_DIR . '/mobile-auth.php'; // Mobile OTP auth handlers for phone signup/login
require_once DEVHUB_INC_DIR . '/email-registration-otp.php'; // Email OTP verification for account creation
require_once DEVHUB_INC_DIR . '/payment-support.php'; // WooCommerce-driven payment methods + retry handling
require_once DEVHUB_INC_DIR . '/pickup-code.php'; // Pickup code generation + customer/admin output
require_once DEVHUB_INC_DIR . '/hero-slides.php'; // Admin-managed homepage hero slides
require_once DEVHUB_INC_DIR . '/promo-banners.php'; // Admin-managed homepage promo banners
// require_once DEVHUB_INC_DIR . '/category-showcase.php'; // disabled — hidden from admin
require_once DEVHUB_INC_DIR . '/footer-settings.php'; // Admin-managed footer settings page

// ── Parent theme integrations (keep from Shopire base) ──────────────────────

require_once DEVHUB_INC_DIR . '/customizer/shopire-customizer.php';
require_once DEVHUB_INC_DIR . '/customizer/controls/code/customizer-repeater/inc/customizer.php';
require_once DEVHUB_INC_DIR . '/customizer/controls/code/control-function/style-functions.php';
require_once DEVHUB_INC_DIR . '/class-wp-bootstrap-navwalker.php';
require_once DEVHUB_INC_DIR . '/custom-header.php';
require_once DEVHUB_INC_DIR . '/template-tags.php';
require_once DEVHUB_INC_DIR . '/admin/getting-started.php';

// ── Page section hooks ───────────────────────────────────────────────────────

require_once DEVHUB_DIR . '/hooks/home.php';
require_once DEVHUB_DIR . '/hooks/flash.php';
require_once DEVHUB_DIR . '/hooks/products.php';

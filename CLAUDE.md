# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Strict Scope Rule

**Only modify files inside this theme directory (`devicehub-theme-dev/`).** Never edit files in plugins, the parent theme (Shopire), or anywhere else outside this theme. If a change requires touching a plugin or parent theme file, implement it via WordPress hooks/filters inside this theme instead (e.g. `inc/hooks.php`).

## Overview

DeviceHub Theme is a custom WordPress WooCommerce theme for HUTCH Device Hub, built as a child of the Shopire parent theme (WPFable). It targets Sri Lankan e-commerce (LKR currency). No build pipeline — all CSS/JS are served directly.

## Development Environment

This theme runs on a LocalWP instance. The WordPress root is at:

```
c:\Users\M Eran Hasareli\Local Sites\customer-journey\app\public\
```

There is no npm, composer, webpack, or any build step. Edit files directly.

### PHP Version

**PHP 8.1.34** — use PHP 8.1-compatible syntax only.

- Use `true`/`false`/`null` (lowercase) — PHP 8.1 deprecates the uppercase `TRUE`/`FALSE`/`NULL` constants
- Enums, fibers, readonly properties, intersection types, and `never` return type are all available (PHP 8.1+)
- Do **not** use PHP 8.2+ features (e.g. readonly classes, `null`/`false`/`true` standalone types)
- Named arguments, match expressions, nullsafe operator (`?->`), union types — all fine (PHP 8.0+)
- Arrow functions, typed properties — all fine (PHP 7.4+)

## Theme Architecture

### Entry Point

`functions.php` is a pure orchestrator — no logic, only `require` statements. All functionality lives in:

| File                 | Responsibility                                          |
| -------------------- | ------------------------------------------------------- |
| `inc/setup.php`      | Theme supports, nav menus (`primary_menu`), image sizes |
| `inc/enqueue.php`    | All CSS/JS asset loading (page-specific, cache-busted)  |
| `inc/helpers.php`    | Product helpers + `devhub_render_product_card()`        |
| `inc/hooks.php`      | Shopire/WooCommerce hook overrides, currency filter     |
| `inc/widgets.php`    | Sidebar registration                                    |
| `hooks/home.php`     | Hero, categories, preorder section renderers            |
| `hooks/flash.php`    | Flash sale section renderer                             |
| `hooks/products.php` | Mobile phones & broadbands section renderers            |

### Hook-Driven Home Page

`page-templates/frontpage.php` fires action hooks; rendering functions live in `hooks/`:

```php
do_action('devhub_hero_section');      // hooks/home.php
do_action('devhub_flash_section');     // hooks/flash.php
do_action('devhub_products_section');  // hooks/products.php
do_action('devhub_categories_section'); // hooks/home.php
do_action('devhub_preorder_section');  // hooks/home.php
do_action('devhub_broadbands_section'); // hooks/products.php
```

To add or modify a home page section: edit the corresponding hook file and add/modify the renderer function.

### Product Cards

All product cards are rendered via `devhub_render_product_card($product)` in `inc/helpers.php`. It deliberately avoids `get_template_part()` to allow passing the `$product` object directly. Use this function everywhere product cards appear.

### Asset Loading Pattern

`inc/enqueue.php` provides two helpers:

- `devhub_style($handle, $path, $deps)` — enqueues CSS with `filemtime` cache-busting, silently skips missing files
- `devhub_script($handle, $path, $deps, $in_footer)` — same for JS

Assets are loaded conditionally per page context (`is_front_page()`, `is_shop()`, `is_product()`, etc.). Add new page-specific assets using these helpers in the appropriate conditional block in `inc/enqueue.php`.

A global `devhubConfig` JS object is always available via the `devhub-utils` handle, containing:

```js
devhubConfig.ajaxUrl; // wp-admin/admin-ajax.php
devhubConfig.restUrl; // WC REST API v3 base URL
devhubConfig.nonce; // WC REST nonce
devhubConfig.cartUrl;
devhubConfig.isLoggedIn;
```

### CSS Architecture

- **Design tokens:** All CSS custom properties (colors, typography) are defined in `style.css` `:root` — this is the single source of truth
- **`assets/css/components/`** — always-loaded (header, footer, product-card)
- **`assets/css/home/`**, **`archive/`**, **`single/`**, **`cart/`**, **`checkout/`**, **`account/`** — page-specific
- **Shopire base** (`core.css`, `themes.css`, `woo-styles.css`) is kept separate for easy parent theme upgrades

### WooCommerce

- **Shop/Archive:** Custom `woocommerce/archive-product.php` enforced via `woocommerce_locate_template` filter in `inc/hooks.php`
- **Brand filtering:** Uses `pwb-brand` taxonomy (PWB Brands plugin); brand slugs exposed as `data-brands` attribute on product cards
- **Bundle flag:** `devhub_bundles` post meta controls bundle label on product cards
- **Currency:** LKR symbol overridden to display as "LKR" via `woocommerce_currency_symbol` filter

### Parent Theme (Shopire)

Legacy `SHOPIRE_THEME_*` constants are bridged in `functions.php` for backward compatibility with customizer files. Customizer controls for hero content use keys: `devhub_hero_eyebrow`, `devhub_hero_title`, `devhub_hero_subtitle`.

## Known Incomplete Work

- **Flash sale section** (`hooks/flash.php`): hardcoded dummy data — needs WooCommerce integration
- **Mobile phones & broadbands sections** (`hooks/products.php`): hardcoded dummy data — needs WooCommerce integration
- **`assets/js/utils/api.js`**: referenced in `inc/enqueue.php` but not yet created
- **Footer links** (`footer.php`): company/contact links are hardcoded and need updating

## Key Constants

Defined in `functions.php`:

```php
DEVHUB_VERSION   // theme version string
DEVHUB_DIR       // absolute path to theme root
DEVHUB_URI       // URL to theme root
DEVHUB_INC_DIR   // path to /inc/
```

Upload the slide image using the Hero Banner Image box.

Recommended desktop image size: 1920 x 493 px.

Upload a portrait/square image in the Mobile Image box for better mobile display.

Recommended mobile image size: 2560 x 1261 px.

Use the Order field in Page Attributes to control slide order.

Slides are identified automatically in the admin list, so no slide name is required.

Required before publish:

Banner Area
Banner Image (Featured Image)
Recommended desktop image size: 2560 x 276 px.

Optional: add a taller Mobile Image for screens 767px and below.

Recommended mobile image size: 2560 x 559 px.

Use the Order field in Page Attributes to control banner order inside the same area.

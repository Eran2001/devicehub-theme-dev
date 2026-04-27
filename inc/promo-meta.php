<?php
/**
 * DeviceHub — PromoForge extra admin fields
 *
 * Adds missing fields to the promoforge_flash CPT edit screen:
 *   - Always shows PromoForge's own Start Date field (hidden by default for Flash type)
 *   - Tagline
 *   - Priority (conflict resolution — higher wins)
 *   - Active / Visible toggle (show/hide without unpublishing)
 *   - Fixed Promo Price (overrides % discount when set)
 *   - Variant Targeting (per-variation promo prices)
 *
 * Data is stored as post meta and read by inc/promo-engine.php.
 * PromoForge's own pricing engine is untouched for % discount fallback.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;


// ── Un-hide PromoForge's built-in Start Date for all offer types ──────────────
// PromoForge hides it for 'flash' via CSS class + JS toggle. We override both.

add_action( 'admin_head', 'devhub_promo_unhide_start_date' );

function devhub_promo_unhide_start_date(): void {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'promoforge_flash' ) {
        return;
    }
    ?>
    <style id="devhub-promo-start-fix">
        /* Always show the start date field regardless of offer type */
        .promoforge_offer_start_fields,
        .promoforge_offer_start_fields.promoforge-display-none {
            display: block !important;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Force start date visible on load
        var startRow = document.querySelector('.promoforge_offer_start_fields');
        if (startRow) {
            startRow.style.cssText = 'display:block!important';
            startRow.classList.remove('promoforge-display-none');
        }
        // Re-show if PromoForge's JS hides it when offer type changes
        var typeSelect = document.getElementById('offer_type');
        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                var row = document.querySelector('.promoforge_offer_start_fields');
                if (row) {
                    row.style.cssText = 'display:block!important';
                    row.classList.remove('promoforge-display-none');
                }
            });
        }
    });
    </script>
    <?php
}


// ── Meta box registration ─────────────────────────────────────────────────────

add_action( 'add_meta_boxes', 'devhub_promo_register_meta_boxes' );

function devhub_promo_register_meta_boxes(): void {
    add_meta_box(
        'devhub_promo_extra',
        __( 'DeviceHub Promo Settings', 'devicehub-theme' ),
        'devhub_promo_meta_box_render',
        'promoforge_flash',
        'normal',
        'high'
    );
}


// ── Meta box render ───────────────────────────────────────────────────────────

function devhub_promo_meta_box_render( WP_Post $post ): void {
    wp_nonce_field( 'devhub_promo_save_meta', 'devhub_promo_nonce' );

    $tagline       = (string) get_post_meta( $post->ID, '_devhub_promo_tagline',      true );
    $priority      = get_post_meta( $post->ID, '_devhub_promo_priority',   true );
    $active        = get_post_meta( $post->ID, '_devhub_promo_active',     true );
    $fixed_price   = (string) get_post_meta( $post->ID, '_devhub_promo_fixed_price',  true );
    $variant_prices = get_post_meta( $post->ID, '_devhub_promo_variant_prices', true );

    if ( $priority === '' ) {
        $priority = '10';
    }
    // Active defaults to true for newly created offers
    if ( $active === '' ) {
        $active = true;
    } else {
        $active = (bool) $active;
    }
    if ( ! is_array( $variant_prices ) ) {
        $variant_prices = [];
    }

    // Load variation data for products assigned to this offer
    $offer_variations = devhub_promo_get_offer_variations( $post->ID );
    ?>
    <style>
        #devhub_promo_extra .dh-row { margin-bottom: 18px; }
        #devhub_promo_extra .dh-row label { display: block; font-weight: 600; margin-bottom: 4px; }
        #devhub_promo_extra .dh-desc { color: #646970; font-size: 12px; margin: 4px 0 0; }
        #devhub_promo_extra input[type="text"],
        #devhub_promo_extra input[type="number"] { width: 100%; max-width: 400px; }
        #devhub_promo_extra .dh-active-row { display: flex; align-items: center; gap: 8px; }
        #devhub_promo_extra .dh-active-row input { width: auto; margin: 0; }
        #devhub_promo_extra .dh-variants-table { border-collapse: collapse; width: 100%; margin-top: 8px; }
        #devhub_promo_extra .dh-variants-table th,
        #devhub_promo_extra .dh-variants-table td { padding: 6px 10px; border: 1px solid #ddd; font-size: 13px; }
        #devhub_promo_extra .dh-variants-table th { background: #f6f7f7; font-weight: 600; text-align: left; }
        #devhub_promo_extra .dh-variants-table input[type="number"] { max-width: 140px; width: 100%; }
        #devhub_promo_extra .dh-no-products { color: #646970; font-style: italic; }
    </style>

    <div class="dh-row">
        <label for="devhub_promo_tagline"><?php esc_html_e( 'Tagline', 'devicehub-theme' ); ?></label>
        <input
            type="text"
            id="devhub_promo_tagline"
            name="devhub_promo_tagline"
            value="<?php echo esc_attr( $tagline ); ?>"
            placeholder="<?php esc_attr_e( 'e.g. Limited time — grab it now!', 'devicehub-theme' ); ?>"
        >
        <p class="dh-desc"><?php esc_html_e( 'Short marketing line shown on product and flash sale cards.', 'devicehub-theme' ); ?></p>
    </div>

    <div class="dh-row">
        <label for="devhub_promo_priority"><?php esc_html_e( 'Priority', 'devicehub-theme' ); ?></label>
        <input
            type="number"
            id="devhub_promo_priority"
            name="devhub_promo_priority"
            value="<?php echo esc_attr( $priority ); ?>"
            min="1"
            max="100"
            step="1"
        >
        <p class="dh-desc"><?php esc_html_e( 'When multiple promos apply to the same product, the highest priority wins. Default: 10.', 'devicehub-theme' ); ?></p>
    </div>

    <div class="dh-row">
        <div class="dh-active-row">
            <input
                type="checkbox"
                id="devhub_promo_active"
                name="devhub_promo_active"
                value="1"
                <?php checked( $active, true ); ?>
            >
            <label for="devhub_promo_active" style="display:inline;font-weight:600;">
                <?php esc_html_e( 'Promo is active (visible on storefront)', 'devicehub-theme' ); ?>
            </label>
        </div>
        <p class="dh-desc"><?php esc_html_e( 'Uncheck to hide from the storefront without unpublishing. Useful for pausing a promo temporarily.', 'devicehub-theme' ); ?></p>
    </div>

    <div class="dh-row">
        <label for="devhub_promo_fixed_price"><?php esc_html_e( 'Fixed Promo Price (Rs.)', 'devicehub-theme' ); ?></label>
        <input
            type="number"
            id="devhub_promo_fixed_price"
            name="devhub_promo_fixed_price"
            value="<?php echo esc_attr( $fixed_price ); ?>"
            min="0"
            step="0.01"
            placeholder="<?php esc_attr_e( 'e.g. 49999.00', 'devicehub-theme' ); ?>"
        >
        <p class="dh-desc"><?php esc_html_e( 'Set a fixed sale price for all selected products. When set, this overrides the percentage discount above. Leave empty to use % discount.', 'devicehub-theme' ); ?></p>
    </div>

    <div class="dh-row">
        <label><?php esc_html_e( 'Variant Pricing (Color / Storage specific)', 'devicehub-theme' ); ?></label>
        <p class="dh-desc"><?php esc_html_e( 'Set a specific promo price per variation. Overrides both the % discount and the fixed price above for that variation. Leave empty to use the global promo price.', 'devicehub-theme' ); ?></p>

        <?php if ( empty( $offer_variations ) ): ?>
            <p class="dh-no-products"><?php esc_html_e( 'No variable products assigned to this offer yet. Assign products below and save to see their variants here.', 'devicehub-theme' ); ?></p>
        <?php else: ?>
            <table class="dh-variants-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Product', 'devicehub-theme' ); ?></th>
                        <th><?php esc_html_e( 'Variation', 'devicehub-theme' ); ?></th>
                        <th><?php esc_html_e( 'Regular Price (Rs.)', 'devicehub-theme' ); ?></th>
                        <th><?php esc_html_e( 'Promo Price (Rs.)', 'devicehub-theme' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $offer_variations as $row ): ?>
                        <tr>
                            <td><?php echo esc_html( $row['product_name'] ); ?></td>
                            <td><?php echo esc_html( $row['variation_label'] ); ?></td>
                            <td><?php echo esc_html( number_format( (float) $row['regular_price'], 2 ) ); ?></td>
                            <td>
                                <input
                                    type="number"
                                    name="devhub_variant_prices[<?php echo esc_attr( $row['variation_id'] ); ?>]"
                                    value="<?php echo esc_attr( $variant_prices[ $row['variation_id'] ] ?? '' ); ?>"
                                    min="0"
                                    step="0.01"
                                    placeholder="—"
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}


// ── Helper: get variation rows for products in this offer ─────────────────────

function devhub_promo_get_offer_variations( int $post_id ): array {
    global $wpdb;

    // Get the offer record
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $offer = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}promoforge_offers WHERE post_id = %d",
        $post_id
    ) );

    if ( ! $offer ) {
        return [];
    }

    // Get products assigned to this offer
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $product_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT product_id FROM {$wpdb->prefix}promoforge_offer_products WHERE offer_id = %d",
        $offer->id
    ) );

    if ( empty( $product_ids ) ) {
        return [];
    }

    $rows = [];

    foreach ( $product_ids as $product_id ) {
        $product = wc_get_product( (int) $product_id );
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            continue;
        }

        foreach ( $product->get_available_variations() as $variation_data ) {
            $variation = wc_get_product( $variation_data['variation_id'] );
            if ( ! $variation ) {
                continue;
            }

            // Build human-readable label from attributes
            $attr_parts = [];
            foreach ( $variation_data['attributes'] as $attr_key => $attr_val ) {
                $taxonomy = str_replace( 'attribute_', '', $attr_key );
                $label    = wc_attribute_label( $taxonomy, $product );
                $term     = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', $attr_val, $taxonomy ) : null;
                $value    = $term ? $term->name : $attr_val;
                if ( $value !== '' ) {
                    $attr_parts[] = $label . ': ' . $value;
                }
            }

            $rows[] = [
                'variation_id'   => $variation_data['variation_id'],
                'product_name'   => $product->get_name(),
                'variation_label' => $attr_parts ? implode( ', ', $attr_parts ) : __( 'Default', 'devicehub-theme' ),
                'regular_price'  => $variation->get_regular_price(),
            ];
        }
    }

    return $rows;
}


// ── Save meta ─────────────────────────────────────────────────────────────────

add_action( 'save_post_promoforge_flash', 'devhub_promo_save_meta', 10, 2 );

function devhub_promo_save_meta( int $post_id, WP_Post $post ): void {
    $nonce = isset( $_POST['devhub_promo_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['devhub_promo_nonce'] ) )
        : '';

    if ( ! wp_verify_nonce( $nonce, 'devhub_promo_save_meta' ) ) {
        return;
    }
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Tagline
    $tagline = isset( $_POST['devhub_promo_tagline'] )
        ? sanitize_text_field( wp_unslash( $_POST['devhub_promo_tagline'] ) )
        : '';
    update_post_meta( $post_id, '_devhub_promo_tagline', $tagline );

    // Priority
    $priority = isset( $_POST['devhub_promo_priority'] )
        ? absint( $_POST['devhub_promo_priority'] )
        : 10;
    $priority = max( 1, min( 100, $priority ) );
    update_post_meta( $post_id, '_devhub_promo_priority', $priority );

    // Active toggle
    $active = isset( $_POST['devhub_promo_active'] ) && $_POST['devhub_promo_active'] === '1';
    update_post_meta( $post_id, '_devhub_promo_active', $active );

    // Fixed price
    $fixed_price = '';
    if ( isset( $_POST['devhub_promo_fixed_price'] ) && $_POST['devhub_promo_fixed_price'] !== '' ) {
        $fixed_price = max( 0.0, (float) sanitize_text_field( wp_unslash( $_POST['devhub_promo_fixed_price'] ) ) );
    }
    update_post_meta( $post_id, '_devhub_promo_fixed_price', $fixed_price );

    // Variant prices
    $raw_variant_prices = isset( $_POST['devhub_variant_prices'] ) && is_array( $_POST['devhub_variant_prices'] )
        ? $_POST['devhub_variant_prices'] // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        : [];
    $clean_variant_prices = [];
    foreach ( $raw_variant_prices as $vid => $price ) {
        $vid   = absint( $vid );
        $price = sanitize_text_field( wp_unslash( (string) $price ) );
        if ( $vid > 0 && $price !== '' ) {
            $clean_variant_prices[ $vid ] = max( 0.0, (float) $price );
        }
    }
    update_post_meta( $post_id, '_devhub_promo_variant_prices', $clean_variant_prices );
}

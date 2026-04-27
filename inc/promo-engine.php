<?php
/**
 * DeviceHub — Promo Pricing Engine
 *
 * Extends PromoForge with:
 *   1. Active flag  — skip promo if admin deactivated it via the visibility toggle
 *   2. Priority     — when multiple promos cover the same product, highest priority wins
 *   3. Fixed price  — per-offer fixed promo price (overrides % discount)
 *   4. Variant price — per-variation promo price (overrides fixed price + % discount)
 *   5. Order integration — store the winning promo post ID on each order line item
 *
 * Hooks at priority 30, after PromoForge (priority 20), so PromoForge still runs
 * first. We then override the price when our meta says to.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;


// ── 1. Pricing override ───────────────────────────────────────────────────────

add_filter( 'woocommerce_product_get_price',               'devhub_promo_price_override', 30, 2 );
add_filter( 'woocommerce_product_get_sale_price',          'devhub_promo_price_override', 30, 2 );
add_filter( 'woocommerce_product_variation_get_price',     'devhub_promo_price_override', 30, 2 );
add_filter( 'woocommerce_product_variation_get_sale_price', 'devhub_promo_price_override', 30, 2 );

function devhub_promo_price_override( string $price, WC_Product $product ): string {
    // Avoid recursion when we call get_regular_price below
    static $in_override = false;
    if ( $in_override ) {
        return $price;
    }

    $promo = devhub_promo_get_winning_offer( $product );
    if ( $promo === null ) {
        return $price;
    }

    // Variation-level price takes top priority
    $variation_id  = $product->is_type( 'variation' ) ? $product->get_id() : 0;
    $variant_prices = $promo['variant_prices'];
    if ( $variation_id > 0 && isset( $variant_prices[ $variation_id ] ) && $variant_prices[ $variation_id ] !== '' ) {
        return (string) $variant_prices[ $variation_id ];
    }

    // Fixed offer price second
    if ( $promo['fixed_price'] !== '' ) {
        return (string) $promo['fixed_price'];
    }

    // Fall through to PromoForge's % discount (already applied at priority 20)
    return $price;
}


// ── 2. Find the winning offer for a product ───────────────────────────────────

/**
 * Returns our meta for the highest-priority active DeviceHub promo covering
 * this product, or null if none applies.
 *
 * @return array{post_id:int,priority:int,active:bool,fixed_price:mixed,variant_prices:array}|null
 */
function devhub_promo_get_winning_offer( WC_Product $product ): ?array {
    global $wpdb;

    $product_id = $product->is_type( 'variation' )
        ? $product->get_parent_id()
        : $product->get_id();

    // Guard: tables must exist
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}promoforge_offers'" ) ) {
        return null;
    }

    $now = current_time( 'mysql' );

    // Get all published PromoForge offers covering this product that are currently active
    // (started already and haven't expired yet)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT o.post_id, o.discount, o.end_date
           FROM {$wpdb->prefix}promoforge_offers o
           JOIN {$wpdb->prefix}promoforge_offer_products p ON o.id = p.offer_id
           JOIN {$wpdb->posts} wp ON o.post_id = wp.ID
          WHERE p.product_id = %d
            AND o.end_date > %s
            AND (o.start_date IS NULL OR o.start_date <= %s)
            AND wp.post_status = 'publish'",
        $product_id,
        $now,
        $now
    ) );

    if ( empty( $rows ) ) {
        return null;
    }

    $winning  = null;
    $best_pri = -1;

    foreach ( $rows as $row ) {
        $post_id = (int) $row->post_id;

        // Check our active flag — skip deactivated promos
        $active = get_post_meta( $post_id, '_devhub_promo_active', true );
        // Empty means the field was never set (old offer before our meta existed) → treat as active
        if ( $active !== '' && ! (bool) $active ) {
            continue;
        }

        $priority      = (int) ( get_post_meta( $post_id, '_devhub_promo_priority', true ) ?: 10 );
        $fixed_price   = get_post_meta( $post_id, '_devhub_promo_fixed_price', true );
        $variant_prices = get_post_meta( $post_id, '_devhub_promo_variant_prices', true );

        if ( ! is_array( $variant_prices ) ) {
            $variant_prices = [];
        }

        if ( $priority > $best_pri ) {
            $best_pri = $priority;
            $winning  = [
                'post_id'        => $post_id,
                'priority'       => $priority,
                'active'         => true,
                'fixed_price'    => $fixed_price,
                'variant_prices' => $variant_prices,
                'discount'       => (float) $row->discount,
                'end_date'       => $row->end_date,
            ];
        }
    }

    return $winning;
}


// ── 3. Order integration — store winning promo ID on each line item ───────────

add_action( 'woocommerce_checkout_create_order_line_item', 'devhub_promo_tag_order_line_item', 10, 4 );

function devhub_promo_tag_order_line_item(
    WC_Order_Item_Product $item,
    string $cart_item_key,
    array $values,
    WC_Order $order
): void {
    // PromoForge already tags the cart item with the applied offer ID
    if ( isset( $values['promoforge_applied_offer_id'] ) && $values['promoforge_applied_offer_id'] > 0 ) {
        $item->add_meta_data( '_devhub_promo_id', (int) $values['promoforge_applied_offer_id'], true );
        return;
    }

    // Fallback: resolve it ourselves via our winning-offer lookup
    $product = $item->get_product();
    if ( ! $product ) {
        return;
    }
    $promo = devhub_promo_get_winning_offer( $product );
    if ( $promo ) {
        $item->add_meta_data( '_devhub_promo_id', $promo['post_id'], true );
    }
}


// ── 4. Hide internal promo meta from order detail UI ─────────────────────────

add_filter( 'woocommerce_hidden_order_itemmeta', function ( array $hidden ): array {
    $hidden[] = '_devhub_promo_id';
    return $hidden;
} );

<?php
/**
 * Order details — DeviceHub override
 *
 * Title rendered outside the card; table rendered inside a rounded card.
 *
 * Based on WooCommerce template version 10.1.0.
 *
 * @package DeviceHub
 */

// phpcs:disable WooCommerce.Commenting.CommentHooks.MissingHookComment

defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

if ( ! $order ) {
    return;
}

$order_items        = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
$show_purchase_note = $order->has_status( apply_filters( 'woocommerce_purchase_note_order_statuses', [ 'completed', 'processing' ] ) );
$downloads          = $order->get_downloadable_items();
$bundle_rows        = function_exists( 'devhub_get_order_bundle_rows_by_item' ) ? devhub_get_order_bundle_rows_by_item( $order ) : [];
$bundle_total       = function_exists( 'devhub_get_order_bundle_rows_total' ) ? devhub_get_order_bundle_rows_total( $bundle_rows ) : 0.0;
$actions            = array_filter(
    wc_get_account_orders_actions( $order ),
    fn( $key ) => 'view' !== $key,
    ARRAY_FILTER_USE_KEY
);

$show_customer_details = $order->get_user_id() === get_current_user_id();

if ( $show_downloads ) {
    wc_get_template( 'order/order-downloads.php', [
        'downloads'  => $downloads,
        'show_title' => true,
    ] );
}
?>

<section class="woocommerce-order-details">
    <?php do_action( 'woocommerce_order_details_before_order_table', $order ); ?>

    <h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order details', 'woocommerce' ); ?></h2>

    <div class="devhub-order-table-card">
        <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">

            <thead>
                <tr>
                    <th class="woocommerce-table__product-name product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
                    <th class="woocommerce-table__product-table product-total"><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php
                do_action( 'woocommerce_order_details_before_order_table_items', $order );

                foreach ( $order_items as $item_id => $item ) {
                    $product = $item->get_product();
                    wc_get_template( 'order/order-details-item.php', [
                        'order'              => $order,
                        'item_id'            => $item_id,
                        'item'               => $item,
                        'show_purchase_note' => $show_purchase_note,
                        'purchase_note'      => $product ? $product->get_purchase_note() : '',
                        'product'            => $product,
                    ] );

                    if ( isset( $bundle_rows[ $item_id ] ) ) {
                        $bundle_row = $bundle_rows[ $item_id ];
                        ?>
                        <tr class="woocommerce-table__line-item order_item devhub-order-bundle-fee">
                            <td class="woocommerce-table__product-name product-name">
                                <strong><?php echo esc_html( $bundle_row['name'] ); ?>:</strong>
                            </td>
                            <td class="woocommerce-table__product-total product-total">
                                <?php echo wp_kses_post( wc_price( (float) $bundle_row['amount'], [ 'currency' => $order->get_currency() ] ) ); ?>
                            </td>
                        </tr>
                        <?php
                    }
                }

                do_action( 'woocommerce_order_details_after_order_table_items', $order );
                ?>
            </tbody>

            <?php if ( ! empty( $actions ) ) : ?>
            <tfoot>
                <tr>
                    <th class="order-actions--heading"><?php esc_html_e( 'Actions', 'woocommerce' ); ?>:</th>
                    <td>
                        <?php
                        $wp_button_class = wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '';
                        foreach ( $actions as $key => $action ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
                            $action_aria_label = empty( $action['aria-label'] )
                                ? sprintf( __( '%1$s order number %2$s', 'woocommerce' ), $action['name'], $order->get_order_number() )
                                : $action['aria-label'];
                            echo '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button' . esc_attr( $wp_button_class ) . ' button ' . sanitize_html_class( $key ) . ' order-actions-button" aria-label="' . esc_attr( $action_aria_label ) . '">' . esc_html( $action['name'] ) . '</a>';
                        }
                        ?>
                    </td>
                </tr>
            </tfoot>
            <?php endif; ?>

            <tfoot>
                <?php foreach ( $order->get_order_item_totals() as $key => $total ) : ?>
                    <?php
                    if ( function_exists( 'devhub_is_order_total_bundle_fee_row' ) && devhub_is_order_total_bundle_fee_row( (string) $key, $total, $bundle_rows ) ) {
                        continue;
                    }

                    if ( 'cart_subtotal' === $key && $bundle_total > 0.0 ) {
                        $total['value'] = wc_price( $order->get_subtotal() + $bundle_total, [ 'currency' => $order->get_currency() ] );
                    }
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html( $total['label'] ); ?></th>
                        <td><?php echo wp_kses_post( $total['value'] ); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ( $order->get_customer_note() ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Note:', 'woocommerce' ); ?></th>
                        <td><?php echo wp_kses( nl2br( wc_wptexturize_order_note( $order->get_customer_note() ) ), [ 'br' => [] ] ); ?></td>
                    </tr>
                <?php endif; ?>
            </tfoot>

        </table>
    </div><!-- .devhub-order-table-card -->

    <?php do_action( 'woocommerce_order_details_after_order_table', $order ); ?>
</section>

<?php
do_action( 'woocommerce_after_order_details', $order );

if ( $show_customer_details ) {
    wc_get_template( 'order/order-details-customer.php', [ 'order' => $order ] );
}

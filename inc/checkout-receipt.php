<?php
/**
 * DeviceHub — Order receipt helpers.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_thankyou', function ( int $order_id ): void {
    $order = wc_get_order( $order_id );
    if ( ! $order instanceof WC_Order ) {
        return;
    }
    $date_created = $order->get_date_created();
    if ( ! $date_created ) {
        return;
    }
    $time_str = $date_created->date_i18n( wc_time_format() );
    echo '<script>window.devhubOrderTime = ' . wp_json_encode( $time_str ) . ';</script>';
} );

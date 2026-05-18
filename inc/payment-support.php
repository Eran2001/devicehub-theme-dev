<?php
/**
 * WooCommerce payment support helpers.
 *
 * Implements:
 * - Dynamic payment method discovery from enabled WooCommerce gateways.
 * - Payment retry tracking within the current WooCommerce session.
 * - Automatic order cancellation after repeated payment failures so WooCommerce
 *   can restore/release reserved stock using its normal hooks.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;

const DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS = 3;
const DEVHUB_PAYMENT_RETRY_SESSION_KEY  = 'devhub_payment_retry_attempts';

add_action( 'woocommerce_order_status_failed', 'devhub_handle_failed_payment_retry', 10, 2 );
add_action( 'woocommerce_payment_complete', 'devhub_clear_payment_retry_attempts', 10, 1 );
add_action( 'woocommerce_order_status_cancelled', 'devhub_clear_payment_retry_attempts', 10, 1 );
add_action( 'woocommerce_order_status_processing', 'devhub_clear_payment_retry_attempts', 10, 1 );
add_action( 'woocommerce_order_status_completed', 'devhub_clear_payment_retry_attempts', 10, 1 );
add_action( 'woocommerce_order_status_on-hold', 'devhub_clear_payment_retry_attempts', 10, 1 );
add_action( 'before_woocommerce_pay', 'devhub_add_order_pay_retry_notice', 5 );
add_action( 'template_redirect', 'devhub_handle_direct_payment_retry', 1 );

add_filter( 'woocommerce_order_needs_payment', 'devhub_maybe_block_payment_after_retry_limit', 10, 3 );

/**
 * Return enabled WooCommerce payment gateways for display purposes.
 *
 * Falls back to enabled gateways when checkout-context availability cannot be
 * determined on non-checkout pages such as the product page.
 *
 * @return array<int, object>
 */
function devhub_get_enabled_payment_gateways(): array {
	if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
		return [];
	}

	$gateway_manager = WC()->payment_gateways();
	$gateways        = $gateway_manager->get_available_payment_gateways();

	if ( empty( $gateways ) ) {
		$gateways = $gateway_manager->payment_gateways();
	}

	$enabled_gateways = [];

	foreach ( $gateways as $gateway ) {
		if ( ! is_object( $gateway ) || empty( $gateway->id ) ) {
			continue;
		}

		$is_enabled = isset( $gateway->enabled ) ? 'yes' === $gateway->enabled : true;

		if ( ! $is_enabled ) {
			continue;
		}

		$enabled_gateways[] = $gateway;
	}

	return $enabled_gateways;
}

/**
 * Build unique payment method data for frontend display.
 *
 * @return array<int, array<string, string>>
 */
function devhub_get_payment_method_display_data(): array {
	$gateways = devhub_get_enabled_payment_gateways();
	$methods  = [];
	$seen     = [];

	foreach ( $gateways as $gateway ) {
		$title = trim( wp_strip_all_tags( (string) $gateway->get_title() ) );

		if ( '' === $title ) {
			$title = ucwords( str_replace( [ '-', '_' ], ' ', (string) $gateway->id ) );
		}

		$dedupe_key = sanitize_title( $title );

		if ( isset( $seen[ $dedupe_key ] ) ) {
			continue;
		}

		$seen[ $dedupe_key ] = true;
		$methods[]           = [
			'id'    => sanitize_html_class( (string) $gateway->id ),
			'title' => $title,
		];
	}

	return $methods;
}

/**
 * Build a direct retry URL that can send the customer back to the gateway
 * without first rendering WooCommerce's default order-pay page.
 *
 * @param WC_Order $order WooCommerce order.
 * @return string
 */
function devhub_get_direct_payment_retry_url( WC_Order $order ): string {
	return add_query_arg(
		[
			'devhub_retry_payment' => '1',
			'order_id'             => (string) $order->get_id(),
			'key'                  => (string) $order->get_order_key(),
		],
		$order->get_checkout_order_received_url()
	);
}

/**
 * Read the current session's retry counts.
 *
 * @return array<string, int>
 */
function devhub_get_payment_retry_attempts_map(): array {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return [];
	}

	$attempts = WC()->session->get( DEVHUB_PAYMENT_RETRY_SESSION_KEY, [] );

	if ( ! is_array( $attempts ) ) {
		return [];
	}

	return array_map( 'absint', $attempts );
}

/**
 * Persist retry counts for the current session.
 *
 * @param array<string, int> $attempts Retry counts keyed by order ID.
 * @return void
 */
function devhub_set_payment_retry_attempts_map( array $attempts ): void {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}

	WC()->session->set( DEVHUB_PAYMENT_RETRY_SESSION_KEY, $attempts );
}

/**
 * Get the retry count for a single order in the current session.
 *
 * @param int $order_id WooCommerce order ID.
 * @return int
 */
function devhub_get_payment_retry_attempts( int $order_id ): int {
	$attempts = devhub_get_payment_retry_attempts_map();
	return isset( $attempts[ (string) $order_id ] ) ? absint( $attempts[ (string) $order_id ] ) : 0;
}

/**
 * Increment the retry count for an order in the current session.
 *
 * @param int $order_id WooCommerce order ID.
 * @return int Updated attempt count.
 */
function devhub_increment_payment_retry_attempts( int $order_id ): int {
	$attempts               = devhub_get_payment_retry_attempts_map();
	$order_key              = (string) $order_id;
	$attempts[ $order_key ] = isset( $attempts[ $order_key ] ) ? absint( $attempts[ $order_key ] ) + 1 : 1;

	devhub_set_payment_retry_attempts_map( $attempts );

	return $attempts[ $order_key ];
}

/**
 * Clear retry tracking for an order in the current session.
 *
 * @param int $order_id WooCommerce order ID.
 * @return void
 */
function devhub_clear_payment_retry_attempts( int $order_id ): void {
	$attempts  = devhub_get_payment_retry_attempts_map();
	$order_key = (string) absint( $order_id );

	if ( ! isset( $attempts[ $order_key ] ) ) {
		return;
	}

	unset( $attempts[ $order_key ] );
	devhub_set_payment_retry_attempts_map( $attempts );
}

/**
 * Handle failed payment attempts and cancel after the retry limit.
 *
 * @param int                $order_id WooCommerce order ID.
 * @param WC_Order|false|null $order   Order instance when supplied by WooCommerce.
 * @return void
 */
function devhub_handle_failed_payment_retry( int $order_id, $order = false ): void {
	$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order || $order->has_status( 'cancelled' ) ) {
		return;
	}

	$attempts = devhub_increment_payment_retry_attempts( $order->get_id() );
	$order->update_meta_data( '_devhub_payment_retry_attempts', $attempts );
	$order->save_meta_data();

	if ( $attempts >= DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS ) {
		$message = sprintf(
			/* translators: %d: retry limit */
			__( 'Payment failed %d times in this session. The order was cancelled automatically and WooCommerce released the reserved stock.', 'devicehub-theme' ),
			DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS
		);

		$order->update_status( 'cancelled', $message );
		return;
	}

	$order->add_order_note(
		sprintf(
			/* translators: 1: current attempts 2: retry limit */
			__( 'Payment retry %1$d of %2$d used in the current session. The customer can retry payment on the order-pay page.', 'devicehub-theme' ),
			$attempts,
			DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS
		)
	);
}

/**
 * Add retry notices on the order-pay page before notices are rendered.
 *
 * @return void
 */
function devhub_add_order_pay_retry_notice(): void {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) ) {
		return;
	}

	$order_id = absint( get_query_var( 'order-pay' ) );

	if ( $order_id <= 0 ) {
		return;
	}

	$attempts = devhub_get_payment_retry_attempts( $order_id );

	if ( $attempts <= 0 || $attempts >= DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS ) {
		return;
	}

	wc_add_notice(
		sprintf(
			/* translators: 1: current attempts 2: retry limit */
			__( 'Previous payment attempt failed. Retry %1$d of %2$d is available in this session.', 'devicehub-theme' ),
			$attempts + 1,
			DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS
		),
		'notice'
	);
}

/**
 * Redirect failed-order retry requests straight back to the payment gateway
 * when the gateway supports programmatic retries.
 *
 * @return void
 */
function devhub_handle_direct_payment_retry(): void {
	if ( '1' !== (string) filter_input( INPUT_GET, 'devhub_retry_payment', FILTER_SANITIZE_SPECIAL_CHARS ) ) {
		return;
	}

	if ( ! function_exists( 'wc_get_order' ) || ! function_exists( 'WC' ) ) {
		return;
	}

	$order_id  = absint( filter_input( INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT ) );
	$order_key = (string) filter_input( INPUT_GET, 'key', FILTER_SANITIZE_SPECIAL_CHARS );
	$order     = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	if ( '' === $order_key || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	if ( ! $order->needs_payment() ) {
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	$attempts = devhub_get_payment_retry_attempts( $order->get_id() );

	if ( $attempts >= DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS ) {
		wc_add_notice(
			sprintf(
				/* translators: %d: retry limit */
				__( 'This order has reached the maximum of %d payment attempts for the current session.', 'devicehub-theme' ),
				DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS
			),
			'error'
		);

		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	$gateway_id      = (string) $order->get_payment_method();
	$gateway_manager = WC()->payment_gateways();
	$gateways        = $gateway_manager ? $gateway_manager->payment_gateways() : [];
	$gateway         = isset( $gateways[ $gateway_id ] ) ? $gateways[ $gateway_id ] : null;

	if ( ! $gateway || ! method_exists( $gateway, 'process_payment' ) ) {
		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	$result = $gateway->process_payment( $order->get_id() );

	if ( is_array( $result ) && 'success' === ( $result['result'] ?? '' ) && ! empty( $result['redirect'] ) ) {
		wp_redirect( $result['redirect'] );
		exit;
	}

	wp_safe_redirect( $order->get_checkout_payment_url() );
	exit;
}

/**
 * Block further payment attempts for an order once the session limit is reached.
 *
 * @param bool     $needs_payment  Whether WooCommerce thinks the order can be paid.
 * @param WC_Order $order          Order instance.
 * @param array    $valid_statuses Valid payment statuses from WooCommerce.
 * @return bool
 */
function devhub_maybe_block_payment_after_retry_limit( bool $needs_payment, WC_Order $order, array $valid_statuses ): bool {
	unset( $valid_statuses );

	if ( ! $needs_payment ) {
		return false;
	}

	$attempts = devhub_get_payment_retry_attempts( $order->get_id() );

	if ( $attempts < DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS ) {
		return true;
	}

	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
		wc_add_notice(
			sprintf(
				/* translators: %d: retry limit */
				__( 'This order has reached the maximum of %d payment attempts for the current session.', 'devicehub-theme' ),
				DEVHUB_PAYMENT_MAX_RETRY_ATTEMPTS
			),
			'error'
		);
	}

	return false;
}

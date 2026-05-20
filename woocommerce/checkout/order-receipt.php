<?php
/**
 * Checkout Order Receipt Template
 *
 * Theme override to provide a cleaner intermediate redirect page for WebXpay
 * while leaving other gateways on the default WooCommerce receipt output.
 *
 * Based on WooCommerce template version 3.2.0.
 *
 * @package DeviceHub
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

$is_webxpay_receipt = $order instanceof WC_Order && 'webxpay' === (string) $order->get_payment_method();
?>

<?php if ( $is_webxpay_receipt ) : ?>
	<div class="devhub-order-receipt-redirect">
		<div class="devhub-order-receipt-redirect__spinner" aria-hidden="true"></div>
		<h2 class="devhub-order-receipt-redirect__title">
			<?php esc_html_e( 'Redirecting to secure payment...', 'devicehub-theme' ); ?>
		</h2>
		<p class="devhub-order-receipt-redirect__text">
			<?php esc_html_e( 'Please wait while we connect you to WebXpay to complete your payment securely.', 'devicehub-theme' ); ?>
		</p>
		<p class="devhub-order-receipt-redirect__subtext">
			<?php esc_html_e( 'Do not refresh or close this page.', 'devicehub-theme' ); ?>
		</p>
	</div>

	<div class="devhub-order-receipt-hidden" aria-hidden="true">
		<?php do_action( 'woocommerce_receipt_' . $order->get_payment_method(), $order->get_id() ); ?>
	</div>

<?php else : ?>

	<ul class="order_details">
		<li class="order">
			<?php esc_html_e( 'Order number:', 'woocommerce' ); ?>
			<strong><?php echo esc_html( $order->get_order_number() ); ?></strong>
		</li>
		<li class="date">
			<?php esc_html_e( 'Date:', 'woocommerce' ); ?>
			<strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong>
		</li>
		<li class="total">
			<?php esc_html_e( 'Total:', 'woocommerce' ); ?>
			<strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
		</li>
		<?php if ( $order->get_payment_method_title() ) : ?>
			<li class="method">
				<?php esc_html_e( 'Payment method:', 'woocommerce' ); ?>
				<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
			</li>
		<?php endif; ?>
	</ul>

	<?php do_action( 'woocommerce_receipt_' . $order->get_payment_method(), $order->get_id() ); ?>

	<div class="clear"></div>

<?php endif; ?>

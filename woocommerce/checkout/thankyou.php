<?php
/**
 * Thankyou page — DeviceHub override.
 *
 * Adds the store pickup code as an extra item inside the order overview
 * list, displayed directly after the Payment method entry.
 *
 * Based on WooCommerce template version 8.1.0.
 *
 * @package DeviceHub
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order">

	<?php
	if ( $order ) :

		do_action( 'woocommerce_before_thankyou', $order->get_id() );
		?>

		<?php if ( function_exists( 'devhub_is_retry_cancelled_order' ) && devhub_is_retry_cancelled_order( $order ) ) : ?>

			<div class="devhub-order-failed-layout">
				<div class="devhub-order-failed-hero devhub-order-failed-hero--cancelled">
					<div class="devhub-order-failed-hero__icon devhub-order-failed-hero__icon--warning" aria-hidden="true">
						<i class="fas fa-exclamation"></i>
					</div>
					<h2 class="devhub-order-failed-hero__title devhub-order-failed-hero__title--cancelled">
						<?php esc_html_e( 'Payment Cancelled', 'devicehub-theme' ); ?>
					</h2>
					<p class="devhub-order-failed-hero__alert devhub-order-failed-hero__alert--cancelled">
						<?php esc_html_e( 'We were unable to complete your payment after several attempts.', 'devicehub-theme' ); ?>
					</p>
					<p class="devhub-order-failed-hero__text">
						<?php esc_html_e( 'No payment was taken for this order. You can return to the home page, continue shopping, and place a new order whenever you are ready.', 'devicehub-theme' ); ?>
					</p>
					<div class="devhub-order-failed-hero__actions">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button devhub-order-failed-hero__button">
							<?php esc_html_e( 'Go Home', 'devicehub-theme' ); ?>
						</a>
					</div>
				</div>
			</div>

		<?php elseif ( $order->has_status( 'failed' ) ) : ?>

			<div class="devhub-order-failed-layout">
				<div class="devhub-order-failed-hero">
					<div class="devhub-order-failed-hero__icon" aria-hidden="true">
						<i class="fas fa-times"></i>
					</div>
					<h2 class="devhub-order-failed-hero__title">
						<?php esc_html_e( 'Payment Failed!', 'devicehub-theme' ); ?>
					</h2>
					<p class="devhub-order-failed-hero__alert">
						<?php esc_html_e( 'Your card could not be authorized.', 'devicehub-theme' ); ?>
					</p>
					<p class="devhub-order-failed-hero__text">
						<?php esc_html_e( "We're sorry, but it seems your payment didn't go through. Please click 'Try Again' to proceed with the payment process.", 'devicehub-theme' ); ?>
					</p>
					<div class="devhub-order-failed-hero__actions">
						<a href="<?php echo esc_url( function_exists( 'devhub_get_direct_payment_retry_url' ) ? devhub_get_direct_payment_retry_url( $order ) : $order->get_checkout_payment_url() ); ?>" class="button devhub-order-failed-hero__button">
							<?php esc_html_e( 'Try Again', 'devicehub-theme' ); ?>
						</a>
					</div>
				</div>
			</div>

		<?php else : ?>

			<div class="devhub-order-received-hero">
				<div class="devhub-order-received-hero__icon" aria-hidden="true">
					<i class="fas fa-check"></i>
				</div>
				<h2 class="devhub-order-received-hero__title">
					<?php esc_html_e( 'Order Received!', 'devicehub-theme' ); ?>
				</h2>
				<p class="devhub-order-received-hero__text">
					<?php esc_html_e( "Thank you for your purchase. We've received your order and are getting it ready for shipment. You'll receive a confirmation email shortly.", 'devicehub-theme' ); ?>
				</p>
			</div>

			<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">

				<li class="woocommerce-order-overview__order order">
					<?php esc_html_e( 'Order number:', 'woocommerce' ); ?>
					<strong><?php echo $order->get_order_number(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<li class="woocommerce-order-overview__date date">
					<?php esc_html_e( 'Date:', 'woocommerce' ); ?>
					<strong><?php echo wc_format_datetime( $order->get_date_created() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<?php
				$customer_email = function_exists( 'devhub_get_order_customer_email' )
					? devhub_get_order_customer_email( $order )
					: $order->get_billing_email();
				?>
				<?php if ( $customer_email ) : ?>
					<li class="woocommerce-order-overview__email email">
						<?php esc_html_e( 'Customer email:', 'devicehub-theme' ); ?>
						<strong><?php echo esc_html( $customer_email ); ?></strong>
					</li>
				<?php endif; ?>

				<li class="woocommerce-order-overview__total total">
					<?php esc_html_e( 'Total:', 'woocommerce' ); ?>
					<strong><?php echo $order->get_formatted_order_total(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				</li>

				<?php
				// Pickup code — shown only for store-pickup orders that already have a code.
				if ( function_exists( 'devhub_is_pickup_order' ) && devhub_is_pickup_order( $order ) ) :
					$pickup_code  = function_exists( 'devhub_ensure_pickup_code' ) ? devhub_ensure_pickup_code( $order ) : '';
					$pickup_store = function_exists( 'devhub_get_pickup_store_label' ) ? devhub_get_pickup_store_label( $order ) : sanitize_text_field( (string) $order->get_meta( '_devhub_pickup_store_label', true ) );

					if ( '' !== $pickup_code ) :
						?>
						<li class="woocommerce-order-overview__pickup-code devhub-overview-pickup">
							<?php esc_html_e( 'Store pickup code:', 'devicehub-theme' ); ?>
							<strong><?php echo esc_html( $pickup_code ); ?></strong>
						</li>
					<?php endif; ?>
				<?php endif; ?>

			</ul>

		<?php endif; ?>

		<?php if ( ! $order->has_status( 'failed' ) && ! ( function_exists( 'devhub_is_retry_cancelled_order' ) && devhub_is_retry_cancelled_order( $order ) ) ) : ?>
			<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
			<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
		<?php endif; ?>

	<?php else : ?>

		<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>

	<?php endif; ?>

</div>

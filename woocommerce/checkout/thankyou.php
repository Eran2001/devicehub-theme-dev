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

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ); ?></p>

			<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions">
				<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay"><?php esc_html_e( 'My account', 'woocommerce' ); ?></a>
				<?php endif; ?>
			</p>

		<?php else : ?>

			<?php wc_get_template( 'checkout/order-received.php', array( 'order' => $order ) ); ?>

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

				<?php if ( $order->get_payment_method_title() ) : ?>
					<li class="woocommerce-order-overview__payment-method method">
						<?php esc_html_e( 'Payment method:', 'woocommerce' ); ?>
						<strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
					</li>
				<?php endif; ?>

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

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

	<?php else : ?>

		<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>

	<?php endif; ?>

</div>

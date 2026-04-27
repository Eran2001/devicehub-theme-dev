<?php
/**
 * Lost password reset form.
 *
 * DeviceHub auth-card override that preserves WooCommerce reset behavior.
 *
 * @package WooCommerce\Templates
 * @version 9.2.0
 */

defined('ABSPATH') || exit;

do_action('woocommerce_before_reset_password_form');
?>

<div class="devhub-auth">
	<div class="devhub-auth__shell">
		<div class="devhub-auth__card">
			<a class="devhub-auth__back" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">
				<span aria-hidden="true">&larr;</span>
				<span><?php esc_html_e('Back to sign-in options', 'devicehub-theme'); ?></span>
			</a>

			<h2 class="devhub-auth__title"><?php esc_html_e('Create New Password', 'devicehub-theme'); ?></h2>
			<p class="devhub-auth__subtitle">
				<?php
				echo wp_kses_post(
					apply_filters(
						'woocommerce_reset_password_message',
						esc_html__('Enter and confirm your new password below.', 'devicehub-theme')
					)
				);
				?>
			</p>

			<div class="devhub-auth__form">
				<form method="post" class="woocommerce-ResetPassword lost_reset_password">
					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password_1">
							<?php esc_html_e('New password', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Required', 'woocommerce'); ?></span>
						</label>
						<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_1" id="password_1" autocomplete="new-password" required aria-required="true" />
					</p>

					<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
						<label for="password_2">
							<?php esc_html_e('Re-enter new password', 'woocommerce'); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('Required', 'woocommerce'); ?></span>
						</label>
						<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_2" id="password_2" autocomplete="new-password" required aria-required="true" />
					</p>

					<input type="hidden" name="reset_key" value="<?php echo esc_attr($args['key']); ?>" />
					<input type="hidden" name="reset_login" value="<?php echo esc_attr($args['login']); ?>" />

					<?php do_action('woocommerce_resetpassword_form'); ?>

					<p class="woocommerce-form-row form-row">
						<input type="hidden" name="wc_reset_password" value="true" />
						<button type="submit"
							class="woocommerce-Button button devhub-auth__submit<?php echo esc_attr(wc_wp_theme_get_element_class_name('button') ? ' ' . wc_wp_theme_get_element_class_name('button') : ''); ?>"
							value="<?php esc_attr_e('Save', 'woocommerce'); ?>">
							<?php esc_html_e('Save Password', 'devicehub-theme'); ?>
						</button>
					</p>

					<?php wp_nonce_field('reset_password', 'woocommerce-reset-password-nonce'); ?>
				</form>
			</div>
		</div>
	</div>
</div>

<?php do_action('woocommerce_after_reset_password_form'); ?>

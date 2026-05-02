<?php
/**
 * Payment Methods — DeviceHub override
 *
 * @package DeviceHub
 */

defined('ABSPATH') || exit;

$payment_methods = WC_Payment_Tokens::get_customer_tokens(get_current_user_id());
$has_methods = !empty($payment_methods);

do_action('woocommerce_before_account_payment_methods', $has_methods);
?>

<?php if ($has_methods): ?>

    <?php do_action('woocommerce_before_payment_methods'); ?>

    <table class="woocommerce-MyAccount-paymentMethods shop_table shop_table_responsive account-payment-methods-table">
        <thead>
            <tr>
                <?php foreach (wc_get_account_payment_methods_columns() as $column_id => $column_name): ?>
                    <th class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr($column_id); ?>">
                        <span class="nobr">
                            <?php echo esc_html($column_name); ?>
                        </span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($payment_methods as $payment_method): ?>
                <tr class="payment-method">
                    <?php foreach (wc_get_account_payment_methods_columns() as $column_id => $column_name): ?>
                        <td class="woocommerce-PaymentMethod woocommerce-PaymentMethod--<?php echo esc_attr($column_id); ?>">
                            <?php
                            if (has_action('woocommerce_account_payment_methods_column_' . $column_id)) {
                                do_action('woocommerce_account_payment_methods_column_' . $column_id, $payment_method);
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php do_action('woocommerce_after_payment_methods'); ?>

<?php else: ?>

    <!-- SAME EMPTY UI REUSED 🔥 -->
    <div class="devhub-empty-state">

        <div class="devhub-empty-state__icon-wrap">
            <div class="devhub-empty-state__layer devhub-empty-state__layer--1"></div>
            <div class="devhub-empty-state__layer devhub-empty-state__layer--2"></div>

            <div class="devhub-empty-state__card">
                <!-- Card Icon -->
                <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <line x1="2" y1="10" x2="22" y2="10" />
                </svg>
            </div>

            <div class="devhub-empty-state__badge">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
            </div>
        </div>

        <div class="devhub-empty-state__text">
            <h4>
                <?php esc_html_e('No payment methods added yet.', 'devicehub-theme'); ?>
            </h4>
            <p>
                <?php esc_html_e('You have not saved any payment methods. Add one during checkout for faster future purchases.', 'devicehub-theme'); ?>
            </p>
        </div>

    </div>

<?php endif; ?>

<?php do_action('woocommerce_after_account_payment_methods', $has_methods); ?>
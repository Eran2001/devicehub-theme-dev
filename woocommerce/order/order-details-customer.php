<?php
/**
 * Order Customer Details — DeviceHub override
 *
 * Shows billing (and optionally shipping) address in a labelled grid layout
 * inside a rounded card, matching the DeviceHub account card style.
 *
 * Based on WooCommerce template version 8.7.0.
 *
 * @package DeviceHub
 */

defined( 'ABSPATH' ) || exit;

$show_shipping = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address();

/**
 * Format an order phone for display with country dial code when needed.
 *
 * @param WC_Order $order
 * @param string   $type 'billing' | 'shipping'
 * @return string
 */
function devhub_get_order_phone_display( WC_Order $order, string $type ): string {
	$get_phone   = "get_{$type}_phone";
	$get_country = "get_{$type}_country";

	$phone        = trim( (string) $order->{$get_phone}() );
	$country_code = trim( (string) $order->{$get_country}() );

	if ( '' === $phone && 'shipping' === $type ) {
		$phone        = trim( (string) $order->get_billing_phone() );
		$country_code = '' !== $country_code ? $country_code : trim( (string) $order->get_billing_country() );
	}

	if ( '' === $phone ) {
		return '';
	}

	$compact_phone = preg_replace( '/\s+/', '', $phone );
	if ( is_string( $compact_phone ) && '' !== $compact_phone && '+' === $compact_phone[0] ) {
		$digits = preg_replace( '/[^\d]/', '', $compact_phone );
		return '' !== $digits ? '+' . $digits : $phone;
	}

	$digits = preg_replace( '/\D+/', '', $phone );
	if ( ! is_string( $digits ) || '' === $digits ) {
		return $phone;
	}

	$calling_codes = WC()->countries->get_country_calling_code( $country_code );
	if ( empty( $calling_codes ) ) {
		return $phone;
	}

	$calling_code = is_array( $calling_codes ) ? reset( $calling_codes ) : $calling_codes;
	$calling_code = preg_replace( '/\D+/', '', (string) $calling_code );

	if ( ! is_string( $calling_code ) || '' === $calling_code ) {
		return $phone;
	}

	if ( 0 === strpos( $digits, $calling_code ) ) {
		return '+' . $digits;
	}

	return '+' . $calling_code . ' ' . ltrim( $digits, '0' );
}

/**
 * Build a field grid from an address type ('billing' or 'shipping').
 *
 * @param WC_Order $order
 * @param string   $type 'billing' | 'shipping'
 * @param array    $options {
 *     Optional display overrides.
 *
 *     @type string      $email_type 'billing' to force billing email display.
 *     @type bool|null   $show_email Whether to show email. Defaults to true for billing.
 * }
 * @return array   [ ['label' => '', 'value' => ''], ... ]
 */
function devhub_address_fields( WC_Order $order, string $type, array $options = [] ): array {
    $get = fn( string $field ) => call_user_func( [ $order, "get_{$type}_{$field}" ] );
    $email_type = isset( $options['email_type'] ) ? (string) $options['email_type'] : $type;
    $show_email = array_key_exists( 'show_email', $options ) ? (bool) $options['show_email'] : 'billing' === $type;

    $first = $get( 'first_name' );
    $last  = $get( 'last_name' );

    $fields = [];

    if ( $first || $last ) {
        $fields[] = [ 'label' => __( 'Contact', 'devicehub-theme' ), 'value' => trim( "$first $last" ) ];
    }

    if ( $addr1 = $get( 'address_1' ) ) {
        $label = $get( 'address_2' )
            ? __( 'Address line 1', 'devicehub-theme' )
            : __( 'Address', 'devicehub-theme' );
        $fields[] = [ 'label' => $label, 'value' => $addr1 ];
    }

    if ( $addr2 = $get( 'address_2' ) ) {
        $fields[] = [ 'label' => __( 'Address line 2', 'devicehub-theme' ), 'value' => $addr2 ];
    }

    if ( $city = $get( 'city' ) ) {
        $fields[] = [ 'label' => __( 'City', 'devicehub-theme' ), 'value' => $city ];
    }

    if ( $state = $get( 'state' ) ) {
        $fields[] = [ 'label' => __( 'State', 'devicehub-theme' ), 'value' => $state ];
    }

    if ( $postcode = $get( 'postcode' ) ) {
        $fields[] = [ 'label' => __( 'Postcode', 'devicehub-theme' ), 'value' => $postcode ];
    }

    if ( $country_code = $get( 'country' ) ) {
        $countries = WC()->countries->get_countries();
        $fields[]  = [
            'label' => __( 'Country', 'devicehub-theme' ),
            'value' => $countries[ $country_code ] ?? $country_code,
        ];
    }

    if ( $phone = devhub_get_order_phone_display( $order, $type ) ) {
        $fields[] = [ 'label' => __( 'Phone', 'devicehub-theme' ), 'value' => $phone ];
    }

    if ( $show_email && 'billing' === $email_type ) {
        if ( $email = $order->get_billing_email() ) {
            $fields[] = [ 'label' => __( 'Email', 'devicehub-theme' ), 'value' => $email ];
        }
    }

    return $fields;
}

/**
 * Build a compact address preview matching the checkout saved-address card.
 *
 * @param WC_Order $order
 * @param string   $type 'billing' | 'shipping'
 * @return array{name:string,address:string}
 */
function devhub_order_address_preview( WC_Order $order, string $type ): array {
    $get = fn( string $field ) => call_user_func( [ $order, "get_{$type}_{$field}" ] );

    $countries    = WC()->countries->get_countries();
    $country_code = (string) $get( 'country' );
    $country      = $country_code !== '' ? ( $countries[ $country_code ] ?? $country_code ) : '';

    $name = trim( (string) $get( 'first_name' ) . ' ' . (string) $get( 'last_name' ) );

    $address_parts = array_filter(
        [
            $get( 'address_1' ),
            $get( 'address_2' ),
            $get( 'city' ),
            $get( 'postcode' ),
            $country,
            devhub_get_order_phone_display( $order, $type ),
        ],
        static fn( $value ) => trim( (string) $value ) !== ''
    );

    return [
        'name'    => $name,
        'address' => implode( ', ', array_map( 'wc_clean', $address_parts ) ),
    ];
}
?>

<section class="woocommerce-customer-details devhub-order-customer-details">

    <?php
    $use_compact_cards = function_exists( 'is_order_received_page' ) && is_order_received_page();
    $sections          = [
        [
            'source'     => 'billing',
            'title'      => __( 'Billing address', 'woocommerce' ),
            'email_type' => 'billing',
            'show_email' => true,
        ],
    ];

    if ( $show_shipping ) {
        if ( $use_compact_cards ) {
            // Checkout UI intentionally relabels the visible address blocks:
            // the first visible block uses Woo shipping fields but is titled
            // "Billing address", while the second uses Woo billing fields and
            // is titled "Shipping address". Mirror that exact user-facing flow
            // here, while still keeping the billing email on the visible
            // billing card.
            $sections = [
                [
                    'source'     => 'shipping',
                    'title'      => __( 'Billing address', 'woocommerce' ),
                    'email_type' => 'billing',
                    'show_email' => true,
                ],
                [
                    'source'     => 'billing',
                    'title'      => __( 'Shipping address', 'woocommerce' ),
                    'email_type' => 'billing',
                    'show_email' => false,
                ],
            ];
        } else {
            $sections[] = [
                'source'     => 'shipping',
                'title'      => __( 'Shipping address', 'woocommerce' ),
                'email_type' => 'billing',
                'show_email' => false,
            ];
        }
    }

    foreach ( $sections as $section ) :
        $type    = $section['source'];
        $title   = $section['title'];
        $fields  = devhub_address_fields(
            $order,
            $type,
            [
                'email_type' => $section['email_type'],
                'show_email' => $section['show_email'],
            ]
        );
        if ( empty( $fields ) ) continue;
        $preview = devhub_order_address_preview( $order, $type );
    ?>

    <?php if ( $use_compact_cards ) : ?>
        <details class="devhub-order-address-card">
            <summary class="devhub-order-address-card__summary">
                <span class="devhub-order-address-card__heading">
                    <span class="devhub-order-address-card__title"><?php echo esc_html( $title ); ?></span>
                    <span class="devhub-order-address-card__toggle" aria-hidden="true"></span>
                </span>
                <span class="devhub-order-address-preview">
                    <?php if ( $preview['name'] !== '' ) : ?>
                        <span class="devhub-order-address-preview__name"><?php echo esc_html( $preview['name'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( $preview['address'] !== '' ) : ?>
                        <span class="devhub-order-address-preview__address"><?php echo esc_html( $preview['address'] ); ?></span>
                    <?php endif; ?>
                </span>
            </summary>
    <?php else : ?>
        <div class="devhub-order-address-card">
            <h2 class="devhub-order-address-card__title"><?php echo esc_html( $title ); ?></h2>
    <?php endif; ?>

        <div class="devhub-order-address-grid">
            <?php foreach ( $fields as $field ) : ?>
                <div class="devhub-order-address-field">
                    <span class="devhub-order-address-field__label"><?php echo esc_html( strtoupper( $field['label'] ) ); ?></span>
                    <span class="devhub-order-address-field__value"><?php echo esc_html( $field['value'] ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php do_action( 'woocommerce_order_details_after_customer_address', $type, $order ); ?>

    <?php if ( $use_compact_cards ) : ?>
        </details>
    <?php else : ?>
        </div>
    <?php endif; ?>

    <?php endforeach; ?>

    <?php do_action( 'woocommerce_order_details_after_customer_details', $order ); ?>

</section>

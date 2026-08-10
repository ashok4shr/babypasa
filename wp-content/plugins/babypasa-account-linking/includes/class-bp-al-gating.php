<?php
/**
 * Redacts the full street address and phone numbers on the My Account
 * "View Order" page while an order's account link is provisional (unverified
 * billing-email match, not yet confirmed by an admin).
 *
 * Scoped narrowly: the redaction filters are only added immediately before,
 * and removed immediately after, WooCommerce renders the single
 * order/order-details-customer.php template (via the universal
 * woocommerce_before_template_part / woocommerce_after_template_part hooks).
 * This deliberately avoids hooking the address/phone getters globally, which
 * would also redact them for order emails, the invoice PDF, admin screens,
 * and — critically — the real address WooCommerce/Upaya needs to ship the
 * order. City/state/country still show, so the order remains identifiable.
 *
 * @package BabyPasa_Account_Linking
 */

defined( 'ABSPATH' ) || exit;

class BP_AL_Gating {

	const TARGET_TEMPLATE = 'order/order-details-customer.php';

	public function __construct() {
		add_action( 'woocommerce_before_template_part', array( $this, 'maybe_start_gating' ), 10, 4 );
		add_action( 'woocommerce_after_template_part', array( $this, 'maybe_stop_gating' ), 10, 4 );
	}

	/**
	 * @param string               $template_name Template file being rendered.
	 * @param string               $template_path Template path override (unused).
	 * @param string               $located       Resolved file path (unused).
	 * @param array<string,mixed>  $args          Template args; expects 'order'.
	 */
	public function maybe_start_gating( $template_name, $template_path, $located, $args ): void {
		if ( self::TARGET_TEMPLATE !== $template_name
			|| empty( $args['order'] )
			|| ! $args['order'] instanceof WC_Order
			|| ! BP_AL_Linker::is_provisional( $args['order'] )
		) {
			return;
		}

		add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'redact_address' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'redact_address' ), 10, 2 );
		add_filter( 'woocommerce_order_get_billing_phone', array( $this, 'redact_phone' ), 10, 2 );
		add_filter( 'woocommerce_order_get_shipping_phone', array( $this, 'redact_phone' ), 10, 2 );
	}

	/**
	 * @param string $template_name Template file that just finished rendering.
	 */
	public function maybe_stop_gating( $template_name ): void {
		if ( self::TARGET_TEMPLATE !== $template_name ) {
			return;
		}

		remove_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'redact_address' ), 10 );
		remove_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'redact_address' ), 10 );
		remove_filter( 'woocommerce_order_get_billing_phone', array( $this, 'redact_phone' ), 10 );
		remove_filter( 'woocommerce_order_get_shipping_phone', array( $this, 'redact_phone' ), 10 );
	}

	/**
	 * @param  array<string,string> $address Raw address parts.
	 * @param  WC_Order             $order   The order.
	 * @return array<string,string>
	 */
	public function redact_address( $address, $order ) {
		/**
		 * Whether to redact the street address for a provisional order.
		 *
		 * @param bool     $gate  Whether to redact. Default true.
		 * @param WC_Order $order The order.
		 */
		if ( ! apply_filters( 'bp_al_gate_address', true, $order ) ) {
			return $address;
		}

		unset( $address['address_1'], $address['address_2'], $address['postcode'] );

		return $address;
	}

	/**
	 * @param  string    $value Phone number.
	 * @param  WC_Order $order The order.
	 * @return string
	 */
	public function redact_phone( $value, $order ) {
		/**
		 * Whether to redact the phone number for a provisional order.
		 *
		 * @param bool     $gate  Whether to redact. Default true.
		 * @param WC_Order $order The order.
		 */
		if ( ! apply_filters( 'bp_al_gate_phone', true, $order ) ) {
			return $value;
		}

		return '';
	}
}

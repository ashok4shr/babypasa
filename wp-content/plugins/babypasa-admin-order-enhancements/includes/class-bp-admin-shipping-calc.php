<?php
/**
 * AJAX handlers for Upaya shipping rate calculation and application
 * in the admin order screen.
 *
 * Uses the Upaya plugin's own UPAYA_API and UPAYA_Location_Cache classes
 * directly so authentication and request handling are identical to checkout.
 *
 * @package BabyPasa_Admin_Order_Enhancements
 */

defined( 'ABSPATH' ) || exit;

class BP_Admin_Shipping_Calc {

	/** Default item weight fallback when a product has no weight set (kg). */
	const DEFAULT_WEIGHT = 0.5;

	/** Order meta flag marking an order whose delivery charge we manage. */
	const MANAGED_META = '_bp_delivery_charge_managed';

	public function __construct() {
		add_action( 'wp_ajax_bp_aoe_calc_shipping',  [ $this, 'ajax_calc'  ] );
		add_action( 'wp_ajax_bp_aoe_apply_shipping', [ $this, 'ajax_apply' ] );

		// Re-resolve the delivery charge at save time from the order's FINAL items
		// + area, so the persisted charge is always correct even if the live JS
		// preview didn't re-run (e.g. a product was added after the area was set).
		// Priority 50 runs after the address form save (10) and WC core save (40),
		// so the billing/shipping area is already persisted when we read it.
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'enforce_delivery_charge_on_save' ], 50 );
	}

	/* ------------------------------------------------------------------
	 * AJAX: calculate rate
	 * ------------------------------------------------------------------ */

	public function ajax_calc(): void {
		check_ajax_referer( 'bp_aoe_calc_shipping', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'babypasa-aoe' ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$hub_area = sanitize_text_field( $_POST['hub_area'] ?? '' );

		if ( ! $hub_area || strpos( $hub_area, '||' ) === false ) {
			wp_send_json_error( __( 'Invalid delivery area.', 'babypasa-aoe' ) );
		}

		$parts = explode( '||', $hub_area, 2 );
		$area  = $parts[1] ?? '';

		// Use Upaya's own classes — identical auth and request logic as checkout.
		if ( ! class_exists( 'UPAYA_API' ) || ! class_exists( 'UPAYA_Location_Cache' ) ) {
			wp_send_json_error( __( 'Upaya Cargo plugin is not active.', 'babypasa-aoe' ) );
		}

		$logger         = new UPAYA_Logger();
		$api            = new UPAYA_API( get_option( 'upaya_api_key', '' ), $logger );
		$location_cache = new UPAYA_Location_Cache( $api, $logger );

		$location_id = $location_cache->get_location_id_by_name( $area );
		if ( ! $location_id ) {
			wp_send_json_error( __( 'Could not resolve location for the selected area. Try flushing the Upaya location cache.', 'babypasa-aoe' ) );
		}

		$weight = $this->get_order_weight( $order_id );

		$result = $api->get_order_rates( [
			'service_type_id' => UPAYA_API::SERVICE_DOOR_TO_DOOR,
			'initial_weight'  => $weight,
			'location_id'     => $location_id,
			'order_type'      => UPAYA_API::ORDER_TYPE_DELIVERY,
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$rate = $result['data']['totalDeliveryCharge']
			?? $result['rate']
			?? $result['total_rate']
			?? null;

		if ( null === $rate ) {
			wp_send_json_error( __( 'No rate returned from Upaya API.', 'babypasa-aoe' ) );
		}

		$rate  = (float) $rate;
		$label = 'Upaya Cargo';

		// Apply delivery overrides in the same order as the frontend filters.
		[ $rate, $label ] = $this->apply_overrides( $rate, $label, $area, $order_id );

		wp_send_json_success( [
			'rate'  => $rate,
			'label' => sprintf(
				/* translators: %s: formatted rate */
				__( '%1$s: Rs %2$s', 'babypasa-aoe' ),
				$label,
				number_format( $rate, 0 )
			),
		] );
	}

	/* ------------------------------------------------------------------
	 * AJAX: apply rate to order
	 * ------------------------------------------------------------------ */

	public function ajax_apply(): void {
		check_ajax_referer( 'bp_aoe_apply_shipping', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'babypasa-aoe' ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$rate     = (float) sanitize_text_field( $_POST['rate'] ?? '0' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Order not found.', 'babypasa-aoe' ) );
		}

		// Remove existing shipping items to avoid duplicates.
		foreach ( $order->get_shipping_methods() as $item_id => $item ) {
			$order->remove_item( $item_id );
		}

		$shipping_item = new WC_Order_Item_Shipping();
		$shipping_item->set_method_title( __( 'Upaya Cargo', 'babypasa-aoe' ) );
		$shipping_item->set_method_id( 'upaya_cargo' );
		$shipping_item->set_total( $rate );
		$order->add_item( $shipping_item );

		// Mark this order as delivery-charge-managed so the save-time enforcement
		// re-resolves it against the final items + area.
		$order->update_meta_data( self::MANAGED_META, '1' );

		$order->calculate_totals();
		$order->save();

		wp_send_json_success( [
			'rate'        => $rate,
			'order_total' => wc_price( $order->get_total() ),
		] );
	}

	/* ------------------------------------------------------------------
	 * Save-time enforcement
	 * ------------------------------------------------------------------ */

	/**
	 * Re-resolves the delivery charge from the order's final items + area on save
	 * and corrects the existing Upaya Cargo shipping line. Runs only for orders we
	 * manage (MANAGED_META set by ajax_apply), so unrelated/legacy orders are never
	 * touched. Uses the shared resolver — identical precedence to the frontend.
	 *
	 * @param int $order_id WC order ID.
	 */
	public function enforce_delivery_charge_on_save( $order_id ): void {
		if ( ! function_exists( 'babypasa_resolve_delivery_charge' ) ) {
			return; // Delivery-overrides plugin inactive.
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || '1' !== (string) $order->get_meta( self::MANAGED_META ) ) {
			return;
		}

		// Only adjust an existing Upaya Cargo shipping line; never create one here.
		$shipping_item = null;
		foreach ( $order->get_shipping_methods() as $item ) {
			if ( 'upaya_cargo' === $item->get_method_id() ) {
				$shipping_item = $item;
				break;
			}
		}
		if ( ! $shipping_item ) {
			return;
		}

		// Resolve area: prefer shipping city, fall back to billing (mirrors the
		// frontend's current_destination_district precedence).
		$area = (string) $order->get_shipping_city();
		if ( '' === $area ) {
			$area = (string) $order->get_billing_city();
		}

		$items = [];
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			$items[] = [
				'product_id'   => (int) $item->get_product_id(),
				'variation_id' => (int) $item->get_variation_id(),
			];
		}

		$result = babypasa_resolve_delivery_charge( $items, $area );
		if ( null === $result ) {
			return; // Nothing applies — leave the last-applied Upaya rate.
		}

		$new_total = (float) $result['charge'];
		if ( (float) $shipping_item->get_total() === $new_total ) {
			return; // Already correct — avoid a redundant recalc/save.
		}

		$shipping_item->set_total( $new_total );
		if ( null !== $result['label'] && '' !== $result['label'] ) {
			$shipping_item->set_method_title( $result['label'] );
		}
		$shipping_item->save();

		$order->calculate_totals();
		$order->save();
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Applies delivery overrides to a raw Upaya rate by delegating to the SAME
	 * resolver the frontend uses (babypasa_resolve_delivery_charge), so the admin
	 * manual-order charge is guaranteed identical to checkout. The full precedence
	 * (product free delivery → product free area → area override → default) lives
	 * in BP_Delivery_Charge_Resolver.
	 *
	 * Falls back to the raw Upaya rate if the delivery-overrides plugin is inactive
	 * (the resolver function is then undefined) — never fatals.
	 *
	 * @param  float  $rate     Raw Upaya rate in Rs.
	 * @param  string $label    Current rate label.
	 * @param  string $area     Area name (the Upaya area selected in the admin form).
	 * @param  int    $order_id WC order ID.
	 * @return array{float, string}  [ adjusted_rate, label ]
	 */
	private function apply_overrides( float $rate, string $label, string $area, int $order_id ): array {
		if ( ! function_exists( 'babypasa_resolve_delivery_charge' ) ) {
			return [ $rate, $label ]; // Delivery-overrides plugin inactive — leave raw Upaya rate.
		}

		$order = wc_get_order( $order_id );
		$items = [];
		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				/** @var WC_Order_Item_Product $item */
				$items[] = [
					'product_id'   => (int) $item->get_product_id(),
					'variation_id' => (int) $item->get_variation_id(),
				];
			}
		}

		$result = babypasa_resolve_delivery_charge( $items, $area );
		if ( null === $result ) {
			return [ $rate, $label ]; // Nothing applies — leave raw Upaya rate.
		}

		$resolved_label = ( null !== $result['label'] && '' !== $result['label'] )
			? $result['label']
			: $label;

		return [ (float) $result['charge'], $resolved_label ];
	}

	/**
	 * Sums item weights for the order, falling back to DEFAULT_WEIGHT per
	 * product with no weight — same logic as UPAYA_Shipping_Method.
	 */
	private function get_order_weight( int $order_id ): float {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return self::DEFAULT_WEIGHT;
		}

		$total = 0.0;
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$w      = (float) $product->get_weight();
			$qty    = (int) $item->get_quantity();
			$total += ( $w > 0 ? $w : self::DEFAULT_WEIGHT ) * $qty;
		}

		return $total > 0 ? $total : self::DEFAULT_WEIGHT;
	}
}

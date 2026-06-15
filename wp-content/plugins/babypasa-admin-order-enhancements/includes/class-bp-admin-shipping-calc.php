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

	public function __construct() {
		add_action( 'wp_ajax_bp_aoe_calc_shipping',  [ $this, 'ajax_calc'  ] );
		add_action( 'wp_ajax_bp_aoe_apply_shipping', [ $this, 'ajax_apply' ] );
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

		$order->calculate_totals();
		$order->save();

		wp_send_json_success( [
			'rate'        => $rate,
			'order_total' => wc_price( $order->get_total() ),
		] );
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Applies delivery overrides to a raw Upaya rate, mirroring the two
	 * woocommerce_package_rates filters from babypasa-delivery-overrides:
	 *
	 *  priority 10 — BP_Free_Delivery_Product: zero rate if ANY item is free-delivery
	 *  priority 20 — BP_Area_Override: apply first matching area rule
	 *
	 * @param  float  $rate     Raw Upaya rate in Rs.
	 * @param  string $label    Current rate label.
	 * @param  string $area     Area name (billing_city).
	 * @param  int    $order_id WC order ID.
	 * @return array{float, string}  [ adjusted_rate, label ]
	 */
	private function apply_overrides( float $rate, string $label, string $area, int $order_id ): array {
		// Priority 10 — free delivery product flag.
		if ( $this->any_item_free_delivery( $order_id ) ) {
			return [ 0.0, __( 'Free Delivery', 'babypasa-aoe' ) ];
		}

		// Priority 20 — area-based override.
		$area_result = $this->get_area_override( $area );
		if ( null !== $area_result ) {
			return [ $area_result['price'], $area_result['label'] ?: $label ];
		}

		return [ $rate, $label ];
	}

	/**
	 * Returns true when AT LEAST ONE item in the order has _bp_free_delivery = 'yes'.
	 * Checks variation ID first, then falls back to parent product ID, exactly
	 * as BP_Free_Delivery_Product::override_rate_if_any_free() does.
	 */
	private function any_item_free_delivery( int $order_id ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			$variation_id = (int) $item->get_variation_id();
			$product_id   = (int) $item->get_product_id();
			$check_id     = $variation_id ?: $product_id;

			if ( 'yes' === get_post_meta( $check_id, '_bp_free_delivery', true ) ||
				'yes' === get_post_meta( $product_id, '_bp_free_delivery', true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the first matching enabled area override rule for the given area
	 * name, or null if none match. Mirrors BP_Area_Override::apply_area_override().
	 *
	 * @param  string $area Area name (billing_city).
	 * @return array{price:float,label:string}|null
	 */
	private function get_area_override( string $area ): ?array {
		if ( '' === $area ) {
			return null;
		}

		$rules = get_option( 'bp_area_delivery_overrides', [] );
		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return null;
		}

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$rule_area = $rule['area_name'] ?? '';
			if ( '' === $rule_area ) {
				continue;
			}

			$matched = ( 'exact' === $rule['match_type'] )
				? ( 0 === strcasecmp( $area, $rule_area ) )
				: ( false !== stripos( $area, $rule_area ) );

			if ( $matched ) {
				return [
					'price' => (float) ( $rule['override_price'] ?? 0 ),
					'label' => sanitize_text_field( $rule['label'] ?? '' ),
				];
			}
		}

		return null;
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

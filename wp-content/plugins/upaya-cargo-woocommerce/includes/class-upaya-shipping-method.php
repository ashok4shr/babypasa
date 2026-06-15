<?php
/**
 * WooCommerce shipping method for Upaya Cargo.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds an "Upaya Cargo Delivery" rate to WooCommerce checkout by querying
 * the Upaya Cargo /order-rates endpoint for the current cart.
 */
class UPAYA_Shipping_Method extends WC_Shipping_Method {

	/** Transient key prefix for cached rate results (per cart hash). */
	const RATE_CACHE_PREFIX = 'upaya_rate_';

	/** Rate cache TTL: 10 minutes. */
	const RATE_CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	/** @var UPAYA_API */
	private UPAYA_API $api;

	/** @var UPAYA_Logger */
	private UPAYA_Logger $logger;

	/** @var UPAYA_Location_Cache */
	private UPAYA_Location_Cache $location_cache;

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping zone instance ID.
	 */
	public function __construct( int $instance_id = 0 ) {
		parent::__construct( $instance_id );

		$this->id                 = 'upaya_cargo';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Upaya Cargo', 'upaya-cargo-woocommerce' );
		$this->method_description = __( 'Upaya Cargo shipping for Nepal', 'upaya-cargo-woocommerce' );
		$this->supports           = [ 'shipping-zones', 'instance-settings' ];


		$this->logger         = new UPAYA_Logger();
		$this->api            = new UPAYA_API( get_option( 'upaya_api_key', '' ), $this->logger );
		$this->location_cache = new UPAYA_Location_Cache( $this->api, $this->logger );

		$this->init();
	}

	/**
	 * Loads form fields and settings.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->get_option( 'title', __( 'Upaya Cargo Delivery', 'upaya-cargo-woocommerce' ) );
	}

	/**
	 * Defines all per-instance settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$service_options  = array_map( 'strval', array_keys( UPAYA_API::get_service_types() ) );
		$service_labels   = UPAYA_API::get_service_types();
		$service_select   = [];
		foreach ( $service_labels as $id => $label ) {
			$service_select[ (string) $id ] = $label;
		}

		$category_select = [];
		foreach ( UPAYA_API::get_product_categories() as $id => $label ) {
			$category_select[ (string) $id ] = $label;
		}

		$this->instance_form_fields = [
			'enabled' => [
				'title'   => __( 'Enable', 'upaya-cargo-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Upaya Cargo shipping', 'upaya-cargo-woocommerce' ),
				'default' => 'yes',
			],
			'title' => [
				'title'       => __( 'Method Title', 'upaya-cargo-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Shown to customer at checkout.', 'upaya-cargo-woocommerce' ),
				'default'     => __( 'Upaya Cargo Delivery', 'upaya-cargo-woocommerce' ),
				'desc_tip'    => true,
			],
			'service_type_id' => [
				'title'       => __( 'Service Type', 'upaya-cargo-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Upaya Cargo service to use for this zone.', 'upaya-cargo-woocommerce' ),
				'default'     => (string) UPAYA_API::SERVICE_DOOR_TO_DOOR,
				'class'       => 'wc-enhanced-select',
				'css'         => 'min-width: 300px;',
				'options'     => $service_select,
				'desc_tip'    => true,
			],
			'default_weight' => [
				'title'             => __( 'Default Weight (kg)', 'upaya-cargo-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Fallback weight in kg when a product has no weight set.', 'upaya-cargo-woocommerce' ),
				'default'           => '0.5',
				'desc_tip'          => true,
				'custom_attributes' => [
					'min'  => '0.01',
					'step' => '0.01',
				],
			],
			'default_product_category_id' => [
				'title'       => __( 'Default Product Category', 'upaya-cargo-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Upaya product category sent with every order from this zone.', 'upaya-cargo-woocommerce' ),
				'default'     => '5', // 5 is Electronics
				'class'       => 'wc-enhanced-select',
				'css'         => 'min-width: 300px;',
				'options'     => $category_select,
				'desc_tip'    => true,
			],
			'order_type' => [
				'title'   => __( 'Order Type', 'upaya-cargo-woocommerce' ),
				'type'    => 'select',
				'default' => UPAYA_API::ORDER_TYPE_DELIVERY,
				'options' => [
					UPAYA_API::ORDER_TYPE_DELIVERY => __( 'Delivery', 'upaya-cargo-woocommerce' ),
					UPAYA_API::ORDER_TYPE_RETURN   => __( 'Return',   'upaya-cargo-woocommerce' ),
					UPAYA_API::ORDER_TYPE_EXCHANGE => __( 'Exchange', 'upaya-cargo-woocommerce' ),
				],
			],
			'enable_cod' => [
				'title'   => __( 'Cash on Delivery', 'upaya-cargo-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Cash on Delivery support', 'upaya-cargo-woocommerce' ),
				'default' => 'yes',
			],
			'fallback_rate' => [
				'title'             => __( 'Fallback Rate (Rs.)', 'upaya-cargo-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Flat rate shown if the API call fails. Set 0 to hide this method on API failure.', 'upaya-cargo-woocommerce' ),
				'default'           => '0',
				'desc_tip'          => true,
				'custom_attributes' => [
					'min'  => '0',
					'step' => '1',
				],
			],
		];
	}

	/**
	 * Calculates shipping rates and adds them to WooCommerce.
	 *
	 * @param  array $package WooCommerce shipping package.
	 * @return void
	 */
	public function calculate_shipping( $package = [] ): void {
		if ( 'yes' !== $this->get_option( 'enabled', 'yes' ) ) {
			return;
		}

		$api_key = get_option( 'upaya_api_key', '' );
		if ( empty( $api_key ) ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				wc_add_notice(
					__( 'Upaya Cargo: API key is not configured. Please configure the plugin in WooCommerce settings.', 'upaya-cargo-woocommerce' ),
					'notice'
				);
			}
			$this->logger->warning( 'calculate_shipping: API key not configured, skipping.' );
			return;
		}

		// Build a cart hash to cache the rate result.
		$cart_hash = md5( serialize( $package['contents'] ) . serialize( $package['destination'] ) );
		$cache_key = self::RATE_CACHE_PREFIX . $cart_hash . '_' . $this->instance_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			$cached['label'] = $this->title; // Always use the live title from settings
			$this->add_rate( $cached );
			return;
		}

		$total_weight = $this->calculate_package_weight( $package );
		$location_id  = $this->resolve_location_id( $package );

		$params = [
			'service_type_id' => (int) $this->get_option( 'service_type_id', UPAYA_API::SERVICE_DOOR_TO_DOOR ),
			'initial_weight'  => $total_weight,
			'location_id'     => $location_id,
			'order_type'      => $this->get_option( 'order_type', UPAYA_API::ORDER_TYPE_DELIVERY ),
		];

		$result = $this->api->get_order_rates( $params );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'calculate_shipping: API error — ' . $result->get_error_message() );
			$this->maybe_add_fallback_rate();
			return;
		}

		$cost = isset( $result['data']['totalDeliveryCharge'] ) ? (float) $result['data']['totalDeliveryCharge'] : ( isset( $result['rate'] ) ? (float) $result['rate'] : ( isset( $result['total_rate'] ) ? (float) $result['total_rate'] : 0.0 ) );

		if ( $cost <= 0 ) {
			$this->logger->warning( 'calculate_shipping: API returned zero or missing rate.' );
			$this->maybe_add_fallback_rate();
			return;
		}

		$rate = [
			'id'    => $this->get_rate_id(),
			'label' => $this->title,
			'cost'  => $cost,
		];

		set_transient( $cache_key, $rate, self::RATE_CACHE_TTL );
		$this->add_rate( $rate );
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Sums total weight of all items in the shipping package.
	 *
	 * @param  array $package WooCommerce shipping package.
	 * @return float Total weight in kg.
	 */
	private function calculate_package_weight( array $package ): float {
		$default_weight = (float) $this->get_option( 'default_weight', 0.5 );
		$total_weight   = 0.0;

		foreach ( $package['contents'] as $item ) {
			/** @var WC_Product $product */
			$product  = $item['data'];
			$quantity = (int) $item['quantity'];
			$weight   = (float) $product->get_weight();

			if ( $weight <= 0 ) {
				$weight = $default_weight;
			}

			$total_weight += $weight * $quantity;
		}

		return $total_weight > 0 ? $total_weight : $default_weight;
	}

	/**
	 * Resolves the Upaya location ID to use for this order based on the destination.
	 *
	 * @param array $package WooCommerce shipping package.
	 * @return int Location ID (0 if none available or invalid city).
	 */
	private function resolve_location_id( array $package = [] ): int {
		$city = $package['destination']['city'] ?? '';

		if ( ! empty( $city ) ) {
			$id = $this->location_cache->get_location_id_by_name( $city );
			if ( $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * Adds the fallback rate if configured, otherwise hides the method.
	 *
	 * @return void
	 */
	private function maybe_add_fallback_rate(): void {
		$fallback = (float) $this->get_option( 'fallback_rate', 0 );

		if ( $fallback > 0 ) {
			$this->add_rate( [
				'id'    => $this->get_rate_id(),
				'label' => $this->title,
				'cost'  => $fallback,
			] );
		}
		// If fallback is 0, the method is simply not shown — intentional.
	}
}

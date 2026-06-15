<?php
/**
 * Feature 1: Free Delivery product flag.
 *
 * Adds a "Offer Free Delivery" checkbox to the WooCommerce product edit page.
 * When ANY item in a cart carries the flag, the Upaya Cargo shipping cost is
 * zeroed out for the whole (single-shipment) package via the
 * woocommerce_package_rates filter (no core files touched).
 * A "Free Delivery" badge is shown on the product page and in the cart.
 *
 * @package BabyPasa_Delivery_Overrides
 */

defined( 'ABSPATH' ) || exit;

class BP_Free_Delivery_Product {

	public function __construct() {
		// Product edit-page field — Shipping tab is visible for all product types.
		add_action( 'woocommerce_product_options_shipping', [ $this, 'add_product_field' ] );
		add_action( 'woocommerce_process_product_meta',     [ $this, 'save_product_field' ] );

		// Rate override — priority 10 so area overrides (priority 20) can still win.
		add_filter( 'woocommerce_package_rates', [ $this, 'override_rate_if_any_free' ], 10, 2 );

		// Frontend badges.
		add_action( 'woocommerce_single_product_summary', [ $this, 'show_product_badge' ], 29 );
		add_filter( 'woocommerce_cart_item_name',         [ $this, 'append_cart_badge'  ], 10, 3 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/* ------------------------------------------------------------------
	 * Product meta field
	 * ------------------------------------------------------------------ */

	public function add_product_field(): void {
		woocommerce_wp_checkbox( [
			'id'          => '_bp_free_delivery',
			'label'       => __( 'Offer Free Delivery', 'babypasa-delivery-overrides' ),
			'description' => __( 'When every item in the cart has this enabled, shipping is free.', 'babypasa-delivery-overrides' ),
		] );
	}

	public function save_product_field( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC handles nonce on product save.
		update_post_meta( $post_id, '_bp_free_delivery', isset( $_POST['_bp_free_delivery'] ) ? 'yes' : '' );
	}

	/* ------------------------------------------------------------------
	 * Shipping rate override
	 * ------------------------------------------------------------------ */

	/**
	 * Zeros the Upaya shipping cost when AT LEAST ONE item in the package is
	 * flagged as free delivery. Because woocommerce_package_rates fires per
	 * package and a package is a single shipment to one destination, one free
	 * item makes the whole shipment free. Returns rates unchanged only when no
	 * item is flagged.
	 *
	 * @param  WC_Shipping_Rate[] $rates   Rates keyed by rate ID.
	 * @param  array              $package WooCommerce shipping package.
	 * @return WC_Shipping_Rate[]
	 */
	public function override_rate_if_any_free( array $rates, array $package ): array {
		if ( empty( $package['contents'] ) ) {
			return $rates;
		}

		$has_free_item = false;
		foreach ( $package['contents'] as $item ) {
			$product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
			// Check the variation/simple ID first, then fall back to the parent
			// product ID — the meta is stored on the parent for variable products.
			if ( 'yes' === get_post_meta( $product_id, '_bp_free_delivery', true )
				|| 'yes' === get_post_meta( $item['product_id'], '_bp_free_delivery', true ) ) {
				$has_free_item = true;
				break;
			}
		}

		if ( ! $has_free_item ) {
			return $rates; // No free-delivery item in this package — charge normally.
		}

		// At least one item qualifies — zero out the Upaya Cargo rate.
		foreach ( $rates as $rate_id => $rate ) {
			if ( false !== strpos( $rate_id, 'upaya_cargo' ) ) {
				$rate->cost  = 0;
				$rate->label = __( 'Free Delivery', 'babypasa-delivery-overrides' );
				// Zero any per-item taxes that WC may have added for shipping.
				$rate->taxes = [];
				$rates[ $rate_id ] = $rate;
			}
		}

		return $rates;
	}

	/* ------------------------------------------------------------------
	 * Badges
	 * ------------------------------------------------------------------ */

	/** Shows "Free Delivery" badge on the single-product page (before add-to-cart). */
	public function show_product_badge(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$flag = get_post_meta( $product->get_id(), '_bp_free_delivery', true );
		// Also check parent for variations shown as single products.
		if ( 'yes' !== $flag && $product->get_parent_id() ) {
			$flag = get_post_meta( $product->get_parent_id(), '_bp_free_delivery', true );
		}

		if ( 'yes' !== $flag ) {
			return;
		}

		echo '<span class="bp-free-delivery-badge">' . esc_html__( 'Free Delivery', 'babypasa-delivery-overrides' ) . '</span>';
	}

	/**
	 * Appends a small "Free Delivery" chip after the product name in the cart
	 * and checkout order-review table.
	 *
	 * @param  string $name          Product name HTML.
	 * @param  array  $cart_item     Cart item data.
	 * @param  string $cart_item_key Cart item hash key.
	 * @return string
	 */
	public function append_cart_badge( string $name, array $cart_item, string $cart_item_key ): string {
		$product_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
		$flag       = get_post_meta( $product_id, '_bp_free_delivery', true );
		if ( 'yes' !== $flag ) {
			$flag = get_post_meta( $cart_item['product_id'], '_bp_free_delivery', true );
		}

		if ( 'yes' === $flag ) {
			$name .= ' <span class="bp-free-delivery-badge bp-free-delivery-badge--inline">'
				. esc_html__( 'Free Delivery', 'babypasa-delivery-overrides' )
				. '</span>';
		}

		return $name;
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public function enqueue_styles(): void {
		wp_enqueue_style(
			'bp-delivery-overrides',
			BP_DELIVERY_OVERRIDES_URL . 'assets/css/delivery-overrides.css',
			[],
			filemtime( BP_DELIVERY_OVERRIDES_DIR . 'assets/css/delivery-overrides.css' )
		);
	}
}

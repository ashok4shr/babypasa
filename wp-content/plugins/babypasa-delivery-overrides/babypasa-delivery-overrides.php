<?php
/**
 * Plugin Name: BabyPasa Delivery Overrides
 * Description: Free-delivery product flag, area-based shipping-cost overrides, and My Account order tracking for BabyPasa. Works on top of Upaya Cargo without modifying it.
 * Version: 1.1.0
 * Author: Ashok Shrestha
 * Text Domain: babypasa-delivery-overrides
 * Requires Plugins: woocommerce, upaya-cargo-woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'BP_DELIVERY_OVERRIDES_VERSION', '1.1.0' );
define( 'BP_DELIVERY_OVERRIDES_FILE',    __FILE__ );
define( 'BP_DELIVERY_OVERRIDES_DIR',     plugin_dir_path( __FILE__ ) );
define( 'BP_DELIVERY_OVERRIDES_URL',     plugin_dir_url( __FILE__ ) );

// Register the track-orders My Account endpoint on activation and flush rewrite rules.
register_activation_hook( __FILE__, 'bp_delivery_overrides_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function bp_delivery_overrides_activate() {
	add_rewrite_endpoint( 'track-orders', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}

// Boot after Upaya (priority 20) so its classes are available if needed.
add_action( 'plugins_loaded', 'bp_delivery_overrides_boot', 25 );

function bp_delivery_overrides_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Canonical resolver first — the two contexts below both depend on it.
	require_once BP_DELIVERY_OVERRIDES_DIR . 'includes/class-delivery-charge-resolver.php';
	require_once BP_DELIVERY_OVERRIDES_DIR . 'includes/class-free-delivery-product.php';
	require_once BP_DELIVERY_OVERRIDES_DIR . 'includes/class-area-override.php';
	require_once BP_DELIVERY_OVERRIDES_DIR . 'includes/class-order-tracking-account.php';
	require_once BP_DELIVERY_OVERRIDES_DIR . 'includes/class-cart-shipping-display.php';

	new BP_Free_Delivery_Product();
	new BP_Area_Override();
	new BP_Order_Tracking_Account();
	new BP_Cart_Shipping_Display();
}

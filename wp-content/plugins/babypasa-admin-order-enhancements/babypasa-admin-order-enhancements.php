<?php
/**
 * Plugin Name: BabyPasa Admin Order Enhancements
 * Description: Adds a unified address form with Upaya delivery area selector and auto-calculated shipping, plus payment status tracking, to the WooCommerce admin order screen.
 * Version:     1.0.0
 * Author:      Ashok Shrestha
 * Text Domain: babypasa-aoe
 */

defined( 'ABSPATH' ) || exit;

define( 'BP_AOE_VERSION', '1.0.0' );
define( 'BP_AOE_DIR',     plugin_dir_path( __FILE__ ) );
define( 'BP_AOE_URL',     plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'bp_aoe_boot', 10 );

function bp_aoe_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once BP_AOE_DIR . 'includes/class-bp-admin-address-form.php';
	require_once BP_AOE_DIR . 'includes/class-bp-admin-payment-status.php';
	require_once BP_AOE_DIR . 'includes/class-bp-admin-shipping-calc.php';
	require_once BP_AOE_DIR . 'includes/class-bp-admin-order-discount.php';

	new BP_Admin_Address_Form();
	new BP_Admin_Payment_Status();
	new BP_Admin_Shipping_Calc();
	new BP_Admin_Order_Discount();
}

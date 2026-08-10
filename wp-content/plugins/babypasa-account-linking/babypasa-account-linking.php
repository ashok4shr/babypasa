<?php
/**
 * Plugin Name: BabyPasa Account Linking
 * Description: Links a guest checkout to an existing (non-privileged) customer account when the billing email matches, so the order appears in that customer's My Account history and totals immediately — cancel, return request, tracking, and address are all available right away, same as if they'd been logged in at checkout. Forward-looking at checkout, plus an opt-in WP-CLI backfill for historical guest orders.
 * Version:     1.0.0
 * Author:      Ashok Shrestha
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: babypasa-account-linking
 *
 * @package BabyPasa_Account_Linking
 */

defined( 'ABSPATH' ) || exit;

define( 'BP_AL_VERSION', '1.0.0' );
define( 'BP_AL_FILE', __FILE__ );
define( 'BP_AL_DIR', plugin_dir_path( __FILE__ ) );
define( 'BP_AL_URL', plugin_dir_url( __FILE__ ) );

require_once BP_AL_DIR . 'includes/class-bp-al-core.php';

// Boot after WooCommerce is ready (mirrors babypasa-returns' plugins_loaded:25).
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		BP_AL_Core::instance();
	},
	25
);

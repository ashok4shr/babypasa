<?php
/**
 * Plugin Name:       Upaya Cargo Shipping for WooCommerce
 * Plugin URI:        https://upayacargo.com
 * Description:       Upaya Cargo shipping integration — live rates at checkout, automatic order submission, and real-time tracking.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Ashok Shrestha
 * Text Domain:       upaya-cargo-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   10.6
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/* --------------------------------------------------------------------------
 * Global constants
 * -------------------------------------------------------------------------- */
define( 'UPAYA_VERSION',         '1.0.0' );
define( 'UPAYA_PLUGIN_FILE',     __FILE__ );
define( 'UPAYA_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'UPAYA_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'UPAYA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'UPAYA_API_BASE',        'https://portal-api.upaya.com.np/api/v1/client' );

/**
 * Default Upaya product category ID used when no per-zone override is set.
 * Corresponds to "Electronic and Gadgets" (ID 5) in the Upaya product
 * category reference table.
 */
define( 'UPAYA_CAT_ELECTRONICS', 5 );

/* --------------------------------------------------------------------------
 * Global helper functions
 *
 * Thin wrappers around UPAYA_API static methods so that shipping method and
 * order-manager code can call plain functions without coupling to the class
 * name.  The functions are defined here (early), but their bodies delegate to
 * UPAYA_API methods which are only called after the class file is loaded by
 * UPAYA_Core::load_dependencies() at plugins_loaded priority 20.
 * -------------------------------------------------------------------------- */

/**
 * Returns all Upaya service types as [id => label].
 *
 * @return array<int,string>
 */
function upaya_get_service_types(): array {
	return UPAYA_API::get_service_types();
}

/**
 * Returns all Upaya product categories as [id => label].
 *
 * @return array<int,string>
 */
function upaya_get_product_categories(): array {
	return UPAYA_API::get_product_categories();
}

/* --------------------------------------------------------------------------
 * Activation
 * -------------------------------------------------------------------------- */

/**
 * Creates the {prefix}upaya_orders tracking table and verifies WooCommerce is active.
 *
 * @return void
 */
function upaya_activate(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		wp_die(
			esc_html__( 'Upaya Cargo requires WooCommerce to be installed and active.', 'upaya-cargo-woocommerce' ),
			esc_html__( 'Plugin Activation Error', 'upaya-cargo-woocommerce' ),
			[ 'back_link' => true ]
		);
	}

	global $wpdb;

	$table           = $wpdb->prefix . 'upaya_orders';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		wc_order_id     BIGINT(20) UNSIGNED NOT NULL,
		upaya_order_id  VARCHAR(100) DEFAULT NULL,
		status          ENUM('pending','submitted','failed') DEFAULT 'pending',
		payload         LONGTEXT,
		response        LONGTEXT,
		attempts        TINYINT DEFAULT 0,
		last_attempt    DATETIME DEFAULT NULL,
		created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY wc_order_id (wc_order_id),
		KEY status (status)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'upaya_db_version', UPAYA_VERSION );
}
register_activation_hook( UPAYA_PLUGIN_FILE, 'upaya_activate' );

/* --------------------------------------------------------------------------
 * Deactivation
 * -------------------------------------------------------------------------- */

/**
 * Flushes rewrite rules on deactivation.
 *
 * @return void
 */
function upaya_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( UPAYA_PLUGIN_FILE, 'upaya_deactivate' );

/* --------------------------------------------------------------------------
 * Boot
 * -------------------------------------------------------------------------- */

/**
 * Loads UPAYA_Core once WooCommerce is confirmed active.
 *
 * @return void
 */
function upaya_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'upaya_wc_missing_notice' );
		return;
	}

	require_once UPAYA_PLUGIN_DIR . 'includes/class-upaya-core.php';
	UPAYA_Core::instance()->init();
}
add_action( 'plugins_loaded', 'upaya_boot', 20 );

/**
 * Admin notice displayed when WooCommerce is not active.
 *
 * @return void
 */
function upaya_wc_missing_notice(): void {
	echo '<div class="notice notice-error"><p>'
		. esc_html__( 'Upaya Cargo Shipping requires WooCommerce to be installed and active.', 'upaya-cargo-woocommerce' )
		. '</p></div>';
}

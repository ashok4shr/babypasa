<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin options and drops the custom tracking table.
 *
 * @package Upaya_Cargo_WooCommerce
 */

// WordPress requires this check when loading uninstall.php directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/* --------------------------------------------------------------------------
 * Delete all plugin options
 * -------------------------------------------------------------------------- */
$option_keys = [
	'upaya_api_key',
	'upaya_auto_submit',
	'upaya_debug_mode',
	'upaya_default_pickup_location',
	'upaya_retry_failed_orders',
	'upaya_db_version',
];

foreach ( $option_keys as $key ) {
	delete_option( $key );
}

// Also remove any dynamically-prefixed options (future-proof).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'upaya_%'"
);

/* --------------------------------------------------------------------------
 * Delete all plugin transients
 * -------------------------------------------------------------------------- */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_upaya_%'
	    OR option_name LIKE '_transient_timeout_upaya_%'"
);

/* --------------------------------------------------------------------------
 * Drop custom table
 * -------------------------------------------------------------------------- */
$table = $wpdb->prefix . 'upaya_orders';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

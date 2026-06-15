<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Data removal is guarded behind the BPNL_REMOVE_DATA constant so that
 * accidental plugin deletion does not wipe subscriber lists.
 * Define  define( 'BPNL_REMOVE_DATA', true );  in wp-config.php to enable.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'BPNL_REMOVE_DATA' ) || ! BPNL_REMOVE_DATA ) {
	return;
}

global $wpdb;

// Drop subscriber table.
$table = $wpdb->prefix . 'bpnl_subscribers';
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Delete all plugin options.
$options = array(
	'bpnl_db_version',
	'bpnl_template_welcome',
	'bpnl_template_newsletter',
);
foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove any lingering scheduled cron events.
wp_clear_scheduled_hook( 'bpnl_send_batch' );

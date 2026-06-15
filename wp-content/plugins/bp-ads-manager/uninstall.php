<?php
/**
 * BP Ads Manager — Uninstall script.
 *
 * Runs when the plugin is deleted from the Plugins screen.
 * Drops the custom table and removes all plugin options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the custom ads table.
$table = $wpdb->prefix . 'bp_ads';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // nosemgrep: table name is safe (prefix + literal)

// Remove plugin options.
delete_option( 'bp_ads_db_version' );
delete_option( 'bp_ads_settings' );

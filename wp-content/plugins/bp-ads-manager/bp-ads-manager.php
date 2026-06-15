<?php
/**
 * Plugin Name: BP Ads Manager
 * Plugin URI:  https://thehivecraft.com
 * Description: Manage popup and banner ads. Stores ads in a custom DB table — no CPT required.
 * Version:     1.0.0
 * Author:      Ashok Shrestha
 * Text Domain: bp-ads-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BP_ADS_VERSION',     '1.0.0' );
define( 'BP_ADS_PATH',        plugin_dir_path( __FILE__ ) );
define( 'BP_ADS_URL',         plugin_dir_url( __FILE__ ) );
define( 'BP_ADS_PLUGIN_FILE', __FILE__ );

require_once BP_ADS_PATH . 'includes/class-bp-ads-db.php';
require_once BP_ADS_PATH . 'includes/class-bp-ads-ajax.php';
require_once BP_ADS_PATH . 'includes/class-bp-ads-renderer.php';
require_once BP_ADS_PATH . 'includes/class-bp-ads-admin.php';
require_once BP_ADS_PATH . 'includes/class-bp-ads-manager.php';

register_activation_hook( BP_ADS_PLUGIN_FILE, array( 'BP_Ads_Manager', 'activate' ) );
register_deactivation_hook( BP_ADS_PLUGIN_FILE, array( 'BP_Ads_Manager', 'deactivate' ) );

BP_Ads_Manager::get_instance();

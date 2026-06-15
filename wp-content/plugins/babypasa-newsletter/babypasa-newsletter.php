<?php
/**
 * Plugin Name: Babypasa Newsletter
 * Description: Newsletter subscription management for Babypasa.
 * Version:     1.0.0
 * Author:      Ashok Shrestha
 * Text Domain: babypasa-newsletter
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 *
 * Notification system mirrors babypasa-wishlist-compare exactly:
 *
 * PHP  → wp_send_json_success/error( [ 'message' => '...' ] )
 *        produces { success: bool, data: { message: string } }
 *
 * JS   → reads response.success + response.data.message,
 *        calls showBpNotification( title, message ) which:
 *          - appends/reuses .bp-notification-container (fixed top-right, z 999999)
 *          - builds .bp-notification with .bp-notification-title / .bp-notification-message
 *          - adds .bp-show  (300 ms slide-in via translateX + opacity)
 *          - after 5 000 ms removes .bp-show, adds .bp-hiding (300 ms slide-out)
 *          - then removes the node from DOM
 *          - .bp-notification-close button cancels the timer and dismisses immediately
 *          - dedup guard: if data-notif-type already present, skip
 *
 * CSS  → same class names; styles defined in public/assets/css/bpnl-form.css
 *        so the toast works even without babypasa-wishlist-compare active.
 */

namespace BabypasaNewsletter;

defined( 'ABSPATH' ) || exit;

define( 'BPNL_VERSION',    '1.0.0' );
define( 'BPNL_PLUGIN_FILE', __FILE__ );
define( 'BPNL_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BPNL_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'BPNL_DB_VERSION',  '1.0' );

/**
 * PSR-4 autoloader for the BabypasaNewsletter namespace.
 *
 * Maps:
 *   BabypasaNewsletter\Includes\Foo  → includes/class-foo.php
 *   BabypasaNewsletter\Admin\Foo     → admin/class-foo.php
 *   BabypasaNewsletter\Frontend\Foo  → public/class-foo.php
 */
spl_autoload_register( function ( string $class ) {
	$prefix = 'BabypasaNewsletter\\';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$parts    = explode( '\\', $relative );

	if ( count( $parts ) < 2 ) {
		return;
	}

	$namespace_map = array(
		'Includes' => 'includes',
		'Admin'    => 'admin',
		'Frontend' => 'public',
	);

	if ( ! isset( $namespace_map[ $parts[0] ] ) ) {
		return;
	}

	$dir       = $namespace_map[ $parts[0] ];
	$classname = end( $parts );
	$slug      = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $classname ) );
	$slug      = preg_replace( '/-+/', '-', str_replace( '_', '-', $slug ) );
	$file      = BPNL_PLUGIN_DIR . $dir . DIRECTORY_SEPARATOR . 'class-' . $slug . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( BPNL_PLUGIN_FILE,   array( Includes\Activator::class, 'activate' ) );
register_deactivation_hook( BPNL_PLUGIN_FILE, array( Includes\Activator::class, 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	new Includes\Ajax();
	new Includes\Unsubscribe();

	if ( is_admin() ) {
		new Admin\Admin();
	} else {
		new Frontend\Shortcode();
	}
} );

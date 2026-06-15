<?php
/**
 * Plugin Name: BabyPasa Google Login
 * Description: Lightweight "Continue with Google" using the OAuth 2.0 Authorization Code flow with a FULL-PAGE REDIRECT (no popup). Avoids the Nextend popup-bridge "Continue..." blank-page issues in PWA/Chrome. Zero external dependencies.
 * Version:     1.0.0
 * Author:      Ashok Shrestha
 * Text Domain: babypasa-google-login
 * Requires PHP: 7.4
 * Requires at least: 6.0
 *
 * Why full-page redirect: the entire browser tab navigates to accounts.google.com
 * and back to our callback. No popup window is opened, so there is no window.opener,
 * no window.close(), no BroadcastChannel and no intermediate "Continue..." page —
 * the whole class of NSL popup bugs (desktop vs standalone PWA vs Chrome-with-PWA)
 * cannot occur. Behaviour is identical in every context.
 */

defined( 'ABSPATH' ) || exit;

define( 'BP_GLOGIN_VERSION', '1.0.0' );
define( 'BP_GLOGIN_FILE',    __FILE__ );
define( 'BP_GLOGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'BP_GLOGIN_URL',     plugin_dir_url( __FILE__ ) );

/* --------------------------------------------------------------------------
 * Activation / deactivation — register the clean auth routes, then flush.
 * Routes (no query string, so Google accepts the redirect URI verbatim):
 *   /bp-google-auth/           → start the login (redirect to Google)
 *   /bp-google-auth/callback/  → Google returns here with ?code & ?state
 * -------------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'bp_glogin_activate' );
function bp_glogin_activate(): void {
	bp_glogin_register_rewrites();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function bp_glogin_register_rewrites(): void {
	add_rewrite_rule( '^bp-google-auth/?$',          'index.php?bp_google_auth=start',    'top' );
	add_rewrite_rule( '^bp-google-auth/callback/?$', 'index.php?bp_google_auth=callback', 'top' );
}

/* --------------------------------------------------------------------------
 * Boot
 * -------------------------------------------------------------------------- */

add_action( 'plugins_loaded', 'bp_glogin_boot' );
function bp_glogin_boot(): void {
	require_once BP_GLOGIN_DIR . 'includes/class-bp-google-login-settings.php';
	require_once BP_GLOGIN_DIR . 'includes/class-bp-google-oauth.php';
	require_once BP_GLOGIN_DIR . 'includes/class-bp-google-login.php';

	new BP_Google_Login_Settings();
	new BP_Google_Login();
}

<?php
/**
 * Plugin Name:  BabyPasa ConnectIPS Gateway
 * Description:  ConnectIPS payment gateway for BabyPasa. Integrates with Upaya Cargo (cod_amount = 0 on paid orders).
 * Requires at least: 6.1
 * Requires PHP:      8.0
 * Version:           1.0.0
 * Author:            Ashok Shrestha
 * Text Domain:       babypasa-connectips
 * Requires Plugins:  woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'BC_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BC_URL',  plugin_dir_url( __FILE__ ) );
define( 'BC_VER',  '1.0.0' );

add_action( 'plugins_loaded', 'bc_boot', 20 );

function bc_boot(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once BC_DIR . 'includes/class-bc-encryption.php';
	require_once BC_DIR . 'includes/class-bc-logger.php';
	require_once BC_DIR . 'includes/class-bc-gateway.php';
	require_once BC_DIR . 'includes/class-bc-checkout-ui.php';

	add_filter( 'woocommerce_payment_gateways', 'bc_register_gateway' );

	// Checkout-facing presentation: gateway logo, payment description, privacy notice.
	BC_Checkout_UI::init();

	// Safety net: ensure cod_amount is always 0 for ConnectIPS-paid orders sent to Upaya.
	add_filter( 'upaya_payload_cod_amount', 'bc_upaya_zero_cod', 10, 2 );
}

function bc_register_gateway( array $gateways ): array {
	$gateways[] = BC_Gateway::class;
	return $gateways;
}

/**
 * Force cod_amount = 0 whenever an order was paid via this gateway.
 * The Upaya plugin already does this via payment-method check, but this
 * filter makes the intent explicit and survives future Upaya refactors.
 */
function bc_upaya_zero_cod( $amount, $order ): int {
	if ( $order instanceof WC_Order && 'babypasa_connectips' === $order->get_payment_method() ) {
		return 0;
	}
	return (int) $amount;
}

// ---------- Pretty-path callback routing ------------------------------------------

add_action( 'init',              'bc_register_callback_rewrite' );
add_filter( 'query_vars',        'bc_callback_query_vars' );
add_action( 'template_redirect', 'bc_dispatch_callback', 1 );
add_action( 'admin_init',        'bc_maybe_flush_rewrites' );
register_activation_hook( __FILE__, 'bc_activation_flush' );

function bc_register_callback_rewrite(): void {
	add_rewrite_rule(
		'^connectips/payment/(success|failure)/?$',
		'index.php?connectips_callback=$matches[1]',
		'top'
	);
}

function bc_callback_query_vars( array $vars ): array {
	$vars[] = 'connectips_callback';
	return $vars;
}

function bc_dispatch_callback(): void {
	$type = get_query_var( 'connectips_callback' );
	if ( ! $type || ! function_exists( 'WC' ) ) {
		return;
	}

	$gateways = WC()->payment_gateways()->payment_gateways();
	$gateway  = $gateways['babypasa_connectips'] ?? null;

	if ( ! $gateway ) {
		wp_die( esc_html__( 'Payment gateway not available.', 'babypasa-connectips' ) );
	}

	if ( 'success' === $type ) {
		$gateway->handle_success();
	} elseif ( 'failure' === $type ) {
		$gateway->handle_failure();
	}
	exit;
}

function bc_activation_flush(): void {
	bc_register_callback_rewrite();
	flush_rewrite_rules();
}

// Flush rewrite rules once after deployment (clears itself after first admin page load).
function bc_maybe_flush_rewrites(): void {
	if ( get_option( 'bc_rewrite_flushed' ) !== 'v1' ) {
		bc_register_callback_rewrite();
		flush_rewrite_rules();
		update_option( 'bc_rewrite_flushed', 'v1' );
	}
}

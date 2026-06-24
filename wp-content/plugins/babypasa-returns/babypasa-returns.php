<?php
/**
 * Plugin Name: BabyPasa Returns & RTO
 * Description: Customer-initiated returns + Upaya RTO (return-to-origin) flow, with the client-design emails E16–E20. Wires the ready-to-wire return templates to real senders.
 * Version:     1.1.0
 * Author:      BabyPasa
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * Flow handled by this plugin (client emails E16–E20):
 *
 *   Logistics RTO path (Upaya webhook driven):
 *     failed delivery attempt        → E16 (no state change, 12h cooldown)
 *     return-to-origin-initiated     → E17 (state → RTO)        [suppressed if customer already requested]
 *     returned-to-vendor (warehouse) → E20 (state → RTO_COMPLETE)
 *
 *   Customer return path (My Account driven):
 *     customer "Request Return"      → E18 (state → REQUESTED)
 *     admin "Approve Return" action  → E19 (state → APPROVED)
 *     returned-to-vendor (warehouse) → E20 (state → RTO_COMPLETE)
 *
 *   The actual refund is issued by the admin in WooCommerce afterwards →
 *   E21 (customer-refunded-order.php, already live in the child theme).
 *
 * Email bodies are the child-theme templates in
 * woocommerce/emails/ready-to-wire/e16..e20-*.php; this plugin supplies the
 * senders (WC_Email classes), the RTO state machine, and the return-request UI.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

define( 'BP_RETURNS_VERSION', '1.1.0' );
define( 'BP_RETURNS_FILE', __FILE__ );
define( 'BP_RETURNS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BP_RETURNS_URL', plugin_dir_url( __FILE__ ) );

require_once BP_RETURNS_DIR . 'includes/class-bp-returns-core.php';

// Boot after WooCommerce (and the Upaya plugin at plugins_loaded:20) are ready.
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		BP_Returns_Core::instance();
	},
	25
);

// Activation: register the return-request rewrite endpoint, then flush.
register_activation_hook(
	__FILE__,
	static function () {
		require_once BP_RETURNS_DIR . 'includes/class-bp-returns-request.php';
		BP_Returns_Request::add_endpoint();
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);

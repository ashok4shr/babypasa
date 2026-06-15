<?php
/**
 * Core loader — boots the return/RTO subsystems.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

final class BP_Returns_Core {

	/** @var BP_Returns_Core|null */
	private static $instance = null;

	public static function instance(): BP_Returns_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();

		// Register the E16–E20 WC_Email classes with the WooCommerce mailer.
		new BP_Returns_Emails();

		// Listen for Upaya webhook statuses → fire E16/E17/E20.
		new BP_Returns_Webhook_Router();

		// Customer "Request Return" (E18) + admin "Approve Return" (E19) + endpoint.
		new BP_Returns_Request();
	}

	private function includes(): void {
		require_once BP_RETURNS_DIR . 'includes/class-bp-returns-state.php';
		require_once BP_RETURNS_DIR . 'includes/class-bp-returns-emails.php';
		require_once BP_RETURNS_DIR . 'includes/class-bp-returns-webhook-router.php';
		require_once BP_RETURNS_DIR . 'includes/class-bp-returns-request.php';
	}
}

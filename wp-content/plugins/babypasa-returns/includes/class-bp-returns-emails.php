<?php
/**
 * Registers the E16–E20 WC_Email classes with the WooCommerce mailer and
 * provides a helper to fetch a registered instance for triggering.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Returns_Emails {

	/**
	 * Map of WC_Email id => class name.
	 *
	 * @var array<string,string>
	 */
	const EMAILS = array(
		'bp_failed_delivery'  => 'BP_Email_Failed_Delivery',
		'bp_rto_initiated'    => 'BP_Email_RTO_Initiated',
		'bp_return_requested' => 'BP_Email_Return_Requested',
		'bp_return_approved'  => 'BP_Email_Return_Approved',
		'bp_return_rejected'  => 'BP_Email_Return_Rejected',
		'bp_rto_complete'     => 'BP_Email_RTO_Complete',
	);

	public function __construct() {
		add_filter( 'woocommerce_email_classes', array( $this, 'register' ) );
	}

	/**
	 * @param array<string,WC_Email> $emails Registered email classes.
	 * @return array<string,WC_Email>
	 */
	public function register( array $emails ): array {
		require_once BP_RETURNS_DIR . 'includes/emails/class-bp-email-base.php';
		require_once BP_RETURNS_DIR . 'includes/emails/class-bp-email-failed-delivery.php';
		require_once BP_RETURNS_DIR . 'includes/emails/class-bp-email-rto-initiated.php';
		require_once BP_RETURNS_DIR . 'includes/emails/class-bp-email-return-requested.php';
		require_once BP_RETURNS_DIR . 'includes/emails/class-bp-email-return-approved.php';
		require_once BP_RETURNS_DIR . 'includes/emails/class-bp-email-return-rejected.php';
		require_once BP_RETURNS_DIR . 'includes/emails/class-bp-email-rto-complete.php';

		foreach ( self::EMAILS as $class ) {
			$instance = new $class();
			$emails[ $class ] = $instance;
		}

		return $emails;
	}

	/**
	 * Fetch the registered WC_Email instance by its email id, so triggers go
	 * through the mailer-managed instance (settings respected).
	 *
	 * @param string $email_id e.g. 'bp_rto_initiated'.
	 * @return WC_Email|null
	 */
	public static function get( string $email_id ): ?WC_Email {
		if ( ! isset( self::EMAILS[ $email_id ] ) ) {
			return null;
		}
		$class  = self::EMAILS[ $email_id ];
		$emails = WC()->mailer()->get_emails();

		if ( isset( $emails[ $class ] ) ) {
			return $emails[ $class ];
		}

		// Fallback: instantiate directly if the mailer hasn't built it.
		if ( class_exists( $class ) ) {
			return new $class();
		}
		return null;
	}
}

<?php
/**
 * Registers the bp_invoice + bp_feedback WC_Email classes with the WooCommerce
 * mailer and provides a helper to fetch a registered instance for triggering.
 *
 * Mirrors babypasa-returns' BP_Returns_Emails registrar.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_OE_Emails {

	/**
	 * Map of WC_Email id => class name.
	 *
	 * @var array<string,string>
	 */
	const EMAILS = array(
		'bp_invoice'  => 'BP_OE_Invoice_Email',
		'bp_feedback' => 'BP_OE_Feedback_Email',
	);

	public function __construct() {
		add_filter( 'woocommerce_email_classes', array( $this, 'register' ) );
	}

	/**
	 * @param array<string,WC_Email> $emails Registered email classes.
	 * @return array<string,WC_Email>
	 */
	public function register( array $emails ): array {
		require_once BP_OE_DIR . 'includes/emails/class-bp-oe-email-base.php';
		require_once BP_OE_DIR . 'includes/emails/class-bp-oe-invoice-email.php';
		require_once BP_OE_DIR . 'includes/emails/class-bp-oe-feedback-email.php';

		foreach ( self::EMAILS as $class ) {
			$emails[ $class ] = new $class();
		}

		return $emails;
	}

	/**
	 * Fetch the registered WC_Email instance by its email id, so triggers go
	 * through the mailer-managed instance (settings respected).
	 *
	 * @param string $email_id e.g. 'bp_invoice'.
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

		// Fallback: instantiate directly if the mailer hasn't built it yet.
		if ( class_exists( $class ) ) {
			return new $class();
		}
		return null;
	}
}

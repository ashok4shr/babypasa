<?php
/**
 * Core loader — boots the order-email subsystems.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

final class BP_OE_Core {

	/** @var BP_OE_Core|null */
	private static $instance = null;

	public static function instance(): BP_OE_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();

		// Register the bp_invoice + bp_feedback WC_Email classes with the mailer.
		new BP_OE_Emails();

		// Order-edit "Order actions" dropdown entries (manual invoice / feedback).
		new BP_OE_Order_Actions();

		// Schedule the delayed feedback email via Action Scheduler on completion.
		new BP_OE_Feedback_Scheduler();

		// Invoice PDF: generator service, email attachment, admin screen + endpoints.
		$pdf_generator = new BP_Invoice_PDF_Generator();
		new BP_Invoice_PDF_Attachment( $pdf_generator );

		if ( is_admin() ) {
			new BP_Invoice_PDF_Admin();
			new BP_Invoice_PDF_Ajax( $pdf_generator );
		}
	}

	private function includes(): void {
		require_once BP_OE_DIR . 'includes/class-bp-oe-emails.php';
		require_once BP_OE_DIR . 'includes/class-bp-oe-order-actions.php';
		require_once BP_OE_DIR . 'includes/class-bp-oe-feedback-scheduler.php';

		// Invoice PDF subsystem.
		require_once BP_OE_DIR . 'includes/pdf/class-bp-invoice-pdf-settings.php';
		require_once BP_OE_DIR . 'includes/pdf/class-bp-invoice-pdf-storage.php';
		require_once BP_OE_DIR . 'includes/pdf/class-bp-invoice-pdf-generator.php';
		require_once BP_OE_DIR . 'includes/pdf/class-bp-invoice-pdf-attachment.php';

		if ( is_admin() ) {
			require_once BP_OE_DIR . 'includes/admin/class-bp-invoice-pdf-admin.php';
			require_once BP_OE_DIR . 'includes/admin/class-bp-invoice-pdf-ajax.php';
		}
	}
}

<?php
/**
 * Attaches the generated invoice PDF to the Invoice email only.
 *
 * Hooks woocommerce_email_attachments (fired inside WC_Email::send(), which our
 * base's send_for_order() calls) and bails unless the email id is 'bp_invoice',
 * so no other email — admin new-order, order-received, feedback — ever gets it.
 * Generation happens here, at send time, so resends reflect the order's current
 * state and the current PDF settings.
 *
 * A PDF failure never blocks the email: the attachment is simply omitted, logged,
 * and recorded on the order.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_Invoice_PDF_Attachment {

	/** @var BP_Invoice_PDF_Generator */
	private $generator;

	public function __construct( BP_Invoice_PDF_Generator $generator ) {
		$this->generator = $generator;
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach' ), 10, 4 );
	}

	/**
	 * @param array<int,string> $attachments Existing attachment paths.
	 * @param string            $email_id    The email being sent.
	 * @param mixed             $object      Usually the WC_Order.
	 * @param WC_Email|null     $email       The email object.
	 * @return array<int,string>
	 */
	public function attach( $attachments, $email_id, $object, $email = null ) {
		if ( 'bp_invoice' !== $email_id || ! $object instanceof WC_Order ) {
			return $attachments;
		}

		try {
			$path = $this->generator->generate_file( $object );
		} catch ( Throwable $e ) {
			// Defensive: the generator is already try/catch-guarded, but never let
			// a PDF problem bubble up and block the transactional email.
			$path = null;
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->log( 'error', 'Invoice PDF attach threw: ' . $e->getMessage(), array( 'source' => BP_Invoice_PDF_Generator::LOG_SOURCE ) );
			}
		}

		$diag = $this->generator->get_last_diagnostics();

		if ( $path && is_readable( $path ) ) {
			$attachments[] = $path;
			// Note when a broken custom template forced the default fallback.
			if ( ! empty( $diag['fallback'] ) ) {
				$object->add_order_note(
					__( 'Invoice PDF: custom template failed, used the default template instead. See the bp-invoice-pdf log.', 'babypasa-order-emails' )
				);
			}
		} else {
			// Attachment omitted — email still goes out.
			$object->add_order_note(
				__( 'Invoice email sent without the PDF attachment (PDF generation failed). See the bp-invoice-pdf log.', 'babypasa-order-emails' )
			);
		}

		return $attachments;
	}
}

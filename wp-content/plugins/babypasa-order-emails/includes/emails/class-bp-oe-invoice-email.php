<?php
/**
 * Invoice email (id: bp_invoice).
 *
 * Auto-sends to the customer when an order is marked "completed", and can be
 * resent on demand from the order-edit Order actions dropdown. The body renders
 * an invoice-style layout (order number/date, billing details, line items with
 * quantities and totals, shipping, payment method, grand total) via the standard
 * WooCommerce email order-detail hooks inside the shared BabyPasa design.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_OE_Invoice_Email extends BP_OE_Email_Base {

	/** @var string */
	protected $bp_template = 'emails/bp-invoice.php';

	public function __construct() {
		$this->id          = 'bp_invoice';
		$this->title       = __( 'Baby Pasa invoice', 'babypasa-order-emails' );
		$this->description = __( 'Invoice/receipt emailed to the customer when an order is completed, and on demand from the order screen.', 'babypasa-order-emails' );

		parent::__construct();

		// Self-register the automatic send, the way core WooCommerce emails do.
		// The mailer builds this class exactly once (via woocommerce_email_classes),
		// so this binds a single completed-order callback.
		add_action( 'woocommerce_order_status_completed_notification', array( $this, 'trigger' ), 10, 2 );
	}

	public function get_default_subject(): string {
		return __( 'Your invoice for order #{order_number}', 'babypasa-order-emails' );
	}

	public function get_default_heading(): string {
		return __( 'Invoice for order #{order_number}', 'babypasa-order-emails' );
	}

	/**
	 * Automatic trigger on order completion. Respects the enable toggle; the
	 * manual resend path (send_for_order via the Order action) bypasses it.
	 *
	 * @param int           $order_id Order id.
	 * @param WC_Order|bool $order    Order object (passed by the notification hook).
	 */
	public function trigger( $order_id, $order = false ): void {
		if ( ! $order_id || ! $this->is_enabled() ) {
			return;
		}

		$this->send_for_order( (int) $order_id );
	}
}

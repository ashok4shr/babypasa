<?php
/**
 * Order-edit "Order actions" dropdown entries for manual sends.
 *
 * Adds two admin-triggered actions (invoice + feedback) via the
 * woocommerce_order_actions filter and their matching
 * woocommerce_order_action_{action} handlers. Both record an order note so the
 * send is auditable, consistent with the returns plugin's admin flows.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_OE_Order_Actions {

	public function __construct() {
		add_filter( 'woocommerce_order_actions', array( $this, 'add_actions' ) );
		add_action( 'woocommerce_order_action_bp_send_invoice', array( $this, 'handle_invoice' ) );
		add_action( 'woocommerce_order_action_bp_send_feedback', array( $this, 'handle_feedback' ) );
	}

	/**
	 * @param array<string,string> $actions Existing order actions.
	 * @return array<string,string>
	 */
	public function add_actions( array $actions ): array {
		$actions['bp_send_invoice']  = __( 'Send Baby Pasa invoice to customer', 'babypasa-order-emails' );
		$actions['bp_send_feedback'] = __( 'Send feedback request to customer', 'babypasa-order-emails' );
		return $actions;
	}

	/**
	 * Manual invoice send. An explicit admin action always sends (the enable
	 * toggle only gates the automatic on-completion send).
	 *
	 * @param WC_Order $order Order object (passed by WooCommerce).
	 */
	public function handle_invoice( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$email = BP_OE_Emails::get( 'bp_invoice' );
		if ( ! $email instanceof WC_Email ) {
			return;
		}

		$email->send_for_order( $order->get_id() );
		$order->add_order_note( __( 'Baby Pasa invoice emailed to the customer (manual send).', 'babypasa-order-emails' ), false, true );
	}

	/**
	 * Manual feedback send. Marks the one-shot guard so the scheduled send (if
	 * still pending) won't duplicate it.
	 *
	 * @param WC_Order $order Order object (passed by WooCommerce).
	 */
	public function handle_feedback( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$email = BP_OE_Emails::get( 'bp_feedback' );
		if ( ! $email instanceof WC_Email ) {
			return;
		}

		if ( $email->send_for_order( $order->get_id() ) ) {
			$order->update_meta_data( BP_OE_Feedback_Email::SENT_META, time() );
			$order->add_order_note( __( 'Feedback request emailed to the customer (manual send).', 'babypasa-order-emails' ), false, true );
			$order->save();
		}
	}
}

<?php
/**
 * Marks an order's canonical payment state as fully paid on delivery.
 *
 * The store's single source of truth for the "Paid / Partially Paid / Unpaid"
 * badge shown in emails and admin is the `_bp_payment_status` order meta
 * (see BP_Admin_Payment_Status + bp_email_payment_badge()). That meta is only
 * ever written by the admin "Payment Status" box, so an ordinary front-end COD
 * order never has it set — and because COD carries no transaction id, the badge
 * falls back to "Unpaid" even after the order is delivered and the cash has been
 * collected. WooCommerce's own is_paid()/date_paid() is deliberately NOT used
 * for the badge (it flips true on the processing transition too, which would
 * wrongly show COD as "Paid" on the order-confirmation email E03).
 *
 * A COD order only reaches "Delivered" (WC status `completed`, via the Upaya
 * webhook's delivered → completed mapping) once payment has actually been
 * collected, so completion is the correct, gateway-agnostic point to advance
 * the canonical state. Fixing it here makes it correct everywhere the badge is
 * read — the delivery email (E12), admin, and reports — not just one template.
 *
 * This reacts to the standard WC status-transition hook; it does not modify the
 * Upaya webhook / transition logic itself. It also covers a manual "Complete"
 * from the admin order screen.
 *
 * @package BabyPasa_Delivery_Overrides
 * @author  Ashok Shrestha / The Hive Craft
 */

defined( 'ABSPATH' ) || exit;

class BP_Payment_Status_Delivered_Sync {

	/** Canonical payment-status meta key (shared with BP_Admin_Payment_Status). */
	const PAYMENT_STATUS_META = '_bp_payment_status';

	public function __construct() {
		// Fires on the delivered → completed transition (Upaya webhook) and on a
		// manual admin "Complete". Runs before the Upaya delivered email is sent,
		// so E12 renders the freshly-saved "Paid" badge.
		add_action( 'woocommerce_order_status_completed', [ $this, 'mark_paid_on_delivery' ], 10, 2 );
	}

	/**
	 * Advances the canonical payment state to "fully paid" once an order is
	 * completed (delivered), unless it is already recorded as fully paid.
	 *
	 * @author Ashok Shrestha / The Hive Craft
	 *
	 * @param  int            $order_id Order ID.
	 * @param  \WC_Order|null $order    Order object (passed by WooCommerce).
	 * @return void
	 */
	public function mark_paid_on_delivery( int $order_id, $order = null ): void {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Idempotent: nothing to do if already recorded fully paid.
		if ( 'fully_paid' === (string) $order->get_meta( self::PAYMENT_STATUS_META ) ) {
			return;
		}

		$order->update_meta_data( self::PAYMENT_STATUS_META, 'fully_paid' );
		$order->delete_meta_data( '_bp_amount_paid' ); // No partial remainder once fully collected.
		$order->add_order_note(
			__( 'Payment marked fully paid on delivery (order completed).', 'babypasa-delivery-overrides' )
		);
		$order->save();
	}
}

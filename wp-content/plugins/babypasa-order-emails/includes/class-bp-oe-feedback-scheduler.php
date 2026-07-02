<?php
/**
 * Schedules and sends the delayed feedback / review-request email.
 *
 * On order completion, queues a single Action Scheduler action for N days later
 * (default 3, filterable via bp_feedback_delay_days). At send time the action
 * re-checks that the order is still "completed" (never sending for a since
 * cancelled/refunded order), that the email is enabled, and that it hasn't
 * already gone out — so the toggle and status are honoured at the moment of
 * sending, not just when scheduled.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_OE_Feedback_Scheduler {

	/** Action Scheduler hook + group. */
	const HOOK  = 'bp_oe_send_feedback';
	const GROUP = 'babypasa-order-emails';

	public function __construct() {
		// Queue on completion.
		add_action( 'woocommerce_order_status_completed', array( $this, 'schedule' ), 20, 2 );
		// Deliver when the scheduled action fires.
		add_action( self::HOOK, array( $this, 'run' ), 10, 1 );
	}

	/**
	 * Queue the feedback email for delivery N days after completion.
	 *
	 * @param int           $order_id Order id.
	 * @param WC_Order|null $order    Order object (passed by the status hook).
	 */
	public function schedule( $order_id, $order = null ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		$email = BP_OE_Emails::get( 'bp_feedback' );
		if ( ! $email instanceof WC_Email || ! $email->is_enabled() ) {
			return;
		}

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || BP_OE_Feedback_Email::already_sent( $order ) ) {
			return;
		}

		$args = array( (int) $order_id );
		if ( as_next_scheduled_action( self::HOOK, $args, self::GROUP ) ) {
			return; // Already queued for this order.
		}

		$days  = (int) apply_filters( 'bp_feedback_delay_days', 3, $order );
		$delay = max( 0, $days ) * DAY_IN_SECONDS;

		as_schedule_single_action( time() + $delay, self::HOOK, $args, self::GROUP );
	}

	/**
	 * Action Scheduler callback — deliver the feedback email if still valid.
	 *
	 * @param int $order_id Order id.
	 */
	public function run( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Status re-check at send time: skip cancelled/refunded/etc. orders.
		if ( ! $order->has_status( 'completed' ) || BP_OE_Feedback_Email::already_sent( $order ) ) {
			return;
		}

		$email = BP_OE_Emails::get( 'bp_feedback' );
		if ( ! $email instanceof WC_Email || ! $email->is_enabled() ) {
			return;
		}

		if ( $email->send_for_order( (int) $order_id ) ) {
			$order->update_meta_data( BP_OE_Feedback_Email::SENT_META, time() );
			$order->add_order_note( __( 'Feedback / review-request email sent to the customer.', 'babypasa-order-emails' ) );
			$order->save();
		}
	}
}

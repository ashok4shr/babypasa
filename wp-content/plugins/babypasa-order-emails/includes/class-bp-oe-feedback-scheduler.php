<?php
/**
 * Schedules and sends the delayed feedback / review-request email.
 *
 * On order completion, queues a single Action Scheduler action for N days later
 * (default 2, filterable via bp_feedback_delay_days). All eligibility guards and
 * the actual send/log live in one place (dispatch()), shared by the automatic
 * Action Scheduler callback and the manual "Order actions" send, so the two
 * paths can never drift apart or double-send.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_OE_Feedback_Scheduler {

	/** Action Scheduler hook + group. */
	const HOOK  = 'bp_oe_send_feedback';
	const GROUP = 'babypasa-order-emails';

	/**
	 * Option recording when this feature first went live. Orders completed
	 * before this timestamp are not eligible for the automatic send and are
	 * excluded from the backfill CLI command by default.
	 */
	const CUTOFF_OPTION = 'bp_feedback_activation_cutoff';

	public function __construct() {
		self::maybe_record_activation_cutoff();

		// Queue on completion.
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule' ), 20, 2 );
		// Deliver when the scheduled action fires.
		add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 1 );
	}

	/** Record the go-live timestamp once, the first time this code runs. */
	public static function maybe_record_activation_cutoff(): void {
		add_option( self::CUTOFF_OPTION, time() );
	}

	/**
	 * Queue the feedback email for delivery N days after completion. Also used
	 * directly by the backfill CLI command for historical orders.
	 *
	 * @param int           $order_id Order id.
	 * @param WC_Order|null $order    Order object (passed by the status hook).
	 */
	public static function schedule( $order_id, $order = null ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		$email = BP_OE_Emails::get( 'bp_feedback' );
		if ( ! $email instanceof WC_Email || ! $email->is_enabled() ) {
			return;
		}

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || null !== self::get_skip_reason( $order ) ) {
			return;
		}

		$args = array( (int) $order->get_id() );
		if ( as_next_scheduled_action( self::HOOK, $args, self::GROUP ) ) {
			return; // Already queued for this order.
		}

		/**
		 * Days to wait after order completion before sending the feedback email.
		 *
		 * @param int      $days  Delay in days. Default 2.
		 * @param WC_Order $order The order.
		 */
		$days  = (int) apply_filters( 'bp_feedback_delay_days', 2, $order );
		$delay = max( 0, $days ) * DAY_IN_SECONDS;

		as_schedule_single_action( time() + $delay, self::HOOK, $args, self::GROUP );
	}

	/**
	 * Action Scheduler callback — deliver the feedback email if still valid.
	 *
	 * @param int $order_id Order id.
	 */
	public static function run( $order_id ): void {
		$email = BP_OE_Emails::get( 'bp_feedback' );
		if ( ! $email instanceof WC_Email || ! $email->is_enabled() ) {
			return; // Toggle may have been switched off during the delay.
		}

		self::dispatch( (int) $order_id, 'automatic' );
	}

	/**
	 * Manual send entry point (Order actions dropdown). Shares the same guards,
	 * idempotency flag, and order-note logging as the automatic path — an
	 * explicit admin send still honours the completed-only constraint and can
	 * never duplicate an already-sent email, it just bypasses the enable toggle
	 * (an explicit admin action always attempts to send, same convention as the
	 * invoice email's manual resend).
	 *
	 * @param WC_Order $order   Order object.
	 * @param int      $user_id Admin user id performing the send.
	 * @return bool Whether the email was sent.
	 */
	public static function send_manually( WC_Order $order, int $user_id ): bool {
		return self::dispatch( $order->get_id(), 'manual', $user_id );
	}

	/**
	 * The single shared send routine. Re-fetches the order, re-checks every
	 * guard, dispatches, and logs the outcome — success, skip, or failure — to
	 * the order notes. Never called directly from outside this class; use
	 * run() (automatic) or send_manually() (manual).
	 *
	 * @param int      $order_id Order id.
	 * @param string   $context  'automatic' or 'manual'.
	 * @param int|null $user_id  Admin user id, when $context is 'manual'.
	 * @return bool Whether the email was sent.
	 */
	private static function dispatch( int $order_id, string $context, ?int $user_id = null ): bool {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$skip_reason = self::get_skip_reason( $order );
		if ( null !== $skip_reason ) {
			$order->add_order_note( $skip_reason );
			return false;
		}

		$email = BP_OE_Emails::get( 'bp_feedback' );
		if ( ! $email instanceof WC_Email ) {
			return false;
		}

		try {
			$sent = $email->send_for_order( $order_id );
		} catch ( \Throwable $e ) {
			wc_get_logger()->error(
				sprintf( 'Feedback email exception for order #%d: %s', $order_id, $e->getMessage() ),
				array( 'source' => 'bp-feedback-email' )
			);
			$order->add_order_note(
				sprintf(
					/* translators: %s: exception message */
					__( 'Feedback email failed — an unexpected error occurred (%s).', 'babypasa-order-emails' ),
					$e->getMessage()
				)
			);
			return false;
		}

		if ( ! $sent ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: recipient email address */
					__( 'Feedback email failed — wp_mail() returned false. Recipient: %s.', 'babypasa-order-emails' ),
					$order->get_billing_email()
				)
			);
			return false;
		}

		$order->update_meta_data( BP_OE_Feedback_Email::SENT_META, time() );

		if ( 'manual' === $context ) {
			$user = $user_id ? get_userdata( $user_id ) : false;
			$order->add_order_note(
				sprintf(
					/* translators: 1: recipient email address, 2: admin display name */
					__( 'Feedback email sent to %1$s (manual, by %2$s).', 'babypasa-order-emails' ),
					$order->get_billing_email(),
					$user ? $user->display_name : __( 'admin', 'babypasa-order-emails' )
				),
				false,
				true
			);
		} else {
			/** This filter is documented in schedule() above. */
			$days = (int) apply_filters( 'bp_feedback_delay_days', 2, $order );
			$order->add_order_note(
				sprintf(
					/* translators: 1: recipient email address, 2: delay in days */
					__( 'Feedback email sent to %1$s (automatic, %2$d days after completion).', 'babypasa-order-emails' ),
					$order->get_billing_email(),
					$days
				)
			);
		}

		$order->save();

		return true;
	}

	/**
	 * Checks every guard the feedback email must pass, regardless of trigger.
	 * Returns null when eligible, or a human-readable reason (already
	 * translated, ready to write straight into an order note) when not.
	 *
	 * @param WC_Order $order Order to check.
	 * @return string|null
	 */
	public static function get_skip_reason( WC_Order $order ): ?string {
		if ( ! $order->has_status( 'completed' ) ) {
			return sprintf(
				/* translators: %s: current order status label */
				__( 'Feedback email skipped — order no longer has status Completed at send time (current: %s).', 'babypasa-order-emails' ),
				wc_get_order_status_name( $order->get_status() )
			);
		}

		if ( BP_OE_Feedback_Email::already_sent( $order ) ) {
			$sent_at = (int) $order->get_meta( BP_OE_Feedback_Email::SENT_META );
			return sprintf(
				/* translators: %s: date the feedback email was previously sent */
				__( 'Feedback email skipped — already sent on %s.', 'babypasa-order-emails' ),
				$sent_at ? wp_date( get_option( 'date_format' ), $sent_at ) : __( 'an earlier date', 'babypasa-order-emails' )
			);
		}

		if ( ! $order->get_billing_email() ) {
			return __( 'Feedback email skipped — no billing email on file.', 'babypasa-order-emails' );
		}

		if ( $order->get_total_refunded() > 0 ) {
			return __( 'Feedback email skipped — order has a refund on file.', 'babypasa-order-emails' );
		}

		/**
		 * Final veto point for custom guards (e.g. test/staging orders).
		 *
		 * @param bool     $allowed Whether the send may proceed. Default true.
		 * @param WC_Order $order   The order.
		 */
		if ( ! apply_filters( 'bp_feedback_send_allowed', true, $order ) ) {
			return __( 'Feedback email skipped — blocked by a custom filter.', 'babypasa-order-emails' );
		}

		return null;
	}
}

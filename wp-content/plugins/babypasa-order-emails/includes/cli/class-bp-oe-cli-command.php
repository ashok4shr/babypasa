<?php
/**
 * WP-CLI commands for the feedback email:
 *
 *   wp babypasa feedback-email status --order=12345
 *   wp babypasa feedback-email backfill --dry-run
 *   wp babypasa feedback-email backfill --after=2026-08-01 --limit=50
 *   wp babypasa feedback-email backfill --after=2026-08-01 --confirm
 *
 * Backfill only ever considers orders that are currently "completed", defaults
 * to a dry-run preview, and refuses to run bare (no flags at all) — pass
 * --dry-run to preview against the activation cutoff, --after to narrow the
 * window, and --confirm to actually schedule.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_OE_CLI_Command extends WP_CLI_Command {

	/**
	 * Shows the feedback-email state for a single order.
	 *
	 * ## OPTIONS
	 *
	 * --order=<id>
	 * : Order id to inspect.
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function status( array $args, array $assoc_args ): void {
		$order_id = (int) ( $assoc_args['order'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			WP_CLI::error( 'Pass a valid --order=<id>.' );
		}

		$email        = BP_OE_Emails::get( 'bp_feedback' );
		$skip_reason  = BP_OE_Feedback_Scheduler::get_skip_reason( $order );
		$sent_at      = (int) $order->get_meta( BP_OE_Feedback_Email::SENT_META );
		$scheduled_ts = function_exists( 'as_next_scheduled_action' )
			? as_next_scheduled_action( BP_OE_Feedback_Scheduler::HOOK, array( $order_id ), BP_OE_Feedback_Scheduler::GROUP )
			: false;

		WP_CLI::log( "Order:              #{$order_id}" );
		WP_CLI::log( 'Status:             ' . $order->get_status() );
		WP_CLI::log( 'Billing email:      ' . ( $order->get_billing_email() ?: '(none)' ) );
		WP_CLI::log( 'Email enabled:      ' . ( $email instanceof WC_Email && $email->is_enabled() ? 'yes' : 'no' ) );
		WP_CLI::log( 'Delay filter:       ' . (int) apply_filters( 'bp_feedback_delay_days', 2, $order ) . ' day(s)' );
		WP_CLI::log( 'Already sent:       ' . ( $sent_at ? gmdate( 'Y-m-d H:i:s', $sent_at ) . ' UTC' : 'no' ) );
		WP_CLI::log( 'AS action pending:  ' . ( $scheduled_ts ? gmdate( 'Y-m-d H:i:s', $scheduled_ts ) . ' UTC' : 'no' ) );
		WP_CLI::log( 'Eligible right now: ' . ( null === $skip_reason ? 'yes' : "no — {$skip_reason}" ) );
	}

	/**
	 * Schedules the feedback email for historical completed orders. Nothing is
	 * ever sent from this command directly — it only queues the same Action
	 * Scheduler action the live "completed" hook would, so all the normal
	 * send-time guards still apply when it fires.
	 *
	 * ## OPTIONS
	 *
	 * [--after=<date>]
	 * : Only consider orders completed on/after this date (YYYY-MM-DD).
	 *   Defaults to the activation cutoff (the point the feedback feature went
	 *   live) when omitted.
	 *
	 * [--limit=<n>]
	 * : Cap the number of orders processed. 0 (default) = no cap.
	 *
	 * [--dry-run]
	 * : Preview only — list what would be scheduled without scheduling it.
	 *
	 * [--confirm]
	 * : Actually schedule the previewed orders. Without this, the command
	 *   always runs as a dry run.
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function backfill( array $args, array $assoc_args ): void {
		if ( empty( $assoc_args ) ) {
			WP_CLI::error( 'Refusing to run with no flags. Pass --dry-run to preview, or --after=YYYY-MM-DD (add --confirm to actually schedule).' );
		}

		$after_input = $assoc_args['after'] ?? '';
		$after_ts    = '' !== $after_input
			? strtotime( $after_input . ' 00:00:00' )
			: (int) get_option( BP_OE_Feedback_Scheduler::CUTOFF_OPTION, time() );

		if ( false === $after_ts ) {
			WP_CLI::error( 'Invalid --after date. Use YYYY-MM-DD.' );
		}

		$limit   = (int) ( $assoc_args['limit'] ?? 0 );
		$confirm = WP_CLI\Utils\get_flag_value( $assoc_args, 'confirm', false );
		$dry_run = ! $confirm;

		WP_CLI::log( sprintf(
			'Considering completed orders on/after %s%s. Mode: %s.',
			gmdate( 'Y-m-d', $after_ts ),
			$limit > 0 ? " (limit {$limit})" : '',
			$dry_run ? 'DRY RUN' : 'LIVE — will schedule'
		) );

		$orders = wc_get_orders( array(
			'status' => 'completed',
			'limit'  => -1,
			'return' => 'objects',
		) );

		$considered = 0;
		$eligible   = 0;
		$scheduled  = 0;

		foreach ( $orders as $order ) {
			if ( $limit > 0 && $considered >= $limit ) {
				break;
			}

			$completed_date = $order->get_date_completed();
			if ( ! $completed_date || $completed_date->getTimestamp() < $after_ts ) {
				continue;
			}

			$considered++;

			$skip_reason = BP_OE_Feedback_Scheduler::get_skip_reason( $order );
			$already_due = function_exists( 'as_next_scheduled_action' )
				&& as_next_scheduled_action( BP_OE_Feedback_Scheduler::HOOK, array( $order->get_id() ), BP_OE_Feedback_Scheduler::GROUP );

			if ( null !== $skip_reason ) {
				WP_CLI::log( "  #{$order->get_id()}: skip — {$skip_reason}" );
				continue;
			}

			if ( $already_due ) {
				WP_CLI::log( "  #{$order->get_id()}: skip — already scheduled." );
				continue;
			}

			$eligible++;

			if ( $dry_run ) {
				WP_CLI::log( "  #{$order->get_id()}: would schedule (completed {$completed_date->date( 'Y-m-d' )}, {$order->get_billing_email()})." );
				continue;
			}

			BP_OE_Feedback_Scheduler::schedule( $order->get_id(), $order );
			$scheduled++;
			WP_CLI::log( "  #{$order->get_id()}: scheduled." );
		}

		WP_CLI::log( "\nConsidered: {$considered}. Eligible: {$eligible}. " . ( $dry_run ? 'Scheduled: 0 (dry run).' : "Scheduled: {$scheduled}." ) );

		if ( $dry_run ) {
			WP_CLI::log( 'Re-run with --confirm to actually schedule these.' );
		}
	}
}

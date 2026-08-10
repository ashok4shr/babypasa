<?php
/**
 * WP-CLI commands for guest-order account linking:
 *
 *   wp babypasa guest-orders audit
 *   wp babypasa guest-orders link --dry-run
 *   wp babypasa guest-orders link --after=2025-01-01 --limit=100 --confirm
 *   wp babypasa guest-orders unlink --order=12345 --confirm
 *   wp babypasa guest-orders status --order=12345
 *
 * `link` only ever considers orders with customer_id = 0 (true guests) and
 * defaults to a dry run; `--confirm` is required to actually link. Backfilled
 * links are always provisional, identical to a checkout-time link, and go
 * through the exact same BP_AL_Linker::link() used by the live checkout hook.
 *
 * @package BabyPasa_Account_Linking
 */

defined( 'ABSPATH' ) || exit;

class BP_AL_CLI_Command extends WP_CLI_Command {

	/**
	 * Read-only report on the current guest-order linking landscape: how many
	 * guest orders exist, how many match an existing account by billing email,
	 * and the role breakdown of those matches. Safe to run at any time — makes
	 * no changes.
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Associative args (unused).
	 */
	public function audit( array $args, array $assoc_args ): void {
		$guest_orders = wc_get_orders( array(
			'customer' => 0,
			'limit'    => -1,
			'return'   => 'objects',
		) );

		WP_CLI::log( 'Total guest orders (customer_id = 0): ' . count( $guest_orders ) );

		$matched       = 0;
		$role_counts   = array();
		$status_counts = array();

		foreach ( $guest_orders as $order ) {
			$status                   = $order->get_status();
			$status_counts[ $status ] = ( $status_counts[ $status ] ?? 0 ) + 1;

			$email = sanitize_email( trim( (string) $order->get_billing_email() ) );
			if ( '' === $email ) {
				continue;
			}

			$user = get_user_by( 'email', $email );
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			$matched++;
			$key                 = implode( ',', $user->roles ) ?: '(no role)';
			$role_counts[ $key ] = ( $role_counts[ $key ] ?? 0 ) + 1;
		}

		WP_CLI::log( "Matching an existing account by billing email: {$matched}" );
		WP_CLI::log( 'Role breakdown of matches:' );
		foreach ( $role_counts as $roles => $count ) {
			WP_CLI::log( "  {$roles}: {$count}" );
		}
		WP_CLI::log( 'Status distribution of all guest orders:' );
		foreach ( $status_counts as $status => $count ) {
			WP_CLI::log( "  {$status}: {$count}" );
		}
	}

	/**
	 * Shows the account-linking state for a single order.
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

		$linked_at    = (int) $order->get_meta( BP_AL_Linker::META_LINKED );
		$confirmed_at = (int) $order->get_meta( BP_AL_Linker::META_CONFIRMED );

		WP_CLI::log( "Order:            #{$order_id}" );
		WP_CLI::log( 'Customer id:      ' . $order->get_customer_id() . ( $order->get_customer_id() ? '' : ' (guest)' ) );
		WP_CLI::log( 'Billing email:    ' . ( $order->get_billing_email() ?: '(none)' ) );
		WP_CLI::log( 'Linked:           ' . ( $linked_at ? gmdate( 'Y-m-d H:i:s', $linked_at ) . ' UTC (' . $order->get_meta( BP_AL_Linker::META_SOURCE ) . ')' : 'no' ) );
		WP_CLI::log( 'Confirmed:        ' . ( $confirmed_at ? gmdate( 'Y-m-d H:i:s', $confirmed_at ) . ' UTC' : 'no' ) );
		WP_CLI::log( 'Provisional now:  ' . ( BP_AL_Linker::is_provisional( $order ) ? 'yes' : 'no' ) );
	}

	/**
	 * Links historical guest orders to matching accounts. Reuses
	 * BP_AL_Linker::link() directly — the same guards and role allowlist that
	 * govern the live checkout hook — so a dry-run preview can never drift
	 * from what a --confirm run actually does.
	 *
	 * ## OPTIONS
	 *
	 * [--after=<date>]
	 * : Only consider guest orders created on/after this date (YYYY-MM-DD).
	 *
	 * [--limit=<n>]
	 * : Cap the number of orders processed. 0 (default) = no cap.
	 *
	 * [--dry-run]
	 * : Preview only (this is also the default with no --confirm).
	 *
	 * [--confirm]
	 * : Actually link the previewed orders. Without this, the command always
	 *   runs as a dry run.
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function link( array $args, array $assoc_args ): void {
		$after_input = $assoc_args['after'] ?? '';
		$after_ts    = '' !== $after_input ? strtotime( $after_input . ' 00:00:00' ) : 0;

		if ( '' !== $after_input && false === $after_ts ) {
			WP_CLI::error( 'Invalid --after date. Use YYYY-MM-DD.' );
		}

		$limit   = (int) ( $assoc_args['limit'] ?? 0 );
		$confirm = WP_CLI\Utils\get_flag_value( $assoc_args, 'confirm', false );
		$dry_run = ! $confirm;

		WP_CLI::log( sprintf(
			'Considering guest orders%s%s. Mode: %s.',
			$after_ts ? ' created on/after ' . gmdate( 'Y-m-d', $after_ts ) : '',
			$limit > 0 ? " (limit {$limit})" : '',
			$dry_run ? 'DRY RUN' : 'LIVE — will link'
		) );

		$orders = wc_get_orders( array(
			'customer' => 0,
			'limit'    => -1,
			'return'   => 'objects',
		) );

		$considered = 0;
		$eligible   = 0;
		$linked     = 0;

		foreach ( $orders as $order ) {
			if ( $limit > 0 && $considered >= $limit ) {
				break;
			}

			$created = $order->get_date_created();
			if ( $after_ts && ( ! $created || $created->getTimestamp() < $after_ts ) ) {
				continue;
			}

			$considered++;

			$result = BP_AL_Linker::link( $order, BP_AL_Linker::SOURCE_BACKFILL );

			if ( BP_AL_Linker::RESULT_LINKED !== $result ) {
				WP_CLI::log( "  #{$order->get_id()}: skip — {$result}" );
				continue;
			}

			$eligible++;

			if ( $dry_run ) {
				WP_CLI::log( "  #{$order->get_id()}: would link to account #{$order->get_customer_id()} ({$order->get_billing_email()})." );
				continue;
			}

			$note = BP_AL_Linker::note_for_result( $result, $order, BP_AL_Linker::SOURCE_BACKFILL );
			if ( $note ) {
				$order->add_order_note( $note );
			}
			$order->save();
			$linked++;
			WP_CLI::log( "  #{$order->get_id()}: linked to account #{$order->get_customer_id()}." );
		}

		WP_CLI::log( "\nConsidered: {$considered}. Eligible: {$eligible}. " . ( $dry_run ? 'Linked: 0 (dry run).' : "Linked: {$linked}." ) );

		if ( $dry_run ) {
			WP_CLI::log( 'Re-run with --confirm to actually link these.' );
		}
	}

	/**
	 * Reverses a link this plugin created. Only ever acts on an order carrying
	 * the _bp_link_created flag — never touches an order that is legitimately
	 * owned (e.g. a logged-in checkout).
	 *
	 * ## OPTIONS
	 *
	 * --order=<id>
	 * : Order id to unlink.
	 *
	 * [--confirm]
	 * : Actually unlink. Without this, only previews what would happen.
	 *
	 * @param array<int,string>    $args       Positional args (unused).
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function unlink( array $args, array $assoc_args ): void {
		$order_id = (int) ( $assoc_args['order'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			WP_CLI::error( 'Pass a valid --order=<id>.' );
		}

		if ( ! $order->get_meta( BP_AL_Linker::META_LINKED ) ) {
			WP_CLI::error( "Order #{$order_id} was not linked by this plugin — refusing to touch it." );
		}

		$confirm = WP_CLI\Utils\get_flag_value( $assoc_args, 'confirm', false );

		if ( ! $confirm ) {
			WP_CLI::log( "Would unlink order #{$order_id} from customer account #{$order->get_customer_id()}. Re-run with --confirm to actually unlink." );
			return;
		}

		$ok = BP_AL_Linker::unlink( $order, get_current_user_id() );
		WP_CLI::log( $ok ? "Order #{$order_id} unlinked." : "Order #{$order_id}: nothing to unlink." );
	}
}

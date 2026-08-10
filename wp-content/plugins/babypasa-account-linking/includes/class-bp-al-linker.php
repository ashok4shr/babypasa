<?php
/**
 * Single source of truth for linking a guest order to an existing customer
 * account by billing email. Used identically by the checkout hook and the
 * WP-CLI backfill/unlink commands so eligibility rules, meta flags, and
 * order-note wording can never drift apart between the two paths.
 *
 * The link is always provisional (the billing email at guest checkout is
 * unverified) until an admin confirms it via the order-edit "Order actions"
 * dropdown. HPOS-safe throughout: never touches wp_postmeta directly.
 *
 * @package BabyPasa_Account_Linking
 */

defined( 'ABSPATH' ) || exit;

class BP_AL_Linker {

	/** Order meta: timestamp the link was created. */
	const META_LINKED = '_bp_link_created';

	/** Order meta: 'checkout' | 'backfill'. */
	const META_SOURCE = '_bp_link_source';

	/** Order meta: timestamp ownership was confirmed (absent while provisional). */
	const META_CONFIRMED = '_bp_link_confirmed';

	const SOURCE_CHECKOUT = 'checkout';
	const SOURCE_BACKFILL = 'backfill';

	const RESULT_LINKED                  = 'linked';
	const RESULT_SKIPPED_NO_MATCH        = 'skipped_no_match';
	const RESULT_SKIPPED_PRIVILEGED_ROLE = 'skipped_privileged_role';
	const RESULT_SKIPPED_ALREADY_OWNED   = 'skipped_already_owned';
	const RESULT_SKIPPED_INVALID_EMAIL   = 'skipped_invalid_email';

	/**
	 * Attempts to link a guest order to an existing account by billing email.
	 * Pure: mutates $order's in-memory customer_id/meta on success but never
	 * calls save() and never writes an order note — the caller (checkout hook
	 * or CLI) decides when to persist and logs via note_for_result() below, so
	 * a dry-run preview can call this safely with zero side effects.
	 *
	 * @param  WC_Order $order  The (guest) order to link.
	 * @param  string    $source SOURCE_CHECKOUT or SOURCE_BACKFILL (recorded in meta / used for note wording).
	 * @return string One of the RESULT_* constants.
	 */
	public static function link( WC_Order $order, string $source ): string {
		if ( $order->get_customer_id() > 0 ) {
			return self::RESULT_SKIPPED_ALREADY_OWNED;
		}

		$email = sanitize_email( trim( (string) $order->get_billing_email() ) );
		if ( '' === $email ) {
			return self::RESULT_SKIPPED_INVALID_EMAIL;
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof WP_User ) {
			return self::RESULT_SKIPPED_NO_MATCH;
		}

		if ( ! self::is_role_eligible( $user ) ) {
			return self::RESULT_SKIPPED_PRIVILEGED_ROLE;
		}

		$order->set_customer_id( $user->ID );
		$order->update_meta_data( self::META_LINKED, time() );
		$order->update_meta_data( self::META_SOURCE, $source );
		self::reset_customer_totals( $user->ID, $order );

		return self::RESULT_LINKED;
	}

	/**
	 * The order note to write for a link() result, or null when no note is
	 * warranted (only a successful link is noted; non-matches are silent, same
	 * as WooCommerce's own guest-checkout behaviour).
	 *
	 * @param  string    $result RESULT_* constant returned by link().
	 * @param  WC_Order $order  The order (already mutated by a successful link()).
	 * @param  string    $source SOURCE_CHECKOUT or SOURCE_BACKFILL.
	 * @return string|null
	 */
	public static function note_for_result( string $result, WC_Order $order, string $source ): ?string {
		if ( self::RESULT_LINKED !== $result ) {
			return null;
		}

		$user  = get_userdata( $order->get_customer_id() );
		$email = $user ? $user->user_email : '';

		if ( self::SOURCE_BACKFILL === $source ) {
			return sprintf(
				/* translators: 1: linked customer account id, 2: customer account email */
				__( 'Order linked to customer account #%1$d (%2$s) — matched by billing email during backfill run.', 'babypasa-account-linking' ),
				$order->get_customer_id(),
				$email
			);
		}

		return sprintf(
			/* translators: 1: linked customer account id, 2: customer account email */
			__( 'Order linked to customer account #%1$d (%2$s) — matched by billing email at checkout. Provisional: ownership not yet confirmed.', 'babypasa-account-linking' ),
			$order->get_customer_id(),
			$email
		);
	}

	/**
	 * Whether $order currently carries a link we created that has not yet been
	 * confirmed by an admin. Used everywhere a customer-facing action needs to
	 * be gated, and by the admin notice/column.
	 */
	public static function is_provisional( WC_Order $order ): bool {
		return (bool) $order->get_meta( self::META_LINKED ) && ! $order->get_meta( self::META_CONFIRMED );
	}

	/**
	 * Confirms ownership of an already-linked order, lifting the provisional
	 * gates. No-op (returns false) if the order was never linked by us, or is
	 * already confirmed.
	 *
	 * @param  WC_Order $order         The order.
	 * @param  int       $admin_user_id Admin performing the confirmation.
	 */
	public static function confirm( WC_Order $order, int $admin_user_id ): bool {
		if ( ! $order->get_meta( self::META_LINKED ) || $order->get_meta( self::META_CONFIRMED ) ) {
			return false;
		}

		$order->update_meta_data( self::META_CONFIRMED, time() );

		$admin = get_userdata( $admin_user_id );
		$order->add_order_note(
			sprintf(
				/* translators: %s: admin display name */
				__( 'Ownership confirmed by %s — provisional restrictions lifted.', 'babypasa-account-linking' ),
				$admin ? $admin->display_name : __( 'admin', 'babypasa-account-linking' )
			)
		);
		$order->save();

		return true;
	}

	/**
	 * Reverses a link this feature created. Only ever acts on orders carrying
	 * our META_LINKED flag, so a legitimately-owned order (logged-in checkout,
	 * or customer_id set by something unrelated to this plugin) is never
	 * touched.
	 *
	 * @param  WC_Order $order         The order.
	 * @param  int       $admin_user_id Admin performing the reversal.
	 */
	public static function unlink( WC_Order $order, int $admin_user_id ): bool {
		if ( ! $order->get_meta( self::META_LINKED ) ) {
			return false;
		}

		$old_customer_id = $order->get_customer_id();

		$order->set_customer_id( 0 );
		$order->delete_meta_data( self::META_LINKED );
		$order->delete_meta_data( self::META_SOURCE );
		$order->delete_meta_data( self::META_CONFIRMED );

		if ( $old_customer_id > 0 ) {
			self::reset_customer_totals( $old_customer_id, $order );
		}

		$admin = get_userdata( $admin_user_id );
		$order->add_order_note(
			sprintf(
				/* translators: 1: customer account id the order is being unlinked from, 2: admin display name */
				__( 'Order unlinked from customer account #%1$d — reversed by %2$s.', 'babypasa-account-linking' ),
				$old_customer_id,
				$admin ? $admin->display_name : __( 'admin', 'babypasa-account-linking' )
			)
		);
		$order->save();

		return true;
	}

	/**
	 * Whether every role the user holds is in the (filterable) safe-to-link
	 * allowlist. A subset check rather than "does it include customer" or a
	 * denylist: fails CLOSED on any role — including future/custom ones — that
	 * hasn't been explicitly allowed, rather than failing open.
	 */
	private static function is_role_eligible( WP_User $user ): bool {
		/**
		 * Roles that are safe to auto-link a guest order to. A user must hold
		 * ONLY roles from this list (not merely one of them) to be eligible —
		 * e.g. a customer account that also holds shop_manager is excluded.
		 *
		 * @param string[] $roles Allowlisted roles. Default array('customer').
		 * @param WP_User  $user  The matched user account.
		 */
		$allowlist = (array) apply_filters( 'bp_al_eligible_roles', array( 'customer' ), $user );

		return ! empty( $user->roles ) && empty( array_diff( $user->roles, $allowlist ) );
	}

	/**
	 * Resets the linked account's cached order-count/money-spent so WooCommerce
	 * recomputes them on next read. WooCommerce's own cache keys for these are
	 * site-specific and normally written via
	 * Automattic\WooCommerce\Internal\Utilities\Users — an `Internal`-namespaced
	 * class with no back-compat guarantee, so rather than call it directly we
	 * replicate its formula using only the stable, public wpdb API (verified
	 * against this site's live data on WooCommerce 10.8.1: the real cache keys
	 * are wc_order_count_wp / wc_money_spent_wp on this non-multisite install).
	 * Deleting (not zeroing) forces WC_Customer::get_order_count()/
	 * get_total_spent() to recompute lazily, matching what core's own
	 * wc_update_new_customer_past_orders() achieves for the same scenario.
	 *
	 * @param  int       $customer_id Account gaining/losing the order.
	 * @param  WC_Order $order       The order being linked/unlinked.
	 */
	private static function reset_customer_totals( int $customer_id, WC_Order $order ): void {
		global $wpdb;

		$suffix = rtrim( $wpdb->get_blog_prefix( get_current_blog_id() ), '_' );

		delete_user_meta( $customer_id, 'wc_order_count_' . $suffix );
		delete_user_meta( $customer_id, 'wc_money_spent_' . $suffix );
		delete_user_meta( $customer_id, 'wc_last_order_' . $suffix );

		if ( $order->has_status( 'completed' ) ) {
			update_user_meta( $customer_id, 'paying_customer', 1 );
		}
	}
}

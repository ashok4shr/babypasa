<?php
/**
 * Routes Upaya webhook statuses to the return-flow emails (E16/E17/E20).
 *
 * Listens to `bp_upaya_status_processed` — a decoupling action fired by the
 * Upaya plugin's webhook processor for every processed status (see the note in
 * class-upaya-webhook-processor.php). This keeps all return logic out of the
 * Upaya plugin so it survives plugin updates.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Returns_Webhook_Router {

	/** Cooldown between E16 sends for the same order (Upaya may repeat the event). */
	const E16_COOLDOWN = 12 * HOUR_IN_SECONDS;

	/** Upaya status slugs → a failed delivery attempt (E16; no state change). */
	const STATUSES_E16 = array( 'on-field-failed-delivery', 'attempted-delivery', 'followup-for-return' );

	/** Upaya status slugs → return-to-origin initiated (E17). */
	const STATUSES_E17 = array( 'return-to-origin-initiated', 'confirmed-for-return', 'out-for-return' );

	/** Upaya status slugs → parcel back at warehouse (E20). */
	const STATUSES_E20 = array( 'returned-to-vendor', 'return-received-at-central-facility' );

	public function __construct() {
		add_action( 'bp_upaya_status_processed', array( $this, 'route' ), 10, 4 );
	}

	/**
	 * @param WC_Order $order         Order object.
	 * @param string   $upaya_status  Upaya status slug.
	 * @param string   $tracking_code Upaya tracking code.
	 * @param string   $readable      Human-readable status label.
	 */
	public function route( $order, $upaya_status, $tracking_code = '', $readable = '' ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$status = (string) $upaya_status;

		if ( in_array( $status, self::STATUSES_E16, true ) ) {
			$this->handle_failed_delivery( $order, (string) $tracking_code );
			return;
		}

		if ( in_array( $status, self::STATUSES_E17, true ) ) {
			$this->handle_rto_initiated( $order );
			return;
		}

		if ( in_array( $status, self::STATUSES_E20, true ) ) {
			$this->handle_rto_complete( $order );
		}
	}

	/**
	 * E16 — failed delivery attempt. Increment the attempt counter, fire the
	 * email at most once per 12h, and leave the state unchanged (the parcel is
	 * still out for delivery and may yet be delivered → E12).
	 */
	private function handle_failed_delivery( WC_Order $order, string $tracking_code ): void {
		$attempts = (int) $order->get_meta( BP_Returns_State::META_ATTEMPTS ) + 1;
		$order->update_meta_data( BP_Returns_State::META_ATTEMPTS, $attempts );
		$order->save();

		$last = (int) $order->get_meta( BP_Returns_State::META_E16_LAST );
		if ( $last && ( time() - $last ) < self::E16_COOLDOWN ) {
			return; // Within cooldown — skip duplicate.
		}

		$email = BP_Returns_Emails::get( 'bp_failed_delivery' );
		if ( $email ) {
			$email->trigger( $order->get_id(), $tracking_code, $attempts );
			BP_Returns_State::set_flag( $order, BP_Returns_State::META_E16_LAST, time() );
		}
	}

	/**
	 * E17 — RTO initiated. Fires once. Suppressed when the customer already
	 * started a return (REQUESTED/APPROVED) — that path uses E18/E19 instead,
	 * and the DEV_NOTE requires E17 and E18 never both fire for one order.
	 */
	private function handle_rto_initiated( WC_Order $order ): void {
		if ( BP_Returns_State::is_customer_return( $order ) ) {
			return;
		}
		if ( BP_Returns_State::flag_set( $order, BP_Returns_State::META_E17_SENT ) ) {
			return;
		}

		BP_Returns_State::set_state( $order, BP_Returns_State::STATE_RTO );

		$email = BP_Returns_Emails::get( 'bp_rto_initiated' );
		if ( $email ) {
			$email->trigger( $order->get_id() );
			BP_Returns_State::set_flag( $order, BP_Returns_State::META_E17_SENT );
		}
	}

	/**
	 * E20 — returned parcel received at the warehouse. Fires once, for both the
	 * logistics RTO path and the customer-return path.
	 */
	private function handle_rto_complete( WC_Order $order ): void {
		if ( BP_Returns_State::flag_set( $order, BP_Returns_State::META_E20_SENT ) ) {
			return;
		}

		BP_Returns_State::set_state( $order, BP_Returns_State::STATE_RTO_COMPLETE );

		$email = BP_Returns_Emails::get( 'bp_rto_complete' );
		if ( $email ) {
			$email->trigger( $order->get_id() );
			BP_Returns_State::set_flag( $order, BP_Returns_State::META_E20_SENT );
		}
	}
}

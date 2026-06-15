<?php
/**
 * Return / RTO state machine + shared meta helpers.
 *
 * One order-meta key `_bp_return_state` tracks where an order sits in the
 * return/RTO journey, so the logistics path (E16/E17/E20) and the customer
 * path (E18/E19/E20) stay mutually aware (the DEV_NOTEs require that E17 and
 * E18 never both fire for the same order).
 *
 *   ''           no return activity
 *   REQUESTED    customer asked to return (E18 sent)
 *   APPROVED     admin approved the customer return (E19 sent)
 *   RTO          logistics return-to-origin initiated (E17 sent)
 *   RTO_COMPLETE parcel back at warehouse (E20 sent)
 *
 * All meta is read/written via WooCommerce CRUD so it lands in the canonical
 * store (HPOS-aware), matching the Upaya plugin's convention.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

final class BP_Returns_State {

	const META_STATE       = '_bp_return_state';
	const META_ITEMS       = '_bp_return_items';        // JSON [{name,qty}]
	const META_REASON      = '_bp_return_reason';
	const META_REQUESTED   = '_bp_return_requested_at';
	const META_APPROVED    = '_bp_return_approved_at';
	const META_ATTEMPTS    = '_bp_delivery_attempts';
	const META_E16_LAST    = '_bp_e16_last_sent';        // timestamp (cooldown)
	const META_E17_SENT    = '_bp_e17_sent';
	const META_E18_SENT    = '_bp_e18_sent';
	const META_E19_SENT    = '_bp_e19_sent';
	const META_E20_SENT    = '_bp_e20_sent';

	const STATE_REQUESTED    = 'REQUESTED';
	const STATE_APPROVED     = 'APPROVED';
	const STATE_RTO          = 'RTO';
	const STATE_RTO_COMPLETE = 'RTO_COMPLETE';

	/**
	 * Whether customer-facing return/RTO emails are enabled (admin toggle,
	 * shared default with the Upaya notify option).
	 */
	public static function notify_enabled(): bool {
		return 'yes' === get_option( 'bp_returns_notify_customer', 'yes' );
	}

	public static function get_state( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META_STATE );
	}

	public static function set_state( \WC_Order $order, string $state ): void {
		$order->update_meta_data( self::META_STATE, $state );
		$order->save();
	}

	/** True once a customer has initiated a return (REQUESTED or APPROVED). */
	public static function is_customer_return( \WC_Order $order ): bool {
		return in_array(
			self::get_state( $order ),
			array( self::STATE_REQUESTED, self::STATE_APPROVED ),
			true
		);
	}

	public static function flag_set( \WC_Order $order, string $meta_key ): bool {
		return '' !== (string) $order->get_meta( $meta_key );
	}

	public static function set_flag( \WC_Order $order, string $meta_key, $value = '1' ): void {
		$order->update_meta_data( $meta_key, $value );
		$order->save();
	}

	/**
	 * Build the return-items array for an order: prefer the items the customer
	 * selected (stored at request time), else fall back to all order items.
	 *
	 * @return array<int,array{name:string,qty:int}>
	 */
	public static function get_return_items( \WC_Order $order ): array {
		$raw   = (string) $order->get_meta( self::META_ITEMS );
		$items = $raw ? json_decode( $raw, true ) : array();

		if ( is_array( $items ) && ! empty( $items ) ) {
			// Normalise.
			$out = array();
			foreach ( $items as $row ) {
				if ( ! empty( $row['name'] ) ) {
					$out[] = array(
						'name' => (string) $row['name'],
						'qty'  => isset( $row['qty'] ) ? (int) $row['qty'] : 1,
					);
				}
			}
			if ( $out ) {
				return $out;
			}
		}

		// Fallback: every line item on the order.
		$out = array();
		foreach ( $order->get_items() as $item ) {
			$out[] = array(
				'name' => $item->get_name(),
				'qty'  => (int) $item->get_quantity(),
			);
		}
		return $out;
	}

	/**
	 * Refund-info array for the E17/E20 "Version A" (paid orders) refund block,
	 * or null for COD/unpaid orders ("Version B"). Reuses the shared child-theme
	 * gateway helpers when available.
	 *
	 * @return array{amount:string,method:string,note:string,timeline:string}|null
	 */
	public static function get_refund_info( \WC_Order $order ): ?array {
		if ( ! $order->is_paid() ) {
			return null;
		}

		self::load_email_helpers();

		$method = function_exists( 'bp_email_refund_label' )
			? bp_email_refund_label( $order )
			: $order->get_payment_method_title();
		$note   = function_exists( 'bp_email_refund_note' )
			? bp_email_refund_note( $order )
			: '';

		return array(
			'amount'   => wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ),
			'method'   => $method,
			'note'     => $note,
			'timeline' => '3–5 business days',
		);
	}

	/** Loads the child-theme shared email helpers (refund label/note) once. */
	public static function load_email_helpers(): void {
		if ( function_exists( 'bp_email_refund_label' ) ) {
			return;
		}
		$path = get_stylesheet_directory() . '/woocommerce/emails/bp-email-helpers.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}

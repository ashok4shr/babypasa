<?php
/**
 * Processes validated Upaya Cargo webhook payloads.
 *
 * Responsibilities:
 *  - Locate the WooCommerce order from the Upaya tracking code or reference ID.
 *  - Update the wp_upaya_orders record.
 *  - Add a human-readable order note.
 *  - Optionally transition the WC order status.
 *  - Fire the customer notification email.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class UPAYA_Webhook_Processor {

	/** @var UPAYA_Logger */
	private UPAYA_Logger $logger;

	/**
	 * Upaya status slug → human-readable message for order notes and emails.
	 * Covers every documented status from the Magento reference implementation.
	 *
	 * @var array<string,string>
	 */
	private const STATUS_MESSAGES = [
		'pending'                             => 'Order has been created and is pending pickup.',
		'unassigned-pickup'                   => 'Order is awaiting pickup assignment.',
		'assigned-pickup'                     => 'Order has been assigned to a rider for pickup.',
		'picked-up-by-rider'                  => 'Order has been picked up.',
		'inbound-at-warehouse'                => 'Your order has arrived at the warehouse.',
		'midmile-sortation'                   => 'Your order is being sorted for transit.',
		'prepared-for-transit'                => 'Your order has been prepared for transit.',
		'in-transit-to-hub'                   => 'Your order is in transit to the delivery hub.',
		'received-at-hub'                     => 'Your order has been received at the delivery hub.',
		'in-hub'                              => 'Your order is at the delivery hub.',
		'hub-transfer-initiated'              => 'Your order is being transferred to another hub.',
		'hub-transfer-in-transit'             => 'Your order is in transit between hubs.',
		'hub-transferred'                     => 'Your order has arrived at the destination hub.',
		'ready-for-dispatch'                  => 'Your order is ready for dispatch.',
		'dispatched-with-rider'               => 'Your order is out for delivery.',
		'out-for-delivery'                    => 'Your order is out for delivery.',
		'delivered'                           => 'Your order has been delivered.',
		'failed-pickup'                       => 'Pickup attempt failed. Our team will retry.',
		'on-field-failed-delivery'            => 'Delivery attempt failed. Our team will retry.',
		'delivery-rescheduled'                => 'Delivery has been rescheduled.',
		'attempted-delivery'                  => 'A delivery attempt was made.',
		'hold'                                => 'Your order is on hold.',
		'loss-and-damage'                     => 'Your order has been reported as lost or damaged.',
		'partially-delivered'                 => 'Part of your order has been delivered.',
		'followup-for-return'                 => 'Your order is being followed up for return.',
		'return-processed-from-hub'           => 'Your return has been processed from the hub.',
		'return-received-at-central-facility' => 'Your return has been received at the central facility.',
		'confirmed-for-return'                => 'A return has been confirmed for your order.',
		'out-for-return'                      => 'Your order is out for return.',
		'on-field-failed-return'              => 'A return attempt failed. Our team will retry.',
		'return-to-origin-initiated'          => 'Return to origin has been initiated.',
		'return-in-transit'                   => 'Your return shipment is in transit.',
		'returned-to-vendor'                  => 'Your order has been returned to the sender.',
		'cancelled'                           => 'Your order has been cancelled.',
		'dispose'                             => 'Your order has been marked for disposal.',
	];

	/**
	 * Upaya statuses that should trigger a WC order status transition.
	 * Map: upaya_status => wc_status (without 'wc-' prefix).
	 *
	 * @var array<string,string>
	 */
	private const STATUS_MAP = [
		'delivered'                => 'completed',
		'cancelled'                => 'cancelled',
		'failed-pickup'            => 'on-hold',
		'on-field-failed-delivery' => 'on-hold',
		'hold'                     => 'on-hold',
		'loss-and-damage'          => 'on-hold',
	];

	/**
	 * Statuses considered "terminal" in WC — do not revert them to on-hold.
	 */
	private const TERMINAL_STATUSES = [ 'completed', 'cancelled', 'refunded' ];

	/**
	 * Statuses for which the customer should receive an email notification.
	 *
	 * NOTE: This file was edited directly inside the plugin.
	 * Re-apply changes after any upaya-cargo-woocommerce plugin update.
	 *
	 * 2026-06 email redesign: the Upaya status email now fires only for
	 * out-for-delivery (incl. dispatched-with-rider) and delivered — the two
	 * states with client-designed templates (E11/E12). 'cancelled' is handled
	 * by WooCommerce's own customer-cancelled-order email via STATUS_MAP, so
	 * it is intentionally absent here to avoid double-emailing. All other
	 * statuses produce an order note only.
	 */
	private const NOTABLE_STATUSES = [
		'dispatched-with-rider',
		'out-for-delivery',
		'delivered',
	];

	/**
	 * Journey email state machine (client DEV_NOTE_E06 tracker logic).
	 *
	 * Maps the journey webhook statuses to a forward-only state stored in the
	 * `_upaya_email_state` order meta, so each journey email fires exactly once
	 * even though Upaya repeats the in-transit/hub events as the parcel passes
	 * through every hub. Forward-only ranking also stops a late in-transit (or a
	 * backward RTO event) from re-firing an earlier email.
	 *
	 * E06 (PICKED_UP) and E07 (IN_TRANSIT) send through this machine; the other
	 * states are tracked purely to gate them. OUT_FOR_DELIVERY (E11) and
	 * DELIVERED (E12) still email via NOTABLE_STATUSES above.
	 *
	 * @var string
	 */
	private const JOURNEY_STATE_META = '_upaya_email_state';

	/** @var array<string,string> Upaya status slug → journey state. */
	private const JOURNEY_STATE_BY_STATUS = [
		'picked-up-by-rider'    => 'PICKED_UP',
		'in-transit-to-hub'     => 'IN_TRANSIT',
		'in-transit'            => 'IN_TRANSIT',
		'dispatched-with-rider' => 'OUT_FOR_DELIVERY',
		'out-for-delivery'      => 'OUT_FOR_DELIVERY',
		'delivered'             => 'DELIVERED',
	];

	/** @var array<string,int> Journey state → rank (forward-only ordering). */
	private const JOURNEY_RANKS = [
		'ORDER_PLACED'     => 1,
		'PICKED_UP'        => 2,
		'IN_TRANSIT'       => 3,
		'OUT_FOR_DELIVERY' => 4,
		'DELIVERED'        => 5,
	];

	public function __construct( UPAYA_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Processes a single webhook payload.
	 *
	 * @param  array<string,mixed> $payload Validated payload from the webhook endpoint.
	 * @return true|\WP_Error
	 */
	public function process( array $payload ) {
		$tracking_code  = sanitize_text_field( $payload['tracking_code'] );
		$upaya_status   = sanitize_text_field( $payload['status'] );
		$reference_id   = sanitize_text_field( $payload['order_reference_id'] );

		// ── Find the WC order ──────────────────────────────────────────────
		$order = $this->find_order( $tracking_code, $reference_id );

		if ( ! $order ) {
			$this->logger->warning(
				"Upaya webhook: no WC order found for tracking_code='{$tracking_code}' reference_id='{$reference_id}'."
			);
			// Return success so Upaya does not keep retrying for unknown orders.
			return true;
		}

		$order_id       = $order->get_id();
		$readable       = self::get_status_label( $upaya_status );
		$current_wc     = $order->get_status();

		$this->logger->debug(
			"Upaya webhook: order #{$order_id}, upaya_status='{$upaya_status}', wc_status='{$current_wc}'."
		);

		// ── Persist the latest pushed status to order meta ─────────────────
		// This is the dependable status source for on-site tracking (the live
		// /track-order API is optional and may be unavailable). Stored via CRUD.
		$order->update_meta_data( '_upaya_last_status', $upaya_status );
		$order->update_meta_data( '_upaya_last_status_label', $readable );
		$order->save();

		// ── Update DB record ───────────────────────────────────────────────
		$this->update_db_record( $order_id, $upaya_status, $payload );

		// ── Add order note ─────────────────────────────────────────────────
		$order->add_order_note(
			sprintf(
				/* translators: 1: Upaya status slug  2: human-readable message */
				__( 'Upaya Cargo update [%1$s]: %2$s', 'upaya-cargo-woocommerce' ),
				esc_html( $upaya_status ),
				esc_html( $readable )
			)
		);

		// ── Optionally transition WC order status ─────────────────────────
		if ( isset( self::STATUS_MAP[ $upaya_status ] ) ) {
			$target_wc = self::STATUS_MAP[ $upaya_status ];

			// Never push an already-terminal order back to on-hold.
			$skip = ( 'on-hold' === $target_wc && in_array( $current_wc, self::TERMINAL_STATUSES, true ) );

			if ( $skip ) {
				$this->logger->debug(
					"Upaya webhook: order #{$order_id} transition to '{$target_wc}' skipped — order already terminal ('{$current_wc}')."
				);
			} elseif ( $target_wc === $current_wc ) {
				$this->logger->debug(
					"Upaya webhook: order #{$order_id} already in target status '{$target_wc}', no transition needed."
				);
			} else {
				$order->update_status(
					$target_wc,
					sprintf(
						/* translators: %s: Upaya status slug */
						__( 'Status updated by Upaya Cargo webhook (%s).', 'upaya-cargo-woocommerce' ),
						esc_html( $upaya_status )
					)
				);
				$this->logger->debug(
					"Upaya webhook: order #{$order_id} status transitioned '{$current_wc}' → '{$target_wc}' (upaya_status='{$upaya_status}')."
				);
			}
		} else {
			$this->logger->debug(
				"Upaya webhook: order #{$order_id} upaya_status='{$upaya_status}' has no WC mapping — note/email only."
			);
		}

		// ── Send customer email for notable statuses ───────────────────────
		if (
			in_array( $upaya_status, self::NOTABLE_STATUSES, true )
			&& 'yes' === get_option( 'upaya_webhook_notify_customer', 'yes' )
		) {
			$this->send_status_email( $order, $upaya_status, $tracking_code, $readable );
		}

		// ── Advance the journey state machine + fire E07 (In Transit) once ──
		$this->advance_journey_state( $order, $upaya_status, $tracking_code, $readable );

		/**
		 * Decoupling hook for downstream handlers (the babypasa-returns plugin
		 * uses it to drive the E16/E17/E20 return-flow emails + RTO state
		 * machine). Fires for EVERY processed status — including ones not in
		 * NOTABLE_STATUSES — so handlers can react to return/RTO statuses.
		 *
		 * NOTE: This do_action was added directly inside the plugin.
		 * Re-apply it after any upaya-cargo-woocommerce plugin update.
		 *
		 * @param \WC_Order $order         Order object.
		 * @param string    $upaya_status  Upaya status slug.
		 * @param string    $tracking_code Upaya tracking code.
		 * @param string    $readable      Human-readable status label.
		 */
		do_action( 'bp_upaya_status_processed', $order, $upaya_status, $tracking_code, $readable );

		return true;
	}

	/**
	 * Returns the human-readable label for an Upaya status slug, falling back to
	 * a prettified version of the slug when it is not in the message map.
	 *
	 * @param  string $slug Upaya status slug.
	 * @return string
	 */
	public static function get_status_label( string $slug ): string {
		return self::STATUS_MESSAGES[ $slug ] ?? ucfirst( str_replace( '-', ' ', $slug ) );
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Advances the forward-only journey state and sends E07 (In Transit) the
	 * first time the order reaches IN_TRANSIT. See client DEV_NOTE_E06/E07.
	 *
	 * Upaya repeats the in-transit/hub events for every hub the parcel passes
	 * through; the rank check guarantees E07 emails exactly once and ignores
	 * duplicate or out-of-order (RTO) events.
	 *
	 * @param  \WC_Order $order         WooCommerce order.
	 * @param  string    $upaya_status  Upaya status slug.
	 * @param  string    $tracking_code Upaya tracking code.
	 * @param  string    $readable      Human-readable status string.
	 * @return void
	 */
	private function advance_journey_state( \WC_Order $order, string $upaya_status, string $tracking_code, string $readable ): void {
		$new_state = self::JOURNEY_STATE_BY_STATUS[ $upaya_status ] ?? '';
		if ( '' === $new_state ) {
			return; // Not a journey status — nothing to advance.
		}

		$current_state = (string) $order->get_meta( self::JOURNEY_STATE_META );
		$current_rank  = self::JOURNEY_RANKS[ $current_state ] ?? 0;
		$new_rank      = self::JOURNEY_RANKS[ $new_state ] ?? 0;

		// Forward-only: ignore duplicate and backward (RTO) events.
		if ( $new_rank <= $current_rank ) {
			$this->logger->debug(
				"Upaya webhook: order #{$order->get_id()} journey state '{$new_state}' (rank {$new_rank}) not ahead of '{$current_state}' (rank {$current_rank}) — no advance/email."
			);
			return;
		}

		$order->update_meta_data( self::JOURNEY_STATE_META, $new_state );
		$order->save();

		// E06 (PICKED_UP) and E07 (IN_TRANSIT) send through the state machine;
		// OUT_FOR_DELIVERY (E11) / DELIVERED (E12) still email via NOTABLE_STATUSES.
		if (
			in_array( $new_state, array( 'PICKED_UP', 'IN_TRANSIT' ), true )
			&& 'yes' === get_option( 'upaya_webhook_notify_customer', 'yes' )
		) {
			$this->logger->debug(
				"Upaya webhook: order #{$order->get_id()} reached {$new_state} — sending journey email."
			);
			$this->send_status_email( $order, $upaya_status, $tracking_code, $readable );
		}
	}

	/**
	 * Locates the WC order by trying multiple lookup strategies:
	 *  1.  Treat reference_id as a numeric order ID (wc_get_order).
	 *  1b. Parse our "BPA{order_number}{counter}" reference back to the order.
	 *  2.  Search _upaya_order_id meta for an exact or partial tracking_code match.
	 *  2b. Search _upaya_reference_id meta (LIKE) for the returned reference.
	 *  3.  Search by order number meta (for custom order-number plugins).
	 *
	 * @param  string $tracking_code Upaya tracking code.
	 * @param  string $reference_id  Upaya order_reference_id (our "BPA…" reference).
	 * @return \WC_Order|null
	 */
	private function find_order( string $tracking_code, string $reference_id ): ?\WC_Order {
		// Strategy 1: numeric reference_id → direct order ID lookup.
		if ( is_numeric( $reference_id ) ) {
			$order = wc_get_order( (int) $reference_id );
			if ( $order instanceof \WC_Order ) {
				return $order;
			}
		}

		// Strategy 1b: our generated reference ("BPA{order_number}{counter}") →
		// parse the WC order number back out. Deterministic; needs no stored meta,
		// which is what makes split-order references (BPA1140001/2/…) resolvable.
		if ( class_exists( 'UPAYA_Order_Manager' ) ) {
			$order_number = UPAYA_Order_Manager::parse_reference_id( $reference_id );

			if ( '' !== $order_number ) {
				if ( is_numeric( $order_number ) ) {
					$order = wc_get_order( (int) $order_number );
					if ( $order instanceof \WC_Order ) {
						return $order;
					}
				}

				// Non-numeric order numbers (sequential-order-number plugins).
				$by_parsed = wc_get_orders( [
					'limit'        => 1,
					'order_number' => $order_number,
					'return'       => 'objects',
				] );
				if ( ! empty( $by_parsed ) ) {
					return reset( $by_parsed );
				}
			}
		}

		// Strategy 2: match _upaya_order_id meta (supports comma-separated multi-chunk IDs).
		$by_tracking = wc_get_orders( [
			'limit'      => 1,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key'     => '_upaya_order_id',
					'value'   => $tracking_code,
					'compare' => 'LIKE',
				],
			],
			'return'     => 'objects',
		] );

		if ( ! empty( $by_tracking ) ) {
			return reset( $by_tracking );
		}

		// Strategy 2b: match the Upaya-returned reference ID meta (resilient to
		// non-numeric order-number schemes where Strategy 1 cannot apply).
		if ( '' !== $reference_id ) {
			$by_reference = wc_get_orders( [
				'limit'      => 1,
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery
					[
						'key'     => '_upaya_reference_id',
						'value'   => $reference_id,
						'compare' => 'LIKE',
					],
				],
				'return'     => 'objects',
			] );

			if ( ! empty( $by_reference ) ) {
				return reset( $by_reference );
			}
		}

		// Strategy 3: match by order number (reference_id as order number string).
		$by_number = wc_get_orders( [
			'limit'          => 1,
			'order_number'   => $reference_id,
			'return'         => 'objects',
		] );

		if ( ! empty( $by_number ) ) {
			return reset( $by_number );
		}

		return null;
	}

	/**
	 * Updates (or inserts) the upaya_orders table record with the new status.
	 *
	 * @param  int                 $order_id     WooCommerce order ID.
	 * @param  string              $upaya_status Upaya status slug.
	 * @param  array<string,mixed> $payload      Full webhook payload.
	 * @return void
	 */
	private function update_db_record( int $order_id, string $upaya_status, array $payload ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'upaya_orders';

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE wc_order_id = %d", $order_id )
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				[
					'status'       => sanitize_text_field( $upaya_status ),
					'response'     => wp_json_encode( $payload ),
					'last_attempt' => current_time( 'mysql' ),
				],
				[ 'wc_order_id' => $order_id ],
				[ '%s', '%s', '%s' ],
				[ '%d' ]
			);
		} else {
			$wpdb->insert(
				$table,
				[
					'wc_order_id'  => $order_id,
					'status'       => sanitize_text_field( $upaya_status ),
					'attempts'     => 0,
					'response'     => wp_json_encode( $payload ),
					'last_attempt' => current_time( 'mysql' ),
				],
				[ '%d', '%s', '%d', '%s', '%s' ]
			);
		}
	}

	/**
	 * Dispatches the WooCommerce customer status email.
	 *
	 * @param  \WC_Order $order         WooCommerce order.
	 * @param  string    $upaya_status  Upaya status slug.
	 * @param  string    $tracking_code Upaya tracking code.
	 * @param  string    $readable      Human-readable status string.
	 * @return void
	 */
	private function send_status_email( \WC_Order $order, string $upaya_status, string $tracking_code, string $readable ): void {
		// Ensure the class file is loaded (WC_Email parent is available by now).
		if ( ! class_exists( 'UPAYA_Status_Email' ) ) {
			require_once UPAYA_PLUGIN_DIR . 'includes/emails/class-upaya-status-email.php';
		}

		// Prefer the instance already registered in the WC mailer so settings are respected.
		$wc_emails = WC()->mailer()->get_emails();
		$email     = $wc_emails['UPAYA_Status_Email'] ?? new UPAYA_Status_Email();

		$email->trigger( $order->get_id(), $upaya_status, $tracking_code, $readable );
	}
}
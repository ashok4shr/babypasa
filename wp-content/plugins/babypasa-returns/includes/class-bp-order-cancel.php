<?php
/**
 * Customer order cancellation from My Account → Orders.
 *
 * PLACEMENT: housed in babypasa-returns because this plugin already owns the
 * customer-facing order-action surface (returns/RTO). Cancellation is the
 * pre-delivery counterpart to the post-delivery return flow, so it belongs here
 * rather than in a new plugin.
 *
 * Upaya has NO cancel API — cancellation is performed ONLY in WooCommerce
 * (order status → cancelled). The live Upaya tracking status is read purely to
 * GATE the action: a customer may cancel only while the shipment has not yet
 * been picked up ("unassigned pickup"). Once it is in transit, online
 * cancellation is refused.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Order_Cancel {

	/** Nonce action for the AJAX cancellation request. */
	const NONCE_ACTION = 'babypasa_cancel_order';

	/**
	 * WooCommerce order statuses for which the Cancel button is offered. These are
	 * paid, pre-completion states where a customer might still catch the order
	 * before dispatch. Never includes cancelled/completed/refunded.
	 */
	const CANCELLABLE_WC_STATUSES = [ 'processing', 'on-hold' ];

	public function __construct() {
		// Cancel button in the My Account orders table. Priority 15 so it renders
		// before the delivery-overrides "Track Order" action (priority 20).
		add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'add_cancel_action' ], 15, 2 );

		// AJAX handler — logged-in users only; nopriv returns a clear auth error.
		add_action( 'wp_ajax_' . self::NONCE_ACTION, [ $this, 'handle_cancel' ] );
		add_action( 'wp_ajax_nopriv_' . self::NONCE_ACTION, [ $this, 'handle_cancel_nopriv' ] );

		// Frontend assets on the My Account pages.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/* ------------------------------------------------------------------ *
	 * Orders-table "Cancel Order" action
	 * ------------------------------------------------------------------ */

	/**
	 * Adds a "Cancel Order" action next to "View" for eligible orders. The order
	 * ID is carried in the href fragment (#bp-cancel-{id}); the JS intercepts the
	 * click by the action's CSS class (WooCommerce renders the array key as a
	 * class), reads the ID, and fires the AJAX request.
	 *
	 * @param  array<string,array> $actions
	 * @param  WC_Order            $order
	 * @return array<string,array>
	 */
	public function add_cancel_action( array $actions, WC_Order $order ): array {
		if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
			return $actions;
		}
		if ( ! $order->has_status( self::CANCELLABLE_WC_STATUSES ) ) {
			return $actions;
		}

		$actions['bp-cancel-order'] = [
			'url'        => '#bp-cancel-' . $order->get_id(),
			'name'       => __( 'Cancel Order', 'babypasa-returns' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'Cancel order #%s', 'babypasa-returns' ), $order->get_order_number() ),
		];

		return $actions;
	}

	/* ------------------------------------------------------------------ *
	 * Frontend assets
	 * ------------------------------------------------------------------ */

	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		$js  = 'assets/js/order-cancel.js';
		$css = 'assets/css/order-cancel.css';

		wp_enqueue_style(
			'bp-order-cancel',
			BP_RETURNS_URL . $css,
			[],
			filemtime( BP_RETURNS_DIR . $css )
		);

		wp_enqueue_script(
			'bp-order-cancel',
			BP_RETURNS_URL . $js,
			[],
			filemtime( BP_RETURNS_DIR . $js ),
			true
		);

		wp_localize_script(
			'bp-order-cancel',
			'BPOrderCancel',
			[
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'confirm'  => __( 'Cancel this order? This cannot be undone.', 'babypasa-returns' ),
				'checking' => __( 'Checking…', 'babypasa-returns' ),
				'success'  => __( 'Your order has been cancelled.', 'babypasa-returns' ),
				'error'    => __( 'Something went wrong. Please try again.', 'babypasa-returns' ),
			]
		);
	}

	/* ------------------------------------------------------------------ *
	 * AJAX handler
	 * ------------------------------------------------------------------ */

	/**
	 * Rejects cancellation attempts from logged-out users.
	 */
	public function handle_cancel_nopriv(): void {
		wp_send_json_error( [ 'message' => __( 'Please log in to cancel an order.', 'babypasa-returns' ) ] );
	}

	/**
	 * Verifies, gates on the live Upaya status, then cancels in WooCommerce.
	 */
	public function handle_cancel(): void {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$nonce    = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		// 1) Nonce.
		if ( ! $order_id || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page and try again.', 'babypasa-returns' ) ] );
		}

		// 2) Ownership.
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || (int) $order->get_customer_id() !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => __( 'This order is not associated with your account.', 'babypasa-returns' ) ] );
		}

		// Re-check the WC status server-side (the button could be stale).
		if ( ! $order->has_status( self::CANCELLABLE_WC_STATUSES ) ) {
			wp_send_json_error( [ 'message' => __( 'This order can no longer be cancelled.', 'babypasa-returns' ) ] );
		}

		// 3) Live Upaya tracking status (read-only gate).
		$status = $this->get_live_upaya_status( $order );

		// 4) Could not verify the shipment status → never cancel.
		if ( false === $status ) {
			wp_send_json_error( [ 'message' => __( 'Unable to verify shipment status. Please contact support.', 'babypasa-returns' ) ] );
		}

		// Statuses that mean "not yet picked up" — cancellation still allowed. The
		// hyphen variant tolerates "unassigned pick up" wording differences.
		$eligible = apply_filters(
			'bp_cancel_eligible_upaya_statuses',
			[ 'unassigned-pickup', 'unassigned-pick-up', 'pending' ],
			$order
		);

		// 6) Anything beyond pickup (in transit, dispatched, delivered, …) → refuse.
		// $status === '' means the order has no Upaya tracking code yet (not
		// dispatched), which is also safe to cancel.
		if ( '' !== $status && ! in_array( $status, $eligible, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Cancellation rejected: your order is already in transit and cannot be cancelled online. Please contact us for assistance.', 'babypasa-returns' ) ] );
		}

		// 5) Eligible → cancel in WooCommerce only (Upaya has no cancel API).
		$note = ( '' === $status )
			? __( 'Order cancelled by customer via My Account (not yet dispatched to Upaya).', 'babypasa-returns' )
			: __( 'Order cancelled by customer via My Account (Upaya status: unassigned pick up).', 'babypasa-returns' );
		$order->update_status( 'cancelled', $note );

		wp_send_json_success( [ 'message' => __( 'Your order has been cancelled.', 'babypasa-returns' ) ] );
	}

	/* ------------------------------------------------------------------ *
	 * Upaya status lookup (read-only)
	 * ------------------------------------------------------------------ */

	/**
	 * Returns the live Upaya tracking status slug for an order.
	 *
	 *   ''     — the order has no Upaya tracking code yet (not dispatched).
	 *   false  — the Upaya API failed / returned no status (cannot verify).
	 *   string — the normalised status slug.
	 *
	 * The 15-minute tracking transient is deleted first so the gate sees the
	 * live status, not a stale cached value.
	 *
	 * @param  WC_Order $order
	 * @return string|false
	 */
	private function get_live_upaya_status( WC_Order $order ) {
		$tracking_code = trim( (string) $order->get_meta( '_upaya_order_id' ) );
		if ( '' === $tracking_code ) {
			return ''; // Not yet submitted to Upaya — pre-dispatch.
		}

		if ( ! class_exists( 'UPAYA_Order_Manager' ) ) {
			return false;
		}

		// Bypass UPAYA_Order_Manager's 15-minute cache for a live read.
		delete_transient( 'upaya_track_' . $order->get_id() );

		$manager  = new UPAYA_Order_Manager();
		$tracking = $manager->get_tracking_info( $order->get_id() );

		if ( is_wp_error( $tracking ) || empty( $tracking['status'] ) ) {
			return false;
		}

		// Normalise "Unassigned Pickup" / "unassigned_pickup" → "unassigned-pickup".
		return sanitize_title( (string) $tracking['status'] );
	}
}

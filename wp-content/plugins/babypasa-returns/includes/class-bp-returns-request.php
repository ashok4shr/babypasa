<?php
/**
 * Customer return-request system (E18) + admin approval action (E19).
 *
 * Customer side:
 *   - A "Request a return" button on the My Account → View Order page (shown
 *     only for eligible orders).
 *   - A `request-return` My Account endpoint that renders the return form
 *     (which items + reason) for one order.
 *   - An admin-post handler that validates, stores the request, sets state
 *     REQUESTED, and fires E18.
 *
 * Admin side:
 *   - An "Approve Return Request" order action that sets state APPROVED and
 *     fires E19.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Returns_Request {

	const ENDPOINT = 'request-return';

	public function __construct() {
		// Endpoint registration.
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_form' ) );

		// "Request a return" button on the view-order page.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'maybe_render_button' ), 20 );

		// Form submission (logged-in customers).
		add_action( 'admin_post_bp_request_return', array( $this, 'handle_submit' ) );

		// Admin approval order action (E19).
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_action' ) );
		add_action( 'woocommerce_order_action_bp_approve_return', array( $this, 'handle_approve' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Endpoint plumbing
	 * ------------------------------------------------------------------ */

	public static function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<string,string> $vars
	 * @return array<string,string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/* ------------------------------------------------------------------ *
	 * Eligibility
	 * ------------------------------------------------------------------ */

	/**
	 * Whether the current customer may request a return for this order:
	 * owns it, order is completed (delivered), no return already in progress,
	 * and within the (filterable) return window.
	 */
	private function is_eligible( WC_Order $order ): bool {
		if ( ! $order->get_user_id() || get_current_user_id() !== $order->get_user_id() ) {
			return false;
		}
		if ( ! $order->has_status( 'completed' ) ) {
			return false;
		}
		if ( '' !== BP_Returns_State::get_state( $order ) ) {
			return false; // Return already requested / in an RTO state.
		}

		$window_days = (int) apply_filters( 'bp_returns_window_days', 7, $order );
		$completed   = $order->get_date_completed() ? $order->get_date_completed() : $order->get_date_modified();
		if ( $completed && ( time() - $completed->getTimestamp() ) > $window_days * DAY_IN_SECONDS ) {
			return false;
		}

		return (bool) apply_filters( 'bp_returns_order_eligible', true, $order );
	}

	/* ------------------------------------------------------------------ *
	 * View-order button
	 * ------------------------------------------------------------------ */

	public function maybe_render_button( $order ): void {
		if ( ! $order instanceof WC_Order || ! $this->is_eligible( $order ) ) {
			// If a return is already under way, show a short status note instead.
			if ( $order instanceof WC_Order && get_current_user_id() === $order->get_user_id() ) {
				$state = BP_Returns_State::get_state( $order );
				if ( '' !== $state ) {
					echo '<p style="margin:18px 0 0;padding:12px 14px;background:#fce7f3;border:1px solid #fbcfe8;border-radius:8px;color:#9d174d;font-size:13px;">'
						. esc_html( $this->state_label( $state ) )
						. '</p>';
				}
			}
			return;
		}

		$url = wc_get_account_endpoint_url( self::ENDPOINT, $order->get_id() );
		echo '<p style="margin:18px 0 0;">'
			. '<a href="' . esc_url( $url ) . '" class="button" style="background:#ec4899;color:#ffffff;border-radius:6px;padding:10px 20px;text-decoration:none;font-weight:700;">'
			. esc_html__( 'Request a return', 'babypasa-returns' )
			. '</a></p>';
	}

	private function state_label( string $state ): string {
		switch ( $state ) {
			case BP_Returns_State::STATE_REQUESTED:
				return __( 'Your return request has been received and is being reviewed.', 'babypasa-returns' );
			case BP_Returns_State::STATE_APPROVED:
				return __( 'Your return has been approved — check your email for return instructions.', 'babypasa-returns' );
			case BP_Returns_State::STATE_RTO:
				return __( 'This order is on its way back to us.', 'babypasa-returns' );
			case BP_Returns_State::STATE_RTO_COMPLETE:
				return __( 'We have received your returned parcel.', 'babypasa-returns' );
			default:
				return '';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Return form
	 * ------------------------------------------------------------------ */

	/**
	 * Renders the return form for the endpoint URL value (the order ID).
	 *
	 * @param mixed $value Endpoint value (order ID).
	 */
	public function render_form( $value ): void {
		$order_id = absint( $value );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order || ! $this->is_eligible( $order ) ) {
			wc_print_notice( esc_html__( 'This order is not eligible for a return request.', 'babypasa-returns' ), 'error' );
			return;
		}

		include BP_RETURNS_DIR . 'templates/return-form.php';
	}

	/* ------------------------------------------------------------------ *
	 * Submission handler (E18)
	 * ------------------------------------------------------------------ */

	public function handle_submit(): void {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( ! $order_id || ! isset( $_POST['bp_return_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['bp_return_nonce'] ) ), 'bp_request_return_' . $order_id )
		) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! $this->is_eligible( $order ) ) {
			wc_add_notice( __( 'This order is not eligible for a return request.', 'babypasa-returns' ), 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}

		// Build the selected return items from the submitted item IDs.
		$selected = isset( $_POST['return_items'] ) && is_array( $_POST['return_items'] )
			? array_map( 'absint', wp_unslash( $_POST['return_items'] ) )
			: array();

		$return_items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( in_array( (int) $item_id, $selected, true ) ) {
				$return_items[] = array(
					'name' => $item->get_name(),
					'qty'  => (int) $item->get_quantity(),
				);
			}
		}

		// No selection → treat as full-order return.
		if ( empty( $return_items ) ) {
			foreach ( $order->get_items() as $item ) {
				$return_items[] = array(
					'name' => $item->get_name(),
					'qty'  => (int) $item->get_quantity(),
				);
			}
		}

		$reason = isset( $_POST['return_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['return_reason'] ) ) : '';

		// Persist the request.
		$order->update_meta_data( BP_Returns_State::META_ITEMS, wp_json_encode( $return_items ) );
		$order->update_meta_data( BP_Returns_State::META_REASON, $reason );
		$order->update_meta_data( BP_Returns_State::META_REQUESTED, time() );
		$order->save();
		BP_Returns_State::set_state( $order, BP_Returns_State::STATE_REQUESTED );

		$order->add_order_note(
			sprintf(
				/* translators: %s: return reason */
				__( 'Customer requested a return. Reason: %s', 'babypasa-returns' ),
				$reason ? $reason : __( '(none given)', 'babypasa-returns' )
			)
		);

		// Fire E18 (once).
		if ( ! BP_Returns_State::flag_set( $order, BP_Returns_State::META_E18_SENT ) ) {
			$email = BP_Returns_Emails::get( 'bp_return_requested' );
			if ( $email ) {
				$email->trigger( $order_id );
				BP_Returns_State::set_flag( $order, BP_Returns_State::META_E18_SENT );
			}
		}

		wc_add_notice( __( "Thanks! We've received your return request and will be in touch shortly.", 'babypasa-returns' ), 'success' );
		wp_safe_redirect( $order->get_view_order_url() );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Admin approval (E19)
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string,string> $actions
	 * @return array<string,string>
	 */
	public function add_order_action( array $actions ): array {
		global $theorder;
		if ( $theorder instanceof WC_Order
			&& BP_Returns_State::STATE_REQUESTED === BP_Returns_State::get_state( $theorder )
		) {
			$actions['bp_approve_return'] = __( 'Approve return request (send E19)', 'babypasa-returns' );
		}
		return $actions;
	}

	public function handle_approve( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		// Guard: a return must have been requested, and not already approved.
		if ( BP_Returns_State::STATE_REQUESTED !== BP_Returns_State::get_state( $order ) ) {
			return;
		}
		if ( BP_Returns_State::flag_set( $order, BP_Returns_State::META_E19_SENT ) ) {
			return;
		}

		$order->update_meta_data( BP_Returns_State::META_APPROVED, time() );
		$order->save();
		BP_Returns_State::set_state( $order, BP_Returns_State::STATE_APPROVED );
		$order->add_order_note( __( 'Return request approved. E19 (return approved) sent to the customer.', 'babypasa-returns' ) );

		$email = BP_Returns_Emails::get( 'bp_return_approved' );
		if ( $email ) {
			$email->trigger( $order->get_id() );
			BP_Returns_State::set_flag( $order, BP_Returns_State::META_E19_SENT );
		}
	}
}

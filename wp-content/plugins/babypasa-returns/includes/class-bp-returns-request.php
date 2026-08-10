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

	/** My Account tab endpoint listing the customer's returns. */
	const ACCOUNT_ENDPOINT = 'returns';

	public function __construct() {
		// Endpoint registration.
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 11 );
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_form' ) );

		// "Returns & Exchanges" My Account tab.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
		add_filter( 'woocommerce_endpoint_' . self::ACCOUNT_ENDPOINT . '_title', array( $this, 'account_endpoint_title' ) );
		add_action( 'woocommerce_account_' . self::ACCOUNT_ENDPOINT . '_endpoint', array( $this, 'render_account_returns' ) );

		// Post-submission banner (above the button) + "Request a return" button.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'maybe_render_notice' ), 15 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'maybe_render_button' ), 20 );

		// Form submission (logged-in customers).
		add_action( 'admin_post_bp_request_return', array( $this, 'handle_submit' ) );

		// Admin "Return Request" meta box + Approve/Reject handlers (E19/E22).
		add_action( 'add_meta_boxes_shop_order', array( $this, 'register_meta_box' ) );
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', array( $this, 'register_meta_box' ) );
		add_action( 'admin_post_bp_admin_approve_return', array( $this, 'handle_admin_approve' ) );
		add_action( 'admin_post_bp_admin_reject_return', array( $this, 'handle_admin_reject' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Endpoint plumbing
	 * ------------------------------------------------------------------ */

	public static function add_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::ACCOUNT_ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/**
	 * Flushes rewrite rules once after the endpoints change, keyed off the plugin
	 * version. Lets new endpoints work on a git-pull deploy without a manual
	 * reactivation / Permalinks re-save.
	 */
	public static function maybe_flush_rewrite(): void {
		if ( get_option( 'bp_returns_rewrite_version' ) !== BP_RETURNS_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'bp_returns_rewrite_version', BP_RETURNS_VERSION );
		}
	}

	/**
	 * @param array<string,string> $vars
	 * @return array<string,string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[ self::ENDPOINT ]         = self::ENDPOINT;
		$vars[ self::ACCOUNT_ENDPOINT ] = self::ACCOUNT_ENDPOINT;
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
		return '' === $this->ineligible_reason( $order );
	}

	/**
	 * Returns an empty string when the order is eligible for a return request,
	 * otherwise a customer-facing explanation of why it is not. Single source of
	 * truth for the view-order button, the request form, and the account tab.
	 */
	private function ineligible_reason( WC_Order $order ): string {
		if ( ! $order->get_user_id() || get_current_user_id() !== $order->get_user_id() ) {
			return __( 'This order is not associated with your account.', 'babypasa-returns' );
		}
		if ( ! $order->has_status( 'completed' ) ) {
			return __( 'Returns can only be requested once an order is completed (delivered).', 'babypasa-returns' );
		}
		if ( '' !== BP_Returns_State::get_state( $order ) ) {
			return __( 'A return is already in progress for this order.', 'babypasa-returns' );
		}

		if ( ! $this->within_window( $order ) ) {
			$window_days = (int) apply_filters( 'bp_returns_window_days', 7, $order );
			return sprintf(
				/* translators: %d: number of days in the return window */
				__( 'The %d-day return window for this order has passed.', 'babypasa-returns' ),
				$window_days
			);
		}

		if ( ! apply_filters( 'bp_returns_order_eligible', true, $order ) ) {
			return __( 'This order is not eligible for a return request.', 'babypasa-returns' );
		}

		return '';
	}

	/**
	 * Whether the order is still inside its return window (counted from the
	 * completion date). Orders with no completion date are treated as in-window
	 * rather than hidden on a timing technicality.
	 */
	private function within_window( WC_Order $order ): bool {
		$window_days = (int) apply_filters( 'bp_returns_window_days', 7, $order );
		$completed   = $order->get_date_completed() ? $order->get_date_completed() : $order->get_date_modified();
		if ( ! $completed ) {
			return true;
		}
		return ( time() - $completed->getTimestamp() ) <= $window_days * DAY_IN_SECONDS;
	}

	/* ------------------------------------------------------------------ *
	 * View-order button
	 * ------------------------------------------------------------------ */

	/**
	 * Renders a one-off confirmation/error banner on the view-order page after a
	 * return-submission redirect (?bp_return=requested|ineligible). Replaces the
	 * frontend-only wc_add_notice(), which is undefined under admin-post.php.
	 * Display-only (no state change), so no nonce is required.
	 *
	 * @param mixed $order
	 */
	public function maybe_render_notice( $order ): void {
		if ( ! isset( $_GET['bp_return'] ) || ! $order instanceof WC_Order ) {
			return;
		}

		$flag = sanitize_key( wp_unslash( $_GET['bp_return'] ) );

		if ( 'requested' === $flag ) {
			$msg = __( "Thanks! We've received your return request and will be in touch shortly.", 'babypasa-returns' );
			$bg  = '#dcfce7';
			$brd = '#bbf7d0';
			$col = '#166534';
		} elseif ( 'ineligible' === $flag ) {
			$msg = __( 'This order is not eligible for a return request.', 'babypasa-returns' );
			$bg  = '#fee2e2';
			$brd = '#fecaca';
			$col = '#991b1b';
		} else {
			return;
		}

		printf(
			'<p style="margin:18px 0 0;padding:12px 14px;background:%s;border:1px solid %s;border-radius:8px;color:%s;font-size:13px;">%s</p>',
			esc_attr( $bg ),
			esc_attr( $brd ),
			esc_attr( $col ),
			esc_html( $msg )
		);
	}

	public function maybe_render_button( $order ): void {
		if ( ! $order instanceof WC_Order || ! $this->is_eligible( $order ) ) {
			// If a return is already under way, show a short status note instead.
			// Skip cancelled orders: an RTO state there is a cancellation parcel
			// returning, not a customer return, so no return status is shown.
			if ( $order instanceof WC_Order && get_current_user_id() === $order->get_user_id()
				&& ! $order->has_status( 'cancelled' )
			) {
				$state = BP_Returns_State::get_state( $order );
				// A rejected request only shows while still within the return window.
				$hide_rejected = ( BP_Returns_State::STATE_REJECTED === $state && ! $this->within_window( $order ) );
				if ( '' !== $state && ! $hide_rejected ) {
					$bp_ref   = BP_Returns_State::get_display_reference( $order );
					$ref_html = ( ( '#' . $order->get_order_number() ) !== $bp_ref )
						? '<br><small>' . esc_html__( 'Ref:', 'babypasa-returns' ) . ' ' . esc_html( $bp_ref ) . '</small>'
						: '';
					echo '<p style="margin:18px 0 0;padding:12px 14px;background:#fce7f3;border:1px solid #fbcfe8;border-radius:8px;color:#9d174d;font-size:13px;">'
						. esc_html( $this->state_label( $state ) )
						. $ref_html // phpcs:ignore WordPress.Security.EscapeOutput -- ref pre-escaped above
						. '</p>';
				}
			}
			return;
		}

		// wc_get_account_endpoint_url() takes only the endpoint and drops any value,
		// so the order ID must be appended via wc_get_endpoint_url() instead.
		$url = wc_get_endpoint_url( self::ENDPOINT, $order->get_id(), wc_get_page_permalink( 'myaccount' ) );
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
			case BP_Returns_State::STATE_REJECTED:
				return __( 'Your return request was not approved.', 'babypasa-returns' );
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

		if ( ! $order instanceof WC_Order ) {
			wc_print_notice( esc_html__( 'Order not found.', 'babypasa-returns' ), 'error' );
			return;
		}

		$reason = $this->ineligible_reason( $order );
		if ( '' !== $reason ) {
			wc_print_notice( esc_html( $reason ), 'error' );
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
		if ( ! $order instanceof WC_Order ) {
			wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
			exit;
		}
		if ( ! $this->is_eligible( $order ) ) {
			wp_safe_redirect( add_query_arg( 'bp_return', 'ineligible', $order->get_view_order_url() ) );
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

		// wc_add_notice() is a frontend-only helper (unavailable under admin-post.php),
		// so pass the outcome as a query flag and render it on the order page instead.
		wp_safe_redirect( add_query_arg( 'bp_return', 'requested', $order->get_view_order_url() ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Admin "Return Request" meta box + Approve (E19) / Reject (E22)
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the Return Request meta box on the order edit screen
	 * (legacy posts table and HPOS orders table).
	 *
	 * @param WP_Post|WC_Order $post_or_order
	 */
	public function register_meta_box( $post_or_order ): void {
		$screen = ( $post_or_order instanceof WP_Post ) ? 'shop_order' : wc_get_page_screen_id( 'shop-order' );

		add_meta_box(
			'bp_return_request',
			__( 'Return Request', 'babypasa-returns' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Renders the Return Request meta box: current state, timestamps, items,
	 * reason, and (while REQUESTED) Approve / Reject controls.
	 *
	 * @param WP_Post|WC_Order $post_or_order
	 */
	public function render_meta_box( $post_or_order ): void {
		$order = ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$state = BP_Returns_State::get_state( $order );

		if ( '' === $state ) {
			echo '<p style="margin:0;color:#646970;">' . esc_html__( 'No return request for this order.', 'babypasa-returns' ) . '</p>';
			return;
		}

		$labels = array(
			BP_Returns_State::STATE_REQUESTED    => __( 'Requested', 'babypasa-returns' ),
			BP_Returns_State::STATE_APPROVED     => __( 'Approved', 'babypasa-returns' ),
			BP_Returns_State::STATE_REJECTED     => __( 'Rejected', 'babypasa-returns' ),
			BP_Returns_State::STATE_RTO          => __( 'Return to origin', 'babypasa-returns' ),
			BP_Returns_State::STATE_RTO_COMPLETE => __( 'Returned to warehouse', 'babypasa-returns' ),
		);
		$state_label = $labels[ $state ] ?? $state;

		echo '<p style="margin:0 0 10px;"><strong>' . esc_html__( 'Status:', 'babypasa-returns' ) . '</strong> '
			. '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#fce7f3;color:#9d174d;font-weight:600;">'
			. esc_html( $state_label ) . '</span></p>';

		// Timestamps.
		$this->render_meta_timestamp( __( 'Requested', 'babypasa-returns' ), $order->get_meta( BP_Returns_State::META_REQUESTED ) );
		$this->render_meta_timestamp( __( 'Approved', 'babypasa-returns' ), $order->get_meta( BP_Returns_State::META_APPROVED ) );
		$this->render_meta_timestamp( __( 'Rejected', 'babypasa-returns' ), $order->get_meta( BP_Returns_State::META_REJECTED ) );

		// Items requested.
		$items = BP_Returns_State::get_return_items( $order );
		if ( ! empty( $items ) ) {
			echo '<p style="margin:10px 0 4px;"><strong>' . esc_html__( 'Items:', 'babypasa-returns' ) . '</strong></p><ul style="margin:0 0 6px 16px;list-style:disc;">';
			foreach ( $items as $item ) {
				echo '<li>' . esc_html( $item['name'] ) . ' &times; ' . esc_html( (string) $item['qty'] ) . '</li>';
			}
			echo '</ul>';
		}

		// Reasons.
		$reason = (string) $order->get_meta( BP_Returns_State::META_REASON );
		if ( '' !== $reason ) {
			echo '<p style="margin:8px 0 0;"><strong>' . esc_html__( 'Customer reason:', 'babypasa-returns' ) . '</strong><br>' . esc_html( $reason ) . '</p>';
		}
		$reject_reason = (string) $order->get_meta( BP_Returns_State::META_REJECT_REASON );
		if ( '' !== $reject_reason ) {
			echo '<p style="margin:8px 0 0;"><strong>' . esc_html__( 'Rejection reason:', 'babypasa-returns' ) . '</strong><br>' . esc_html( $reject_reason ) . '</p>';
		}

		// Action controls — only while a customer request is pending.
		if ( BP_Returns_State::STATE_REQUESTED !== $state ) {
			return;
		}

		$action_url = admin_url( 'admin-post.php' );
		?>
		<hr style="margin:12px 0;">
		<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin:0;">
			<?php wp_nonce_field( 'bp_admin_approve_return_' . $order->get_id() ); ?>
			<input type="hidden" name="action" value="bp_admin_approve_return">
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
			<button type="submit" class="button button-primary" style="width:100%;">
				<?php esc_html_e( 'Approve return (send E19)', 'babypasa-returns' ); ?>
			</button>
		</form>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>" style="margin:16px 0 0;border-top:1px solid #e2e4e7;padding-top:12px;">
			<?php wp_nonce_field( 'bp_admin_reject_return_' . $order->get_id() ); ?>
			<input type="hidden" name="action" value="bp_admin_reject_return">
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
			<label for="bp_reject_reason" style="display:block;margin-bottom:4px;font-weight:600;">
				<?php esc_html_e( 'Rejection reason', 'babypasa-returns' ); ?>
			</label>
			<textarea id="bp_reject_reason" name="reject_reason" rows="2" style="width:100%;margin-bottom:6px;" placeholder="<?php esc_attr_e( 'Optional — shown to the customer in the rejection email', 'babypasa-returns' ); ?>"></textarea>
			<button type="submit" class="button" style="width:100%;">
				<?php esc_html_e( 'Reject return (send E22)', 'babypasa-returns' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Echoes a "Label: formatted date" line for a stored unix-timestamp meta value.
	 *
	 * @param string $label
	 * @param mixed  $timestamp
	 */
	private function render_meta_timestamp( string $label, $timestamp ): void {
		$ts = (int) $timestamp;
		if ( $ts <= 0 ) {
			return;
		}
		echo '<p style="margin:0 0 2px;color:#646970;font-size:12px;">' . esc_html( $label ) . ': '
			. esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) . '</p>';
	}

	/**
	 * admin-post handler: approve a pending return (E19).
	 */
	public function handle_admin_approve(): void {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id || ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'babypasa-returns' ) );
		}
		check_admin_referer( 'bp_admin_approve_return_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$this->approve_order( $order );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}

	/**
	 * admin-post handler: reject a pending return (E22).
	 */
	public function handle_admin_reject(): void {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id || ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'babypasa-returns' ) );
		}
		check_admin_referer( 'bp_admin_reject_return_' . $order_id );

		$order  = wc_get_order( $order_id );
		$reason = isset( $_POST['reject_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reject_reason'] ) ) : '';
		if ( $order instanceof WC_Order ) {
			$this->reject_order( $order, $reason );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=wc-orders' ) );
		exit;
	}

	/**
	 * Core approve routine: sets state APPROVED and fires E19 (one-shot).
	 */
	private function approve_order( WC_Order $order ): void {
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

	/**
	 * Core reject routine: sets state REJECTED (+ optional reason) and fires
	 * E22 (one-shot).
	 */
	private function reject_order( WC_Order $order, string $reason ): void {
		if ( BP_Returns_State::STATE_REQUESTED !== BP_Returns_State::get_state( $order ) ) {
			return;
		}
		if ( BP_Returns_State::flag_set( $order, BP_Returns_State::META_E22_SENT ) ) {
			return;
		}

		$order->update_meta_data( BP_Returns_State::META_REJECTED, time() );
		if ( '' !== $reason ) {
			$order->update_meta_data( BP_Returns_State::META_REJECT_REASON, $reason );
		}
		$order->save();
		BP_Returns_State::set_state( $order, BP_Returns_State::STATE_REJECTED );
		$order->add_order_note(
			sprintf(
				/* translators: %s: rejection reason */
				__( 'Return request rejected. Reason: %s. E22 (return rejected) sent to the customer.', 'babypasa-returns' ),
				$reason ? $reason : __( '(none given)', 'babypasa-returns' )
			)
		);

		$email = BP_Returns_Emails::get( 'bp_return_rejected' );
		if ( $email ) {
			$email->trigger( $order->get_id() );
			BP_Returns_State::set_flag( $order, BP_Returns_State::META_E22_SENT );
		}
	}

	/* ------------------------------------------------------------------ *
	 * "Returns & Exchanges" My Account tab
	 * ------------------------------------------------------------------ */

	/**
	 * Inserts the "Returns & Exchanges" item into the My Account menu, right
	 * after Orders (falls back to before Logout if Orders is absent).
	 *
	 * @param array<string,string> $items
	 * @return array<string,string>
	 */
	public function add_account_menu_item( array $items ): array {
		$label = __( 'Returns', 'babypasa-returns' );
		$new   = array();

		foreach ( $items as $key => $value ) {
			if ( 'customer-logout' === $key && ! isset( $new[ self::ACCOUNT_ENDPOINT ] ) ) {
				$new[ self::ACCOUNT_ENDPOINT ] = $label;
			}
			$new[ $key ] = $value;
			if ( 'orders' === $key ) {
				$new[ self::ACCOUNT_ENDPOINT ] = $label;
			}
		}

		if ( ! isset( $new[ self::ACCOUNT_ENDPOINT ] ) ) {
			$new[ self::ACCOUNT_ENDPOINT ] = $label;
		}

		return $new;
	}

	/**
	 * Title for the Returns & Exchanges endpoint page.
	 *
	 * @param string $title
	 * @return string
	 */
	public function account_endpoint_title( $title ): string {
		return __( 'Returns', 'babypasa-returns' );
	}

	/**
	 * Renders the Returns & Exchanges tab: the customer's in-progress returns,
	 * plus a chooser to start a new return on an eligible order.
	 */
	public function render_account_returns(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		// In-progress returns (status view).
		$active = array();
		foreach ( $this->get_orders_with_active_return( $user_id ) as $order ) {
			$state = BP_Returns_State::get_state( $order );
			if ( '' === $state ) {
				continue;
			}
			// A cancelled order may carry an RTO state (parcel returning because it
			// was cancelled) — that is not a customer return, so don't list it here.
			if ( $order->has_status( 'cancelled' ) ) {
				continue;
			}
			// A rejected request is only surfaced while the order is still within
			// its return window; after that it drops off the list.
			if ( BP_Returns_State::STATE_REJECTED === $state && ! $this->within_window( $order ) ) {
				continue;
			}
			$active[] = array(
				'order' => $order,
				'state' => $state,
				'label' => $this->state_label( $state ),
				'items' => BP_Returns_State::get_return_items( $order ),
			);
		}

		// Orders the customer can still request a return on.
		$eligible = $this->get_eligible_orders( $user_id );

		include BP_RETURNS_DIR . 'templates/returns-list.php';
	}

	/**
	 * Returns the customer's orders that currently have a return/RTO in progress.
	 *
	 * @param int $user_id
	 * @return WC_Order[]
	 */
	private function get_orders_with_active_return( int $user_id ): array {
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 50,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects',
				'meta_query'  => array(
					array(
						'key'     => BP_Returns_State::META_STATE,
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Returns the customer's orders that are still eligible for a return request
	 * (completed, within the window, no return already in progress).
	 *
	 * @param int $user_id
	 * @return WC_Order[]
	 */
	private function get_eligible_orders( int $user_id ): array {
		$window_days = (int) apply_filters( 'bp_returns_window_days', 7, null );

		$orders = wc_get_orders(
			array(
				'customer_id'    => $user_id,
				'status'         => array( 'completed' ),
				'limit'          => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'objects',
				'date_completed' => '>' . ( time() - $window_days * DAY_IN_SECONDS ),
			)
		);

		if ( ! is_array( $orders ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$orders,
				function ( $order ) {
					return $order instanceof WC_Order && $this->is_eligible( $order );
				}
			)
		);
	}
}

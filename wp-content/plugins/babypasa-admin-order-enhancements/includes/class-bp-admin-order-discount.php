<?php
/**
 * Manual fixed-amount discount on the WC admin order screen.
 *
 * UX: a "Discount" button sits in the order-items meta box action row, right
 * beside the Refund button (via woocommerce_order_item_add_action_buttons).
 * Clicking it prompts for a fixed amount (mirroring WooCommerce's own
 * "Apply coupon" flow), saves it to order meta (`_manual_discount_amount`)
 * over AJAX, recalculates, and reloads the items panel.
 *
 * The discount is NOT stored as a fee line (a fee renders above the Shipping
 * row in the items list). Instead it is:
 *   1. Subtracted from the order total inside woocommerce_order_after_calculate_totals,
 *      so it is recalc-safe — it re-applies on every calculate_totals(), including
 *      the "Recalculate" button — and never stacks.
 *   2. Displayed as a "Discount: -X" line directly BELOW the Shipping line in the
 *      totals summary (via woocommerce_admin_order_totals_after_shipping).
 *
 * @package BabyPasa_Admin_Order_Enhancements
 */

defined( 'ABSPATH' ) || exit;

class BP_Admin_Order_Discount {

	/** Order meta key holding the fixed discount amount. */
	const META_KEY = '_manual_discount_amount';

	public function __construct() {
		add_action( 'admin_enqueue_scripts',                        [ $this, 'enqueue_assets' ] );
		// Button in the order-items action row (fires immediately after Refund).
		add_action( 'woocommerce_order_item_add_action_buttons',    [ $this, 'render_button' ] );
		// Discount line in the totals summary, directly below Shipping.
		add_action( 'woocommerce_admin_order_totals_after_shipping', [ $this, 'render_total_line' ] );
		// Subtract the discount from the order total whenever totals are calculated.
		add_action( 'woocommerce_order_after_calculate_totals',     [ $this, 'apply_discount_to_total' ], 10, 2 );
		// AJAX endpoint behind the Discount button.
		add_action( 'wp_ajax_bp_aoe_set_order_discount',            [ $this, 'ajax_set_discount' ] );
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public function enqueue_assets( string $hook ): void {
		if ( ! $this->is_order_screen( $hook ) ) {
			return;
		}

		wp_enqueue_script(
			'bp-aoe-discount',
			BP_AOE_URL . 'assets/js/bp-admin-order-discount.js',
			// Depends on WC's order meta-box script so the #woocommerce-order-items
			// `wc_order_items_reload` handler is bound and `woocommerce_admin_meta_boxes`
			// (pulled in via its own dependency) is localised before this runs.
			[ 'jquery', 'wc-admin-order-meta-boxes' ],
			filemtime( BP_AOE_DIR . 'assets/js/bp-admin-order-discount.js' ),
			true
		);

		wp_localize_script( 'bp-aoe-discount', 'bpAoeDiscount', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bp-aoe-discount' ),
			'i18n'    => [
				'prompt'  => __( 'Enter discount amount (leave blank to remove):', 'babypasa-aoe' ),
				'invalid' => __( 'Please enter a valid, non-negative amount.', 'babypasa-aoe' ),
				'error'   => __( 'Could not apply the discount. Please try again.', 'babypasa-aoe' ),
			],
		] );
	}

	/* ------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------ */

	/**
	 * Renders the "Discount" button beside Refund in the order-items action row.
	 *
	 * @param \WC_Order $order Current order (passed by the action).
	 */
	public function render_button( $order ): void {
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order );
		if ( ! $order ) {
			return;
		}

		// Only when the order is editable (pending / on-hold / draft) — matches the
		// visibility of the sibling Add item(s) / Recalculate buttons. The total
		// can only be changed on an editable order.
		if ( ! $order->is_editable() ) {
			return;
		}

		$current = (float) $order->get_meta( self::META_KEY );
		?>
		<button type="button" class="button bp-aoe-add-discount"
			data-current="<?php echo esc_attr( $current > 0 ? wc_format_decimal( $current ) : '' ); ?>">
			<?php esc_html_e( 'Discount', 'babypasa-aoe' ); ?>
		</button>
		<?php
	}

	/**
	 * Renders the "Discount: -X" line in the totals summary, below Shipping.
	 *
	 * @param int $order_id Current order ID (passed by the action).
	 */
	public function render_total_line( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$discount = (float) $order->get_meta( self::META_KEY );
		if ( $discount <= 0 ) {
			return;
		}
		?>
		<tr>
			<td class="label"><?php esc_html_e( 'Discount', 'babypasa-aoe' ); ?>:</td>
			<td width="1%"></td>
			<td class="total">-<?php echo wp_kses_post( wc_price( $discount, [ 'currency' => $order->get_currency() ] ) ); ?></td>
		</tr>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Total adjustment
	 * ------------------------------------------------------------------ */

	/**
	 * Subtracts the stored discount from the order total after WooCommerce has
	 * (re)computed it. Runs on every calculate_totals() call, so the discount is
	 * recalc-safe and never accumulates: calculate_totals() always rebuilds the
	 * total from items + fees + shipping + tax first, then this subtracts once.
	 *
	 * Guarded by the meta check so only orders carrying a manual discount are
	 * affected — normal checkout orders and carts are untouched.
	 *
	 * @param bool      $and_taxes Whether taxes were recalculated (unused).
	 * @param \WC_Order $order     The order being calculated.
	 */
	public function apply_discount_to_total( $and_taxes, $order ): void {
		if ( ! $order instanceof \WC_Abstract_Order ) {
			return;
		}

		$discount = (float) $order->get_meta( self::META_KEY );
		if ( $discount <= 0 ) {
			return;
		}

		$order->set_total( max( 0.0, (float) $order->get_total() - $discount ) );
	}

	/* ------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------ */

	public function ajax_set_discount(): void {
		check_ajax_referer( 'bp-aoe-discount', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'babypasa-aoe' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$amount   = isset( $_POST['amount'] ) ? (float) wc_format_decimal( wp_unslash( $_POST['amount'] ) ) : 0.0;
		$amount   = max( 0.0, $amount );

		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'babypasa-aoe' ) ], 404 );
		}

		if ( $amount > 0 ) {
			$order->update_meta_data( self::META_KEY, $amount );
		} else {
			$order->delete_meta_data( self::META_KEY );
		}

		// Recalculate so the discount (applied in apply_discount_to_total) is baked
		// into the stored total. false = leave tax recalculation alone, matching the
		// WC admin recalc behaviour.
		$order->calculate_totals( false );
		$order->save();

		wp_send_json_success( [
			'discount' => $amount,
			'total'    => $order->get_total(),
		] );
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * True on any order add/edit screen — legacy (post.php / post-new.php for
	 * shop_order) and HPOS (woocommerce_page_wc-orders). Unlike the address form,
	 * the discount button is available on both new and existing orders.
	 */
	private function is_order_screen( string $hook ): bool {
		if ( 'woocommerce_page_wc-orders' === $hook ) {
			return true;
		}
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return false;
		}
		global $post;
		return ! isset( $post ) || 'shop_order' === $post->post_type;
	}
}

<?php
/**
 * Payment Status meta box on the WC admin order screen.
 *
 * Three options: Not Paid / Partially Paid / Fully Paid.
 * - Fully Paid    → sets order to processing; COD amount = 0
 * - Partially Paid → sets order to processing; COD amount = order_total - amount_paid
 * - Not Paid      → no auto-transition; COD amount = full (standard COD logic)
 *
 * @package BabyPasa_Admin_Order_Enhancements
 */

defined( 'ABSPATH' ) || exit;

class BP_Admin_Payment_Status {

	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
		add_action( 'woocommerce_process_shop_order_meta', [ $this, 'save' ], 20, 2 );
		add_filter( 'upaya_payload_cod_amount', [ $this, 'override_cod_amount' ], 10, 4 );
	}

	/* ------------------------------------------------------------------
	 * Meta box
	 * ------------------------------------------------------------------ */

	public function register_meta_box(): void {
		foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
			add_meta_box(
				'bp_aoe_payment_status',
				__( 'Payment Status', 'babypasa-aoe' ),
				[ $this, 'render' ],
				$screen,
				'side',
				'default'
			);
		}
	}

	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order
			? $post_or_order
			: wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		$order_id      = $order->get_id();
		$status        = $order->get_meta( '_bp_payment_status' ) ?: 'not_paid';
		$amount_paid   = (float) $order->get_meta( '_bp_amount_paid' );
		$order_total   = (float) $order->get_total();

		wp_nonce_field( 'bp_aoe_payment_' . $order_id, '_bp_aoe_payment_nonce' );
		?>
		<div id="bp-aoe-payment-status">
			<p>
				<label>
					<input type="radio" name="_bp_payment_status" value="not_paid"
						<?php checked( $status, 'not_paid' ); ?> />
					<?php esc_html_e( 'Not Paid', 'babypasa-aoe' ); ?>
				</label>
			</p>
			<p>
				<label>
					<input type="radio" name="_bp_payment_status" value="partial"
						<?php checked( $status, 'partial' ); ?> />
					<?php esc_html_e( 'Partially Paid', 'babypasa-aoe' ); ?>
				</label>
			</p>
			<p id="bp-aoe-amount-paid-row"
				<?php echo 'partial' === $status ? '' : 'style="display:none;"'; ?>>
				<label style="display:block;margin-bottom:4px;">
					<?php esc_html_e( 'Amount already paid (Rs.)', 'babypasa-aoe' ); ?>
				</label>
				<input type="number" id="bp_amount_paid" name="_bp_amount_paid"
					value="<?php echo esc_attr( $amount_paid > 0 ? $amount_paid : '' ); ?>"
					min="0" step="1" style="width:100%;" />
			</p>
			<p>
				<label>
					<input type="radio" name="_bp_payment_status" value="fully_paid"
						<?php checked( $status, 'fully_paid' ); ?> />
					<?php esc_html_e( 'Fully Paid', 'babypasa-aoe' ); ?>
				</label>
			</p>

			<?php if ( in_array( $status, [ 'partial', 'fully_paid' ], true ) ) : ?>
				<p class="description" style="margin-top:8px;color:#1d8348;">
					<?php if ( 'fully_paid' === $status ) : ?>
						<?php esc_html_e( 'COD amount will be sent as Rs. 0 to Upaya.', 'babypasa-aoe' ); ?>
					<?php else : ?>
						<?php
						$remaining = max( 0, $order_total - $amount_paid );
						printf(
							/* translators: %s: remaining amount */
							esc_html__( 'COD amount will be sent as Rs. %s to Upaya.', 'babypasa-aoe' ),
							esc_html( number_format( $remaining, 0 ) )
						);
						?>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Save
	 * ------------------------------------------------------------------ */

	public function save( int $order_id, $post_or_order ): void {
		if ( ! isset( $_POST['_bp_aoe_payment_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_bp_aoe_payment_nonce'] ) ), 'bp_aoe_payment_' . $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$raw_status = sanitize_text_field( $_POST['_bp_payment_status'] ?? 'not_paid' );
		$status     = in_array( $raw_status, [ 'not_paid', 'partial', 'fully_paid' ], true )
			? $raw_status : 'not_paid';

		$order->update_meta_data( '_bp_payment_status', $status );

		if ( 'partial' === $status ) {
			$order_total = (float) $order->get_total();
			$raw_paid    = (float) sanitize_text_field( $_POST['_bp_amount_paid'] ?? '0' );
			$amount_paid = max( 0.0, $raw_paid );

			// Cap at the order total only when it is known (> 0). On a brand-new
			// order the total may not be finalised at this point, so clamping to 0
			// would wrongly wipe the entered amount.
			if ( $order_total > 0 ) {
				$amount_paid = min( $amount_paid, $order_total );
			}

			$order->update_meta_data( '_bp_amount_paid', $amount_paid );
		} else {
			$order->delete_meta_data( '_bp_amount_paid' );
		}
		// phpcs:enable

		$order->save();

		// Transition to processing if payment has been collected (fully or partially).
		if ( in_array( $status, [ 'partial', 'fully_paid' ], true ) ) {
			$current = $order->get_status();
			if ( in_array( $current, [ 'pending', 'on-hold', 'failed' ], true ) ) {
				$order->update_status(
					'processing',
					__( 'Admin recorded payment. Order moved to processing.', 'babypasa-aoe' )
				);
			}
		}
	}

	/* ------------------------------------------------------------------
	 * COD filter
	 * ------------------------------------------------------------------ */

	/**
	 * Overrides the Upaya cod_amount based on the stored payment status.
	 *
	 * @param  float     $cod_amount       Calculated per-chunk COD amount.
	 * @param  \WC_Order $order            The WooCommerce order.
	 * @param  float     $item_total       This chunk's item subtotal.
	 * @param  float     $total_items_sum  Sum of all chunks' item totals.
	 * @return float
	 */
	public function override_cod_amount( float $cod_amount, \WC_Order $order, float $item_total, float $total_items_sum ): float {
		$status = $order->get_meta( '_bp_payment_status' );

		// No admin payment status recorded (e.g. front-end orders) — leave the
		// gateway-driven COD amount the Upaya order manager calculated untouched.
		if ( empty( $status ) ) {
			return $cod_amount;
		}

		// Fully paid — nothing left to collect on delivery.
		if ( 'fully_paid' === $status ) {
			return 0.0;
		}

		$order_total = (float) $order->get_total();

		if ( 'partial' === $status ) {
			$amount_paid = (float) $order->get_meta( '_bp_amount_paid' );
			$remaining   = max( 0.0, $order_total - $amount_paid );

			if ( $total_items_sum > 0 ) {
				// Distribute remaining proportionally to this chunk.
				return round( $remaining * ( $item_total / $total_items_sum ) );
			}

			// Single chunk or all-zero totals: apply full remaining.
			return round( $remaining );
		}

		// not_paid — collect the FULL order total on delivery, regardless of the
		// WooCommerce payment method or whether the order was moved to Processing.
		// (Admin orders have no 'cod' gateway, so the base amount is 0; this makes
		// "Not Paid" mean a full COD collection, as expected.)
		// Divide proportionally per chunk so split shipments sum to the order
		// total instead of each carrying the full amount.
		if ( $total_items_sum > 0 ) {
			return round( $order_total * ( $item_total / $total_items_sum ) );
		}

		return round( $order_total );
	}
}

<?php
/**
 * Order-edit "Confirm ownership" action + provisional-link notice, and a
 * status column on the WooCommerce Orders list (HPOS-compatible screen id
 * woocommerce_page_wc-orders).
 *
 * @package BabyPasa_Account_Linking
 */

defined( 'ABSPATH' ) || exit;

class BP_AL_Admin {

	public function __construct() {
		add_filter( 'woocommerce_order_actions', array( $this, 'add_actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_bp_al_confirm_link', array( $this, 'handle_confirm' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_notice' ) );

		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * @param array<string,string> $actions Existing order actions.
	 * @param WC_Order|null        $order   Order object, when available.
	 * @return array<string,string>
	 */
	public function add_actions( array $actions, $order = null ): array {
		if ( $order instanceof WC_Order && BP_AL_Linker::is_provisional( $order ) ) {
			$actions['bp_al_confirm_link'] = __( "Confirm this order's account link", 'babypasa-account-linking' );
		}

		return $actions;
	}

	/**
	 * @param WC_Order $order Order object (passed by WooCommerce).
	 */
	public function handle_confirm( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		BP_AL_Linker::confirm( $order, get_current_user_id() );
	}

	/**
	 * @param WC_Order $order Order being rendered on the edit screen.
	 */
	public function render_notice( $order ): void {
		if ( ! $order instanceof WC_Order || ! BP_AL_Linker::is_provisional( $order ) ) {
			return;
		}

		$customer = get_userdata( $order->get_customer_id() );

		printf(
			'<p class="form-field form-field-wide" style="background:#fef3c7;border:1px solid #fde68a;padding:8px 10px;border-radius:4px;margin-top:12px;"><strong>%s</strong><br>%s</p>',
			esc_html__( 'Provisional account link', 'babypasa-account-linking' ),
			esc_html(
				sprintf(
					/* translators: %s: linked customer account email */
					__( 'Linked to account %s by billing-email match. The billing email was not verified — cancellation, return requests, tracking, and the full delivery address stay hidden from the customer until confirmed via Order actions.', 'babypasa-account-linking' ),
					$customer ? $customer->user_email : __( '(unknown account)', 'babypasa-account-linking' )
				)
			)
		);
	}

	/**
	 * @param array<string,string> $columns Existing Orders list columns.
	 * @return array<string,string>
	 */
	public function add_column( array $columns ): array {
		$columns['bp_al_status'] = __( 'Account Link', 'babypasa-account-linking' );

		return $columns;
	}

	/**
	 * @param string   $column Column being rendered.
	 * @param WC_Order $order  Order for this row.
	 */
	public function render_column( $column, $order ): void {
		if ( 'bp_al_status' !== $column || ! $order instanceof WC_Order ) {
			return;
		}

		if ( BP_AL_Linker::is_provisional( $order ) ) {
			echo '<mark class="order-status status-on-hold"><span>' . esc_html__( 'Provisional', 'babypasa-account-linking' ) . '</span></mark>';
		} elseif ( $order->get_meta( BP_AL_Linker::META_CONFIRMED ) ) {
			echo '<mark class="order-status status-completed"><span>' . esc_html__( 'Confirmed', 'babypasa-account-linking' ) . '</span></mark>';
		}
	}
}

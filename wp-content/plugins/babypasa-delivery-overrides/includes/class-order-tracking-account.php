<?php
/**
 * My Account — Track Orders endpoint.
 *
 * Adds a "Track Orders" item to the WooCommerce My Account navigation.
 * The page lists all of the current customer's orders that were submitted to
 * Upaya Cargo (_upaya_submitted = '1') and shows live tracking status fetched
 * via UPAYA_Order_Manager::get_tracking_info(), which caches results for 15
 * minutes to avoid hammering the Upaya API.
 *
 * @package BabyPasa_Delivery_Overrides
 */

defined( 'ABSPATH' ) || exit;

class BP_Order_Tracking_Account {

	/** My Account endpoint slug. */
	const ENDPOINT = 'track-orders';

	public function __construct() {
		// Register the rewrite endpoint on every request so it survives theme switches.
		add_action( 'init', [ $this, 'register_endpoint' ] );

		// Add "Track Orders" to the My Account navigation menu.
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ] );

		// Render content when the endpoint is active.
		// Hook name: woocommerce_account_{endpoint-slug}_endpoint (hyphens kept as-is).
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render_endpoint' ] );

		// Orders table: "Track Order" action (only when Upaya has a tracking code).
		// Migrated from functions.php — 2026-06-05
		add_filter( 'woocommerce_my_account_my_orders_actions', [ $this, 'add_track_order_action' ], 20, 2 );
	}

	/* ------------------------------------------------------------------
	 * Orders-table "Track Order" action
	 * ------------------------------------------------------------------ */

	/**
	 * Adds a "Track Order" action to the My Account orders table, shown only when
	 * the order carries an Upaya tracking code (_upaya_order_id). The link opens
	 * in a new tab via the theme's account-orders.js enhancement.
	 *
	 * Migrated from functions.php — 2026-06-05
	 *
	 * @param  array<string,array> $actions
	 * @param  WC_Order            $order
	 * @return array<string,array>
	 */
	public function add_track_order_action( array $actions, WC_Order $order ): array {
		// Don't offer tracking for finished orders — once delivered (completed) or
		// cancelled there is nothing left to track.
		if ( $order->has_status( [ 'completed', 'cancelled' ] ) ) {
			return $actions;
		}

		// Only show once Upaya has returned a tracking code for the shipment.
		// The Upaya plugin stores _upaya_order_id via WooCommerce CRUD, so get_meta()
		// reads the canonical store (HPOS table when enabled, post meta otherwise).
		$tracking_code = trim( (string) $order->get_meta( '_upaya_order_id' ) );
		if ( '' === $tracking_code ) {
			return $actions;
		}
		$actions['track-order'] = array(
			'url'        => $this->get_tracking_url( $tracking_code, $order ),
			'name'       => __( 'Track Order', 'babypasa-delivery-overrides' ),
			/* translators: %s: order number */
			'aria-label' => sprintf( __( 'Track order #%s (opens in a new tab)', 'babypasa-delivery-overrides' ), $order->get_order_number() ),
		);
		return $actions;
	}

	/**
	 * Returns the URL used by the "Track Order" action.
	 *
	 * Upaya exposes no configured public tracking-page URL in this install, so the
	 * link defaults to the on-site Track Orders endpoint, which pulls live status
	 * from the Upaya API using the tracking code. Point it at an external Upaya
	 * tracking page by filtering 'bp_upaya_tracking_url' once that URL pattern is
	 * confirmed, e.g.
	 * add_filter( 'bp_upaya_tracking_url', fn( $url, $code ) => "https://…/{$code}", 10, 2 ).
	 *
	 * Migrated from functions.php — 2026-06-05
	 *
	 * @param  string   $tracking_code Comma-separated Upaya tracking ID(s).
	 * @param  WC_Order $order
	 * @return string
	 */
	private function get_tracking_url( string $tracking_code, WC_Order $order ): string {
		$default = wc_get_account_endpoint_url( self::ENDPOINT );
		/**
		 * Filter the Track Order destination URL.
		 *
		 * @param string   $default       On-site Track Orders endpoint URL.
		 * @param string   $tracking_code Upaya tracking ID(s).
		 * @param WC_Order $order
		 */
		return (string) apply_filters( 'bp_upaya_tracking_url', $default, $tracking_code, $order );
	}

	/* ------------------------------------------------------------------
	 * Endpoint registration
	 * ------------------------------------------------------------------ */

	public function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/* ------------------------------------------------------------------
	 * Menu item
	 * ------------------------------------------------------------------ */

	/**
	 * Inserts "Track Orders" into the My Account nav immediately after "Orders".
	 *
	 * @param  array<string,string> $items Existing menu items.
	 * @return array<string,string>
	 */
	public function add_menu_item( array $items ): array {
		$new = [];
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT ] = __( 'Track Orders', 'babypasa-delivery-overrides' );
			}
		}
		// Fallback: if 'orders' key wasn't found, append at end (before logout).
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$logout = $new['customer-logout'] ?? null;
			unset( $new['customer-logout'] );
			$new[ self::ENDPOINT ] = __( 'Track Orders', 'babypasa-delivery-overrides' );
			if ( $logout ) {
				$new['customer-logout'] = $logout;
			}
		}
		return $new;
	}

	/* ------------------------------------------------------------------
	 * Endpoint content
	 * ------------------------------------------------------------------ */

	/**
	 * Queries Upaya-submitted orders for the current customer and loads the
	 * template. The UPAYA_Order_Manager is instantiated here (not in the
	 * constructor) to avoid registering its WC hooks too early; those hooks
	 * (order_status_processing, thankyou, etc.) will not fire on the My
	 * Account page, so duplicate registration is harmless.
	 */
	public function render_endpoint(): void {
		// Only Upaya-submitted orders — meta_query limits results to those
		// where _upaya_submitted = '1', set by UPAYA_Order_Manager after a
		// successful /add-order API call.
		//
		// Finished orders are excluded: once delivered (completed) or cancelled
		// there is nothing left to track. We pass every order status EXCEPT those
		// two so the limit applies to trackable orders only.
		$trackable_statuses = array_diff(
			array_keys( wc_get_order_statuses() ),
			[ 'wc-completed', 'wc-cancelled' ]
		);

		$customer_orders = wc_get_orders( [
			'customer_id' => get_current_user_id(),
			'status'      => $trackable_statuses,
			'meta_key'    => '_upaya_submitted', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => '1',                // phpcs:ignore WordPress.DB.SlowDBQuery
			'limit'       => 20,
			'orderby'     => 'date',
			'order'       => 'DESC',
		] );

		// Instantiate the Upaya order manager to access get_tracking_info().
		$manager = new UPAYA_Order_Manager();

		include BP_DELIVERY_OVERRIDES_DIR . 'templates/myaccount/track-orders.php';
	}
}

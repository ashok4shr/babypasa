<?php
/**
 * WooCommerce Analytics → Customers screen: Phone & Alternate Phone columns.
 *
 * The Customers report is a React-rendered table that ignores the classic
 * WP_List_Table column hooks. Adding columns therefore needs three halves —
 * two for the on-screen table and one for the CSV export, which is a wholly
 * separate server-side code path:
 *
 *  1. PHP — inject the phone values into the Customers REST report response
 *     (woocommerce_rest_prepare_report_customers) so they reach the browser.
 *  2. JS  — register the two columns on the wp.hooks 'woocommerce_admin_report_table'
 *     filter (assets/js/upaya-customers-report.js, enqueued on the Customers screen).
 *  3. PHP — add the columns to the CSV export. WooCommerce runs a server-side
 *     export whenever the dataset exceeds the rows loaded on screen; that export
 *     builds its own columns and ignores both the JS filter (1/2) and the column-
 *     visibility toggles. The woocommerce_report_customers_export_columns /
 *     _prepare_export_item filters are the only way to reach that CSV.
 *
 * Both phone values are read from USER meta:
 *   - billing_phone            — WooCommerce native.
 *   - billing_alternate_phone  — Upaya custom field. WooCommerce core persists
 *     billing_-prefixed posted fields to user meta for logged-in customers at
 *     checkout (WC_Checkout::process_customer, class-wc-checkout.php), and the
 *     My Account Edit Billing Address form saves it via WC_Form_Handler::save_address().
 *     The order meta key (_billing_alternate_phone, with underscore) is a
 *     SEPARATE per-order copy and is not used here.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds Phone and Alternate Phone columns to the Analytics Customers report.
 */
class UPAYA_Customers_Report {

	/**
	 * Constructor — wires the REST data injection and the admin script enqueue.
	 *
	 * The REST filter must be registered on REST requests: the Analytics
	 * screen fetches its rows over the REST API, where is_admin() is false.
	 * This class is therefore instantiated unconditionally by UPAYA_Core. The
	 * enqueue hook is admin_enqueue_scripts, which simply never fires outside
	 * wp-admin, so no extra guard is required there.
	 */
	public function __construct() {
		// On-screen table (REST response + enqueued JS).
		add_filter( 'woocommerce_rest_prepare_report_customers', [ $this, 'inject_phone_data' ], 10, 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_columns_js' ] );

		// CSV export (server-side — separate from the on-screen table).
		add_filter( 'woocommerce_report_customers_export_columns',      [ $this, 'add_export_columns' ] );
		add_filter( 'woocommerce_report_customers_prepare_export_item', [ $this, 'add_export_item_data' ], 10, 2 );
	}

	/**
	 * Returns [ billing_phone, alternate_phone ] from user meta for a customer.
	 *
	 * Guests (user_id <= 0) have no user meta, so both values are empty — see
	 * inject_phone_data() for the guest limitation. Single source of truth for
	 * the two meta keys used by every consumer in this class.
	 *
	 * @param  int $user_id Registered customer user ID (0 for guests).
	 * @return array{0:string,1:string} [ billing_phone, alternate_phone ].
	 */
	private function get_user_phones( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [ '', '' ];
		}

		return [
			(string) get_user_meta( $user_id, 'billing_phone', true ),
			// Alternate phone — user meta key has NO leading underscore (the
			// order meta copy is `_billing_alternate_phone`).
			(string) get_user_meta( $user_id, 'billing_alternate_phone', true ),
		];
	}

	/**
	 * Injects billing_phone and the alternate phone into each Customers report
	 * row so the JS column filter can read them.
	 *
	 * Limitation — guest orders: the Customers report keys rows by registered
	 * user (user_id). Guest customers have user_id = 0 and no user meta, so
	 * both columns are blank for them. The report response does not expose
	 * order IDs, so order meta (_billing_alternate_phone) cannot be reached
	 * here — surfacing guest phones would require a separate report extension.
	 *
	 * @param  WP_REST_Response $response Prepared customers report row.
	 * @return WP_REST_Response
	 */
	public function inject_phone_data( $response ) {
		$user_id = isset( $response->data['user_id'] ) ? (int) $response->data['user_id'] : 0;

		list( $billing_phone, $alternate_phone ) = $this->get_user_phones( $user_id );

		$response->data['billing_phone']   = $billing_phone;
		$response->data['alternate_phone'] = $alternate_phone;

		return $response;
	}

	/**
	 * Adds the Phone and Alternate Phone columns to the Customers CSV export,
	 * positioned right after the Name column to match the on-screen table.
	 *
	 * Note: the server-side export always emits every column it defines — the
	 * on-screen column-visibility toggles do NOT apply to it. So these two
	 * columns always appear in the CSV (alongside WooCommerce's defaults).
	 *
	 * @param  array<string,string> $columns Column ID => label.
	 * @return array<string,string>
	 */
	public function add_export_columns( $columns ) {
		$phone_columns = [
			'billing_phone'   => __( 'Phone', 'upaya-cargo-woocommerce' ),
			'alternate_phone' => __( 'Alternate Phone', 'upaya-cargo-woocommerce' ),
		];

		if ( ! isset( $columns['name'] ) ) {
			return array_merge( (array) $columns, $phone_columns );
		}

		// Rebuild the list so the phone columns sit immediately after Name.
		$out = [];
		foreach ( (array) $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'name' === $key ) {
				$out += $phone_columns;
			}
		}

		return $out;
	}

	/**
	 * Supplies the Phone and Alternate Phone values for each exported row.
	 *
	 * The export $item is the raw report row (not the REST-prepared response),
	 * so inject_phone_data() does not run here — we look the values up directly.
	 * The row carries `user_id` (Customers DataStore); guests have 0.
	 *
	 * @param  array $export_item Column ID => value for the row being exported.
	 * @param  array $item        Single report item/row.
	 * @return array
	 */
	public function add_export_item_data( $export_item, $item ) {
		$user_id = isset( $item['user_id'] ) ? (int) $item['user_id'] : 0;

		list( $billing_phone, $alternate_phone ) = $this->get_user_phones( $user_id );

		$export_item['billing_phone']   = $billing_phone;
		$export_item['alternate_phone'] = $alternate_phone;

		return $export_item;
	}

	/**
	 * Enqueues the column-registration script — admin only, Customers screen only.
	 *
	 * Note: the Analytics UI is a React SPA. Landing on (or refreshing) the
	 * Customers URL — including the Analytics → Customers submenu link, which is
	 * a full page load with path=/customers — enqueues this script correctly.
	 * Switching tabs entirely client-side from another Analytics report would
	 * not (the script for that page load targeted a different path); a refresh
	 * restores the columns. The JS itself is endpoint-guarded, so it is inert on
	 * any other report.
	 *
	 * @return void
	 */
	public function enqueue_columns_js() {
		$screen = get_current_screen();
		if ( ! $screen || 'woocommerce_page_wc-admin' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
		if ( '/customers' !== $path ) {
			return;
		}

		$rel = 'assets/js/upaya-customers-report.js';

		wp_enqueue_script(
			'upaya-customers-report',
			UPAYA_PLUGIN_URL . $rel,
			[ 'wp-hooks', 'wp-dom-ready', 'wc-admin-app' ],
			(string) filemtime( UPAYA_PLUGIN_DIR . $rel ),
			true
		);
	}
}

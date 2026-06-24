<?php
/**
 * WooCommerce Analytics → Customers screen: Phone & Alternate Phone columns.
 *
 * The Customers report is a React-rendered table that ignores the classic
 * WP_List_Table column hooks. Adding columns therefore needs two halves:
 *
 *  1. PHP — inject the phone values into the Customers REST report response
 *     (woocommerce_rest_prepare_report_customers) so they reach the browser.
 *  2. JS  — register the two columns on the wp.hooks 'woocommerce_admin_report_table'
 *     filter (assets/js/upaya-customers-report.js, enqueued on the Customers screen).
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
		add_filter( 'woocommerce_rest_prepare_report_customers', [ $this, 'inject_phone_data' ], 10, 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_columns_js' ] );
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

		if ( $user_id > 0 ) {
			// Native WooCommerce billing phone.
			$response->data['billing_phone'] = get_user_meta( $user_id, 'billing_phone', true );
			// Alternate phone — user meta key has NO leading underscore (the
			// order meta copy is `_billing_alternate_phone`).
			$response->data['alternate_phone'] = get_user_meta( $user_id, 'billing_alternate_phone', true );
		} else {
			// Guest customer — user_id is 0, phone cannot be retrieved from user meta.
			$response->data['billing_phone']   = '';
			$response->data['alternate_phone'] = '';
		}

		return $response;
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

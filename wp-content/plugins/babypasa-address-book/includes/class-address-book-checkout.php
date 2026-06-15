<?php
/**
 * Injects the saved-address picker above the billing form on the checkout page.
 *
 * @package BabyPasa_Address_Book
 */

defined( 'ABSPATH' ) || exit;

class BP_Address_Book_Checkout {

	public function __construct() {
		add_action( 'woocommerce_before_checkout_billing_form', [ $this, 'render_address_picker' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Automatically file the delivery address into the address book once the
		// order is created — but only when it is genuinely new (see auto_save_address).
		add_action( 'woocommerce_checkout_order_created', [ $this, 'auto_save_address' ] );
	}

	/**
	 * Auto-saves the address used at checkout to the customer's address book.
	 *
	 * Behaviour:
	 *  - Logged-in customers only (the book is per-user; guests are skipped).
	 *  - Uses the order's billing details, which this store treats as the
	 *    delivery address (billing is copied to shipping on save by the Upaya
	 *    plugin).
	 *  - Never creates a duplicate: if an address with the same street, area,
	 *    postcode and hub is already stored — including one the customer just
	 *    re-used from their saved addresses — nothing is saved.
	 *  - New addresses are saved with the Address Line 1 as the label and a note
	 *    marking them as auto-saved.
	 *
	 * Any validation failure (e.g. the 10-address limit, a malformed phone) is
	 * swallowed: auto-save must never interrupt a successful checkout.
	 *
	 * @param \WC_Order $order The freshly created order.
	 * @return void
	 */
	public function auto_save_address( \WC_Order $order ): void {
		$user_id = $order->get_customer_id();
		if ( ! $user_id ) {
			return; // Guest checkout — no address book to save into.
		}

		$state     = (string) $order->get_billing_state();   // Upaya hub.
		$city      = (string) $order->get_billing_city();    // Upaya area.
		$address_1 = (string) $order->get_billing_address_1();
		$postcode  = (string) $order->get_billing_postcode();

		// A usable saved address needs a hub, an area and a street line.
		if ( '' === $state || '' === $city || '' === $address_1 ) {
			return;
		}

		// Rules 1 & 2: skip if this address is already on file (covers re-using
		// a previously saved address at checkout).
		$existing = BP_Address_Book::find_matching_address( $user_id, [
			'address_1' => $address_1,
			'city'      => $city,
			'postcode'  => $postcode,
			'state'     => $state,
		] );
		if ( null !== $existing ) {
			return;
		}

		// Rule 3: new address → save it. Label is the Address Line 1, trimmed to
		// the 50-char limit enforced by the data layer.
		BP_Address_Book::save_address( $user_id, [
			'nickname'        => mb_substr( $address_1, 0, 50 ),
			'is_default'      => false,
			'first_name'      => $order->get_billing_first_name(),
			'last_name'       => $order->get_billing_last_name(),
			'hub_area'        => $state . '||' . $city,
			'address_1'       => $address_1,
			'address_2'       => $order->get_billing_address_2(),
			'postcode'        => $postcode,
			'phone'           => $order->get_billing_phone(),
			'alternate_phone' => (string) $order->get_meta( '_billing_alternate_phone' ),
			'email'           => $order->get_billing_email(),
			'landmark'        => (string) $order->get_meta( '_upaya_landmark' ),
			'note'            => __( 'Saved for future quick access', 'babypasa-address-book' ),
			'auto_saved'      => true,
		] );
	}

	public function render_address_picker(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$addresses = BP_Address_Book::get_addresses( $user_id );

		if ( empty( $addresses ) ) {
			return;
		}

		include BP_ADDRESS_BOOK_DIR . 'templates/checkout/address-picker.php';
	}

	public function enqueue_assets(): void {
		if ( ! is_checkout() || ! is_user_logged_in() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$addresses = BP_Address_Book::get_addresses( $user_id );

		if ( empty( $addresses ) ) {
			return;
		}

		wp_enqueue_style(
			'bp-address-book',
			BP_ADDRESS_BOOK_URL . 'assets/css/address-book.css',
			[],
			filemtime( BP_ADDRESS_BOOK_DIR . 'assets/css/address-book.css' )
		);

		wp_enqueue_script(
			'bp-address-book',
			BP_ADDRESS_BOOK_URL . 'assets/js/address-book.js',
			[ 'jquery', 'woocommerce' ],
			filemtime( BP_ADDRESS_BOOK_DIR . 'assets/js/address-book.js' ),
			true
		);

		wp_localize_script( 'bp-address-book', 'bpAddressBook', [
			'addresses' => $addresses,
			'nonce'     => wp_create_nonce( 'bp_address_book_nonce' ),
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'context'   => 'checkout',
		] );
	}
}

<?php
/**
 * Plugin Name: BabyPasa Address Book
 * Description: Saved addresses for BabyPasa WooCommerce — manage in My Account, use at checkout for fast fill.
 * Version: 1.0.0
 * Author: Ashok Shrestha
 * Text Domain: babypasa-address-book
 * Requires Plugins: woocommerce, upaya-cargo-woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'BP_ADDRESS_BOOK_VERSION', '1.0.0' );
define( 'BP_ADDRESS_BOOK_FILE',    __FILE__ );
define( 'BP_ADDRESS_BOOK_DIR',     plugin_dir_path( __FILE__ ) );
define( 'BP_ADDRESS_BOOK_URL',     plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, function () {
	add_rewrite_endpoint( 'saved-addresses', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

// Boot after Upaya (priority 20) so location transients are available.
add_action( 'plugins_loaded', 'bp_address_book_boot', 25 );

function bp_address_book_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once BP_ADDRESS_BOOK_DIR . 'includes/class-address-book.php';
	require_once BP_ADDRESS_BOOK_DIR . 'includes/class-address-book-account.php';
	require_once BP_ADDRESS_BOOK_DIR . 'includes/class-address-book-checkout.php';
	require_once BP_ADDRESS_BOOK_DIR . 'includes/class-address-book-ajax.php';

	new BP_Address_Book_Account();
	new BP_Address_Book_Checkout();
	new BP_Address_Book_Ajax();
}

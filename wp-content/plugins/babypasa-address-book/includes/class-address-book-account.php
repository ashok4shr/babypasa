<?php
/**
 * Registers the "Saved Addresses" My Account endpoint and renders its content.
 *
 * @package BabyPasa_Address_Book
 */

defined( 'ABSPATH' ) || exit;

class BP_Address_Book_Account {

	public function __construct() {
		add_action( 'init', [ $this, 'register_endpoint' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ] );
		add_action( 'woocommerce_account_saved-addresses_endpoint', [ $this, 'render_endpoint' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_endpoint(): void {
		add_rewrite_endpoint( 'saved-addresses', EP_ROOT | EP_PAGES );
	}

	/**
	 * Replace the default "Addresses" menu item with "Saved Addresses".
	 */
	public function add_menu_item( array $items ): array {
		$new = [];
		foreach ( $items as $key => $label ) {
			if ( 'edit-address' === $key ) {
				// Swap default Addresses for Saved Addresses in the same position.
				$new['saved-addresses'] = __( 'Saved Addresses', 'babypasa-address-book' );
				continue;
			}
			$new[ $key ] = $label;
		}
		return $new ?: $items;
	}

	public function render_endpoint(): void {
		$user_id   = get_current_user_id();
		$addresses = BP_Address_Book::get_addresses( $user_id );
		$locations = $this->get_hub_area_options();

		include BP_ADDRESS_BOOK_DIR . 'templates/myaccount/saved-addresses.php';
	}

	public function enqueue_assets(): void {
		if ( ! is_account_page() ) {
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
			[ 'jquery', 'selectWoo' ],
			filemtime( BP_ADDRESS_BOOK_DIR . 'assets/js/address-book.js' ),
			true
		);

		wp_localize_script( 'bp-address-book', 'bpAddressBook', [
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'bp_address_book_nonce' ),
			'context'   => 'account',
			'addresses' => BP_Address_Book::get_addresses( get_current_user_id() ),
			'i18n'      => [
				'confirm_delete' => __( 'Are you sure you want to delete this address?', 'babypasa-address-book' ),
				'saving'         => __( 'Saving…', 'babypasa-address-book' ),
				'error_generic'  => __( 'Something went wrong. Please try again.', 'babypasa-address-book' ),
			],
		] );
	}

	/**
	 * Build ["HubName||AreaName" => "HubName › AreaName"] options from the
	 * Upaya location transient. Reads the same cache used at checkout.
	 *
	 * @return array<string,string>
	 */
	public function get_hub_area_options(): array {
		$raw = get_transient( 'upaya_raw_cities_cache' );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return [];
		}
		return $this->build_options_from_raw( $raw );
	}

	/**
	 * @param array $cities Raw city tree from Upaya /locations response.
	 * @return array<string,string>
	 */
	private function build_options_from_raw( array $cities ): array {
		$hubs = [];
		foreach ( $cities as $city ) {
			$hub = $city['hubName'] ?? '';
			if ( $hub === '' ) {
				continue;
			}
			foreach ( $city['areas'] ?? [] as $area ) {
				if ( ! ( $area['isActive'] ?? true ) ) {
					continue;
				}
				$name = $area['name'] ?? '';
				if ( $name !== '' ) {
					$hubs[ $hub ][] = $name;
				}
			}
		}

		ksort( $hubs );
		$options = [];
		foreach ( $hubs as $hub => $areas ) {
			sort( $areas );
			foreach ( $areas as $area ) {
				$options[ $hub . '||' . $area ] = $hub . ' › ' . $area;
			}
		}
		return $options;
	}
}

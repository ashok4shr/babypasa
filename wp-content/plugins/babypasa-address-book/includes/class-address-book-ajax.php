<?php
/**
 * AJAX handlers for address book CRUD operations.
 * All handlers require the user to be logged in (no nopriv hooks).
 *
 * @package BabyPasa_Address_Book
 */

defined( 'ABSPATH' ) || exit;

class BP_Address_Book_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_bp_save_address',        [ $this, 'save_address' ] );
		add_action( 'wp_ajax_bp_delete_address',      [ $this, 'delete_address' ] );
		add_action( 'wp_ajax_bp_set_default_address', [ $this, 'set_default_address' ] );
	}

	public function save_address(): void {
		check_ajax_referer( 'bp_address_book_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'babypasa-address-book' ) ] );
		}

		$user_id = get_current_user_id();
		$result  = BP_Address_Book::save_address( $user_id, $_POST );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'address_id' => $result,
			'addresses'  => BP_Address_Book::get_addresses( $user_id ),
		] );
	}

	public function delete_address(): void {
		check_ajax_referer( 'bp_address_book_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'babypasa-address-book' ) ] );
		}

		$user_id    = get_current_user_id();
		$address_id = sanitize_text_field( $_POST['address_id'] ?? '' );

		if ( ! $address_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid address.', 'babypasa-address-book' ) ] );
		}

		if ( BP_Address_Book::delete_address( $user_id, $address_id ) ) {
			wp_send_json_success( [ 'addresses' => BP_Address_Book::get_addresses( $user_id ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not delete address.', 'babypasa-address-book' ) ] );
		}
	}

	public function set_default_address(): void {
		check_ajax_referer( 'bp_address_book_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'babypasa-address-book' ) ] );
		}

		$user_id    = get_current_user_id();
		$address_id = sanitize_text_field( $_POST['address_id'] ?? '' );

		if ( ! $address_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid address.', 'babypasa-address-book' ) ] );
		}

		if ( BP_Address_Book::set_default( $user_id, $address_id ) ) {
			wp_send_json_success( [ 'addresses' => BP_Address_Book::get_addresses( $user_id ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not set default address.', 'babypasa-address-book' ) ] );
		}
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers for BP Ads Manager (admin-only, logged-in users).
 *
 * Registers: bp_toggle_ad_active, bp_delete_ad, bp_bulk_ads_action.
 */
class BP_Ads_Ajax {

	/** Registers all wp_ajax_ hooks. */
	public function __construct() {
		add_action( 'wp_ajax_bp_toggle_ad_active', array( $this, 'toggle_active' ) );
		add_action( 'wp_ajax_bp_delete_ad',         array( $this, 'delete_ad' ) );
		add_action( 'wp_ajax_bp_bulk_ads_action',   array( $this, 'bulk_action' ) );
	}

	/**
	 * Toggles an ad's active state.
	 *
	 * Expects POST: id (int), nonce (string).
	 * Returns JSON: { success, new_state: 0|1, label: string }.
	 */
	public function toggle_active() {
		$id = absint( $_POST['id'] ?? 0 );

		if ( ! $this->verify( 'bp_toggle_ad_' . $id ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}

		$new_state = BP_Ads_DB::toggle_active( $id );

		if ( false === $new_state ) {
			wp_send_json_error( array( 'message' => 'Ad not found or DB error.' ) );
		}

		wp_send_json_success( array(
			'new_state' => (int) $new_state,
			'label'     => $new_state ? 'Active' : 'Inactive',
		) );
	}

	/**
	 * Deletes a single ad.
	 *
	 * Expects POST: id (int), nonce (string).
	 * Returns JSON: { success }.
	 */
	public function delete_ad() {
		$id = absint( $_POST['id'] ?? 0 );

		if ( ! $this->verify( 'bp_delete_ad_' . $id ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}

		$result = BP_Ads_DB::delete( $id );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => 'DB error during delete.' ) );
		}

		wp_send_json_success();
	}

	/**
	 * Performs a bulk action on multiple ads.
	 *
	 * Expects POST: ids (array of int), action (string: enable|disable|delete), nonce (string).
	 * Returns JSON: { success, affected: int }.
	 */
	public function bulk_action() {
		if ( ! $this->verify( 'bulk-ads' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed.' ) );
		}

		$action = sanitize_key( $_POST['bulk_action'] ?? '' );
		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

		if ( ! in_array( $action, array( 'enable', 'disable', 'delete' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid action.' ) );
		}

		$affected = BP_Ads_DB::bulk_action( $ids, $action );

		wp_send_json_success( array( 'affected' => $affected ) );
	}

	/**
	 * Verifies nonce and manage_options capability.
	 *
	 * @param string $action Nonce action string.
	 * @return bool
	 */
	private function verify( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		return (bool) wp_verify_nonce( $nonce, $action );
	}
}

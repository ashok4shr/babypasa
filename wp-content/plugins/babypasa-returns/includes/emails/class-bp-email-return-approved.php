<?php
/**
 * E19 — Return approved (admin-approved, with return instructions).
 *
 * Triggered by the admin "Approve Return Request" order action.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Email_Return_Approved extends BP_Email_Base {

	/** @var string */
	protected $bp_template = 'emails/ready-to-wire/e19-return-approved.php';

	public function __construct() {
		$this->id          = 'bp_return_approved';
		$this->title       = __( 'Return/RTO: Return approved (E19)', 'babypasa-returns' );
		$this->description = __( 'Sent when an admin approves a customer return request.', 'babypasa-returns' );
		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Your return request has been approved — Order #{order_number}', 'babypasa-returns' );
	}

	public function get_default_heading(): string {
		return __( 'Your return has been approved!', 'babypasa-returns' );
	}

	protected function get_template_vars(): array {
		$order_number = $this->object->get_order_number();

		$branch_url  = (string) apply_filters(
			'bp_returns_branch_url',
			get_option( 'bp_returns_branch_url', 'https://upayacargo.com/branches' ),
			$this->object
		);
		$pickup_url  = 'mailto:support@babypasa.com?subject=' . rawurlencode( 'Return Pickup Request — Order #' . $order_number );

		return array(
			'order'           => $this->object,
			'email_heading'   => $this->get_heading(),
			'return_items'    => BP_Returns_State::get_return_items( $this->object ),
			// Upaya's order_reference_id ("BPA{order_number}{counter}") — the id Upaya
			// recognises. Shown in the pack note so the warehouse can match the parcel.
			'upaya_reference' => BP_Returns_State::get_display_reference( $this->object ),
			'branch_url'      => $branch_url,
			'pickup_url'      => $pickup_url,
			'support_url'     => $this->support_url(),
			'email'           => $this,
		);
	}
}

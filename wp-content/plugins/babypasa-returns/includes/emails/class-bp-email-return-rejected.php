<?php
/**
 * E22 — Return rejected (admin declined the customer's return request).
 *
 * Triggered by the admin "Reject" action in the Return Request meta box.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Email_Return_Rejected extends BP_Email_Base {

	/** @var string */
	protected $bp_template = 'emails/ready-to-wire/e22-return-rejected.php';

	public function __construct() {
		$this->id          = 'bp_return_rejected';
		$this->title       = __( 'Return/RTO: Return rejected (E22)', 'babypasa-returns' );
		$this->description = __( 'Sent when an admin rejects a customer return request.', 'babypasa-returns' );
		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'Update on your return request — Order #{order_number}', 'babypasa-returns' );
	}

	public function get_default_heading(): string {
		return __( 'About your return request', 'babypasa-returns' );
	}

	protected function get_template_vars(): array {
		return array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'return_items'  => BP_Returns_State::get_return_items( $this->object ),
			'reject_reason' => (string) $this->object->get_meta( BP_Returns_State::META_REJECT_REASON ),
			'support_url'   => $this->support_url(),
			'email'         => $this,
		);
	}
}

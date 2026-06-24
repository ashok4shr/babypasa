<?php
/**
 * E18 — Return requested (customer-initiated, acknowledgement).
 *
 * Triggered when a customer submits a return request from My Account.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Email_Return_Requested extends BP_Email_Base {

	/** @var string */
	protected $bp_template = 'emails/ready-to-wire/e18-return-requested.php';

	public function __construct() {
		$this->id          = 'bp_return_requested';
		$this->title       = __( 'Return/RTO: Return requested (E18)', 'babypasa-returns' );
		$this->description = __( 'Sent when a customer submits a return request for their order.', 'babypasa-returns' );
		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( "We've received your return request — Order #{order_number}", 'babypasa-returns' );
	}

	public function get_default_heading(): string {
		return __( "We've received your return request", 'babypasa-returns' );
	}

	protected function get_template_vars(): array {
		return array(
			'order'           => $this->object,
			'email_heading'   => $this->get_heading(),
			'return_items'    => BP_Returns_State::get_return_items( $this->object ),
			// Upaya reference ("BPA…") the customer should quote for the return.
			'upaya_reference' => BP_Returns_State::get_display_reference( $this->object ),
			'email'           => $this,
		);
	}
}

<?php
/**
 * E20 — RTO complete (returned parcel received at warehouse).
 *
 * Triggered when Upaya reports the parcel back at the warehouse, for both the
 * logistics RTO path and the customer-return path. Paid orders get the refund
 * details block (Version A); COD/unpaid get the simple confirmation (Version B).
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Email_RTO_Complete extends BP_Email_Base {

	/** @var string */
	protected $bp_template = 'emails/ready-to-wire/e20-rto-complete.php';

	public function __construct() {
		$this->id          = 'bp_rto_complete';
		$this->title       = __( 'Return/RTO: RTO complete (E20)', 'babypasa-returns' );
		$this->description = __( 'Sent when a returned parcel is received back at the warehouse.', 'babypasa-returns' );
		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( "We've received your returned parcel — Order #{order_number}", 'babypasa-returns' );
	}

	public function get_default_heading(): string {
		return __( "We've received your parcel", 'babypasa-returns' );
	}

	protected function get_template_vars(): array {
		return array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'return_items'  => BP_Returns_State::get_return_items( $this->object ),
			'refund_info'   => BP_Returns_State::get_refund_info( $this->object ),
			'email'         => $this,
		);
	}
}

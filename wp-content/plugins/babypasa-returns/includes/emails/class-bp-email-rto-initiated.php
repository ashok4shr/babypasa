<?php
/**
 * E17 — RTO initiated (parcel on its way back to us).
 *
 * Triggered when Upaya reports a return-to-origin. Paid orders get the
 * two-option card + refund details (Version A); COD/unpaid get the re-order
 * card only (Version B).
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Email_RTO_Initiated extends BP_Email_Base {

	/** @var string */
	protected $bp_template = 'emails/ready-to-wire/e17-rto-initiated.php';

	public function __construct() {
		$this->id          = 'bp_rto_initiated';
		$this->title       = __( 'Return/RTO: RTO initiated (E17)', 'babypasa-returns' );
		$this->description = __( 'Sent when a parcel begins its return-to-origin journey back to the warehouse.', 'babypasa-returns' );
		parent::__construct();
	}

	public function get_default_subject(): string {
		return __( 'An update on your order #{order_number}', 'babypasa-returns' );
	}

	public function get_default_heading(): string {
		return __( 'Your order is on its way back to us', 'babypasa-returns' );
	}

	protected function get_template_vars(): array {
		return array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'items'         => $this->order_items(),
			'refund_info'   => BP_Returns_State::get_refund_info( $this->object ),
			'shop_url'      => home_url( '/' ),
			'support_url'   => $this->support_url(),
			'email'         => $this,
		);
	}
}

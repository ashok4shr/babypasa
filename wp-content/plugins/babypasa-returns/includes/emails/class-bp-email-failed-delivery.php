<?php
/**
 * E16 — Failed delivery attempt (positive "we'll try again").
 *
 * Triggered from the webhook router when Upaya reports a failed/retry
 * delivery attempt. State is NOT advanced (parcel still out for delivery).
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

class BP_Email_Failed_Delivery extends BP_Email_Base {

	/** @var string */
	protected $bp_template = 'emails/ready-to-wire/e16-failed-delivery.php';

	/** @var string */
	protected $bp_tracking_code = '';

	/** @var int */
	protected $bp_attempts = 0;

	public function __construct() {
		$this->id          = 'bp_failed_delivery';
		$this->title       = __( 'Return/RTO: Failed delivery attempt (E16)', 'babypasa-returns' );
		$this->description = __( 'Sent when a delivery attempt fails and another attempt is scheduled.', 'babypasa-returns' );
		parent::__construct();
	}

	/**
	 * @param int    $order_id      Order ID.
	 * @param string $tracking_code Upaya tracking code.
	 * @param int    $attempts      Delivery attempts so far.
	 */
	public function trigger( int $order_id, string $tracking_code = '', int $attempts = 0 ): void {
		$this->bp_tracking_code = $tracking_code;
		$this->bp_attempts      = $attempts;
		parent::trigger( $order_id );
	}

	public function get_default_subject(): string {
		return __( 'We tried to deliver your order #{order_number}', 'babypasa-returns' );
	}

	public function get_default_heading(): string {
		return __( 'We tried to deliver your order!', 'babypasa-returns' );
	}

	protected function get_template_vars(): array {
		$order = $this->object;

		return array(
			'order'         => $order,
			'email_heading' => $this->get_heading(),
			'tracking_code' => $this->bp_tracking_code,
			'address'       => array(
				'line1'    => $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
				'line2'    => $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
				'city'     => $order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city(),
				'district' => $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state(),
			),
			'items'         => $this->order_items(),
			'track_url'     => $this->track_url(),
			'support_url'   => $this->support_url(),
			'attempts'      => $this->bp_attempts,
			'email'         => $this,
		);
	}
}

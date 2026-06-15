<?php
/**
 * WooCommerce email class for Upaya Cargo delivery status notifications.
 *
 * Extends WC_Email so the notification appears in WooCommerce > Settings >
 * Emails and respects WC's header/footer email wrapper.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class UPAYA_Status_Email extends WC_Email {

	/** @var string Current Upaya status slug. */
	public string $upaya_status = '';

	/** @var string Upaya tracking code. */
	public string $tracking_code = '';

	/** @var string Human-readable status message. */
	public string $readable_status = '';

	public function __construct() {
		$this->id             = 'upaya_delivery_status';
		$this->customer_email = true;
		$this->title          = __( 'Upaya Delivery Status Update', 'upaya-cargo-woocommerce' );
		$this->description    = __( 'Sent to the customer when Upaya Cargo pushes a delivery status update.', 'upaya-cargo-woocommerce' );

		$this->template_base  = UPAYA_PLUGIN_DIR . 'templates/';
		$this->template_html  = 'emails/upaya-status-update.php';
		$this->template_plain = 'emails/plain/upaya-status-update.php';

		$this->placeholders = [
			'{order_number}' => '',
			'{order_date}'   => '',
		];

		parent::__construct();
	}

	/**
	 * Triggers the email for the given order and Upaya status.
	 *
	 * @param  int    $order_id        WooCommerce order ID.
	 * @param  string $upaya_status    Upaya status slug.
	 * @param  string $tracking_code   Upaya tracking code.
	 * @param  string $readable_status Human-readable status string.
	 * @return void
	 */
	public function trigger( int $order_id, string $upaya_status, string $tracking_code, string $readable_status ): void {
		$this->setup_locale();

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$this->object          = $order;
		$this->upaya_status    = $upaya_status;
		$this->tracking_code   = $tracking_code;
		$this->readable_status = $readable_status;
		$this->recipient       = $order->get_billing_email();

		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}

		$this->restore_locale();
	}

	/**
	 * Returns the email subject line.
	 *
	 * NOTE: This file was edited directly inside the plugin.
	 * Re-apply changes after any upaya-cargo-woocommerce plugin update.
	 *
	 * 2026-06 email redesign: per-status defaults matching the client
	 * templates (E11 out-for-delivery / E12 delivered). An admin-configured
	 * subject/heading option still takes precedence.
	 */
	public function get_subject(): string {
		switch ( $this->upaya_status ) {
			case 'delivered':
				$default = __( 'Your order #{order_number} has been delivered!', 'upaya-cargo-woocommerce' );
				break;
			case 'dispatched-with-rider':
			case 'out-for-delivery':
				$default = __( 'Your order #{order_number} is out for delivery!', 'upaya-cargo-woocommerce' );
				break;
			case 'picked-up-by-rider':
				$default = __( 'Your order #{order_number} has been picked up!', 'upaya-cargo-woocommerce' );
				break;
			case 'in-transit-to-hub':
			case 'in-transit':
				$default = __( 'Your order #{order_number} is on its way! 🚚', 'upaya-cargo-woocommerce' );
				break;
			default:
				$default = __( 'Update on your order #{order_number}', 'upaya-cargo-woocommerce' );
		}
		return $this->format_string( $this->get_option( 'subject', $default ) ?: $default );
	}

	/**
	 * Returns the email heading (shown inside the WC email wrapper).
	 * Per-status defaults per the client design (see get_subject() note).
	 */
	public function get_heading(): string {
		switch ( $this->upaya_status ) {
			case 'delivered':
				$default = __( 'Your order has been delivered!', 'upaya-cargo-woocommerce' );
				break;
			case 'dispatched-with-rider':
			case 'out-for-delivery':
				$default = __( 'Your order is out for delivery!', 'upaya-cargo-woocommerce' );
				break;
			case 'picked-up-by-rider':
				$default = __( 'Your order has been picked up!', 'upaya-cargo-woocommerce' );
				break;
			case 'in-transit-to-hub':
			case 'in-transit':
				$default = __( 'Your order is on its way!', 'upaya-cargo-woocommerce' );
				break;
			default:
				$default = __( 'Delivery Status Update', 'upaya-cargo-woocommerce' );
		}
		return $this->format_string( $this->get_option( 'heading', $default ) ?: $default );
	}

	/**
	 * Renders the HTML email body.
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			[
				'order'           => $this->object,
				'email_heading'   => $this->get_heading(),
				'upaya_status'    => $this->upaya_status,
				'tracking_code'   => $this->tracking_code,
				'readable_status' => $this->readable_status,
				'sent_to_admin'   => false,
				'plain_text'      => false,
				'email'           => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Renders the plain-text email body.
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			[
				'order'           => $this->object,
				'email_heading'   => $this->get_heading(),
				'upaya_status'    => $this->upaya_status,
				'tracking_code'   => $this->tracking_code,
				'readable_status' => $this->readable_status,
				'sent_to_admin'   => false,
				'plain_text'      => true,
				'email'           => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * Always HTML — WC_Email::__construct() sets $this->email_type from get_option('email_type')
	 * which returns '' when no form field defines the default, making get_email_type() fall back
	 * to 'plain'. Overriding here ensures the EmailPreview and real sends always use HTML.
	 */
	public function get_email_type() {
		return 'html';
	}

	/**
	 * Always send as HTML content type.
	 */
	public function get_content_type( $default_content_type = '' ): string {
		return 'text/html';
	}

	/**
	 * Append a Bcc header from the per-email "Bcc(s)" setting.
	 *
	 * Self-contained: WC_Email::get_headers() only emits a Bcc when the
	 * `email_improvements` feature flag is on, so we add it here regardless and
	 * guard against duplicating the header when that flag is already active.
	 *
	 * @return string
	 */
	public function get_headers(): string {
		$header = parent::get_headers();

		if ( false === stripos( $header, 'Bcc:' ) ) {
			/** Mirror WC core's per-email filter name for consistency. */
			$bcc  = apply_filters( 'woocommerce_email_bcc_recipient_' . $this->id, $this->get_option( 'bcc', '' ), $this->object, $this );
			$bccs = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) $bcc ) ) ), 'is_email' );
			if ( ! empty( $bccs ) ) {
				$header .= 'Bcc: ' . implode( ', ', $bccs ) . "\r\n";
			}
		}

		return $header;
	}

	/**
	 * Adds subject/heading fields to the WC email settings screen.
	 */
	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'    => [
				'title'   => __( 'Enable/Disable', 'upaya-cargo-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'upaya-cargo-woocommerce' ),
				'default' => 'yes',
			],
			'subject'    => [
				'title'       => __( 'Subject', 'upaya-cargo-woocommerce' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: list of available placeholders */
					__( 'Available placeholders: %s', 'upaya-cargo-woocommerce' ),
					'{order_number}, {order_date}'
				),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
				'desc_tip'    => true,
			],
			'heading'    => [
				'title'       => __( 'Email Heading', 'upaya-cargo-woocommerce' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: list of available placeholders */
					__( 'Available placeholders: %s', 'upaya-cargo-woocommerce' ),
					'{order_number}, {order_date}'
				),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
				'desc_tip'    => true,
			],
			'bcc'        => [
				'title'       => __( 'Bcc(s)', 'upaya-cargo-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter Bcc recipients (comma-separated). They receive a hidden copy of this email.', 'upaya-cargo-woocommerce' ),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true,
			],
			'email_type' => [
				'title'       => __( 'Email type', 'upaya-cargo-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'upaya-cargo-woocommerce' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			],
		];
	}

	/** @return string */
	public function get_default_subject(): string {
		return __( 'Update on your order #{order_number}', 'upaya-cargo-woocommerce' );
	}

	/** @return string */
	public function get_default_heading(): string {
		return __( 'Delivery Status Update', 'upaya-cargo-woocommerce' );
	}
}
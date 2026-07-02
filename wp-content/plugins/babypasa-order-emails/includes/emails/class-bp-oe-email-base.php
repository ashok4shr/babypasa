<?php
/**
 * Base WC_Email for the BabyPasa order emails (invoice + feedback).
 *
 * Renders a child-theme body template (woocommerce/emails/…) through the shared
 * email-header.php / email-footer.php partials, so these emails match the rest
 * of the client design. Subclasses supply the template path, subject/heading
 * defaults, and (optionally) extra template variables.
 *
 * Modelled on babypasa-returns' BP_Email_Base, including the self-contained Bcc
 * mechanism used across the site's custom email classes.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

abstract class BP_OE_Email_Base extends WC_Email {

	/**
	 * Body template, relative to the template base (the child theme's
	 * woocommerce/ folder). Set by each subclass.
	 *
	 * @var string
	 */
	protected $bp_template = '';

	public function __construct() {
		$this->customer_email = true;
		$this->template_base  = trailingslashit( get_stylesheet_directory() ) . 'woocommerce/';
		$this->template_html  = $this->bp_template;
		$this->placeholders   = array(
			'{order_number}' => '',
			'{order_date}'   => '',
		);

		parent::__construct();
	}

	/**
	 * Subclasses may return extra variables for the body template. Merged over
	 * the standard set provided by get_content_html().
	 *
	 * @return array<string,mixed>
	 */
	protected function get_template_vars(): array {
		return array();
	}

	/**
	 * Populate object / recipient / placeholders from an order id.
	 *
	 * @param int $order_id Order id.
	 * @return WC_Order|null The order, or null when it can't be loaded.
	 */
	protected function setup_order( int $order_id ): ?WC_Order {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return null;
		}

		$this->object                         = $order;
		$this->recipient                      = $order->get_billing_email();
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );

		return $order;
	}

	/**
	 * Unconditionally send this email for an order (used by the manual admin
	 * Order actions — the toggle/status guards live at the automatic call sites,
	 * an explicit admin send should always go out).
	 *
	 * @param int $order_id Order id.
	 * @return bool Whether the mail was handed to the mailer.
	 */
	public function send_for_order( int $order_id ): bool {
		$this->setup_locale();

		$order = $this->setup_order( $order_id );
		$sent  = false;

		if ( $order && $this->get_recipient() ) {
			$sent = $this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}

		$this->restore_locale();

		return (bool) $sent;
	}

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			array_merge(
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				),
				$this->get_template_vars()
			),
			'',
			$this->template_base
		);
	}

	/** Always HTML — these designs have no plain-text variant. */
	public function get_email_type() {
		return 'html';
	}

	public function get_content_type( $default_content_type = '' ): string {
		return 'text/html';
	}

	/** Shared settings screen (enable/disable + subject + heading + content + bcc). */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'babypasa-order-emails' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'babypasa-order-emails' ),
				'default' => 'yes',
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'babypasa-order-emails' ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of available placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'babypasa-order-emails' ), '{order_number}, {order_date}' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'babypasa-order-emails' ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of available placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'babypasa-order-emails' ), '{order_number}, {order_date}' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'babypasa-order-emails' ),
				'type'        => 'textarea',
				'description' => __( 'Text to appear below the main email content.', 'babypasa-order-emails' ),
				'placeholder' => __( 'N/A', 'babypasa-order-emails' ),
				'default'     => '',
				'css'         => 'width:400px; height:75px;',
			),
			'bcc'                => array(
				'title'       => __( 'Bcc(s)', 'babypasa-order-emails' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Enter Bcc recipients (comma-separated). They receive a hidden copy of this email.', 'babypasa-order-emails' ),
				'placeholder' => '',
				'default'     => '',
			),
		);
	}

	/**
	 * Append a Bcc header from the per-email "Bcc(s)" setting.
	 *
	 * Self-contained: WC_Email::get_headers() only emits a Bcc when the
	 * `email_improvements` feature flag is on, so we add it here regardless and
	 * guard against duplicating the header when that flag is already active.
	 * Matches the pattern in babypasa-returns / upaya-cargo-woocommerce.
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

	public function get_subject(): string {
		return $this->format_string( $this->get_option( 'subject' ) ?: $this->get_default_subject() );
	}

	public function get_heading(): string {
		return $this->format_string( $this->get_option( 'heading' ) ?: $this->get_default_heading() );
	}
}

<?php
/**
 * Base WC_Email for the BabyPasa return/RTO emails (E16–E20).
 *
 * Renders a child-theme body template (woocommerce/emails/ready-to-wire/…)
 * through the shared email-header.php / email-footer.php partials, so these
 * emails match the rest of the client design. Subclasses supply the template
 * path, subject/heading defaults, and the template variable array.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;

abstract class BP_Email_Base extends WC_Email {

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
	 * Subclasses return the variable array passed into the body template.
	 * Must include at least 'order', 'email_heading', and 'email'.
	 *
	 * @return array<string,mixed>
	 */
	abstract protected function get_template_vars(): array;

	/**
	 * Common trigger: resolve the order, set placeholders + recipient, send.
	 * Subclasses may override to capture extra state before calling this.
	 */
	public function trigger( int $order_id ): void {
		$this->setup_locale();

		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$this->object                         = $order;
			$this->recipient                      = $order->get_billing_email();
			$this->placeholders['{order_number}'] = $order->get_order_number();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
		}

		if ( $this->object && $this->is_enabled() && $this->get_recipient() && BP_Returns_State::notify_enabled() ) {
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

	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			$this->get_template_vars(),
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

	/** Shared settings screen (enable/disable + subject + heading). */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'babypasa-returns' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'babypasa-returns' ),
				'default' => 'yes',
			),
			'subject' => array(
				'title'       => __( 'Subject', 'babypasa-returns' ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of available placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'babypasa-returns' ), '{order_number}, {order_date}' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading' => array(
				'title'       => __( 'Email heading', 'babypasa-returns' ),
				'type'        => 'text',
				'desc_tip'    => true,
				/* translators: %s: list of available placeholders */
				'description' => sprintf( __( 'Available placeholders: %s', 'babypasa-returns' ), '{order_number}, {order_date}' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'bcc'     => array(
				'title'       => __( 'Bcc(s)', 'babypasa-returns' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __( 'Enter Bcc recipients (comma-separated). They receive a hidden copy of this email.', 'babypasa-returns' ),
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
	 * Applies to all E16–E20 subclasses via this shared base.
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

	/**
	 * Build a normalised line-items array ([{name,qty}]) from the order.
	 *
	 * @return array<int,array{name:string,qty:int}>
	 */
	protected function order_items(): array {
		$out = array();
		if ( ! $this->object instanceof WC_Order ) {
			return $out;
		}
		foreach ( $this->object->get_items() as $item ) {
			$out[] = array(
				'name' => $item->get_name(),
				'qty'  => (int) $item->get_quantity(),
			);
		}
		return $out;
	}

	/** Filterable support mailto used across the return emails. */
	protected function support_url(): string {
		return apply_filters( 'bp_returns_support_url', 'mailto:support@babypasa.com', $this->object );
	}

	/** Filterable on-site tracking URL (reuses the Upaya filter convention). */
	protected function track_url(): string {
		$url = wc_get_account_endpoint_url( 'track-orders' );
		return (string) apply_filters( 'bp_upaya_tracking_url', $url, '', $this->object );
	}
}
<?php
/**
 * Nonce + capability protected PDF endpoints:
 *   - bp_invoice_pdf_preview  : settings-screen live preview (unsaved state), inline.
 *   - bp_invoice_pdf_download : per-order download from the order screen, attachment.
 *
 * Both are wp_ajax_ (authenticated admin only); no nopriv variants.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_Invoice_PDF_Ajax {

	/** @var BP_Invoice_PDF_Generator */
	private $generator;

	public function __construct( BP_Invoice_PDF_Generator $generator ) {
		$this->generator = $generator;
		add_action( 'wp_ajax_bp_invoice_pdf_preview', array( $this, 'preview' ) );
		add_action( 'wp_ajax_bp_invoice_pdf_download', array( $this, 'download' ) );
	}

	/**
	 * Live preview from the settings screen using the CURRENT (possibly unsaved)
	 * field values. Uses the latest order for realistic data, or dummy data when
	 * no orders exist. Streams inline (opens in a new tab).
	 */
	public function preview(): void {
		if ( ! current_user_can( BP_Invoice_PDF_Admin::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'babypasa-order-emails' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'bp_invoice_pdf_preview' );

		// Structured settings from the submitted form (sanitised), so the preview
		// reflects unsaved edits.
		$raw_input = isset( $_POST['bp_invoice_pdf'] ) && is_array( $_POST['bp_invoice_pdf'] )
			? wp_unslash( $_POST['bp_invoice_pdf'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised in ::sanitize().
			: array();
		$settings = BP_Invoice_PDF_Settings::sanitize( $raw_input );

		// Raw template override — only honoured for unfiltered_html users.
		$raw = null;
		if ( BP_Invoice_PDF_Admin::can_edit_raw() && isset( $_POST['bp_invoice_pdf_template'] ) ) {
			$raw = (string) wp_unslash( $_POST['bp_invoice_pdf_template'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unfiltered_html-gated raw template.
		}

		$order = $this->latest_order();
		$bytes = $order
			? $this->generator->generate_bytes_for_order( $order, $settings, $raw )
			: $this->generator->generate_bytes_sample( $settings, $raw );

		if ( null === $bytes ) {
			wp_die( esc_html__( 'Preview failed — check the bp-invoice-pdf log.', 'babypasa-order-emails' ) );
		}

		$this->stream( $bytes, 'invoice-preview.pdf', 'inline' );
	}

	/**
	 * Download a single order's invoice using the SAVED settings. Attachment.
	 */
	public function download(): void {
		if ( ! current_user_can( BP_Invoice_PDF_Admin::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'babypasa-order-emails' ), '', array( 'response' => 403 ) );
		}
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'bp_invoice_pdf_download_' . $order_id );

		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'babypasa-order-emails' ) );
		}

		$bytes = $this->generator->generate_bytes_for_order( $order );
		if ( null === $bytes ) {
			wp_die( esc_html__( 'Invoice PDF generation failed — check the bp-invoice-pdf log.', 'babypasa-order-emails' ) );
		}

		$this->stream( $bytes, BP_Invoice_PDF_Storage::filename( $order ), 'attachment' );
	}

	/** @return WC_Order|null Most recent order, or null if none exist. */
	private function latest_order(): ?WC_Order {
		$ids = wc_get_orders(
			array(
				'limit'   => 1,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'ids',
			)
		);
		if ( empty( $ids ) ) {
			return null;
		}
		$order = wc_get_order( $ids[0] );
		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Stream PDF bytes to the browser and exit.
	 *
	 * @param string $bytes       PDF binary.
	 * @param string $filename    Suggested filename.
	 * @param string $disposition 'inline' | 'attachment'.
	 */
	private function stream( string $bytes, string $filename, string $disposition ): void {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $bytes ) );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF stream.
		exit;
	}
}

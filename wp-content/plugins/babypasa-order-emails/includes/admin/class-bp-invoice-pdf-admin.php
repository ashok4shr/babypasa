<?php
/**
 * Invoice-PDF admin settings screen (WooCommerce → Invoice PDF).
 *
 * Structured content/layout fields + section toggles, plus (for users with
 * unfiltered_html) the advanced raw-HTML template editor. Save/reset go through
 * admin-post with nonce + capability checks; mirrors the bp-ads-manager custom
 * form + single-option pattern rather than the Settings API.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_Invoice_PDF_Admin {

	const PAGE_SLUG = 'bp-invoice-pdf';
	const CAP       = 'manage_woocommerce';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_bp_invoice_pdf_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_bp_invoice_pdf_reset', array( $this, 'handle_reset' ) );

		// Ensure the protected upload dirs exist once we're in admin.
		add_action( 'admin_init', array( 'BP_Invoice_PDF_Storage', 'bootstrap' ) );

		// "Download invoice PDF" button on the order edit screen.
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'render_download_button' ) );
	}

	/** Whether the current user may edit the raw HTML template. */
	public static function can_edit_raw(): bool {
		return current_user_can( self::CAP ) && current_user_can( 'unfiltered_html' );
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Invoice PDF', 'babypasa-order-emails' ),
			__( 'Invoice PDF', 'babypasa-order-emails' ),
			self::CAP,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue color picker, media, code editor and our glue — only on our screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );

		$settings_js = array();
		if ( self::can_edit_raw() ) {
			// CodeMirror for the raw HTML template.
			$settings_js = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );
		}

		wp_enqueue_style(
			'bp-invoice-pdf-admin',
			BP_OE_URL . 'assets/admin/invoice-pdf.css',
			array( 'wp-color-picker' ),
			BP_OE_VERSION
		);
		wp_enqueue_script(
			'bp-invoice-pdf-admin',
			BP_OE_URL . 'assets/admin/invoice-pdf.js',
			array( 'jquery', 'wp-color-picker' ),
			BP_OE_VERSION,
			true
		);
		wp_localize_script(
			'bp-invoice-pdf-admin',
			'bpInvoicePdf',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'previewNonce' => wp_create_nonce( 'bp_invoice_pdf_preview' ),
				'canRaw'       => self::can_edit_raw(),
				'codeEditor'   => $settings_js, // false when disabled.
				'i18n'         => array(
					'selectLogo'  => __( 'Select invoice logo', 'babypasa-order-emails' ),
					'useLogo'     => __( 'Use this logo', 'babypasa-order-emails' ),
					'confirmReset' => __( 'Restore the shipped defaults? This cannot be undone.', 'babypasa-order-emails' ),
				),
			)
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'babypasa-order-emails' ) );
		}

		$settings     = BP_Invoice_PDF_Settings::get();
		$raw_template = BP_Invoice_PDF_Settings::get_raw_template();
		$can_raw      = self::can_edit_raw();
		$logo_url     = $settings['logo_id'] ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'medium' ) : '';

		include BP_OE_DIR . 'admin/views/settings-page.php';
	}

	/* ------------------------------------------------------------------ *
	 * Save / reset
	 * ------------------------------------------------------------------ */

	public function handle_save(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'babypasa-order-emails' ) );
		}
		check_admin_referer( 'bp_invoice_pdf_save' );

		// Structured settings (sanitised).
		$raw_input = isset( $_POST['bp_invoice_pdf'] ) && is_array( $_POST['bp_invoice_pdf'] )
			? wp_unslash( $_POST['bp_invoice_pdf'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised field-by-field in ::sanitize().
			: array();
		update_option( BP_Invoice_PDF_Settings::OPTION, BP_Invoice_PDF_Settings::sanitize( $raw_input ) );

		// Raw template — only for users allowed to author unfiltered HTML.
		if ( self::can_edit_raw() && isset( $_POST['bp_invoice_pdf_template'] ) ) {
			// Capability-gated (unfiltered_html): stored verbatim, like the post editor.
			$template = (string) wp_unslash( $_POST['bp_invoice_pdf_template'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unfiltered_html-gated raw template.
			update_option( BP_Invoice_PDF_Settings::TEMPLATE_OPTION, $template );
		}

		$this->redirect_back( 'saved' );
	}

	public function handle_reset(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Permission denied.', 'babypasa-order-emails' ) );
		}
		check_admin_referer( 'bp_invoice_pdf_reset' );

		$target = isset( $_POST['reset_target'] ) ? sanitize_key( wp_unslash( $_POST['reset_target'] ) ) : 'all';

		if ( in_array( $target, array( 'settings', 'all' ), true ) ) {
			delete_option( BP_Invoice_PDF_Settings::OPTION );
		}
		// Resetting the template just clears the custom override → default file resumes.
		if ( in_array( $target, array( 'template', 'all' ), true ) && self::can_edit_raw() ) {
			delete_option( BP_Invoice_PDF_Settings::TEMPLATE_OPTION );
		}

		$this->redirect_back( 'reset' );
	}

	/**
	 * @param string $notice Notice slug.
	 */
	private function redirect_back( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'bp_pdf_note' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Order-screen download button
	 * ------------------------------------------------------------------ */

	/**
	 * @param WC_Order $order Order object.
	 */
	public function render_download_button( $order ): void {
		if ( ! $order instanceof WC_Order || ! current_user_can( self::CAP ) ) {
			return;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-ajax.php?action=bp_invoice_pdf_download&order_id=' . $order->get_id() ),
			'bp_invoice_pdf_download_' . $order->get_id()
		);
		echo '<p class="form-field form-field-wide bp-invoice-pdf-dl">'
			. '<a href="' . esc_url( $url ) . '" class="button" target="_blank" rel="noopener">'
			. esc_html__( 'Download invoice PDF', 'babypasa-order-emails' )
			. '</a></p>';
	}
}

<?php
/**
 * Invoice PDF generator service.
 *
 * The ONLY place that touches Dompdf. Resolves the template (saved raw → default
 * file), replaces merge tags, renders via Dompdf, and guarantees a fail-safe:
 * a broken custom template falls back to the shipped default; any hard failure
 * returns null so the caller can send the email without an attachment.
 *
 * Logs to wc_get_logger() source 'bp-invoice-pdf'. Never fatals.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_Invoice_PDF_Generator {

	const LOG_SOURCE = 'bp-invoice-pdf';

	/**
	 * Diagnostics from the last generate call, for the caller to surface as an
	 * order note. Keys: 'fallback' (bool), 'error' (string), 'unknown_tags' (array).
	 *
	 * @var array<string,mixed>
	 */
	private $diagnostics = array();

	/** @return array<string,mixed> */
	public function get_last_diagnostics(): array {
		return $this->diagnostics;
	}

	/* ------------------------------------------------------------------ *
	 * Public entry points
	 * ------------------------------------------------------------------ */

	/**
	 * Generate and persist an order's invoice PDF.
	 *
	 * @param WC_Order $order Order.
	 * @return string|null Absolute file path, or null on total failure.
	 */
	public function generate_file( WC_Order $order ): ?string {
		$bytes = $this->generate_bytes_for_order( $order );
		if ( null === $bytes ) {
			return null;
		}
		$path = BP_Invoice_PDF_Storage::save( $order, $bytes );
		if ( null === $path ) {
			$this->log( 'Failed to write invoice PDF to storage for order #' . $order->get_order_number() );
		}
		return $path;
	}

	/**
	 * Render an order's invoice to PDF bytes (no file write).
	 *
	 * @param WC_Order              $order    Order.
	 * @param array<string,mixed>|null $settings Structured settings override (unsaved preview). Null = saved.
	 * @param string|null           $raw      Raw-template override (unsaved preview). Null = saved/default.
	 * @return string|null
	 */
	public function generate_bytes_for_order( WC_Order $order, ?array $settings = null, ?string $raw = null ): ?string {
		$settings = null === $settings ? BP_Invoice_PDF_Settings::get() : $settings;
		$tags     = $this->build_order_tags( $order, $settings );
		return $this->render( $tags, $settings, $raw );
	}

	/**
	 * Render a sample invoice (dummy data) — used by Preview when no order exists.
	 *
	 * @param array<string,mixed>|null $settings Structured settings override.
	 * @param string|null           $raw      Raw-template override.
	 * @return string|null
	 */
	public function generate_bytes_sample( ?array $settings = null, ?string $raw = null ): ?string {
		$settings = null === $settings ? BP_Invoice_PDF_Settings::get() : $settings;
		$tags     = $this->build_sample_tags( $settings );
		return $this->render( $tags, $settings, $raw );
	}

	/* ------------------------------------------------------------------ *
	 * Rendering pipeline
	 * ------------------------------------------------------------------ */

	/**
	 * Resolve template → apply tags → Dompdf, with fail-safe fallback to default.
	 *
	 * @param array<string,string> $tags     Merge-tag map.
	 * @param array<string,mixed>  $settings Structured settings.
	 * @param string|null          $raw      Raw-template override (null = use saved/default).
	 * @return string|null PDF bytes, or null on total failure.
	 */
	private function render( array $tags, array $settings, ?string $raw ): ?string {
		$this->diagnostics = array(
			'fallback'     => false,
			'error'        => '',
			'unknown_tags' => array(),
		);

		// Decide the primary template: explicit override → saved raw → default file.
		if ( null !== $raw ) {
			$template   = $raw;
			$is_custom  = ( '' !== trim( $raw ) );
		} else {
			$saved     = BP_Invoice_PDF_Settings::get_raw_template();
			$template  = ( '' !== trim( $saved ) ) ? $saved : $this->default_template();
			$is_custom = ( '' !== trim( $saved ) );
		}
		if ( '' === trim( (string) $template ) ) {
			$template  = $this->default_template();
			$is_custom = false;
		}

		// First attempt (may be a custom template).
		try {
			$html  = $this->apply_tags( $template, $tags );
			$bytes = $this->render_pdf( $html, $settings );
			if ( '' !== $bytes ) {
				return $bytes;
			}
			throw new RuntimeException( 'Dompdf returned empty output.' );
		} catch ( Throwable $e ) {
			// A broken DEFAULT template is unrecoverable; report and bail.
			if ( ! $is_custom ) {
				$this->diagnostics['error'] = $e->getMessage();
				$this->log( 'Invoice PDF generation failed on the default template: ' . $e->getMessage() );
				return null;
			}
			// A broken CUSTOM template falls back to the shipped default.
			$this->diagnostics['fallback'] = true;
			$this->diagnostics['error']    = $e->getMessage();
			$this->log( 'Custom invoice template failed (' . $e->getMessage() . '); falling back to the default template.', 'warning' );
		}

		// Fallback attempt on the shipped default.
		try {
			$html  = $this->apply_tags( $this->default_template(), $tags );
			$bytes = $this->render_pdf( $html, $settings );
			return ( '' !== $bytes ) ? $bytes : null;
		} catch ( Throwable $e ) {
			$this->diagnostics['error'] = $e->getMessage();
			$this->log( 'Invoice PDF fallback (default template) also failed: ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Replace known merge tags; blank unknown {{tags}} and record them.
	 *
	 * @param string               $template Template HTML with {{tags}}.
	 * @param array<string,string> $tags     tag-name => replacement.
	 * @return string
	 */
	private function apply_tags( string $template, array $tags ): string {
		$search  = array();
		$replace = array();
		foreach ( $tags as $name => $value ) {
			$search[]  = '{{' . $name . '}}';
			$replace[] = (string) $value;
		}
		$html = str_replace( $search, $replace, $template );

		// Any remaining {{...}} are unknown → blank them, log once.
		if ( preg_match_all( '/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $html, $m ) ) {
			$unknown = array_values( array_unique( $m[1] ) );
			if ( ! empty( $unknown ) ) {
				$this->diagnostics['unknown_tags'] = $unknown;
				$this->log( 'Unknown invoice merge tag(s) rendered empty: ' . implode( ', ', $unknown ), 'notice' );
				$html = preg_replace( '/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $html );
			}
		}

		return $html;
	}

	/**
	 * Render final HTML to PDF via Dompdf.
	 *
	 * @param string              $html     Final HTML.
	 * @param array<string,mixed> $settings Structured settings (paper size).
	 * @return string PDF bytes ('' when Dompdf yields nothing).
	 * @throws Throwable On Dompdf failure.
	 */
	private function render_pdf( string $html, array $settings ): string {
		// Graceful degradation: if the vendored library is absent (e.g. an
		// incomplete deploy), throw so the caller sends the email without a PDF.
		$autoload = BP_OE_DIR . 'lib/autoload.php';
		if ( ! is_readable( $autoload ) ) {
			throw new RuntimeException( 'Dompdf library not found at ' . $autoload );
		}
		require_once $autoload;

		$options = new \Dompdf\Options();
		$options->set( 'isRemoteEnabled', false ); // No remote fetching — assets are local/data-URI.
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'defaultFont', 'DejaVu Sans' );

		// Point the writable dirs at the protected uploads cache so prod (read-only
		// plugin tree) can still build the font cache. fontDir stays at the bundled
		// default so Dompdf finds the shipped DejaVu metrics.
		$cache = BP_Invoice_PDF_Storage::cache_dir();
		if ( $cache ) {
			$options->set( 'fontCache', $cache );
			$options->set( 'tempDir', $cache );
		}

		$paper   = ( 'Letter' === ( $settings['paper_size'] ?? 'A4' ) ) ? 'Letter' : 'A4';
		$dompdf  = new \Dompdf\Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( $paper, 'portrait' );
		$dompdf->render();

		return (string) $dompdf->output();
	}

	/* ------------------------------------------------------------------ *
	 * Default template
	 * ------------------------------------------------------------------ */

	/** Load the shipped default template (HTML with {{tags}}). */
	private function default_template(): string {
		$path = apply_filters(
			'bp_invoice_pdf_default_template_path',
			BP_OE_DIR . 'templates/pdf/invoice-default.php'
		);
		if ( ! is_readable( $path ) ) {
			// Last-ditch inline template so an invoice is never blank.
			return '<html><body><h1>{{invoice_title}} {{order_number}}</h1>{{line_items_table}}{{totals_table}}</body></html>';
		}
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/* ------------------------------------------------------------------ *
	 * Merge-tag builders
	 * ------------------------------------------------------------------ */

	/**
	 * Build the full merge-tag map for a real order.
	 *
	 * @param WC_Order            $order    Order.
	 * @param array<string,mixed> $settings Structured settings.
	 * @return array<string,string>
	 */
	private function build_order_tags( WC_Order $order, array $settings ): array {
		$alt_phone = (string) $order->get_meta( '_billing_alternate_phone' );

		$shipping_addr = $order->get_formatted_shipping_address();
		$ship_block    = '';
		if ( ! empty( $settings['show_shipping_address'] ) && $shipping_addr ) {
			$ship_block = '<div class="bp-addr-col"><span class="bp-addr-label">' . esc_html__( 'Ship To', 'babypasa-order-emails' ) . '</span>' . $shipping_addr . '</div>';
		}

		$terms       = (string) ( $settings['terms'] ?? '' );
		$terms_block = '';
		if ( ! empty( $settings['show_terms'] ) && '' !== trim( $terms ) ) {
			$terms_block = '<div class="bp-terms"><h3 class="bp-terms-title">' . esc_html__( 'Terms &amp; Notes', 'babypasa-order-emails' ) . '</h3><div class="bp-terms-body">' . $terms . '</div></div>';
		}

		return array(
			'invoice_title'         => esc_html( (string) $settings['invoice_title'] ),
			'order_number'          => esc_html( $order->get_order_number() ),
			'order_date'            => esc_html( wc_format_datetime( $order->get_date_created() ) ),
			'invoice_date'          => esc_html( date_i18n( wc_date_format() ) ),
			'payment_method'        => esc_html( $order->get_payment_method_title() ),
			'billing_name'          => esc_html( $order->get_formatted_billing_full_name() ),
			'billing_address'       => $order->get_formatted_billing_address(), // WC returns safe <br/> markup.
			'billing_phone'         => esc_html( $order->get_billing_phone() ),
			'billing_alt_phone'     => esc_html( $alt_phone ),
			'billing_email'         => esc_html( $order->get_billing_email() ),
			'shipping_address'      => $shipping_addr ? $shipping_addr : '',
			'shipping_address_block' => $ship_block,
			'line_items_table'      => $this->line_items_table( $order, $settings ),
			'totals_table'          => $this->totals_table( $order, $settings ),
			'subtotal'              => wc_price( $order->get_subtotal(), array( 'currency' => $order->get_currency() ) ),
			'discount_total'        => wc_price( $order->get_total_discount(), array( 'currency' => $order->get_currency() ) ),
			'shipping_total'        => wc_price( (float) $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ),
			'order_total'           => $order->get_formatted_order_total(),
			'shop_name'             => esc_html( (string) $settings['shop_name'] ),
			'shop_address'          => nl2br( esc_html( (string) $settings['shop_address'] ) ),
			'shop_contact'          => nl2br( esc_html( (string) $settings['shop_contact'] ) ),
			'shop_reg_number'       => esc_html( (string) $settings['shop_reg_number'] ),
			'footer_text'           => esc_html( (string) $settings['footer_text'] ),
			'terms'                 => $terms,
			'terms_block'           => $terms_block,
			'accent_color'          => esc_attr( (string) $settings['accent_color'] ),
			'shop_logo'             => $this->logo_img( $settings ),
		) + $this->layout_tags( $settings );
	}

	/**
	 * Derived layout tags (font-size preset + logo alignment) shared by both maps.
	 *
	 * @param array<string,mixed> $settings Structured settings.
	 * @return array<string,string>
	 */
	private function layout_tags( array $settings ): array {
		$sizes = array(
			'small'  => '12px',
			'normal' => '13.5px',
			'large'  => '15px',
		);
		$size  = $sizes[ $settings['font_size'] ?? 'normal' ] ?? $sizes['normal'];
		$align = in_array( $settings['logo_position'] ?? 'left', array( 'left', 'center', 'right' ), true )
			? $settings['logo_position']
			: 'left';

		return array(
			'base_font_size' => $size,
			'logo_align'     => $align,
		);
	}

	/**
	 * Build a sample merge-tag map (no order) for the settings-screen preview.
	 *
	 * @param array<string,mixed> $settings Structured settings.
	 * @return array<string,string>
	 */
	private function build_sample_tags( array $settings ): array {
		$currency = get_woocommerce_currency();

		$rows = array(
			array( 'Organic Cotton Baby Onesie (0-3m)', 'BP-ONE-003', 2, 899, 1798 ),
			array( 'Bamboo Hooded Baby Towel', 'BP-TWL-011', 1, 1250, 1250 ),
		);
		$items_html = '';
		foreach ( $rows as $r ) {
			$items_html .= '<tr><td>' . esc_html( $r[0] ) . '</td>';
			if ( ! empty( $settings['show_sku'] ) ) {
				$items_html .= '<td>' . esc_html( $r[1] ) . '</td>';
			}
			$items_html .= '<td class="bp-num">' . (int) $r[2] . '</td>'
				. '<td class="bp-num">' . wc_price( $r[3], array( 'currency' => $currency ) ) . '</td>'
				. '<td class="bp-num">' . wc_price( $r[4], array( 'currency' => $currency ) ) . '</td></tr>';
		}
		$sku_head          = ! empty( $settings['show_sku'] ) ? '<th>' . esc_html__( 'SKU', 'babypasa-order-emails' ) . '</th>' : '';
		$line_items_table  = $this->items_table_wrap( $sku_head, $items_html );

		$totals = '<table class="bp-totals"><tr><td>' . esc_html__( 'Subtotal', 'babypasa-order-emails' ) . '</td><td class="bp-num">' . wc_price( 3048, array( 'currency' => $currency ) ) . '</td></tr>';
		$totals .= '<tr><td>' . esc_html__( 'Discount', 'babypasa-order-emails' ) . '</td><td class="bp-num">-' . wc_price( 300, array( 'currency' => $currency ) ) . '</td></tr>';
		$totals .= '<tr><td>' . esc_html__( 'Delivery', 'babypasa-order-emails' ) . '</td><td class="bp-num">' . wc_price( 0, array( 'currency' => $currency ) ) . '</td></tr>';
		if ( ! empty( $settings['show_payment_method'] ) ) {
			$totals .= '<tr><td>' . esc_html__( 'Payment method', 'babypasa-order-emails' ) . '</td><td class="bp-num">' . esc_html__( 'Cash on Delivery', 'babypasa-order-emails' ) . '</td></tr>';
		}
		$totals .= '<tr class="bp-grand"><td>' . esc_html__( 'Total', 'babypasa-order-emails' ) . '</td><td class="bp-num">' . wc_price( 2748, array( 'currency' => $currency ) ) . '</td></tr></table>';

		$ship_block = '';
		if ( ! empty( $settings['show_shipping_address'] ) ) {
			$ship_block = '<div class="bp-addr-col"><span class="bp-addr-label">' . esc_html__( 'Ship To', 'babypasa-order-emails' ) . '</span>Sample Customer<br/>Baneshwor, Kathmandu<br/>Nepal</div>';
		}

		$terms       = (string) ( $settings['terms'] ?? '' );
		$terms_block = '';
		if ( ! empty( $settings['show_terms'] ) && '' !== trim( $terms ) ) {
			$terms_block = '<div class="bp-terms"><h3 class="bp-terms-title">' . esc_html__( 'Terms &amp; Notes', 'babypasa-order-emails' ) . '</h3><div class="bp-terms-body">' . $terms . '</div></div>';
		}

		return array(
			'invoice_title'          => esc_html( (string) $settings['invoice_title'] ),
			'order_number'           => 'SAMPLE-1001',
			'order_date'             => esc_html( date_i18n( wc_date_format() ) ),
			'invoice_date'           => esc_html( date_i18n( wc_date_format() ) ),
			'payment_method'         => esc_html__( 'Cash on Delivery', 'babypasa-order-emails' ),
			'billing_name'           => 'Sample Customer',
			'billing_address'        => 'Sample Customer<br/>Baneshwor, Kathmandu<br/>Nepal',
			'billing_phone'          => '+977 98XXXXXXXX',
			'billing_alt_phone'      => '+977 97XXXXXXXX',
			'billing_email'          => 'customer@example.com',
			'shipping_address'       => 'Sample Customer<br/>Baneshwor, Kathmandu<br/>Nepal',
			'shipping_address_block' => $ship_block,
			'line_items_table'       => $line_items_table,
			'totals_table'           => $totals,
			'subtotal'               => wc_price( 3048, array( 'currency' => $currency ) ),
			'discount_total'         => wc_price( 300, array( 'currency' => $currency ) ),
			'shipping_total'         => wc_price( 0, array( 'currency' => $currency ) ),
			'order_total'            => wc_price( 2748, array( 'currency' => $currency ) ),
			'shop_name'              => esc_html( (string) $settings['shop_name'] ),
			'shop_address'           => nl2br( esc_html( (string) $settings['shop_address'] ) ),
			'shop_contact'           => nl2br( esc_html( (string) $settings['shop_contact'] ) ),
			'shop_reg_number'        => esc_html( (string) $settings['shop_reg_number'] ),
			'footer_text'            => esc_html( (string) $settings['footer_text'] ),
			'terms'                  => $terms,
			'terms_block'            => $terms_block,
			'accent_color'           => esc_attr( (string) $settings['accent_color'] ),
			'shop_logo'              => $this->logo_img( $settings ),
		) + $this->layout_tags( $settings );
	}

	/* ------------------------------------------------------------------ *
	 * HTML fragment builders
	 * ------------------------------------------------------------------ */

	/**
	 * Line-items table for a real order (name, SKU?, qty, unit price, line total).
	 *
	 * @param WC_Order            $order    Order.
	 * @param array<string,mixed> $settings Structured settings.
	 * @return string
	 */
	private function line_items_table( WC_Order $order, array $settings ): string {
		$show_sku = ! empty( $settings['show_sku'] );
		$rows     = '';

		foreach ( $order->get_items() as $item ) {
			$product   = $item->get_product();
			$sku       = $product ? $product->get_sku() : '';
			$unit      = wc_price( (float) $order->get_item_subtotal( $item, false, true ), array( 'currency' => $order->get_currency() ) );
			$line      = $order->get_formatted_line_subtotal( $item ); // Currency-formatted line subtotal.

			$rows .= '<tr><td>' . esc_html( $item->get_name() ) . '</td>';
			if ( $show_sku ) {
				$rows .= '<td>' . esc_html( $sku ) . '</td>';
			}
			$rows .= '<td class="bp-num">' . (int) $item->get_quantity() . '</td>'
				. '<td class="bp-num">' . $unit . '</td>'
				. '<td class="bp-num">' . $line . '</td></tr>';
		}

		$sku_head = $show_sku ? '<th>' . esc_html__( 'SKU', 'babypasa-order-emails' ) . '</th>' : '';
		return $this->items_table_wrap( $sku_head, $rows );
	}

	/** Wrap item rows in the items table with headers. */
	private function items_table_wrap( string $sku_head, string $rows ): string {
		return '<table class="bp-items"><thead><tr>'
			. '<th>' . esc_html__( 'Item', 'babypasa-order-emails' ) . '</th>'
			. $sku_head
			. '<th class="bp-num">' . esc_html__( 'Qty', 'babypasa-order-emails' ) . '</th>'
			. '<th class="bp-num">' . esc_html__( 'Unit Price', 'babypasa-order-emails' ) . '</th>'
			. '<th class="bp-num">' . esc_html__( 'Total', 'babypasa-order-emails' ) . '</th>'
			. '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	/**
	 * Totals block from WC's own order-item-totals (coupons/shipping/total exactly
	 * as WooCommerce renders them), with the payment-method row toggle-aware.
	 *
	 * @param WC_Order            $order    Order.
	 * @param array<string,mixed> $settings Structured settings.
	 * @return string
	 */
	private function totals_table( WC_Order $order, array $settings ): string {
		$totals = $order->get_order_item_totals(); // label/value rows, already formatted.
		if ( empty( $settings['show_payment_method'] ) ) {
			unset( $totals['payment_method'] );
		}

		$rows = '';
		foreach ( $totals as $key => $total ) {
			$is_grand = ( 'order_total' === $key );
			$rows    .= '<tr' . ( $is_grand ? ' class="bp-grand"' : '' ) . '>'
				. '<td>' . wp_kses_post( $total['label'] ) . '</td>'
				. '<td class="bp-num">' . wp_kses_post( $total['value'] ) . '</td></tr>';
		}
		return '<table class="bp-totals">' . $rows . '</table>';
	}

	/**
	 * Build the logo <img> as a base64 data URI (Dompdf remote fetching is off),
	 * from the media-library selection or the default theme email logo.
	 *
	 * @param array<string,mixed> $settings Structured settings.
	 * @return string <img> markup, or '' when no readable logo file.
	 */
	private function logo_img( array $settings ): string {
		$path = '';
		$logo_id = (int) ( $settings['logo_id'] ?? 0 );
		if ( $logo_id > 0 ) {
			$attached = get_attached_file( $logo_id );
			if ( $attached && is_readable( $attached ) ) {
				$path = $attached;
			}
		}
		if ( '' === $path ) {
			$default = get_stylesheet_directory() . '/assets/images/email-logo.jpg';
			if ( is_readable( $default ) ) {
				$path = $default;
			}
		}
		if ( '' === $path ) {
			return '';
		}

		$data = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		if ( false === $data ) {
			return '';
		}
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$mime = ( 'png' === $ext ) ? 'image/png' : ( ( 'gif' === $ext ) ? 'image/gif' : 'image/jpeg' );
		$uri  = 'data:' . $mime . ';base64,' . base64_encode( $data );

		return '<img class="bp-logo" src="' . esc_attr( $uri ) . '" alt="" />';
	}

	/* ------------------------------------------------------------------ */

	/**
	 * Log to the WooCommerce logger under our source.
	 *
	 * @param string $message Message.
	 * @param string $level   WC log level.
	 */
	private function log( string $message, string $level = 'error' ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
		}
	}
}

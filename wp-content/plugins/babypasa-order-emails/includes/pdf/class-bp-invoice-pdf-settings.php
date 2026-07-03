<?php
/**
 * Invoice-PDF settings data layer.
 *
 * Owns the two options and their defaults/sanitisation, shared by the generator
 * (read) and the admin screen (read/write):
 *   - bp_invoice_pdf_settings  : structured content + layout fields (array).
 *   - bp_invoice_pdf_template  : the advanced raw-HTML template ('' = use default).
 *
 * Storing the raw template separately means "reset structured settings" and
 * "reset raw template" are independent, per the approved storage split.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_Invoice_PDF_Settings {

	const OPTION          = 'bp_invoice_pdf_settings';
	const TEMPLATE_OPTION = 'bp_invoice_pdf_template';

	/**
	 * Shipped defaults for the structured fields.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			// Content.
			'shop_name'             => get_bloginfo( 'name', 'display' ),
			'shop_address'          => "Kathmandu, Nepal",
			'shop_contact'          => "support@babypasa.com",
			'shop_reg_number'       => '', // PAN / VAT / registration number.
			'invoice_title'         => __( 'INVOICE', 'babypasa-order-emails' ),
			'footer_text'           => __( 'Thank you for shopping with BabyPasa.Com!', 'babypasa-order-emails' ),
			'terms'                 => '',
			// Layout.
			'logo_id'               => 0,
			'logo_position'         => 'left',   // left | center | right.
			'accent_color'          => '#ec4899',
			'paper_size'            => 'A4',      // A4 | Letter.
			'font_size'             => 'normal',  // small | normal | large.
			// Section toggles.
			'show_sku'              => true,
			'show_shipping_address' => true,
			'show_payment_method'   => true,
			'show_terms'            => true,
		);
	}

	/** Allowed values for the bounded enum fields. */
	public static function enums(): array {
		return array(
			'logo_position' => array( 'left', 'center', 'right' ),
			'paper_size'    => array( 'A4', 'Letter' ),
			'font_size'     => array( 'small', 'normal', 'large' ),
		);
	}

	/** Boolean toggle keys. */
	public static function toggles(): array {
		return array( 'show_sku', 'show_shipping_address', 'show_payment_method', 'show_terms' );
	}

	/**
	 * Saved structured settings merged over defaults (so new keys always resolve).
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/** The saved raw template, or '' when none has been saved (use the default file). */
	public static function get_raw_template(): string {
		return (string) get_option( self::TEMPLATE_OPTION, '' );
	}

	/**
	 * Sanitise a raw structured-settings input array (from $_POST) into storable form.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();
		$enums    = self::enums();
		$out      = array();

		$out['shop_name']       = isset( $input['shop_name'] ) ? sanitize_text_field( wp_unslash( $input['shop_name'] ) ) : $defaults['shop_name'];
		$out['shop_address']    = isset( $input['shop_address'] ) ? sanitize_textarea_field( wp_unslash( $input['shop_address'] ) ) : $defaults['shop_address'];
		$out['shop_contact']    = isset( $input['shop_contact'] ) ? sanitize_textarea_field( wp_unslash( $input['shop_contact'] ) ) : $defaults['shop_contact'];
		$out['shop_reg_number'] = isset( $input['shop_reg_number'] ) ? sanitize_text_field( wp_unslash( $input['shop_reg_number'] ) ) : '';
		$out['invoice_title']   = isset( $input['invoice_title'] ) ? sanitize_text_field( wp_unslash( $input['invoice_title'] ) ) : $defaults['invoice_title'];
		$out['footer_text']     = isset( $input['footer_text'] ) ? sanitize_text_field( wp_unslash( $input['footer_text'] ) ) : $defaults['footer_text'];

		// Rich text — allow safe HTML only.
		$out['terms'] = isset( $input['terms'] ) ? wp_kses_post( wp_unslash( $input['terms'] ) ) : '';

		$out['logo_id'] = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;

		// Enums — fall back to default when the submitted value is not whitelisted.
		foreach ( $enums as $key => $allowed ) {
			$val         = isset( $input[ $key ] ) ? sanitize_text_field( wp_unslash( $input[ $key ] ) ) : '';
			$out[ $key ] = in_array( $val, $allowed, true ) ? $val : $defaults[ $key ];
		}

		// Colour — sanitize_hex_color() returns null on invalid input.
		$color               = isset( $input['accent_color'] ) ? sanitize_hex_color( wp_unslash( $input['accent_color'] ) ) : '';
		$out['accent_color'] = $color ? $color : $defaults['accent_color'];

		// Toggles — checkbox present (any truthy) = on.
		foreach ( self::toggles() as $key ) {
			$out[ $key ] = ! empty( $input[ $key ] );
		}

		return $out;
	}
}

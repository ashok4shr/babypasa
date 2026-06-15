<?php
/**
 * Checkout-facing presentation for the ConnectIPS gateway.
 *
 * Keeps all front-of-house UI tweaks (gateway logo, payment description,
 * checkout privacy notice) in one place so the gateway class stays focused
 * on the payment flow. Everything here is scoped to the ConnectIPS gateway
 * and/or the checkout page only.
 *
 * @package BabyPasa\ConnectIPS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Presentation helper for the ConnectIPS gateway on the checkout page.
 */
class BC_Checkout_UI {

	/** Gateway logo asset, relative to the plugin root. */
	const LOGO_REL_PATH = 'assets/img/logo_connectIPS.png';

	/** Exact customer-facing payment description shown under the gateway. */
	const DESCRIPTION = 'You can pay with your bank app or digital wallets by scanning the QR code or login to ConnectIPS.';

	/**
	 * Wires the presentation hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'woocommerce_gateway_icon',             [ __CLASS__, 'gateway_icon' ], 10, 2 );
		add_filter( 'woocommerce_gateway_description',      [ __CLASS__, 'gateway_description' ], 10, 2 );
		add_filter( 'woocommerce_get_privacy_policy_text',  [ __CLASS__, 'remove_checkout_privacy_text' ], 10, 2 );
		add_action( 'wp_enqueue_scripts',                   [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Replaces the default gateway icon markup with the ConnectIPS logo.
	 *
	 * Uses the idiomatic `woocommerce_gateway_icon` filter so the markup is
	 * rendered wherever WooCommerce prints the gateway icon (checkout, blocks,
	 * pay-for-order page). Falls back to the gateway title text when the logo
	 * asset is missing so the customer is never shown a broken image.
	 *
	 * @param string $icon Existing icon HTML.
	 * @param string $id   Gateway ID.
	 * @return string
	 */
	public static function gateway_icon( string $icon, string $id ): string {
		if ( BC_Gateway::ID !== $id ) {
			return $icon;
		}

		$logo_path = BC_DIR . self::LOGO_REL_PATH;

		if ( ! file_exists( $logo_path ) ) {
			// No asset available — let WooCommerce fall back to the title text.
			return '';
		}

		return sprintf(
			'<img src="%1$s" class="bc-gateway-logo" alt="%2$s" />',
			esc_url( BC_URL . self::LOGO_REL_PATH ),
			esc_attr__( 'ConnectIPS', 'babypasa-connectips' )
		);
	}

	/**
	 * Overrides the gateway's payment description with the approved copy.
	 *
	 * @param string $description Existing description HTML.
	 * @param string $id          Gateway ID.
	 * @return string
	 */
	public static function gateway_description( string $description, string $id ): string {
		if ( BC_Gateway::ID !== $id ) {
			return $description;
		}

		return esc_html__(
			'You can pay with your bank or digital wallets by scanning the QR code or login to ConnectIPS.',
			'babypasa-connectips'
		);
	}

	/**
	 * Suppresses the default WooCommerce privacy/data notice on the checkout
	 * page only. Registration and other contexts are left untouched.
	 *
	 * WooCommerce resolves this text through `wc_get_privacy_policy_text()`,
	 * which exposes the `woocommerce_get_privacy_policy_text` filter with the
	 * policy `$type`. Returning an empty string for the `checkout` type while
	 * on the checkout page removes the paragraph without affecting the site.
	 *
	 * @param string $text Privacy policy text.
	 * @param string $type Policy type ('checkout' | 'registration').
	 * @return string
	 */
	public static function remove_checkout_privacy_text( string $text, string $type ): string {
		if ( 'checkout' === $type && function_exists( 'is_checkout' ) && is_checkout() ) {
			return '';
		}

		return $text;
	}

	/**
	 * Enqueues the small checkout stylesheet that sizes the gateway logo.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		$css_rel = 'assets/css/checkout-ui.css';
		$css_abs = BC_DIR . $css_rel;

		if ( ! file_exists( $css_abs ) ) {
			return;
		}

		wp_enqueue_style(
			'bc-checkout-ui',
			BC_URL . $css_rel,
			[],
			filemtime( $css_abs )
		);
	}
}

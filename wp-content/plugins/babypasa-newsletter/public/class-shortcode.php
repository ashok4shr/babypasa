<?php
/**
 * Registers the [bpnl_form] shortcode and enqueues front-end assets.
 */

namespace BabypasaNewsletter\Frontend;

defined( 'ABSPATH' ) || exit;

class Shortcode {

	public function __construct() {
		add_shortcode( 'bpnl_form', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets(): void {
		// Enqueued globally because the footer always contains the newsletter form.
		wp_enqueue_style(
			'bpnl-form',
			BPNL_PLUGIN_URL . 'public/assets/css/bpnl-form.css',
			array(),
			filemtime( BPNL_PLUGIN_DIR . 'public/assets/css/bpnl-form.css' )
		);

		wp_enqueue_script(
			'bpnl-form',
			BPNL_PLUGIN_URL . 'public/assets/js/bpnl-form.js',
			array(),
			filemtime( BPNL_PLUGIN_DIR . 'public/assets/js/bpnl-form.js' ),
			true
		);

		wp_localize_script(
			'bpnl-form',
			'bpnlData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bpnl_nonce' ),
			)
		);
	}

	/**
	 * Outputs the subscription form HTML and lazily enqueues assets.
	 *
	 * @param  array|string $atts  Shortcode attributes (none used currently).
	 * @return string
	 */
	public function render( $atts ): string {
		ob_start();
		?>
		<div class="bpnl-form-wrap">
			<form class="bp-newsletter-form bpnl-form" novalidate>
				<input
					type="email"
					name="bpnl_email"
					placeholder="<?php esc_attr_e( 'Enter your email address', 'babypasa-newsletter' ); ?>"
					required
					autocomplete="email"
				>
				<button type="submit">SUBSCRIBE</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}
}

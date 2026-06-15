<?php
/**
 * Routing, button rendering and assets for BabyPasa Google Login.
 *
 * @package BabyPasa_Google_Login
 */

defined( 'ABSPATH' ) || exit;

class BP_Google_Login {

	public function __construct() {
		// Routes are registered every load (cheap) so they survive cache/migration;
		// activation already flushed once to write them to the rewrite table.
		add_action( 'init', 'bp_glogin_register_rewrites' );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );

		// Dispatch the auth endpoints before any template renders.
		add_action( 'template_redirect', [ $this, 'dispatch' ] );

		// Button.
		add_shortcode( 'bp_google_login', [ $this, 'render_button' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	public function query_vars( array $vars ): array {
		$vars[] = 'bp_google_auth';
		return $vars;
	}

	/**
	 * Route /bp-google-auth/ and /bp-google-auth/callback/ to the OAuth handler.
	 */
	public function dispatch(): void {
		$action = get_query_var( 'bp_google_auth' );
		if ( '' === $action || null === $action ) {
			return;
		}

		$oauth = new BP_Google_OAuth();

		if ( 'start' === $action ) {
			$oauth->start();   // exits
		} elseif ( 'callback' === $action ) {
			$oauth->callback(); // exits
		}
	}

	/* ------------------------------------------------------------------
	 * Button
	 * ------------------------------------------------------------------ */

	public function register_assets(): void {
		wp_register_style(
			'bp-google-login',
			BP_GLOGIN_URL . 'assets/css/google-login.css',
			[],
			BP_GLOGIN_VERSION
		);
	}

	/**
	 * [bp_google_login] — renders the "Continue with Google" button.
	 *
	 * The button is just an anchor to our start route, so clicking it triggers a
	 * normal full-page navigation (the whole point of this plugin).
	 *
	 * @param  array $atts  Supports redirect_to="..." to override the destination.
	 * @return string
	 */
	public function render_button( $atts = [] ): string {
		// Don't show a login button to users who are already logged in.
		if ( is_user_logged_in() ) {
			return '';
		}
		if ( ! BP_Google_Login_Settings::is_configured() ) {
			// Quietly render nothing on the front end if not set up yet.
			return current_user_can( 'manage_options' )
				? '<p class="bp-google-login-notice">' . esc_html__( '[bp_google_login] Configure Google Login in Settings → Google Login.', 'babypasa-google-login' ) . '</p>'
				: '';
		}

		$atts = shortcode_atts( [ 'redirect_to' => '' ], $atts, 'bp_google_login' );

		// Default the post-login destination to the current page when not specified.
		$redirect_to = $atts['redirect_to'];
		if ( '' === $redirect_to && isset( $_SERVER['REQUEST_URI'] ) ) {
			$redirect_to = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}

		$settings = BP_Google_Login_Settings::get();
		$label    = $settings['button_text'] ?: __( 'Continue with Google', 'babypasa-google-login' );

		wp_enqueue_style( 'bp-google-login' );

		ob_start();
		?>
		<a class="bp-google-login-btn" href="<?php echo esc_url( BP_Google_Login_Settings::start_url( $redirect_to ) ); ?>">
			<span class="bp-google-login-btn__icon" aria-hidden="true">
				<svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
					<path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
					<path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>
					<path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>
					<path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
				</svg>
			</span>
			<span class="bp-google-login-btn__label"><?php echo esc_html( $label ); ?></span>
		</a>
		<?php
		return ob_get_clean();
	}
}

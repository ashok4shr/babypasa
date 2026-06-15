<?php
/**
 * Settings page + settings accessor for BabyPasa Google Login.
 *
 * Stores everything in a single option array so it migrates with the site.
 * The admin screen also displays the exact Redirect URI to paste into the
 * Google Cloud Console, so there is no guesswork.
 *
 * @package BabyPasa_Google_Login
 */

defined( 'ABSPATH' ) || exit;

class BP_Google_Login_Settings {

	const OPTION = 'bp_google_login_settings';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/* ------------------------------------------------------------------
	 * Static accessors
	 * ------------------------------------------------------------------ */

	/**
	 * @return array{client_id:string,client_secret:string,enabled:bool,button_text:string,create_users:bool}
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, [] );
		return wp_parse_args( is_array( $saved ) ? $saved : [], [
			'client_id'     => '',
			'client_secret' => '',
			'enabled'       => false,
			'button_text'   => __( 'Continue with Google', 'babypasa-google-login' ),
			'create_users'  => true,
		] );
	}

	/** The exact redirect URI to register in Google Cloud Console. */
	public static function redirect_uri(): string {
		return home_url( '/bp-google-auth/callback/' );
	}

	/** The URL that starts the login (used by the button / shortcode). */
	public static function start_url( string $redirect_to = '' ): string {
		$url = home_url( '/bp-google-auth/' );
		if ( '' !== $redirect_to ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $url );
		}
		return $url;
	}

	public static function is_configured(): bool {
		$s = self::get();
		return $s['enabled'] && '' !== $s['client_id'] && '' !== $s['client_secret'];
	}

	/* ------------------------------------------------------------------
	 * Admin screen
	 * ------------------------------------------------------------------ */

	public function add_menu(): void {
		add_options_page(
			__( 'Google Login', 'babypasa-google-login' ),
			__( 'Google Login', 'babypasa-google-login' ),
			'manage_options',
			'bp-google-login',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting( 'bp_google_login_group', self::OPTION, [ $this, 'sanitize' ] );
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : [];
		return [
			'client_id'     => sanitize_text_field( $input['client_id'] ?? '' ),
			'client_secret' => sanitize_text_field( $input['client_secret'] ?? '' ),
			'enabled'       => ! empty( $input['enabled'] ),
			'button_text'   => sanitize_text_field( $input['button_text'] ?? __( 'Continue with Google', 'babypasa-google-login' ) ),
			'create_users'  => ! empty( $input['create_users'] ),
		];
	}

	public function render_page(): void {
		$s = self::get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BabyPasa Google Login', 'babypasa-google-login' ); ?></h1>

			<div class="notice notice-info inline" style="margin:1em 0;padding:12px;">
				<p style="margin:0 0 6px;"><strong><?php esc_html_e( 'Setup', 'babypasa-google-login' ); ?></strong></p>
				<ol style="margin:0 0 0 18px;">
					<li><?php esc_html_e( 'In Google Cloud Console → APIs &amp; Services → Credentials, create (or reuse) an OAuth 2.0 Client ID of type "Web application".', 'babypasa-google-login' ); ?></li>
					<li>
						<?php esc_html_e( 'Add this exact Authorized redirect URI:', 'babypasa-google-login' ); ?>
						<code style="user-select:all;"><?php echo esc_html( self::redirect_uri() ); ?></code>
					</li>
					<li><?php esc_html_e( 'Paste the Client ID and Client Secret below, tick "Enable", and Save.', 'babypasa-google-login' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: shortcode */
							esc_html__( 'Place the button anywhere with the shortcode %s (or render it in the My Account login form once you are ready to switch off Nextend).', 'babypasa-google-login' ),
							'[bp_google_login]'
						);
						?>
					</li>
				</ol>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'bp_google_login_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bpgl_enabled"><?php esc_html_e( 'Enable', 'babypasa-google-login' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="bpgl_enabled" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'] ); ?> />
								<?php esc_html_e( 'Activate Google login (the button does nothing until this is on).', 'babypasa-google-login' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bpgl_client_id"><?php esc_html_e( 'Client ID', 'babypasa-google-login' ); ?></label></th>
						<td><input type="text" id="bpgl_client_id" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[client_id]" value="<?php echo esc_attr( $s['client_id'] ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bpgl_client_secret"><?php esc_html_e( 'Client Secret', 'babypasa-google-login' ); ?></label></th>
						<td><input type="password" id="bpgl_client_secret" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[client_secret]" value="<?php echo esc_attr( $s['client_secret'] ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bpgl_button_text"><?php esc_html_e( 'Button text', 'babypasa-google-login' ); ?></label></th>
						<td><input type="text" id="bpgl_button_text" class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'New accounts', 'babypasa-google-login' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[create_users]" value="1" <?php checked( $s['create_users'] ); ?> />
								<?php esc_html_e( 'Create a customer account automatically when the Google email is new. If unticked, only existing users can sign in.', 'babypasa-google-login' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}

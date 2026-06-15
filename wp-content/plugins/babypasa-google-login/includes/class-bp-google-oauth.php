<?php
/**
 * Google OAuth 2.0 Authorization Code flow (full-page redirect).
 *
 * Flow:
 *   1. start()    — generate a CSRF `state`, stash it (+ redirect_to) in a short
 *                   transient, then redirect the WHOLE tab to Google.
 *   2. callback() — Google returns to /bp-google-auth/callback/ with ?code&?state.
 *                   Validate state, exchange the code for tokens server-to-server,
 *                   read the verified identity, log the user in, redirect.
 *
 * No popup, no window.opener, no window.close() — so none of the Nextend
 * popup-bridge "Continue..." failure modes can occur, in any browser/PWA context.
 *
 * @package BabyPasa_Google_Login
 */

defined( 'ABSPATH' ) || exit;

class BP_Google_OAuth {

	const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	const STATE_PREFIX   = 'bp_glogin_state_';
	const STATE_TTL      = 600; // 10 minutes.

	/* ------------------------------------------------------------------
	 * Step 1 — start the login
	 * ------------------------------------------------------------------ */

	public function start(): void {
		if ( ! BP_Google_Login_Settings::is_configured() ) {
			$this->fail( __( 'Google login is not configured.', 'babypasa-google-login' ) );
		}

		// Already logged in → just send them on.
		if ( is_user_logged_in() ) {
			wp_safe_redirect( $this->resolve_redirect( $this->requested_redirect() ) );
			exit;
		}

		$settings = BP_Google_Login_Settings::get();

		// CSRF state: random, single-use, server-side. Carries the post-login
		// destination so it survives the round-trip to Google without a cookie.
		$state = wp_generate_password( 32, false );
		set_transient( self::STATE_PREFIX . $state, [
			'redirect_to' => $this->requested_redirect(),
		], self::STATE_TTL );

		$auth_url = add_query_arg( [
			'client_id'     => rawurlencode( $settings['client_id'] ),
			'redirect_uri'  => rawurlencode( BP_Google_Login_Settings::redirect_uri() ),
			'response_type' => 'code',
			'scope'         => rawurlencode( 'openid email profile' ),
			'state'         => rawurlencode( $state ),
			'prompt'        => 'select_account',
			// access_type=online: we only need to identify the user, not call
			// Google APIs later, so we don't request a refresh token.
			'access_type'   => 'online',
		], self::AUTH_ENDPOINT );

		// Full-page redirect — wp_redirect (not wp_safe_redirect) because the host is Google.
		wp_redirect( $auth_url );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Step 2 — handle Google's callback
	 * ------------------------------------------------------------------ */

	public function callback(): void {
		// Google reports user-side errors (e.g. access_denied) via ?error.
		if ( isset( $_GET['error'] ) ) {
			$this->fail( __( 'Google sign-in was cancelled or denied.', 'babypasa-google-login' ) );
		}

		$code  = isset( $_GET['code'] )  ? sanitize_text_field( wp_unslash( $_GET['code'] ) )  : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( '' === $code || '' === $state ) {
			$this->fail( __( 'Invalid response from Google.', 'babypasa-google-login' ) );
		}

		// Validate + consume the single-use state.
		$stored = get_transient( self::STATE_PREFIX . $state );
		if ( false === $stored ) {
			$this->fail( __( 'Your sign-in session expired. Please try again.', 'babypasa-google-login' ) );
		}
		delete_transient( self::STATE_PREFIX . $state );
		$redirect_to = is_array( $stored ) ? ( $stored['redirect_to'] ?? '' ) : '';

		$identity = $this->exchange_code_for_identity( $code );
		if ( is_wp_error( $identity ) ) {
			$this->fail( $identity->get_error_message() );
		}

		$user_id = $this->resolve_user( $identity );
		if ( is_wp_error( $user_id ) ) {
			$this->fail( $user_id->get_error_message() );
		}

		// Log in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		$user = get_user_by( 'id', $user_id );
		do_action( 'wp_login', $user->user_login, $user );

		wp_safe_redirect( $this->resolve_redirect( $redirect_to ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Token exchange + identity validation
	 * ------------------------------------------------------------------ */

	/**
	 * Exchange the authorization code for an ID token and return the verified
	 * identity claims.
	 *
	 * Signature verification is intentionally skipped: the id_token is delivered
	 * directly from Google's token endpoint over an authenticated HTTPS channel
	 * (server-to-server with our client secret), never via the browser — Google's
	 * own docs state it can be trusted without re-verifying the signature in this
	 * case. We still validate the security-relevant claims (aud/iss/exp/verified).
	 *
	 * @param  string $code
	 * @return array|WP_Error  [ 'email', 'name', 'first', 'last', 'sub' ]
	 */
	private function exchange_code_for_identity( string $code ) {
		$settings = BP_Google_Login_Settings::get();

		$response = wp_remote_post( self::TOKEN_ENDPOINT, [
			'timeout' => 15,
			'body'    => [
				'code'          => $code,
				'client_id'     => $settings['client_id'],
				'client_secret' => $settings['client_secret'],
				'redirect_uri'  => BP_Google_Login_Settings::redirect_uri(),
				'grant_type'    => 'authorization_code',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'bp_glogin_http', __( 'Could not reach Google. Please try again.', 'babypasa-google-login' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['id_token'] ) ) {
			return new WP_Error( 'bp_glogin_token', __( 'Google did not return a valid token.', 'babypasa-google-login' ) );
		}

		$claims = $this->decode_jwt_payload( $body['id_token'] );
		if ( ! is_array( $claims ) ) {
			return new WP_Error( 'bp_glogin_jwt', __( 'Could not read the Google identity token.', 'babypasa-google-login' ) );
		}

		// --- Claim validation -------------------------------------------------
		$aud = $claims['aud'] ?? '';
		if ( ! hash_equals( (string) $settings['client_id'], (string) $aud ) ) {
			return new WP_Error( 'bp_glogin_aud', __( 'Token audience mismatch.', 'babypasa-google-login' ) );
		}

		$iss = $claims['iss'] ?? '';
		if ( 'accounts.google.com' !== $iss && 'https://accounts.google.com' !== $iss ) {
			return new WP_Error( 'bp_glogin_iss', __( 'Token issuer mismatch.', 'babypasa-google-login' ) );
		}

		if ( isset( $claims['exp'] ) && (int) $claims['exp'] < time() ) {
			return new WP_Error( 'bp_glogin_exp', __( 'Google token has expired. Please try again.', 'babypasa-google-login' ) );
		}

		$email          = isset( $claims['email'] ) ? sanitize_email( $claims['email'] ) : '';
		$email_verified = ! empty( $claims['email_verified'] )
			&& ( true === $claims['email_verified'] || 'true' === $claims['email_verified'] );

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'bp_glogin_email', __( 'Google did not provide an email address.', 'babypasa-google-login' ) );
		}
		if ( ! $email_verified ) {
			// Linking by email is only safe when Google has verified ownership.
			return new WP_Error( 'bp_glogin_unverified', __( 'Your Google email is not verified.', 'babypasa-google-login' ) );
		}

		return [
			'email' => $email,
			'name'  => isset( $claims['name'] ) ? sanitize_text_field( $claims['name'] ) : '',
			'first' => isset( $claims['given_name'] ) ? sanitize_text_field( $claims['given_name'] ) : '',
			'last'  => isset( $claims['family_name'] ) ? sanitize_text_field( $claims['family_name'] ) : '',
			'sub'   => isset( $claims['sub'] ) ? sanitize_text_field( $claims['sub'] ) : '',
		];
	}

	/**
	 * Decode the (middle) payload segment of a JWT. No signature check — see the
	 * note on exchange_code_for_identity() for why that is safe here.
	 *
	 * @param  string $jwt
	 * @return array|null
	 */
	private function decode_jwt_payload( string $jwt ): ?array {
		$parts = explode( '.', $jwt );
		if ( count( $parts ) < 2 ) {
			return null;
		}
		$payload = strtr( $parts[1], '-_', '+/' );
		$decoded = base64_decode( $payload, true );
		if ( false === $decoded ) {
			return null;
		}
		$data = json_decode( $decoded, true );
		return is_array( $data ) ? $data : null;
	}

	/* ------------------------------------------------------------------
	 * User resolution (match by verified email, else create)
	 * ------------------------------------------------------------------ */

	/**
	 * @param  array $identity
	 * @return int|WP_Error  WP user ID.
	 */
	private function resolve_user( array $identity ) {
		$existing = get_user_by( 'email', $identity['email'] );

		if ( $existing ) {
			// Harden future logins: remember the Google account id once.
			if ( $identity['sub'] && ! get_user_meta( $existing->ID, '_bp_google_sub', true ) ) {
				update_user_meta( $existing->ID, '_bp_google_sub', $identity['sub'] );
			}
			return $existing->ID;
		}

		$settings = BP_Google_Login_Settings::get();
		if ( empty( $settings['create_users'] ) ) {
			return new WP_Error( 'bp_glogin_no_account', __( 'No account exists for this Google email.', 'babypasa-google-login' ) );
		}

		$username = $this->unique_username_from_email( $identity['email'] );
		$user_id  = wp_insert_user( [
			'user_login'   => $username,
			'user_email'   => $identity['email'],
			'user_pass'    => wp_generate_password( 24, true ),
			'display_name' => $identity['name'] ?: $username,
			'first_name'   => $identity['first'],
			'last_name'    => $identity['last'],
			'role'         => 'customer', // WooCommerce role; falls back gracefully if WC absent.
		] );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		if ( $identity['sub'] ) {
			update_user_meta( $user_id, '_bp_google_sub', $identity['sub'] );
		}

		// Fire WooCommerce/WordPress new-customer hooks for email + integrations.
		if ( function_exists( 'wc_get_customer_default_location' ) ) {
			do_action( 'woocommerce_created_customer', $user_id, [], false );
		}

		return $user_id;
	}

	private function unique_username_from_email( string $email ): string {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'user';
		}
		$username = $base;
		$i        = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $i;
			$i++;
		}
		return $username;
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/** The redirect_to requested when the login started (sanitised). */
	private function requested_redirect(): string {
		return isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
	}

	/**
	 * Resolve the final destination: honour an on-site redirect_to, else My Account.
	 *
	 * @param  string $redirect_to
	 * @return string
	 */
	private function resolve_redirect( string $redirect_to ): string {
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );

		if ( '' === $redirect_to ) {
			return $account ?: home_url( '/' );
		}

		// Keep on-site only (wp_validate_redirect rejects off-host targets).
		$safe = wp_validate_redirect( $redirect_to, $account ?: home_url( '/' ) );
		return $safe;
	}

	/**
	 * Abort: queue a WooCommerce notice (if available) and return to My Account.
	 *
	 * @param string $message
	 */
	private function fail( string $message ): void {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'error' );
		}
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		wp_safe_redirect( $account ?: home_url( '/' ) );
		exit;
	}
}

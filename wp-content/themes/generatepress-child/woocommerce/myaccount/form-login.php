<?php
/**
 * Login / Register Form — BabyPasa Custom Auth Card
 *
 * A modern, card-based authentication interface shown only to logged-out
 * visitors. Supports an in-card login ↔ register toggle (Option A) driven
 * by a minimal inline script — no full-page navigation required.
 *
 * Google OAuth is rendered above the credential fields via the Nextend Social
 * Login shortcode: [nextend_social_login provider="google"].
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package BabyPasa\MyAccount
 * @version 9.9.0 (WooCommerce base)
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_customer_login_form' );

// Show register panel by default when a registration error occurred.
$show_register = ! empty( $_POST['register'] ) || ! empty( $_GET['register'] );
$reg_enabled   = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
?>

<div class="bp-auth-card" id="bp-auth-card" role="main">

	<!-- ══════════════════════════════════════════════════════════════
	     LOGIN PANEL
	     ══════════════════════════════════════════════════════════════ -->
	<div class="bp-auth-panel bp-auth-panel--login<?php echo ( $reg_enabled && $show_register ) ? ' bp-auth-panel--hidden' : ''; ?>"
	     id="bp-panel-login"
	     aria-labelledby="bp-login-heading">

		<h2 class="bp-auth-heading" id="bp-login-heading">
			<?php esc_html_e( 'Welcome Back', 'generatepress-child' ); ?>
		</h2>

		<!-- Google OAuth button -->
		<div class="bp-auth-social" aria-label="<?php esc_attr_e( 'Social login options', 'generatepress-child' ); ?>">
			<?php echo do_shortcode( '[bp_google_login]' ); ?>
		</div>

		<div class="bp-auth-divider" role="separator" aria-hidden="true">
			<span><?php esc_html_e( 'or', 'generatepress-child' ); ?></span>
		</div>

		<form class="woocommerce-form woocommerce-form-login login"
		      method="post"
		      novalidate
		      aria-label="<?php esc_attr_e( 'Login form', 'generatepress-child' ); ?>">

			<?php do_action( 'woocommerce_login_form_start' ); ?>

			<!-- WooCommerce error notices -->
			<?php wc_print_notices(); ?>

			<div class="bp-form-field">
				<label for="username">
					<?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>
					<span class="required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span>
				</label>
				<input
					type="text"
					class="woocommerce-Input woocommerce-Input--text input-text"
					name="username"
					id="username"
					autocomplete="username"
					value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized */ ?>"
					required
					aria-required="true"
				/>
			</div>

			<div class="bp-form-field">
				<label for="password">
					<?php esc_html_e( 'Password', 'woocommerce' ); ?>
					<span class="required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span>
				</label>
				<input
					type="password"
					class="woocommerce-Input woocommerce-Input--text input-text"
					name="password"
					id="password"
					autocomplete="current-password"
					required
					aria-required="true"
				/>
			</div>

			<?php do_action( 'woocommerce_login_form' ); ?>

			<div class="bp-form-row-inline">
				<label class="bp-checkbox-label" for="rememberme">
					<input
						class="bp-checkbox-input"
						name="rememberme"
						type="checkbox"
						id="rememberme"
						value="forever"
					/>
					<span class="bp-checkbox-box" aria-hidden="true"></span>
					<span class="bp-checkbox-text"><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
				</label>
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="bp-forgot-link">
					<?php esc_html_e( 'Forgot password?', 'woocommerce' ); ?>
				</a>
			</div>

			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>

			<button
				type="submit"
				class="bp-btn bp-btn--primary"
				name="login"
				value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>">
				<?php esc_html_e( 'Log in', 'woocommerce' ); ?>
			</button>

			<?php do_action( 'woocommerce_login_form_end' ); ?>

		</form>

		<?php if ( $reg_enabled ) : ?>
		<p class="bp-auth-switch">
			<?php esc_html_e( "Don't have an account?", 'generatepress-child' ); ?>
			<button
				type="button"
				class="bp-auth-toggle"
				data-show="bp-panel-register"
				data-hide="bp-panel-login"
				aria-controls="bp-panel-register">
				<?php esc_html_e( 'Register', 'generatepress-child' ); ?>
			</button>
		</p>
		<?php endif; ?>

	</div><!-- .bp-auth-panel--login -->

	<?php if ( $reg_enabled ) : ?>
	<!-- ══════════════════════════════════════════════════════════════
	     REGISTER PANEL
	     ══════════════════════════════════════════════════════════════ -->
	<div class="bp-auth-panel bp-auth-panel--register<?php echo $show_register ? '' : ' bp-auth-panel--hidden'; ?>"
	     id="bp-panel-register"
	     aria-labelledby="bp-register-heading">

		<h2 class="bp-auth-heading" id="bp-register-heading">
			<?php esc_html_e( 'Create Account', 'generatepress-child' ); ?>
		</h2>

		<!-- Google OAuth button -->
		<div class="bp-auth-social" aria-label="<?php esc_attr_e( 'Social signup options', 'generatepress-child' ); ?>">
			<?php echo do_shortcode( '[bp_google_login]' ); ?>
		</div>

		<div class="bp-auth-divider" role="separator" aria-hidden="true">
			<span><?php esc_html_e( 'or', 'generatepress-child' ); ?></span>
		</div>

		<form method="post"
		      class="woocommerce-form woocommerce-form-register register"
		      aria-label="<?php esc_attr_e( 'Register form', 'generatepress-child' ); ?>"
		      <?php do_action( 'woocommerce_register_form_tag' ); ?>>

			<?php do_action( 'woocommerce_register_form_start' ); ?>

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
			<div class="bp-form-field">
				<label for="reg_username">
					<?php esc_html_e( 'Username', 'woocommerce' ); ?>
					<span class="required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span>
				</label>
				<input
					type="text"
					class="woocommerce-Input woocommerce-Input--text input-text"
					name="username"
					id="reg_username"
					autocomplete="username"
					value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized */ ?>"
					required
					aria-required="true"
				/>
			</div>
			<?php endif; ?>

			<div class="bp-form-field">
				<label for="reg_email">
					<?php esc_html_e( 'Email address', 'woocommerce' ); ?>
					<span class="required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span>
				</label>
				<input
					type="email"
					class="woocommerce-Input woocommerce-Input--text input-text"
					name="email"
					id="reg_email"
					autocomplete="email"
					value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; /* phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized */ ?>"
					required
					aria-required="true"
				/>
			</div>

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
			<div class="bp-form-field">
				<label for="reg_password">
					<?php esc_html_e( 'Password', 'woocommerce' ); ?>
					<span class="required" aria-hidden="true">*</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span>
				</label>
				<input
					type="password"
					class="woocommerce-Input woocommerce-Input--text input-text"
					name="password"
					id="reg_password"
					autocomplete="new-password"
					required
					aria-required="true"
				/>
			</div>
			<?php else : ?>
			<p class="bp-auto-password-note">
				<?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?>
			</p>
			<?php endif; ?>

			<?php do_action( 'woocommerce_register_form' ); ?>

			<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>

			<button
				type="submit"
				class="bp-btn bp-btn--primary"
				name="register"
				value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">
				<?php esc_html_e( 'Create Account', 'generatepress-child' ); ?>
			</button>

			<?php do_action( 'woocommerce_register_form_end' ); ?>

		</form>

		<p class="bp-auth-switch">
			<?php esc_html_e( 'Already have an account?', 'generatepress-child' ); ?>
			<button
				type="button"
				class="bp-auth-toggle"
				data-show="bp-panel-login"
				data-hide="bp-panel-register"
				aria-controls="bp-panel-login">
				<?php esc_html_e( 'Log in', 'generatepress-child' ); ?>
			</button>
		</p>

	</div><!-- .bp-auth-panel--register -->
	<?php endif; ?>

</div><!-- #bp-auth-card -->

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>

<?php if ( $reg_enabled ) : ?>
<script>
( function () {
	'use strict';

	/**
	 * Minimal login ↔ register panel toggle.
	 * Uses data-show / data-hide attributes on .bp-auth-toggle buttons.
	 * No jQuery dependency.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		var card = document.getElementById( 'bp-auth-card' );
		if ( ! card ) return;

		card.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.bp-auth-toggle' );
			if ( ! btn ) return;

			var showId = btn.getAttribute( 'data-show' );
			var hideId = btn.getAttribute( 'data-hide' );
			var showEl = document.getElementById( showId );
			var hideEl = document.getElementById( hideId );

			if ( hideEl ) {
				hideEl.classList.add( 'bp-auth-panel--hidden' );
				hideEl.setAttribute( 'aria-hidden', 'true' );
			}
			if ( showEl ) {
				showEl.classList.remove( 'bp-auth-panel--hidden' );
				showEl.removeAttribute( 'aria-hidden' );
				// Focus the first focusable element in the revealed panel.
				var first = showEl.querySelector( 'input, button, a' );
				if ( first ) first.focus();
			}
		} );
	} );
}() );
</script>
<?php endif; ?>

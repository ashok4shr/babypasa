<?php
/**
 * Customer new account email — BabyPasa client design E01 (Account Created).
 *
 * Overrides woocommerce/templates/emails/customer-new-account.php.
 * Hero, logo, feature strip, support line and footer are rendered by the
 * shared email-header.php / email-footer.php overrides; this file outputs
 * only the body copy, account credentials and CTA per client design E01.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

defined( 'ABSPATH' ) || exit;

// CLIENT TEMPLATE: E01 hero heading (overrides admin heading setting per client design).
$bp_user  = get_user_by( 'login', $user_login );
$bp_first = $bp_user ? ( $bp_user->first_name ? $bp_user->first_name : $bp_user->display_name ) : '';
if ( $bp_first ) {
	$email_heading = sprintf( 'Welcome, %s!', $bp_first );
}

/**
 * Fires to output the email header.
 *
 * @hooked WC_Emails::email_header()
 *
 * @since 3.7.0
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<?php // CLIENT PLACEHOLDER: E01 body copy ("We handpick every product…") — copied verbatim from client HTML, .body-pad wrapper stripped (header already opened the body cell). ?>
<p class="body-copy" style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;">
	We handpick every product with your baby's safety and comfort in mind. From diapers and wipes to formula milk and baby care &mdash; it's all here, trusted by parents across Nepal.
</p>

<?php // CLIENT PLACEHOLDER: stock "Your username is …" line converted to client body-copy style ({{username}} → $user_login, site title → $blogname, My account link → wc_get_page_permalink). ?>
<p class="body-copy" style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;">
	<?php
	printf(
		/* translators: %1$s: Site title, %2$s: Username */
		esc_html__( 'Thanks for creating an account on %1$s. Your username is %2$s.', 'woocommerce' ),
		esc_html( $blogname ),
		'<strong>' . esc_html( $user_login ) . '</strong>'
	); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<?php esc_html_e( 'You can access your account area to view orders, change your password, and more at:', 'woocommerce' ); ?>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" style="color:#ec4899;font-weight:700;text-decoration:none;"><?php esc_html_e( 'My account', 'woocommerce' ); ?></a>
</p>

<?php if ( $password_generated && $set_password_url ) : ?>
	<?php // CLIENT PLACEHOLDER: stock set-password logic preserved — when WooCommerce generated the password, "Set Your Password" becomes the primary E01 CTA and "Start Shopping" demotes to a secondary text link. ?>
	<p class="body-copy" style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;">
		<?php esc_html_e( 'To get started, set a password for your account using the button below.', 'woocommerce' ); ?>
	</p>

	<!-- CTA Button: Set Your Password (client E01 CTA markup, href → $set_password_url) -->
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin-bottom:16px;">
		<tr>
			<td align="center">
				<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
					<tr>
						<td style="background:#ec4899;border-radius:6px;text-align:center;">
							<a href="<?php echo esc_url( $set_password_url ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
								<?php esc_html_e( 'Set Your Password', 'woocommerce' ); ?>
							</a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<?php // CLIENT PLACEHOLDER: "Start Shopping" CTA (client href https://babypasa.com → home_url('/')) rendered as secondary link so the set-password action stays primary. ?>
	<p style="margin:0 0 28px;font-family:Arial,Helvetica,sans-serif;font-size:14px;text-align:center;">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" style="color:#ec4899;font-weight:700;text-decoration:none;">Start Shopping &rarr;</a>
	</p>

<?php else : ?>

	<?php // CLIENT PLACEHOLDER: primary "Start Shopping" CTA — exact client E01 CTA table markup, hardcoded https://babypasa.com → home_url('/'). ?>
	<!-- CTA Button: Start Shopping -->
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin-bottom:28px;">
		<tr>
			<td align="center">
				<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
					<tr>
						<td style="background:#ec4899;border-radius:6px;text-align:center;">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
								Start Shopping
							</a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

<?php endif; ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo '<div style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;line-height:1.7;">';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo '</div>';
}

/**
 * Fires to output the email footer.
 *
 * @hooked WC_Emails::email_footer()
 *
 * @since 3.7.0
 */
do_action( 'woocommerce_email_footer', $email );

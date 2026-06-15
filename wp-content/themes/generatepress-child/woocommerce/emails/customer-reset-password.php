<?php
/**
 * Customer Reset Password email — BabyPasa client design (E02 Password Reset).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-reset-password.php.
 *
 * Hero (lock icon + heading + "We received a request…" subline) is rendered
 * by emails/email-header.php; support line, feature strip and footer band are
 * rendered by emails/email-footer.php. This template adds the E02 content box
 * (copy + CTA + raw-URL fallback) and the "Didn't request this?" security banner.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// CLIENT TEMPLATE: E02 hero heading (overrides admin heading setting per client design).
$email_heading = 'Reset your password';

/*
 * CLIENT PLACEHOLDER: {{password_reset_link}} → stock WooCommerce reset URL:
 * add_query_arg( key/id/login, wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ).
 */
$bp_reset_url = esc_url( add_query_arg( array( 'key' => $reset_key, 'id' => $user_id, 'login' => rawurlencode( $user_login ) ), wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E02 — content box: copy + CTA + fallback URL -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;">
	<tr>
		<td class="content-pad" style="padding:24px;">

			<!-- Body copy -->
			<p style="margin:0 0 22px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.8;">
				Click the button below to choose a new password.
				This link is only valid for
				<strong>24 hours</strong> &mdash; after that you&rsquo;ll
				need to request a new one.
			</p>

			<!-- CTA Button -->
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin-bottom:22px;">
				<tr>
					<td align="center">
						<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="background:#ec4899;border-radius:6px;text-align:center;">
									<a href="<?php echo $bp_reset_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via esc_url() above. ?>" target="_blank" style="display:inline-block;padding:14px 48px 15px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
										Reset My Password
									</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>

			<!-- Fallback URL -->
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-top:1px solid #ebebeb;">
				<tr>
					<td style="padding-top:18px;">
						<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
							Button not working? Copy and paste this
							link into your browser:
						</p>
						<p style="margin:0;">
							<a class="reset-url" href="<?php echo $bp_reset_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via esc_url() above. ?>" target="_blank" style="color:#ec4899;word-break:break-all;font-size:11px;font-family:'Courier New',Courier,monospace;text-decoration:none;">
								<?php
								// CLIENT PLACEHOLDER: {{password_reset_link}} (raw-text fallback) → same $bp_reset_url printed as link text.
								echo esc_html( $bp_reset_url );
								?>
							</a>
						</p>
					</td>
				</tr>
			</table>

		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E02 — security banner (calm pink tone, not red/alarming) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin-top:14px;background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;">
	<tr>
		<td class="banner-icon-td" style="padding:14px 0 14px 16px;vertical-align:middle;width:46px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:32px;height:32px;background:#ec4899;border-radius:16px;text-align:center;vertical-align:middle;">
						<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/shield-check.png' ); ?>" width="16" height="16" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
					</td>
				</tr>
			</table>
		</td>
		<td style="padding:14px 16px 14px 12px;vertical-align:middle;">
			<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
				Didn&rsquo;t request this?
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.6;">
				You can safely ignore this email. Your account
				password will not change unless you click the
				button above.
			</p>
		</td>
	</tr>
</table>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin-top:20px;"><tr><td class="email-additional-content" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo '</td></tr></table>';
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );

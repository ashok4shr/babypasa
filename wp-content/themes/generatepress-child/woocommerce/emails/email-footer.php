<?php
/**
 * Email Footer — BabyPasa client design (2026 migration).
 *
 * Closes the body cell opened in email-header.php, then renders the
 * client footer: support line, pink feature strip (Delivery · Gift ·
 * Hassle-free), pink social footer band, and the copyright line.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

defined( 'ABSPATH' ) || exit;

$email = $email ?? null;

?>
						</div>
					</td>
				</tr>
				<!-- /BODY -->

				<!-- SUPPORT LINE -->
				<tr>
					<td style="background:#ffffff;padding:0 36px 24px;">
						<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
							<tr>
								<td style="border-top:1px solid #fce7f3;padding-top:18px;">
									<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">
										Questions?&nbsp;
										<a href="mailto:support@babypasa.com" style="color:#ec4899;font-weight:700;text-decoration:none;">
											support@babypasa.com
										</a>
									</p>
								</td>
							</tr>
						</table>
					</td>
				</tr>

				<?php
				// Feature strip — for the welcome email (E01) it is rendered after
				// the hero by email-header.php instead (client design).
				if ( ! is_object( $email ) || 'customer_new_account' !== ( $email->id ?? '' ) ) {
					wc_get_template( 'emails/bp-feature-strip.php' );
				}
				?>
				<!-- FOOTER BAND -->
				<tr>
					<td class="footer-pad" align="center" style="background:#fce7f3;padding:20px 32px 24px;">

						<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.6px;">
							Follow us for offers, tips &amp; more
						</p>

						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 12px;">
							<tr>
								<td class="social-td" style="padding:0 12px;">
									<a href="https://facebook.com/babypasanepal" target="_blank" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#be185d;text-decoration:none;white-space:nowrap;">
										&#x1F4D8;&nbsp;Facebook &middot; @babypasanepal
									</a>
								</td>
								<td class="social-td" style="padding:0 12px;">
									<a href="https://instagram.com/babypasanepal" target="_blank" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#be185d;text-decoration:none;white-space:nowrap;">
										&#x1F4F8;&nbsp;Instagram &middot; @babypasanepal
									</a>
								</td>
							</tr>
						</table>

						<a href="https://blog.babypasa.com.np" target="_blank" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;text-decoration:none;">
							&#x1F4D6;&nbsp;Read our blog for parenting tips &amp; more
						</a>

						<?php
						// Preserve the admin-configurable WooCommerce footer text (Settings > Emails).
						$email_footer_text = get_option( 'woocommerce_email_footer_text' );
						if ( apply_filters( 'woocommerce_is_email_preview', false ) ) {
							$text_transient    = get_transient( 'woocommerce_email_footer_text' );
							$email_footer_text = false !== $text_transient ? $text_transient : $email_footer_text;
						}
						$email_footer_text = apply_filters( 'woocommerce_email_footer_text', $email_footer_text, $email );
						if ( $email_footer_text ) :
							?>
						<div style="margin-top:14px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;">
							<?php echo wp_kses_post( wpautop( wptexturize( $email_footer_text ) ) ); ?>
						</div>
						<?php endif; ?>

					</td>
				</tr>

			</table>
			<!-- /EMAIL CARD -->

			<p style="margin:14px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#9ca3af;text-align:center;">
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> BabyPasa.Com &mdash; Kathmandu, Nepal
			</p>

		</td>
	</tr>
</table>

</body>
</html>

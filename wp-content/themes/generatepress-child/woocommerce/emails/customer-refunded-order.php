<?php
/**
 * Customer refunded order email — BabyPasa client design (E21 Refund Processed).
 *
 * Fires via WC_Email_Customer_Refunded_Order on
 * woocommerce_order_fully_refunded / woocommerce_order_partially_refunded.
 * The same class/template serves both full and partial refunds ($partial_refund).
 *
 * Hero (check-in-circle icon + "Your refund is on its way!" heading +
 * "We've processed your refund for order #X…" subline) is rendered by
 * emails/email-header.php. This template adds the refund details block
 * (amount = total refunded so far, method/note from the shared gateway
 * helpers), the warm thank-you card, and the "Shop Again" CTA.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Gateway-aware refund helpers (bp_email_refund_label / bp_email_refund_note).
require_once get_stylesheet_directory() . '/woocommerce/emails/bp-email-helpers.php';

$partial_refund = $partial_refund ?? false;

// CLIENT TEMPLATE: E21 hero heading (overrides admin heading setting per client design).
$email_heading = 'Your refund is on its way!';

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

// CLIENT PLACEHOLDER: Rs. {{refund_amount}} → amount refunded so far on this order.
// In the WC refunded-order template the individual refund object is not passed;
// $order->get_total_refunded() is the accurate amount the customer has been refunded
// (equals the refund for a single refund, cumulative for repeated partials).
$bp_refund_amount = (float) $order->get_total_refunded();
$bp_refund_note   = bp_email_refund_note( $order );
?>

<!-- CLIENT TEMPLATE: E21 — refund details -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 14px;">
	<tr>
		<td style="padding:20px;">

			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">

				<!-- Amount row — prominent (18px, largest number in the email) -->
				<tr>
					<td style="padding:8px 0;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;vertical-align:middle;">
						Refund amount
					</td>
					<td style="padding:8px 0;text-align:right;border-bottom:1px solid #fbcfe8;vertical-align:middle;">
						<span class="refund-amount" style="font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#9d174d;">
							<?php echo wp_kses_post( wc_price( $bp_refund_amount, array( 'currency' => $order->get_currency() ) ) ); ?>
						</span>
					</td>
				</tr>

				<!-- Method row -->
				<tr>
					<td style="padding:8px 0;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
						Refund method
					</td>
					<td style="padding:8px 0;text-align:right;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#374151;">
						<?php
						// CLIENT PLACEHOLDER: {{refund_method}} → bp_email_refund_label().
						echo esc_html( bp_email_refund_label( $order ) );
						?>
					</td>
				</tr>

				<!-- Timeline row -->
				<tr>
					<td style="padding:8px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
						Appears in your account within
					</td>
					<td style="padding:8px 0;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#374151;white-space:nowrap;">
						3&ndash;5 business days
					</td>
				</tr>

			</table>

			<?php if ( '' !== $bp_refund_note ) : ?>
				<p style="margin:14px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;line-height:1.6;border-top:1px solid #fbcfe8;padding-top:12px;">
					<?php
					// CLIENT PLACEHOLDER: {{refund_note}} → bp_email_refund_note() (rendered only when non-empty).
					echo esc_html( $bp_refund_note );
					?>
				</p>
			<?php endif; ?>

		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E21 — thank-you card (warm close) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;margin:0 0 16px;">
	<tr>
		<td class="thankyou-icon-td" style="padding:16px 0 16px 16px;vertical-align:middle;width:52px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
						<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/heart.png' ); ?>" width="18" height="18" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
					</td>
				</tr>
			</table>
		</td>
		<td style="padding:16px 16px 16px 12px;vertical-align:middle;">
			<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				Thank you for your patience
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;line-height:1.6;">
				We&rsquo;re sorry this order didn&rsquo;t work out. We hope to see you again &mdash; your little one&rsquo;s essentials are always waiting for you at BabyPasa.
			</p>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E21 — Shop Again CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 4px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Shop Again
						</a>
					</td>
				</tr>
			</table>
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

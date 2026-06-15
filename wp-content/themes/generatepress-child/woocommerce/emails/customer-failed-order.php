<?php
/**
 * Customer failed order email — BabyPasa client design (E04 Payment Failed).
 *
 * Fires via WC_Email_Customer_Failed_Order on woocommerce_order_status_failed.
 *
 * Hero (payment-card icon + "Your payment didn't go through" heading +
 * "Don't worry, {first}…" subline) is rendered by emails/email-header.php.
 * The order summary card / address tiles come from the overridden order
 * partials via the standard hooks. This template adds the "Retry Payment"
 * CTA (→ the order's pay-for-order URL) and the gateway-specific payment
 * tips box (bp_email_payment_tips()).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Gateway-aware payment-tips helper (bp_email_payment_tips).
require_once get_stylesheet_directory() . '/woocommerce/emails/bp-email-helpers.php';

// CLIENT TEMPLATE: E04 hero heading (overrides admin heading setting per client design).
$email_heading = "Your payment didn't go through";

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

/*
 * @hooked WC_Emails::order_details() Shows the order details table (client order summary card).
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details (client address tiles).
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
?>

<!-- CLIENT TEMPLATE: E04 — Retry Payment CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Retry Payment
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php
// CLIENT TEMPLATE: E04 — dynamic, gateway-specific payment-tips box.
// CLIENT PLACEHOLDER: {{tips_html}} → bp_email_payment_tips( $order ) (keyed off the order's payment gateway).
echo wp_kses_post( bp_email_payment_tips( $order ) );

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

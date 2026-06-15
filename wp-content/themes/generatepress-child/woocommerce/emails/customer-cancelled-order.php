<?php
/**
 * Customer cancelled order email — BabyPasa client design (E15 Order Cancelled).
 *
 * Hero (X icon + heading + "Order #X has been cancelled…" subline) is rendered
 * by emails/email-header.php; the order summary card and address tiles are
 * rendered by the overridden email-order-details.php / email-addresses.php
 * partials via the standard WooCommerce hooks below. This template adds the
 * client's slim "Cancelled" status bar, the conditional refund details block
 * (paid orders only), the "Shop Again" CTA and the "Contact Support" link.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Gateway-aware refund helpers (bp_email_refund_label / bp_email_refund_note),
// shared with E21 refunded + the E17/E20 return-flow templates.
require_once get_stylesheet_directory() . '/woocommerce/emails/bp-email-helpers.php';

// CLIENT TEMPLATE: E15 hero heading (overrides admin heading setting per client design).
$email_heading = 'Your order has been cancelled';

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E15 — slim "Cancelled" status bar (adapted from the client's
     cancelled-table header row). The client's lean item table is superseded by the
     shared order summary card below, keeping the woocommerce_email_order_details
     hook as the single item renderer — no duplication. -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 20px;">
	<tr>
		<td style="background:#f3f4f6;padding:10px 16px;border-radius:8px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
						Cancelled order
					</td>
					<td align="right">
						<!-- Only red element in the entire email system — maximum contrast per client design. -->
						<span style="background:#fee2e2;color:#991b1b;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;padding:4px 12px;border-radius:4px;">
							Cancelled
						</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php
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

// CLIENT TEMPLATE: E15 — refund details block. Conditional: only paid orders get a refund.
if ( $order->is_paid() ) :
	$bp_refund_note = bp_email_refund_note( $order );
	?>
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 24px;">
		<tr>
			<td style="padding:16px;">

				<!-- Refund block header -->
				<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:12px;">
					<tr>
						<td class="refund-icon-td" style="vertical-align:middle;padding-right:8px;">
							<table border="0" cellpadding="0" cellspacing="0" role="presentation">
								<tr>
									<td style="width:28px;height:28px;background:#ec4899;border-radius:6px;text-align:center;vertical-align:middle;">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
											<path d="M1 4v6h6" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M3.51 15a9 9 0 102.13-9.36L1 10" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</td>
								</tr>
							</table>
						</td>
						<td style="vertical-align:middle;">
							<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#9d174d;">
								Refund details
							</p>
						</td>
					</tr>
				</table>

				<!-- Refund rows -->
				<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
					<tr>
						<td style="padding:6px 0;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
							Refund amount
						</td>
						<td style="padding:6px 0;text-align:right;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#9d174d;white-space:nowrap;">
							<?php
							// CLIENT PLACEHOLDER: Rs. {{order_total}} → wc_price().
							echo wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
							?>
						</td>
					</tr>
					<tr>
						<td style="padding:6px 0;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
							Refund method
						</td>
						<td style="padding:6px 0;text-align:right;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#374151;">
							<?php
							// CLIENT PLACEHOLDER: {{refund_method}} → bp_email_refund_label().
							echo esc_html( bp_email_refund_label( $order ) );
							?>
						</td>
					</tr>
					<tr>
						<td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
							Expected within
						</td>
						<td style="padding:6px 0;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#374151;white-space:nowrap;">
							3&ndash;5 business days
						</td>
					</tr>
				</table>

				<?php if ( '' !== $bp_refund_note ) : ?>
					<p style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;line-height:1.6;">
						<?php echo esc_html( $bp_refund_note ); ?>
					</p>
				<?php endif; ?>

			</td>
		</tr>
	</table>
<?php endif; ?>

<!-- CLIENT TEMPLATE: E15 — CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 12px;">
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

<p style="margin:0 0 4px;text-align:center;">
	<a href="mailto:support@babypasa.com" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ec4899;text-decoration:none;">
		Contact Support
	</a>
</p>

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

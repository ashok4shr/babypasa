<?php
/**
 * Customer processing order email — BabyPasa client design (E03 Order Confirmation).
 *
 * Hero (icon + heading + subline) is rendered by emails/email-header.php;
 * the order summary card and address tiles are rendered by the overridden
 * email-order-details.php / email-addresses.php partials via the standard
 * WooCommerce hooks below. This template adds the client's payment +
 * delivery-partner tiles and the "View Your Order" CTA.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// CLIENT TEMPLATE: E03 hero heading — "Order Confirmed!" (fixed per client design,
// takes precedence over the admin-configured heading for this email).
$email_heading = 'Order Confirmed!';

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

<!-- CLIENT TEMPLATE: E03 — payment | delivery partner tiles -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 24px;">
	<tr>

		<!-- Payment -->
		<td class="tile-cell tile-cell-b tile-left tile-left-b" style="width:50%;vertical-align:top;padding-right:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="background:#f9fafb;border-radius:8px;padding:14px;border:1px solid #f3f4f6;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;padding-right:6px;">
									<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/card-pink.png' ); ?>" width="15" height="15" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
								</td>
								<td style="font-size:10px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">Payment Method</td>
							</tr>
						</table>
						<p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#374151;">
							<?php
							// CLIENT PLACEHOLDER: {{payment_method}} → $order->get_payment_method_title().
							echo esc_html( $order->get_payment_method_title() );
							?>
						</p>
						<p style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
							Payment status:
						</p>
						<?php
						// Use date_paid (money actually received) — NOT is_paid(), which only
						// reflects order *status*. COD orders go to "processing" on placement
						// without payment, so is_paid() wrongly reads as Paid. date_paid is set
						// only when payment_complete() runs (e.g. ConnectIPS) or on delivery/completion.
						?>
						<?php if ( $order->get_date_paid() ) : ?>
							<span style="background:#dcfce7;color:#15803d;padding:4px 12px;border-radius:4px;font-size:11px;font-weight:700;font-family:Arial,Helvetica,sans-serif;">
								Paid
							</span>
						<?php else : ?>
							<span style="background:#fef9c3;color:#854d0e;padding:4px 12px;border-radius:4px;font-size:11px;font-weight:700;font-family:Arial,Helvetica,sans-serif;">
								Unpaid
							</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>

		<!-- Delivery Partner -->
		<td class="tile-cell tile-cell-b tile-right tile-right-b" style="width:50%;vertical-align:top;padding-left:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="background:#fce7f3;border-radius:8px;padding:14px;border:1px solid #fbcfe8;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;padding-right:6px;">
									<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/truck-pink.png' ); ?>" width="15" height="15" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
								</td>
								<td style="font-size:10px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
									Delivery partner
								</td>
							</tr>
						</table>
						<p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
							Upaya City Cargo
						</p>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.6;">
							You&rsquo;ll receive a call &amp; SMS before your order arrives.
						</p>
					</td>
				</tr>
			</table>
		</td>

	</tr>
</table>

<!-- CLIENT TEMPLATE: E03 — CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 4px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							View Your Order
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
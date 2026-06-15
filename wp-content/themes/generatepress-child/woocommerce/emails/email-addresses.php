<?php
/**
 * Email Addresses — BabyPasa client design (E03 address tiles).
 *
 * Shipping (left) and billing (right) tiles with pin/invoice icons;
 * tiles stack on mobile via the .tile-cell media rules in email-styles.php.
 * Stock hooks (woocommerce_email_customer_address_section) preserved.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$address       = $order->get_formatted_billing_address();
$shipping      = $order->get_formatted_shipping_address();
$show_shipping = ! wc_ship_to_billing_address_only() && $order->needs_shipping_address() && $shipping;

?>
<!-- CLIENT TEMPLATE: E03 — address tiles (hybrid: inline-block divs stack on narrow screens with NO media query). -->
<div id="addresses" style="font-size:0;margin:0 0 16px;text-align:left;">
	<!--[if mso]><table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><![endif]-->

		<?php if ( $show_shipping ) : ?>
		<!--[if mso]><td width="50%" valign="top"><![endif]-->
		<!-- Shipping -->
		<div class="tile-cell tile-left" style="display:inline-block;width:100%;max-width:260px;vertical-align:top;box-sizing:border-box;padding-right:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td class="tile-box" style="background:#f9fafb;border-radius:8px;padding:14px;border:1px solid #f3f4f6;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;padding-right:6px;">
									<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/pin-line-pink.png' ); ?>" width="15" height="15" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
								</td>
								<td class="tile-label" style="font-size:10px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
									<?php esc_html_e( 'Shipping address', 'woocommerce' ); ?>
								</td>
							</tr>
						</table>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#374151;line-height:1.7;">
							<?php
							// CLIENT PLACEHOLDER: {{shipping_full_name}} / {{shipping_address_1}} / {{shipping_city}} / {{shipping_district}}
							// → $order->get_formatted_shipping_address() (covers all locale fields incl. district/state).
							echo wp_kses_post( $shipping );
							?>
							<?php if ( $order->get_shipping_phone() ) : ?>
								<br /><?php echo wc_make_phone_clickable( $order->get_shipping_phone() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
							<?php
							/**
							 * Fires after the core address fields in emails.
							 *
							 * @since 8.6.0
							 *
							 * @param string $type Address type. Either 'billing' or 'shipping'.
							 * @param WC_Order $order Order instance.
							 * @param bool $sent_to_admin If this email is being sent to the admin or not.
							 * @param bool $plain_text If this email is plain text or not.
							 */
							do_action( 'woocommerce_email_customer_address_section', 'shipping', $order, $sent_to_admin, false );
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>
		<!--[if mso]></td><![endif]-->
		<?php endif; ?>

		<!--[if mso]><td width="<?php echo $show_shipping ? '50%' : '100%'; ?>" valign="top"><![endif]-->
		<!-- Billing -->
		<div class="tile-cell tile-right" style="display:inline-block;width:100%;max-width:<?php echo $show_shipping ? '260px' : '100%'; ?>;vertical-align:top;box-sizing:border-box;<?php echo $show_shipping ? 'padding-left:6px;' : ''; ?>">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td class="tile-box" style="background:#f9fafb;border-radius:8px;padding:14px;border:1px solid #f3f4f6;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;padding-right:6px;">
									<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/doc-pink.png' ); ?>" width="15" height="15" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
								</td>
								<td class="tile-label" style="font-size:10px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
									<?php esc_html_e( 'Billing address', 'woocommerce' ); ?>
								</td>
							</tr>
						</table>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#374151;line-height:1.7;">
							<?php
							// CLIENT PLACEHOLDER: {{billing_full_name}} / {{billing_address_1}} / {{billing_city}} / {{billing_district}}
							// → $order->get_formatted_billing_address().
							echo wp_kses_post( $address ? $address : esc_html__( 'N/A', 'woocommerce' ) );
							?>
							<?php if ( $order->get_billing_phone() ) : ?>
								<br/><?php echo wc_make_phone_clickable( $order->get_billing_phone() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
							<?php if ( $order->get_billing_email() ) : ?>
								<br/><?php echo esc_html( $order->get_billing_email() ); ?>
							<?php endif; ?>
							<?php
							/**
							 * Fires after the core address fields in emails.
							 *
							 * @since 8.6.0
							 *
							 * @param string $type Address type. Either 'billing' or 'shipping'.
							 * @param WC_Order $order Order instance.
							 * @param bool $sent_to_admin If this email is being sent to the admin or not.
							 * @param bool $plain_text If this email is plain text or not.
							 */
							do_action( 'woocommerce_email_customer_address_section', 'billing', $order, $sent_to_admin, false );
							?>
						</p>
					</td>
				</tr>
			</table>
		</div>
	<!--[if mso]></td><![endif]-->

	<!--[if mso]></tr></table><![endif]-->
</div>

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
<!-- CLIENT TEMPLATE: E03 — address tiles -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" id="addresses" style="margin:0 0 16px;">
	<tr>

		<?php if ( $show_shipping ) : ?>
		<!-- Shipping -->
		<td class="tile-cell tile-left" style="width:50%;vertical-align:top;padding-right:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td class="tile-box" style="background:#f9fafb;border-radius:8px;padding:14px;border:1px solid #f3f4f6;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;padding-right:6px;">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="#ec4899" stroke-width="1.8" fill="none"/>
										<circle cx="12" cy="9" r="2.5" stroke="#ec4899" stroke-width="1.6" fill="none"/>
									</svg>
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
		</td>
		<?php endif; ?>

		<!-- Billing -->
		<td class="tile-cell tile-right" style="width:<?php echo $show_shipping ? '50%' : '100%'; ?>;vertical-align:top;<?php echo $show_shipping ? 'padding-left:6px;' : ''; ?>">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td class="tile-box" style="background:#f9fafb;border-radius:8px;padding:14px;border:1px solid #f3f4f6;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;padding-right:6px;">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
										<rect x="4" y="2" width="16" height="20" rx="2" stroke="#ec4899" stroke-width="1.8" fill="none"/>
										<line x1="8" y1="8" x2="16" y2="8" stroke="#ec4899" stroke-width="1.4"/>
										<line x1="8" y1="12" x2="16" y2="12" stroke="#ec4899" stroke-width="1.4"/>
										<line x1="8" y1="16" x2="12" y2="16" stroke="#ec4899" stroke-width="1.4"/>
									</svg>
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
		</td>

	</tr>
</table>

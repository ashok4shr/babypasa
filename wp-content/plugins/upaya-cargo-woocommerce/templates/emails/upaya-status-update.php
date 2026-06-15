<?php
/**
 * Upaya Cargo delivery status update — HTML email template.
 *
 * Variables available:
 *   WC_Order $order
 *   string   $email_heading
 *   string   $upaya_status
 *   string   $tracking_code
 *   string   $readable_status
 *   bool     $sent_to_admin
 *   bool     $plain_text
 *   WC_Email $email
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p style="margin:0 0 16px; color:#666666; font-size:16px; line-height:24px;">
	Hello <?php echo esc_html( $order->get_billing_first_name() ); ?>,
</p>

<p style="margin:0 0 20px; color:#666666; font-size:16px; line-height:24px;">
	We&rsquo;ve got an update on your order <strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>! Here&rsquo;s what&rsquo;s happening:
</p>

<?php /* Status box */ ?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin-bottom:20px;">
	<tr>
		<td style="background-color:#f8f9fa; border-radius:8px; padding:20px;">
			<p style="margin:0; color:#2e7d32; font-size:16px; line-height:24px; font-weight:600;">
				<?php echo esc_html( $readable_status ); ?>
			</p>
			<?php if ( $tracking_code ) : ?>
			<p style="margin:10px 0 0; color:#666666; font-size:16px; line-height:24px;">
				<?php echo nl2br( esc_html( $tracking_code ) ); ?>
			</p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<p style="margin:0 0 16px; color:#666666; font-size:16px; line-height:24px;">
	Want to check your order details?
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin-bottom:24px;">
	<tr>
		<td align="center">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background-color:#FF2A61; border-radius:4px; text-align:center;">
						<a href="<?php echo esc_url( home_url( '/my-account/track-orders/' ) ); ?>"
						   target="_blank"
						   style="display:inline-block; padding:14px 30px; color:#ffffff !important; font-size:16px; text-decoration:none; font-weight:bold; font-family:Arial,sans-serif;">
							Track Your Order
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php
do_action( 'woocommerce_email_footer', $email );

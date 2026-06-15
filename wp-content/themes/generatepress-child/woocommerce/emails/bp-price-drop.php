<?php
/**
 * Price-drop alert — BabyPasa client design.
 *
 * Rendered by Price_Drop_Notification's BP_Price_Drop_Email through the shared
 * email-header.php / email-footer.php partials (hero icon/subline come from the
 * `bp_price_drop` case in email-header.php). Goes through WooCommerce's CSS
 * inliner like the other custom emails.
 *
 * Variables:
 *   WC_Product $product       Product whose price dropped.
 *   string     $new_price     New (lower) price.
 *   string     $old_price     Previously subscribed price.
 *   string     $customer_name Recipient display name.
 *   string     $email_heading Hero heading.
 *   bool       $sent_to_admin Whether sent to admin (false).
 *   bool       $plain_text    Whether plain text (false).
 *   WC_Email   $email         Email object.
 *
 * @package GeneratePress_Child\WooCommerce\Emails
 */

defined( 'ABSPATH' ) || exit;

if ( ! $product instanceof WC_Product ) {
	return;
}

$bp_first_name = trim( (string) $customer_name );
if ( '' !== $bp_first_name ) {
	$bp_parts      = explode( ' ', $bp_first_name );
	$bp_first_name = $bp_parts[0];
}

$bp_old = (float) $old_price;
$bp_new = (float) $new_price;
$bp_save     = $bp_old > $bp_new ? $bp_old - $bp_new : 0;
$bp_save_pct = $bp_old > 0 ? round( ( $bp_save / $bp_old ) * 100 ) : 0;

$bp_image_id  = $product->get_image_id();
$bp_image_url = $bp_image_id
	? (string) wp_get_attachment_image_url( $bp_image_id, 'woocommerce_thumbnail' )
	: ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '' );

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.8;">
	Hello<?php echo $bp_first_name ? ' <strong>' . esc_html( $bp_first_name ) . '</strong>' : ''; ?>,
</p>

<p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.8;">
	Great news &mdash; the price of <strong><?php echo esc_html( $product->get_name() ); ?></strong> just dropped. Here&rsquo;s what you&rsquo;ll pay now:
</p>

<!-- PRODUCT + PRICE CARD -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"
       style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;margin:0 0 24px;">
	<tr>
		<?php if ( $bp_image_url ) : ?>
		<td width="96" valign="top" style="padding:16px 0 16px 16px;width:96px;">
			<img src="<?php echo esc_url( $bp_image_url ); ?>" width="80" alt="<?php echo esc_attr( $product->get_name() ); ?>"
			     style="display:block;width:80px;max-width:80px;height:auto;border-radius:8px;border:1px solid #f3f4f6;" />
		</td>
		<?php endif; ?>
		<td valign="middle" style="padding:16px;">
			<p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#1f2937;line-height:1.4;">
				<?php echo esc_html( $product->get_name() ); ?>
			</p>
			<?php if ( $bp_old > $bp_new ) : ?>
			<p style="margin:0 0 2px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#9ca3af;text-decoration:line-through;">
				<?php echo wp_kses_post( wc_price( $bp_old ) ); ?>
			</p>
			<?php endif; ?>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#ec4899;line-height:1.2;">
				<?php echo wp_kses_post( wc_price( $bp_new ) ); ?>
			</p>
			<?php if ( $bp_save > 0 ) : ?>
			<p style="margin:8px 0 0;">
				<span style="display:inline-block;background:#dcfce7;color:#15803d;padding:4px 12px;border-radius:4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;">
					You save <?php echo wp_kses_post( wc_price( $bp_save ) ); ?><?php echo $bp_save_pct > 0 ? ' (' . esc_html( $bp_save_pct ) . '% off)' : ''; ?>
				</span>
			</p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<!-- CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $product->get_permalink() ); ?>" target="_blank"
						   style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Grab It Now
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<p style="margin:20px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;line-height:1.7;text-align:center;">
	Prices can change at any time &mdash; we&rsquo;d grab it before it&rsquo;s gone!
</p>

<?php
do_action( 'woocommerce_email_footer', $email );

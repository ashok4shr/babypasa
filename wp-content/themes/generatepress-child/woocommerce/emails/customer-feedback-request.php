<?php
/**
 * Feedback / review-request email body — BabyPasa Order Emails plugin
 * (id: bp_feedback). Wired counterpart to the ready-to-wire E13 design,
 * generalised to cover every product on the order.
 *
 * Renders through the shared client design (email-header.php / email-footer.php):
 * a star-rating prompt + "Leave Your Review" CTA, then a card per purchased
 * product with its own review link.
 *
 * Expected variables (supplied by BP_OE_Feedback_Email):
 *   WC_Order  $order              The completed order.
 *   string    $email_heading      Hero heading.
 *   string    $additional_content Admin-configured trailing content (may be empty).
 *   array     $products           [{name, qty, url}] — url is the product #reviews link.
 *   string    $review_cta_url     Primary review URL (first product, or shop fallback).
 *   int       $days_since_order   Whole days since the order was placed.
 *   WC_Email  $email              The email object (may be null in previews).
 *
 * @package GeneratePress_Child\WooCommerce\Emails
 */

defined( 'ABSPATH' ) || exit;

$email          = $email ?? null;
$products       = isset( $products ) && is_array( $products ) ? $products : array();
$review_cta_url = isset( $review_cta_url ) ? $review_cta_url : '';

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p style="margin:0 0 16px;">
	<?php
	if ( $order && '' !== $order->get_billing_first_name() ) {
		/* translators: %s: Customer first name */
		printf( esc_html__( 'Hi %s,', 'generatepress-child' ), esc_html( $order->get_billing_first_name() ) );
	} else {
		esc_html_e( 'Hi,', 'generatepress-child' );
	}
	?>
</p>

<p style="margin:0 0 16px;">
	<?php esc_html_e( 'Thank you for shopping with BabyPasa.Com! We hope you and your little one are loving your order. If you have a moment, we&rsquo;d be grateful for a quick review.', 'generatepress-child' ); ?>
</p>

<!-- Star rating box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:10px;border:1px solid #fbcfe8;margin:0 0 16px;">
	<tr>
		<td align="center" style="padding:28px 24px;">
			<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				<?php esc_html_e( 'Tap a star to rate your experience', 'generatepress-child' ); ?>
			</p>
			<p class="stars" style="margin:14px 0 16px;font-size:0;line-height:1;">
				<?php
				$bp_review_url = esc_url( $review_cta_url );
				for ( $bp_i = 0; $bp_i < 5; $bp_i++ ) {
					echo '<a href="' . $bp_review_url . '" target="_blank" style="font-size:38px;color:#ec4899;line-height:1;text-decoration:none;display:inline-block;margin:0 4px;">&#9733;</a>';
				}
				?>
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.7;">
				<?php esc_html_e( 'Your honest review helps other parents across Nepal make confident choices for their little ones.', 'generatepress-child' ); ?>
			</p>
		</td>
	</tr>
</table>

<!-- CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $review_cta_url ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							<?php esc_html_e( 'Leave Your Review', 'generatepress-child' ); ?>
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php if ( ! empty( $products ) ) : ?>
<!-- Purchased products, each with its own review link -->
<p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
	<?php esc_html_e( 'Products from your order', 'generatepress-child' ); ?>
</p>
	<?php foreach ( $products as $bp_product ) : ?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;margin:0 0 8px;">
	<tr>
		<td style="padding:14px 16px;vertical-align:middle;">
			<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				<?php echo esc_html( $bp_product['name'] ); ?>
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
				<?php
				printf(
					/* translators: %d: quantity ordered */
					esc_html__( 'Qty: %d', 'generatepress-child' ),
					(int) $bp_product['qty']
				);
				?>
			</p>
		</td>
		<?php if ( ! empty( $bp_product['url'] ) ) : ?>
		<td align="right" style="padding:14px 16px;vertical-align:middle;white-space:nowrap;">
			<a href="<?php echo esc_url( $bp_product['url'] ); ?>" target="_blank" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ec4899;text-decoration:none;">
				<?php esc_html_e( 'Write a review', 'generatepress-child' ); ?> &rarr;
			</a>
		</td>
		<?php endif; ?>
	</tr>
</table>
	<?php endforeach; ?>
<?php endif; ?>

<?php
/**
 * Admin-configured additional content (Settings → Emails → Baby Pasa feedback request).
 */
if ( ! empty( $additional_content ) ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );

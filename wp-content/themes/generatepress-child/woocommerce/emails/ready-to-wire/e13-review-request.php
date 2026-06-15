<?php
/**
 * Ready-to-wire email template — Purchase Feedback / Review Request.
 *
 * CLIENT TEMPLATE: E13 (woocommerce/Email Template and Logo/7. Purchase Feedback  Review).
 *
 * INERT: no sender is wired to this template yet. Render it from a future
 * plugin with:
 *   wc_get_template_html(
 *       'emails/ready-to-wire/e13-review-request.php',
 *       array( 'order' => $order, 'email_heading' => $heading, ... )
 *   );
 *
 * Expected variables:
 *   WC_Order   $order            The order being reviewed.
 *   string     $email_heading    Hero heading, e.g. "How are you finding it, {first}?".
 *   string     $product_name     Name of the product to review.
 *   int        $product_qty      Quantity ordered.
 *   int        $days_since_order Whole days since the order was placed.
 *   string     $review_link      Review URL — get_permalink( $product_id ) . '#reviews'.
 *   WC_Email   $email            Email object (may be null in previews).
 *
 * Wiring summary (see ready-to-wire/README.md and DEV_NOTE_E13_review.txt):
 *   - Scheduled via wp_schedule_single_event() +3 days from the "delivered"
 *     handler (Upaya webhook / WC completed).
 *   - Guard against resending with the `_e13_sent` order meta flag.
 *   - $review_link = get_permalink( $product_id ) . '#reviews' (or the review
 *     plugin's submission URL). All 5 stars link to the same URL.
 *   - $days_since_order = (int) ( ( time() - $order->get_date_created()->getTimestamp() ) / DAY_IN_SECONDS ).
 *   - When wiring, add a `customer_review_request` (or chosen email id) case to
 *     email-header.php's hero switch for the heart icon + subline.
 *
 * @package GeneratePress_Child\WooCommerce\Emails\ReadyToWire
 */

defined( 'ABSPATH' ) || exit;

$email = $email ?? null;

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E13 — star rating box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:10px;border:1px solid #fbcfe8;margin:0 0 16px;">
	<tr>
		<td align="center" style="padding:28px 24px;">
			<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				Tap a star to rate your experience
			</p>
			<p class="stars" style="margin:14px 0 16px;font-size:0;line-height:1;">
				<?php
				// CLIENT PLACEHOLDER: {{review_link}} → $review_link (all 5 stars link to the same URL).
				$bp_review_url = esc_url( $review_link );
				for ( $bp_i = 0; $bp_i < 5; $bp_i++ ) {
					echo '<a href="' . $bp_review_url . '" target="_blank" style="font-size:38px;color:#ec4899;line-height:1;text-decoration:none;display:inline-block;margin:0 4px;">&#9733;</a>';
				}
				?>
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.7;">
				Your honest review helps other parents across Nepal<br />
				make confident choices for their little ones.
			</p>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E13 — CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $review_link ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Leave Your Review
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E13 — product card -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;">
	<tr>
		<td class="product-icon-td" style="padding:14px 0 14px 16px;vertical-align:middle;width:52px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:40px;height:40px;background:#fce7f3;border-radius:8px;text-align:center;vertical-align:middle;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
							<path d="M12 2L2 7l10 5 10-5-10-5Z" stroke="#ec4899" stroke-width="1.8" stroke-linejoin="round" fill="none"/>
							<path d="M2 17l10 5 10-5" stroke="#ec4899" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M2 12l10 5 10-5" stroke="#ec4899" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</td>
				</tr>
			</table>
		</td>
		<td style="padding:14px 16px;vertical-align:middle;">
			<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				<?php
				// CLIENT PLACEHOLDER: {{product_name}} → $product_name.
				echo esc_html( $product_name );
				?>
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
				<?php
				// CLIENT PLACEHOLDER: {{days_since_order}} → $days_since_order; {{product_qty}} → $product_qty.
				printf(
					/* translators: 1: whole days since order, 2: quantity ordered */
					esc_html__( 'Ordered %1$d days ago &middot; Qty: %2$d', 'generatepress-child' ),
					(int) $days_since_order,
					(int) $product_qty
				);
				?>
			</p>
		</td>
	</tr>
</table>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer (support line, feature strip, footer band).
 */
do_action( 'woocommerce_email_footer', $email );

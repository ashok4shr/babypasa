<?php
/**
 * Abandoned cart email — BabyPasa client design (E05 Abandoned Cart).
 *
 * READY-TO-WIRE: nothing on the site includes this template yet. A future
 * plugin renders it via:
 *
 *   wc_get_template_html( 'emails/ready-to-wire/e05-abandoned-cart.php', array( ...vars ) );
 *
 * Expected variables (set by the future sender before rendering):
 *
 * @var string        $email_heading Hero heading, e.g. "You left something behind, {first}!".
 * @var array         $cart_items    Cart rows. Each: array(
 *                                       'name'       => (string) product name,
 *                                       'qty'        => (int)    quantity,
 *                                       'unit_price' => (float)  unit price,
 *                                       'line_total' => (float)  line total,
 *                                   ).
 * @var float         $cart_total    Cart total (raw float — formatted here with wc_price()).
 * @var string        $cart_url      wc_get_cart_url() — persistent cart auto-restores for logged-in users.
 * @var string        $first_name    Customer first name (falls back to display name in sender).
 * @var WC_Email|null $email         Email object (may be null — sender may use raw wp_mail()).
 *
 * Trigger / wiring summary (from DEV_NOTE_E05_abandoned_cart.txt):
 * - On `woocommerce_cart_updated` (logged-in, non-empty cart): stamp
 *   `_babypasa_cart_updated` user meta, clear + reschedule a
 *   `babypasa_send_abandoned_cart_email` single event 90 minutes out.
 * - On `woocommerce_checkout_order_created`: clear the scheduled event
 *   (suppress on order complete).
 * - Send handler: skip if `_babypasa_e05_sent` user meta set; build items
 *   from the `_woocommerce_persistent_cart_{blog_id}` user meta; after send
 *   set `_babypasa_e05_sent` and schedule `babypasa_reset_e05_flag`
 *   +24h to clear it (max one send per 24h per user).
 * - Subject: "You left something behind, {{first_name}}! 🛒".
 *
 * NOTE FOR WIRING: emails/email-header.php decides the hero icon/subline via
 * a switch on $email->id. When this email gets a real WC_Email class, add a
 * case for its id (client E05 hero: shopping-bag icon, subline "Your cart is
 * saved and waiting for you. / Your little one's essentials are just a click
 * away!"). Until then the header falls back to the default check icon with
 * no subline.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// CLIENT TEMPLATE: E05 hero heading — "You left something behind, {{first_name}}!".
// CLIENT PLACEHOLDER: {{first_name}} → $first_name (sender builds the heading; fallback kept here).
if ( empty( $email_heading ) ) {
	$email_heading = sprintf( 'You left something behind, %s!', $first_name );
}

$email = isset( $email ) ? $email : null;

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E05 — "Still in your cart" items table -->
<p style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
	Still in your cart
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;overflow:hidden;margin:0 0 16px;">

	<?php
	// CLIENT PLACEHOLDER: repeated <tr> per cart item — {{product_name}} / {{unit_price}} / {{product_qty}} / {{line_total}}.
	$bp_item_count = count( $cart_items );
	$bp_item_i     = 0;
	foreach ( $cart_items as $bp_item ) :
		$bp_item_i++;
		// Last item row is borderless (cart total row follows with its own background).
		$bp_row_border = ( $bp_item_i < $bp_item_count ) ? 'border-bottom:1px solid #f3f4f6;' : '';
		?>
	<tr>
		<!-- Product name + unit price (unit price hidden on mobile via .price-col) -->
		<td style="padding:14px 16px;vertical-align:middle;<?php echo esc_attr( $bp_row_border ); ?>">
			<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				<?php
				// CLIENT PLACEHOLDER: {{product_name}} → $bp_item['name'].
				echo esc_html( $bp_item['name'] );
				?>
			</p>
			<p class="price-col" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
				<?php
				// CLIENT PLACEHOLDER: {{unit_price}} → wc_price( $bp_item['unit_price'] ).
				echo wp_kses_post( wc_price( $bp_item['unit_price'] ) );
				?>
				per unit
			</p>
		</td>
		<!-- Qty -->
		<td style="padding:14px 12px;text-align:right;vertical-align:middle;white-space:nowrap;<?php echo esc_attr( $bp_row_border ); ?>width:50px;">
			<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
				<?php
				// CLIENT PLACEHOLDER: {{product_qty}} → $bp_item['qty'].
				echo '&times;&nbsp;' . esc_html( (string) absint( $bp_item['qty'] ) );
				?>
			</span>
		</td>
		<!-- Line total -->
		<td style="padding:14px 16px;text-align:right;vertical-align:middle;white-space:nowrap;<?php echo esc_attr( $bp_row_border ); ?>width:90px;">
			<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				<?php
				// CLIENT PLACEHOLDER: {{line_total}} → wc_price( $bp_item['line_total'] ).
				echo wp_kses_post( wc_price( $bp_item['line_total'] ) );
				?>
			</span>
		</td>
	</tr>
	<?php endforeach; ?>

	<!-- Cart total row -->
	<tr>
		<td colspan="2" style="padding:12px 16px;background:#fce7f3;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#9d174d;">
			Cart total
		</td>
		<td style="padding:12px 16px;background:#fce7f3;text-align:right;white-space:nowrap;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#9d174d;">
			<?php
			// CLIENT PLACEHOLDER: {{cart_total}} → wc_price( $cart_total ).
			echo wp_kses_post( wc_price( $cart_total ) );
			?>
		</td>
	</tr>

</table>

<!-- CLIENT TEMPLATE: E05 — CTA "Complete Your Order" -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $cart_url ); // CLIENT PLACEHOLDER: {{cart_url}} → wc_get_cart_url(). ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Complete Your Order
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E05 — trust tiles (free delivery / safe products / easy returns; stack on mobile) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 4px;">
	<tr>

		<!-- Free delivery -->
		<td class="tile-cell tile-left trust-cell" style="width:33.33%;vertical-align:top;padding-right:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="background:#fce7f3;border-radius:8px;padding:14px;text-align:center;border:1px solid #fbcfe8;">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;margin-bottom:8px;" aria-hidden="true">
							<rect x="1" y="6" width="13" height="10" rx="1" stroke="#ec4899" stroke-width="1.8" fill="none"/>
							<path d="M14 9h4.5L21 12.5V16H14V9Z" stroke="#ec4899" stroke-width="1.8" fill="none" stroke-linejoin="round"/>
							<circle cx="5.5" cy="17.5" r="1.5" fill="#ec4899"/>
							<circle cx="17.5" cy="17.5" r="1.5" fill="#ec4899"/>
						</svg>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;line-height:1.4;">
							Free delivery<br />across Nepal
						</p>
					</td>
				</tr>
			</table>
		</td>

		<!-- Safe products -->
		<td class="tile-cell trust-cell-mid" style="width:33.34%;vertical-align:top;padding:0 3px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="background:#fce7f3;border-radius:8px;padding:14px;text-align:center;border:1px solid #fbcfe8;">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;margin-bottom:8px;" aria-hidden="true">
							<path d="M12 3L4 6.5V12c0 4.5 3.3 8.3 8 9.5 4.7-1.2 8-5 8-9.5V6.5L12 3Z" stroke="#ec4899" stroke-width="1.8" fill="none" stroke-linejoin="round"/>
							<path d="M9 12l2 2 4-4" stroke="#ec4899" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;line-height:1.4;">
							Safe &amp; trusted<br />products
						</p>
					</td>
				</tr>
			</table>
		</td>

		<!-- Easy returns -->
		<td class="tile-cell tile-right trust-cell-last" style="width:33.33%;vertical-align:top;padding-left:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="background:#fce7f3;border-radius:8px;padding:14px;text-align:center;border:1px solid #fbcfe8;">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;margin-bottom:8px;" aria-hidden="true">
							<path d="M1 4v6h6" stroke="#ec4899" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M3.51 15a9 9 0 102.13-9.36L1 10" stroke="#ec4899" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;line-height:1.4;">
							Easy returns,<br />no hassle
						</p>
					</td>
				</tr>
			</table>
		</td>

	</tr>
</table>

<?php
/*
 * @hooked WC_Emails::email_footer() Close body cell + support line + feature strip + pink footer + copyright.
 */
do_action( 'woocommerce_email_footer', $email );

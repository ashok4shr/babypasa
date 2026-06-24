<?php
/**
 * Return-requested email — BabyPasa client design (E18 Return Requested).
 *
 * CLIENT TEMPLATE: E18 (woocommerce/Email Template and Logo/15. Return Requested).
 *
 * READY-TO-WIRE: nothing on the site includes this template yet. A future
 * plugin / custom return-request system renders it via:
 *
 *   wc_get_template_html( 'emails/ready-to-wire/e18-return-requested.php', array( ...vars ) );
 *
 * Expected variables (set by the future sender before rendering):
 *
 * @var WC_Order      $order        The order the customer wants to return.
 * @var string        $email_heading Hero heading, e.g. "We've received your return request".
 * @var array         $return_items Items requested for return (may be a subset of the
 *                                   order — partial return). Each: name (string), qty (int).
 * @var WC_Email|null $email        Email object (may be null — sender may use raw wp_mail()).
 *
 * Trigger / wiring summary (from DEV_NOTE_E18_return_requested.txt):
 * - TRIGGER: customer submits a return request from My Account -> Orders ->
 *   View Order -> "Request Return". WooCommerce has NO native return-request
 *   system, so wiring E18 needs a custom feature that does NOT exist yet:
 *   a My Account "Request Return" button (Option B in the note) or a returns
 *   plugin (Option A — e.g. WP Desk's wcrw_new_return_request).
 * - On submission, the sender stores return meta for the later E19 approval:
 *   `_return_requested` = '1', `_return_items` = wp_json_encode($return_items),
 *   `_return_reason`, `_return_requested_at` = time(). Send guard: `_e18_sent`
 *   meta; set `_e18_sent` = '1' after send (one-shot).
 * - $return_items may be ALL order items or a SUBSET (partial return). If a
 *   plugin is used, map its return-item data to the name/qty shape.
 * - DISTINCT FROM E17: E18 is customer-initiated (customer HAS the parcel, so the
 *   packaging instructions apply); E17 is logistics-triggered (Upaya webhook).
 *   Never fire both for the same order.
 * - Subject: "We've received your return request — Order #{{order_number}}".
 * - Next in sequence: E19 Return Approved (manual admin action, ~1–2 business days).
 *
 * NOTE FOR WIRING: emails/email-header.php decides the hero icon/subline via a
 * switch on $email->id. When this email gets a real WC_Email class, add a case
 * to email-header.php's hero switch for this email id (client E18 hero: a
 * return-box / check icon, subline "We're reviewing your request."). Until then
 * the header falls back to the default check icon with no subline — acceptable
 * for an inert template.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package GeneratePress_Child\WooCommerce\Emails\ReadyToWire
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email = isset( $email ) ? $email : null;

// CLIENT TEMPLATE: E18 hero heading (fallback if sender omits it).
if ( empty( $email_heading ) ) {
	$email_heading = "We've received your return request";
}

$return_items = isset( $return_items ) && is_array( $return_items ) ? $return_items : array();

// Upaya reference ("BPA…") if available; else the WC order number. This is the id
// the customer should quote so there is no confusion on Upaya's side.
$upaya_reference = isset( $upaya_reference ) ? trim( (string) $upaya_reference ) : '';
$bp_order_ref    = ( '' !== $upaya_reference ) ? $upaya_reference : ( '#' . $order->get_order_number() );

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E18 — "Items requested for return" list (may be a partial subset) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #f3f4f6;margin:0 0 14px;">
	<tr>
		<td colspan="2" style="background:#f3f4f6;padding:10px 16px;border-bottom:1px solid #ebebeb;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				Items requested for return
			</p>
		</td>
	</tr>
	<?php
	// CLIENT PLACEHOLDER: repeated <tr> per item — {{product_name}} → $bp_item['name']; {{product_qty}} → $bp_item['qty'] (loop $return_items).
	$bp_item_count = count( $return_items );
	$bp_item_i     = 0;
	foreach ( $return_items as $bp_item ) :
		$bp_item_i++;
		$bp_row_border = ( $bp_item_i < $bp_item_count ) ? 'border-bottom:1px solid #f3f4f6;' : '';
		$bp_item_name  = isset( $bp_item['name'] ) ? $bp_item['name'] : '';
		$bp_item_qty   = isset( $bp_item['qty'] ) ? $bp_item['qty'] : 0;
		?>
	<tr class="item-row">
		<td class="item-name" style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#1f2937;<?php echo esc_attr( $bp_row_border ); ?>">
			<?php echo esc_html( $bp_item_name ); ?>
		</td>
		<td class="item-qty" style="padding:10px 16px;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:600;color:#9d174d;white-space:nowrap;<?php echo esc_attr( $bp_row_border ); ?>">
			<?php echo '&times;&nbsp;' . esc_html( (string) $bp_item_qty ); ?>
		</td>
	</tr>
	<?php endforeach; ?>
</table>

<!-- CLIENT TEMPLATE: E18 — STATIC pending-instructions pink box (4 points; only point 3 is
     dynamic — it injects the order number). Icon hidden on mobile via .instr-icon-td -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 16px;">
	<tr>
		<td style="padding:16px;">

			<!-- Header -->
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:12px;">
				<tr>
					<td class="instr-icon-td" style="vertical-align:middle;padding-right:8px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:28px;height:28px;background:#ec4899;border-radius:6px;text-align:center;vertical-align:middle;">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
										<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2" stroke="#ffffff" stroke-width="1.8" fill="none" stroke-linejoin="round"/>
										<rect x="9" y="3" width="6" height="4" rx="1" stroke="#ffffff" stroke-width="1.8" fill="none"/>
										<line x1="9" y1="12" x2="15" y2="12" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round"/>
										<line x1="9" y1="16" x2="13" y2="16" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round"/>
									</svg>
								</td>
							</tr>
						</table>
					</td>
					<td style="vertical-align:middle;">
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#9d174d;">
							While you wait &mdash; please keep these in mind
						</p>
					</td>
				</tr>
			</table>

			<!-- Instructions list — numbered circles (STATIC except #3 order number) -->
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">

				<!-- 1 -->
				<tr>
					<td style="padding:5px 0;vertical-align:top;width:24px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:18px;height:18px;background:#ec4899;border-radius:9px;text-align:center;vertical-align:middle;">
									<span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#ffffff;line-height:1;">1</span>
								</td>
							</tr>
						</table>
					</td>
					<td style="padding:5px 0 5px 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.5;">
						Keep the item(s) in their original packaging if possible &mdash; this helps speed up inspection.
					</td>
				</tr>

				<!-- 2 -->
				<tr>
					<td style="padding:5px 0;vertical-align:top;width:24px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:18px;height:18px;background:#ec4899;border-radius:9px;text-align:center;vertical-align:middle;">
									<span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#ffffff;line-height:1;">2</span>
								</td>
							</tr>
						</table>
					</td>
					<td style="padding:5px 0 5px 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.5;">
						Do not use or open sealed products &mdash; returned items must be unused and in resalable condition.
					</td>
				</tr>

				<!-- 3 — dynamic order number -->
				<tr>
					<td style="padding:5px 0;vertical-align:top;width:24px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:18px;height:18px;background:#ec4899;border-radius:9px;text-align:center;vertical-align:middle;">
									<span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#ffffff;line-height:1;">3</span>
								</td>
							</tr>
						</table>
					</td>
					<td style="padding:5px 0 5px 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.5;">
						Keep your order reference
						<strong><?php
						// Upaya reference ("BPA…"), or the WC order number as fallback.
						echo esc_html( $bp_order_ref );
						?></strong> handy &mdash; you&rsquo;ll need it when sending the item back.
					</td>
				</tr>

				<!-- 4 — most critical -->
				<tr>
					<td style="padding:5px 0;vertical-align:top;width:24px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:18px;height:18px;background:#ec4899;border-radius:9px;text-align:center;vertical-align:middle;">
									<span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#ffffff;line-height:1;">4</span>
								</td>
							</tr>
						</table>
					</td>
					<td style="padding:5px 0 5px 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.5;">
						<strong>Don&rsquo;t ship anything yet</strong> &mdash; wait for our approval email with full return instructions before sending anything back.
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E18 — STATIC "What happens next" 3-step block (sets expectations for E19 + E21) -->
<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
	What happens next
</p>

<!-- Step 1 -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
	<tr>
		<td style="vertical-align:top;width:44px;padding-right:12px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:32px;height:32px;background:#ec4899;border-radius:16px;text-align:center;vertical-align:middle;">
						<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;">1</span>
					</td>
				</tr>
			</table>
		</td>
		<td style="vertical-align:top;padding-top:4px;">
			<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				We review your request
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;line-height:1.5;">
				Our team will review your return request within 1&ndash;2 business days.
			</p>
		</td>
	</tr>
</table>

<!-- Step 2 -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
	<tr>
		<td style="vertical-align:top;width:44px;padding-right:12px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:32px;height:32px;background:#ec4899;border-radius:16px;text-align:center;vertical-align:middle;">
						<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;">2</span>
					</td>
				</tr>
			</table>
		</td>
		<td style="vertical-align:top;padding-top:4px;">
			<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				You&rsquo;ll receive return instructions
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;line-height:1.5;">
				Once approved, we&rsquo;ll email you step-by-step instructions on how to send the item back.
			</p>
		</td>
	</tr>
</table>

<!-- Step 3 -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
	<tr>
		<td style="vertical-align:top;width:44px;padding-right:12px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:32px;height:32px;background:#ec4899;border-radius:16px;text-align:center;vertical-align:middle;">
						<span style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;">3</span>
					</td>
				</tr>
			</table>
		</td>
		<td style="vertical-align:top;padding-top:4px;">
			<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
				Refund processed
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;line-height:1.5;">
				Once we receive and inspect the item, your refund will be processed within 3&ndash;5 business days.
			</p>
		</td>
	</tr>
</table>

<?php
/*
 * @hooked WC_Emails::email_footer() Close body cell + support line + feature strip + pink footer + copyright.
 */
do_action( 'woocommerce_email_footer', $email );

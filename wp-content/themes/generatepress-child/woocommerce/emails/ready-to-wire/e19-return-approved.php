<?php
/**
 * Return-approved email — BabyPasa client design (E19 Return Approved).
 *
 * CLIENT TEMPLATE: E19 (woocommerce/Email Template and Logo/16. Return Approved).
 *
 * READY-TO-WIRE: nothing on the site includes this template yet. A future
 * plugin / custom return-request system renders it via:
 *
 *   wc_get_template_html( 'emails/ready-to-wire/e19-return-approved.php', array( ...vars ) );
 *
 * Expected variables (set by the future sender before rendering):
 *
 * @var WC_Order      $order        The order whose return was approved.
 * @var string        $email_heading Hero heading, e.g. "Your return request has been approved".
 * @var array         $return_items Items approved for return (read from `_return_items` meta
 *                                   set by E18; fallback to all order items). Each: name, qty.
 * @var string        $branch_url   Upaya branch-locator URL (Option 2 "Find a Branch").
 * @var string        $pickup_url   Pickup-request mailto with pre-filled subject (Option 1).
 * @var string        $support_url  Support mailto, e.g. "mailto:support@babypasa.com".
 * @var WC_Email|null $email        Email object (may be null — sender may use raw wp_mail()).
 *
 * Trigger / wiring summary (from DEV_NOTE_E19_return_approved.txt):
 * - TRIGGER: manual admin action. Wiring needs a custom "Approve Return" order
 *   action (woocommerce_order_actions + woocommerce_order_action_* handler) that
 *   does NOT exist yet — part of the same custom return system E18 depends on.
 * - Guards: a return must have been requested (`_return_requested` meta from E18);
 *   don't approve twice (`_return_approved`). On approve, set `_return_approved`
 *   = '1', `_return_approved_at` = time(), then send. Send guard: `_e19_sent`
 *   meta; set `_e19_sent` = '1' after send (one-shot).
 * - $return_items: read `_return_items` (json_decode), fallback to all order items.
 * - Two return options, both ending at the same place (E20 fires when Upaya's
 *   webhook confirms warehouse receipt):
 *     Option 1 "Request Pickup" -> $pickup_url (mailto, support arranges Upaya collection).
 *     Option 2 "Find a Branch"  -> $branch_url (Upaya branch locator; verify the live URL).
 * - Subject: "Your return request has been approved — Order #{{order_number}} ✅".
 * - Next in sequence: E20 RTO Complete (Upaya webhook "RTO Complete").
 *
 * NOTE FOR WIRING: emails/email-header.php decides the hero icon/subline via a
 * switch on $email->id. When this email gets a real WC_Email class, add a case
 * to email-header.php's hero switch for this email id (client E19 hero: a
 * check / approved icon, subline "Here's how to send it back."). Until then the
 * header falls back to the default check icon with no subline — acceptable for
 * an inert template.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package GeneratePress_Child\WooCommerce\Emails\ReadyToWire
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email = isset( $email ) ? $email : null;

// CLIENT TEMPLATE: E19 hero heading (fallback if sender omits it).
if ( empty( $email_heading ) ) {
	$email_heading = 'Your return request has been approved';
}

$return_items = isset( $return_items ) && is_array( $return_items ) ? $return_items : array();
$branch_url   = isset( $branch_url ) ? $branch_url : 'https://upayacargo.com/branches';
$pickup_url   = isset( $pickup_url ) ? $pickup_url : 'mailto:support@babypasa.com';
$support_url  = isset( $support_url ) ? $support_url : 'mailto:support@babypasa.com';

// Upaya reference ("BPA…") if the order was submitted; else fall back to the WC
// order number. This is the id the customer should write inside the package.
$upaya_reference = isset( $upaya_reference ) ? trim( (string) $upaya_reference ) : '';
$bp_pack_ref     = ( '' !== $upaya_reference ) ? $upaya_reference : ( '#' . $order->get_order_number() );

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E19 — "Items approved for return" list -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #f3f4f6;margin:0 0 14px;">
	<tr>
		<td colspan="2" style="background:#f3f4f6;padding:10px 16px;border-bottom:1px solid #ebebeb;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				Items approved for return
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

<!-- CLIENT TEMPLATE: E19 — Step 1 STATIC pack-instructions pink box (2 points; only point 2 is
     dynamic — it injects the order number). Icon hidden on mobile via .pack-icon-td -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 16px;">
	<tr>
		<td style="padding:16px;">

			<!-- Header -->
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:12px;">
				<tr>
					<td class="pack-icon-td" style="vertical-align:middle;padding-right:8px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:28px;height:28px;background:#ec4899;border-radius:6px;text-align:center;vertical-align:middle;">
									<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
										<path d="M12 2L2 7l10 5 10-5-10-5Z" stroke="#ffffff" stroke-width="1.8" stroke-linejoin="round" fill="none"/>
										<path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
									</svg>
								</td>
							</tr>
						</table>
					</td>
					<td style="vertical-align:middle;">
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#9d174d;">
							Step 1 &mdash; Pack your items
						</p>
					</td>
				</tr>
			</table>

			<!-- Pack instructions (STATIC except #2 order number) -->
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">

				<!-- Pack 1 -->
				<tr>
					<td style="padding:4px 0;vertical-align:top;width:24px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:18px;height:18px;background:#ec4899;border-radius:9px;text-align:center;vertical-align:middle;">
									<span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#ffffff;line-height:1;">1</span>
								</td>
							</tr>
						</table>
					</td>
					<td style="padding:4px 0 4px 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.5;">
						Pack item(s) securely &mdash; use original packaging if possible.
					</td>
				</tr>

				<!-- Pack 2 — dynamic order number -->
				<tr>
					<td style="padding:4px 0;vertical-align:top;width:24px;">
						<table border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="width:18px;height:18px;background:#ec4899;border-radius:9px;text-align:center;vertical-align:middle;">
									<span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#ffffff;line-height:1;">2</span>
								</td>
							</tr>
						</table>
					</td>
					<td style="padding:4px 0 4px 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.5;">
						Include a note with your order reference
						<strong><?php
						// Upaya reference ("BPA…"), or the WC order number as fallback.
						echo esc_html( $bp_pack_ref );
						?></strong> inside the package.
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E19 — Step 2 two return-method option cards (Pickup pink / Drop-off neutral) -->
<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
	Step 2 &mdash; Choose how to send it back
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 4px;">
	<tr>

		<!-- Option 1: Pickup by Upaya (pink) -->
		<td class="opt-cell opt-left tile-cell tile-left" style="width:50%;vertical-align:top;padding-right:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="background:#fce7f3;border-radius:8px;padding:16px;border:1px solid #fbcfe8;text-align:center;">

						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 10px;">
							<tr>
								<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
										<rect x="1" y="6" width="13" height="10" rx="1" stroke="#ffffff" stroke-width="1.8" fill="none"/>
										<path d="M14 9h4.5L21 12.5V16H14V9Z" stroke="#ffffff" stroke-width="1.8" fill="none" stroke-linejoin="round"/>
										<circle cx="5.5" cy="17.5" r="1.5" fill="#ffffff"/>
										<circle cx="17.5" cy="17.5" r="1.5" fill="#ffffff"/>
									</svg>
								</td>
							</tr>
						</table>

						<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
							Pickup by Upaya
						</p>
						<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;line-height:1.5;">
							Upaya City Cargo will collect the parcel from your address. Contact us to schedule.
						</p>

						<table class="opt-cta cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
							<tr>
								<td style="background:#ec4899;border-radius:5px;text-align:center;">
									<a href="<?php echo esc_url( $pickup_url ); // CLIENT PLACEHOLDER: {{pickup_url}} → $pickup_url (mailto w/ pre-filled subject). ?>" style="display:inline-block;padding:8px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff !important;text-decoration:none;">
										Request Pickup
									</a>
								</td>
							</tr>
						</table>

					</td>
				</tr>
			</table>
		</td>

		<!-- Option 2: Drop off at Upaya branch (neutral) -->
		<td class="opt-cell opt-right tile-cell tile-right" style="width:50%;vertical-align:top;padding-left:6px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="background:#f9fafb;border-radius:8px;padding:16px;border:1px solid #f3f4f6;text-align:center;">

						<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 10px;">
							<tr>
								<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
										<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="#ffffff" stroke-width="1.8" fill="none"/>
										<circle cx="12" cy="9" r="2.5" stroke="#ffffff" stroke-width="1.6" fill="none"/>
									</svg>
								</td>
							</tr>
						</table>

						<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
							Drop off yourself
						</p>
						<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#6b7280;line-height:1.5;">
							Drop the parcel at your nearest Upaya City Cargo branch at your convenience.
						</p>

						<table class="opt-cta" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
							<tr>
								<td style="background:#f3f4f6;border-radius:5px;border:1px solid #e5e7eb;text-align:center;">
									<a href="<?php echo esc_url( $branch_url ); // CLIENT PLACEHOLDER: {{branch_url}} → $branch_url (Upaya branch locator). ?>" target="_blank" style="display:inline-block;padding:8px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#374151 !important;text-decoration:none;">
										Find a Branch
									</a>
								</td>
							</tr>
						</table>

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

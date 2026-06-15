<?php
/**
 * Failed-delivery-attempt email — BabyPasa client design (E16 Delivery Failed).
 *
 * CLIENT TEMPLATE: E16 (woocommerce/Email Template and Logo/19. Delivery Failed).
 *
 * READY-TO-WIRE: nothing on the site includes this template yet. A future
 * plugin renders it via:
 *
 *   wc_get_template_html( 'emails/ready-to-wire/e16-failed-delivery.php', array( ...vars ) );
 *
 * IMPORTANT TONE RULE (from the DEV_NOTE): this email is deliberately positive
 * and NEVER says "failed", "return", "RTO", "sending back" or implies fault.
 * It leads with "Another attempt is on its way!" and shows the address so the
 * customer can verify/correct it.
 *
 * Expected variables (set by the future sender before rendering):
 *
 * @var WC_Order      $order         The order with the failed attempt.
 * @var string        $email_heading Hero heading, e.g. "We tried to deliver your order".
 * @var string        $tracking_code Upaya tracking code (order meta `_upaya_tracking_code`).
 * @var array         $address       Shipping address — keys: line1, line2, city, district.
 * @var array         $items         Each item: name (string), qty (int).
 * @var string        $track_url     Track-order URL, e.g. home_url( '/my-account/track-orders/' ).
 * @var string        $support_url   Support mailto, e.g. "mailto:support@babypasa.com".
 * @var int           $attempts      Number of delivery attempts so far (logged only).
 * @var WC_Email|null $email         Email object (may be null — sender may use raw wp_mail()).
 *
 * Trigger / wiring summary (from DEV_NOTE_E16_failed_delivery.txt):
 * - TRIGGER: Upaya webhook status "Follow Up for Return". State stays
 *   OUT_FOR_DELIVERY — a failed attempt is NOT a state change (the parcel is
 *   still with the agent; only DELIVERED or RTO advance the state machine).
 * - On the webhook: increment `_delivery_attempts`, then send E16 only if
 *   `_e16_last_sent` is unset OR >= 12h ago (cooldown — Upaya can fire the same
 *   "Follow Up for Return" event multiple times per attempt). After send set
 *   `_e16_last_sent` = time(). E16 may fire more than once per order (unlike the
 *   one-shot _e17_sent / _e20_sent flags).
 * - "Follow Up for Return" is currently NOT in the Upaya plugin's
 *   NOTABLE_STATUSES (deliberately narrowed to out-for-delivery + delivered);
 *   wiring E16 requires re-adding it + the attempt-count / cooldown logic in
 *   class-upaya-webhook-processor.php.
 * - Address correction is a manual support task (no automated /update-address
 *   flow yet) — the "Wrong address?" link is a plain support mailto.
 * - Subject: "We tried to deliver your order #{{order_number}}, {{first_name}}!"
 * - Next in sequence: delivery succeeds on retry -> E12 Delivered; all attempts
 *   exhausted -> E17 RTO Initiated (both fire from webhook state changes, not E16).
 *
 * NOTE FOR WIRING: emails/email-header.php decides the hero icon/subline via a
 * switch on $email->id. When this email gets a real WC_Email class, add a case
 * to email-header.php's hero switch for this email id (client E16 hero: a
 * truck / redelivery icon, subline "We'll try again soon — here's your delivery
 * info."). Until then the header falls back to the default check icon with no
 * subline — acceptable for an inert template.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package GeneratePress_Child\WooCommerce\Emails\ReadyToWire
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email = isset( $email ) ? $email : null;

// CLIENT TEMPLATE: E16 hero heading (fallback if sender omits it).
if ( empty( $email_heading ) ) {
	$email_heading = 'We tried to deliver your order';
}

// Defensive defaults so an inert preview never notices undefined vars.
$address       = isset( $address ) && is_array( $address ) ? $address : array();
$items         = isset( $items ) && is_array( $items ) ? $items : array();
$tracking_code = isset( $tracking_code ) ? $tracking_code : '';
$track_url     = isset( $track_url ) ? $track_url : home_url( '/my-account/track-orders/' );
$support_url   = isset( $support_url ) ? $support_url : 'mailto:support@babypasa.com';

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E16 — "Another attempt is on its way!" reassurance banner
     (positive tone; banner icon hidden on mobile via .banner-icon-td) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 14px;">
	<tr>
		<td class="banner-icon-td" style="padding:14px 0 14px 16px;vertical-align:middle;width:52px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
							<path d="M1 4v6h6" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
							<path d="M3.51 15a9 9 0 102.13-9.36L1 10" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</td>
				</tr>
			</table>
		</td>
		<td class="banner-text-td" style="padding:14px 16px 14px 12px;vertical-align:middle;">
			<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
				Another attempt is on its way!
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.6;">
				Upaya City Cargo will make another delivery attempt. They&rsquo;ll call or SMS you before they arrive &mdash; please keep your phone handy and make sure someone is home.
			</p>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E16 — tracking code box -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;margin:0 0 14px;">
	<tr>
		<td style="padding:14px 16px;">
			<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				Tracking code
			</p>
			<p style="margin:0;font-family:'Courier New',Courier,monospace;font-size:20px;font-weight:700;color:#9d174d;letter-spacing:2px;">
				<?php
				// CLIENT PLACEHOLDER: {{tracking_code}} → $tracking_code (order meta `_upaya_tracking_code`).
				echo esc_html( $tracking_code );
				?>
			</p>
		</td>
		<td style="padding:14px 16px;text-align:right;vertical-align:middle;width:60px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-left:auto;">
				<tr>
					<td style="background:#ec4899;border-radius:6px;padding:10px;text-align:center;">
						<a href="<?php echo esc_url( $track_url ); // CLIENT PLACEHOLDER: {{track_url}} → $track_url (track-order page URL). ?>" target="_blank" style="text-decoration:none;">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">
								<circle cx="11" cy="11" r="8" stroke="#ffffff" stroke-width="2" fill="none"/>
								<path d="M21 21l-4.35-4.35" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
								<path d="M11 8v6M8 11h6" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E16 — order items "What's waiting for you" -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #f3f4f6;margin:0 0 14px;">
	<tr>
		<td colspan="2" style="background:#f3f4f6;padding:10px 16px;border-bottom:1px solid #ebebeb;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				What&rsquo;s waiting for you
			</p>
		</td>
	</tr>
	<?php
	// CLIENT PLACEHOLDER: repeated <tr> per item — {{product_name}} → $bp_item['name']; {{product_qty}} → $bp_item['qty'] (loop $items).
	$bp_item_count = count( $items );
	$bp_item_i     = 0;
	foreach ( $items as $bp_item ) :
		$bp_item_i++;
		// Last item row is borderless.
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

<!-- CLIENT TEMPLATE: E16 — delivery address card so the customer can verify/correct it -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;margin:0 0 20px;">
	<tr>
		<td style="padding:14px 16px;">

			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:8px;">
				<tr>
					<td style="width:20px;vertical-align:middle;padding-right:6px;">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="#ec4899" stroke-width="1.8" fill="none"/>
							<circle cx="12" cy="9" r="2.5" stroke="#ec4899" stroke-width="1.6" fill="none"/>
						</svg>
					</td>
					<td style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
						Delivery address
					</td>
				</tr>
			</table>

			<p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#374151;line-height:1.7;">
				<?php
				// CLIENT PLACEHOLDER: {{shipping_address_1}} → $address['line1'] (+ optional line2).
				$bp_line1 = isset( $address['line1'] ) ? $address['line1'] : '';
				$bp_line2 = isset( $address['line2'] ) ? $address['line2'] : '';
				echo esc_html( $bp_line1 );
				if ( '' !== $bp_line2 ) {
					echo '<br />' . esc_html( $bp_line2 );
				}
				?>
				<br />
				<?php
				// CLIENT PLACEHOLDER: {{shipping_city}} → $address['city']; {{shipping_district}} → $address['district'].
				$bp_city     = isset( $address['city'] ) ? $address['city'] : '';
				$bp_district = isset( $address['district'] ) ? $address['district'] : '';
				echo esc_html( trim( $bp_city . ( ( '' !== $bp_city && '' !== $bp_district ) ? ', ' : '' ) . $bp_district ) );
				?>
			</p>

			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-top:1px solid #ebebeb;padding-top:10px;">
				<tr>
					<td style="padding-top:10px;">
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
							Wrong address?&nbsp;
							<a href="<?php echo esc_url( $support_url ); // CLIENT PLACEHOLDER: {{support_url}} → $support_url (support mailto). ?>" style="color:#ec4899;font-weight:700;text-decoration:none;">
								Contact us to update it
							</a>
						</p>
					</td>
				</tr>
			</table>

		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E16 — CTA "Track Your Order" -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 12px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $track_url ); // CLIENT PLACEHOLDER: {{track_url}} → $track_url (track-order page URL). ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Track Your Order
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E16 — secondary "Contact Support" link -->
<p style="margin:0 0 4px;text-align:center;">
	<a href="<?php echo esc_url( $support_url ); // CLIENT PLACEHOLDER: {{support_url}} → $support_url (support mailto). ?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ec4899;text-decoration:none;">
		Contact Support
	</a>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Close body cell + support line + feature strip + pink footer + copyright.
 */
do_action( 'woocommerce_email_footer', $email );

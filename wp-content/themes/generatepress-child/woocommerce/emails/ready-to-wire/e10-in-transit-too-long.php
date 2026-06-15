<?php
/**
 * In-transit-too-long email — BabyPasa client design (E10 In Transit Too Long).
 *
 * READY-TO-WIRE: nothing on the site includes this template yet. A future
 * plugin renders it via:
 *
 *   wc_get_template_html( 'emails/ready-to-wire/e10-in-transit-too-long.php', array( ...vars ) );
 *
 * Expected variables (set by the future sender before rendering):
 *
 * @var WC_Order      $order         The stuck order (items looped via $order->get_items()).
 * @var string        $email_heading Hero heading, e.g. "Your order is still on its way".
 * @var string        $tracking_code Upaya tracking code (order meta `_upaya_tracking_code`).
 * @var string        $track_url     Track-order URL, e.g. wc_get_account_endpoint_url( 'track-orders' ).
 * @var WC_Email|null $email         Email object (may be null — sender may use raw wp_mail()).
 *
 * Trigger / wiring summary (from DEV_NOTE_E10_in_transit_too_long.txt):
 * - CRITICAL: the Upaya webhook handler must stamp `_upaya_last_webhook_at`
 *   (timestamp) on EVERY incoming webhook hit — including suppressed events
 *   (Arrived At / Received At) that send no customer email — so the 3-day
 *   clock tracks real carrier activity, not the last emailed event.
 * - Daily cron `babypasa_daily_transit_check` scheduled at 09:00 NPT
 *   (03:15 UTC) via wp_schedule_event() on activation (cleared on
 *   deactivation). No weekend/holiday skip — Upaya operates 7 days.
 * - Handler queries orders with `_upaya_email_state` = IN_TRANSIT (statuses
 *   processing/on-hold); if `_upaya_last_webhook_at` (fallback: order
 *   created date) is >= 3 days old, fire E10.
 * - Send guards: state still IN_TRANSIT; order not cancelled/refunded/failed;
 *   `_e10_sent` order meta not set. After send: set `_e10_sent` = 1 (fires
 *   once per order) and `_e10_sent_at` = time(); optionally wp_mail() an
 *   internal "[ACTION NEEDED] Order #N stuck in transit" alert to support.
 * - Subject: "An update on your order #{{order_number}}, {{first_name}}"
 *   (neutral — never says "delayed" or "problem").
 *
 * NOTE FOR WIRING: emails/email-header.php decides the hero icon/subline via
 * a switch on $email->id. When this email gets a real WC_Email class, add a
 * case for its id (client E10 hero: clock-search icon, subline "Order
 * #{{order_number}} is in transit, {{first_name}}. / We're actively
 * monitoring it with Upaya City Cargo."). Until then the header falls back
 * to the default check icon with no subline.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// CLIENT TEMPLATE: E10 hero heading — "Your order is still on its way" (fallback if sender omits it).
if ( empty( $email_heading ) ) {
	$email_heading = 'Your order is still on its way';
}

$email = isset( $email ) ? $email : null;

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E10 — journey tracker: steps 1–3 completed, step 3 ACTIVE
     with clock icon (not checkmark) + "Checking" badge, steps 4–5 upcoming -->
<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
	Delivery journey
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 20px;">
	<tr>

		<!-- Step 1: Order Placed (completed) -->
		<td class="trk-step" style="width:20%;text-align:center;vertical-align:top;padding:0 2px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 8px;">
				<tr>
					<td style="width:30px;height:30px;background:#ec4899;border-radius:15px;text-align:center;vertical-align:middle;">
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
							<path d="M2 7l3.5 3.5L12 4" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</td>
				</tr>
			</table>
			<p class="trk-label" style="margin:0;font-size:10px;font-weight:700;color:#9d174d;line-height:1.3;font-family:Arial,Helvetica,sans-serif;">
				Order<br />placed
			</p>
		</td>

		<!-- Connector 1→2 (completed) -->
		<td class="trk-conn" style="vertical-align:top;padding-top:14px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="height:2px;background:#ec4899;font-size:0;line-height:0;">&nbsp;</td>
				</tr>
			</table>
		</td>

		<!-- Step 2: Picked Up (completed) -->
		<td class="trk-step" style="width:20%;text-align:center;vertical-align:top;padding:0 2px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 8px;">
				<tr>
					<td style="width:30px;height:30px;background:#ec4899;border-radius:15px;text-align:center;vertical-align:middle;">
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
							<path d="M2 7l3.5 3.5L12 4" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</td>
				</tr>
			</table>
			<p class="trk-label" style="margin:0;font-size:10px;font-weight:700;color:#9d174d;line-height:1.3;font-family:Arial,Helvetica,sans-serif;">
				Picked<br />up
			</p>
		</td>

		<!-- Connector 2→3 (completed) -->
		<td class="trk-conn" style="vertical-align:top;padding-top:14px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="height:2px;background:#ec4899;font-size:0;line-height:0;">&nbsp;</td>
				</tr>
			</table>
		</td>

		<!-- Step 3: In Transit (ACTIVE — clock icon + "Checking" badge, per client E10) -->
		<td class="trk-step" style="width:20%;text-align:center;vertical-align:top;padding:0 2px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 8px;">
				<tr>
					<td style="width:30px;height:30px;background:#ec4899;border:3px solid #9d174d;border-radius:15px;text-align:center;vertical-align:middle;">
						<!--
							Clock icon — replaces checkmark for E10 only.
							Signals "waiting/checking" rather than "done".
						-->
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
							<circle cx="12" cy="12" r="9" stroke="#ffffff" stroke-width="2.2" fill="none"/>
							<path d="M12 7v5l3 3" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</td>
				</tr>
			</table>
			<p class="trk-label" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#9d174d;line-height:1.3;font-family:Arial,Helvetica,sans-serif;">
				In<br />transit
			</p>
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
				<tr>
					<td style="background:#ec4899;border-radius:4px;padding:2px 6px;text-align:center;">
						<span style="font-family:Arial,Helvetica,sans-serif;font-size:9px;font-weight:700;color:#ffffff;">
							Checking
						</span>
					</td>
				</tr>
			</table>
		</td>

		<!-- Connector 3→4 (upcoming) -->
		<td class="trk-conn" style="vertical-align:top;padding-top:14px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="height:2px;background:#fbcfe8;font-size:0;line-height:0;">&nbsp;</td>
				</tr>
			</table>
		</td>

		<!-- Step 4: Out for Delivery (upcoming) -->
		<td class="trk-step" style="width:20%;text-align:center;vertical-align:top;padding:0 2px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 8px;">
				<tr>
					<td style="width:30px;height:30px;background:#ffffff;border:2px solid #fbcfe8;border-radius:15px;font-size:0;line-height:0;">&nbsp;</td>
				</tr>
			</table>
			<p class="trk-label-dim" style="margin:0;font-size:10px;color:#be185d;line-height:1.3;font-family:Arial,Helvetica,sans-serif;">
				Out for<br />delivery
			</p>
		</td>

		<!-- Connector 4→5 (upcoming) -->
		<td class="trk-conn" style="vertical-align:top;padding-top:14px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td style="height:2px;background:#fbcfe8;font-size:0;line-height:0;">&nbsp;</td>
				</tr>
			</table>
		</td>

		<!-- Step 5: Delivered (upcoming) -->
		<td class="trk-step" style="width:20%;text-align:center;vertical-align:top;padding:0 2px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 8px;">
				<tr>
					<td style="width:30px;height:30px;background:#ffffff;border:2px solid #fbcfe8;border-radius:15px;font-size:0;line-height:0;">&nbsp;</td>
				</tr>
			</table>
			<p class="trk-label-dim" style="margin:0;font-size:10px;color:#be185d;line-height:1.3;font-family:Arial,Helvetica,sans-serif;">
				Delivered
			</p>
		</td>

	</tr>
</table>

<!-- CLIENT TEMPLATE: E10 — tracking code box -->
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
						<a href="<?php echo esc_url( $track_url ); // CLIENT PLACEHOLDER: {{track_url}} → track-order page URL. ?>" target="_blank" style="text-decoration:none;">
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

<!-- CLIENT TEMPLATE: E10 — order items "What's on its way to you" -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #f3f4f6;margin:0 0 14px;">
	<tr>
		<td colspan="2" style="background:#f3f4f6;padding:10px 16px;border-bottom:1px solid #ebebeb;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				What&rsquo;s on its way to you
			</p>
		</td>
	</tr>
	<?php
	// CLIENT PLACEHOLDER: repeated <tr> per item — {{product_name}} / {{product_qty}} → $order->get_items() loop.
	$bp_order_items = $order->get_items();
	$bp_item_count  = count( $bp_order_items );
	$bp_item_i      = 0;
	foreach ( $bp_order_items as $bp_item ) :
		$bp_item_i++;
		// Last item row is borderless.
		$bp_row_border = ( $bp_item_i < $bp_item_count ) ? 'border-bottom:1px solid #f3f4f6;' : '';
		?>
	<tr class="item-row">
		<td class="item-name" style="padding:10px 16px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#1f2937;<?php echo esc_attr( $bp_row_border ); ?>">
			<?php echo esc_html( $bp_item->get_name() ); ?>
		</td>
		<td class="item-qty" style="padding:10px 16px;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:600;color:#9d174d;white-space:nowrap;<?php echo esc_attr( $bp_row_border ); ?>">
			<?php echo '&times;&nbsp;' . esc_html( (string) $bp_item->get_quantity() ); ?>
		</td>
	</tr>
	<?php endforeach; ?>
</table>

<!-- CLIENT TEMPLATE: E10 — "We're watching your order" banner (eye icon hidden on mobile via .banner-icon-td) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 20px;">
	<tr>
		<td class="banner-icon-td" style="padding:14px 0 14px 16px;vertical-align:middle;width:52px;">
			<table border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
							<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="#ffffff" stroke-width="2" fill="none"/>
							<circle cx="12" cy="12" r="3" stroke="#ffffff" stroke-width="2" fill="none"/>
						</svg>
					</td>
				</tr>
			</table>
		</td>
		<td class="banner-text-td" style="padding:14px 16px 14px 12px;vertical-align:middle;">
			<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
				We&rsquo;re watching your order
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.6;">
				Our team has flagged this order with Upaya City Cargo and is following up to make sure it reaches you as soon as possible.
			</p>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E10 — CTA "Track Your Order" -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 12px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $track_url ); // CLIENT PLACEHOLDER: {{track_url}} → track-order page URL. ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Track Your Order
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E10 — secondary "Contact Support" mailto link -->
<p style="margin:0 0 4px;text-align:center;">
	<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); // CLIENT PLACEHOLDER: {{support_url}} → support mailto (admin email; swap for support@ when wired). ?>" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ec4899;text-decoration:none;">
		Contact Support
	</a>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Close body cell + support line + feature strip + pink footer + copyright.
 */
do_action( 'woocommerce_email_footer', $email );

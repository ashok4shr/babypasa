<?php
/**
 * RTO-initiated email — BabyPasa client design (E17 RTO Initiated / Return to Origin).
 *
 * CLIENT TEMPLATE: E17 (woocommerce/Email Template and Logo/14. RTO Initiated).
 *
 * READY-TO-WIRE: nothing on the site includes this template yet. A future
 * plugin renders it via:
 *
 *   wc_get_template_html( 'emails/ready-to-wire/e17-rto-initiated.php', array( ...vars ) );
 *
 * Conditional layout (two versions, per DEV_NOTE):
 *  - Version A ($refund_info !== null, i.e. order was paid online): a two-option
 *    card (Re-order pink CTA + Request-a-refund neutral button) followed by a
 *    refund details table (amount / method / timeline / optional note), styled
 *    like customer-cancelled-order.php's refund block.
 *  - Version B ($refund_info === null, i.e. COD / unpaid): a single full-width
 *    Re-order CTA, with NO refund mention anywhere (nothing was paid).
 *
 * Expected variables (set by the future sender before rendering):
 *
 * @var WC_Order      $order        The order entering RTO.
 * @var string        $email_heading Hero heading, e.g. "An update on your order".
 * @var array         $items        Each item: name (string), qty (int).
 * @var array|null    $refund_info  null for COD/unpaid (Version B). Otherwise keys:
 *                                   amount   (string — PRE-FORMATTED via wc_price; echo, do NOT re-wrap),
 *                                   method   (string — refund label),
 *                                   note     (string — refund note; rendered only if non-empty),
 *                                   timeline (string — e.g. "3–5 business days").
 * @var string        $shop_url     Re-order URL, e.g. home_url( '/' ).
 * @var string        $support_url  Support mailto, e.g. "mailto:support@babypasa.com".
 * @var WC_Email|null $email        Email object (may be null — sender may use raw wp_mail()).
 *
 * Trigger / wiring summary (from DEV_NOTE_E17_rto_initiated.txt):
 * - TRIGGER: Upaya webhook status "Return Process". State transition Any -> RTO
 *   (update_post_meta `_upaya_email_state` = 'RTO'). Fire once: if state already
 *   RTO, skip.
 * - Send guard: `_e17_sent` order meta; set `_e17_sent` = '1' after send (one-shot).
 * - State-machine note: once state = RTO, no further delivery emails fire. The
 *   webhook handler must guard RTO / FOLLOW_UP states and only allow
 *   "RTO Complete" (-> RTO_COMPLETE, fires E20) to advance; ignore stale late
 *   out-for-delivery / delivered events.
 * - "Return Process" is currently NOT in the Upaya plugin's NOTABLE_STATUSES
 *   (deliberately narrowed to out-for-delivery + delivered); wiring E17 requires
 *   re-adding it + the RTO state machine in class-upaya-webhook-processor.php.
 * - $refund_info is built by the sender ONLY when $order->is_paid() is true; its
 *   'method'/'note' come from the shared helpers below (see "REFUND DATA" note).
 * - IMPORTANT: E17 only OFFERS a refund — the actual refund is issued later
 *   (after E20 parcel receipt + admin inspection -> E21). Never imply it is
 *   automatic; "processed 3–5 days after the parcel is received" is correct.
 * - Subject: "An update on your order #{{order_number}}, {{first_name}}" (neutral).
 * - Next in sequence: E20 RTO Complete (webhook "RTO Complete").
 *
 * REFUND DATA: this template require_once's bp-email-helpers.php and prefers the
 * sender-supplied $refund_info['method'] / ['note']. If the sender omits either
 * (empty), the template falls back to bp_email_refund_label($order) /
 * bp_email_refund_note($order). $refund_info['amount'] is already a wc_price()
 * string — it is echoed via wp_kses_post, NOT re-wrapped in wc_price().
 *
 * NOTE FOR WIRING: emails/email-header.php decides the hero icon/subline via a
 * switch on $email->id. When this email gets a real WC_Email class, add a case
 * to email-header.php's hero switch for this email id (client E17 hero: a
 * package / return icon, subline neutral "Your order is on its way back to
 * us."). Until then the header falls back to the default check icon with no
 * subline — acceptable for an inert template.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package GeneratePress_Child\WooCommerce\Emails\ReadyToWire
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Gateway-aware refund helpers (bp_email_refund_label / bp_email_refund_note),
// shared with E15 cancelled, E21 refunded and the E17/E20 return flow.
require_once get_stylesheet_directory() . '/woocommerce/emails/bp-email-helpers.php';

$email = isset( $email ) ? $email : null;

// CLIENT TEMPLATE: E17 hero heading (fallback if sender omits it).
if ( empty( $email_heading ) ) {
	$email_heading = 'An update on your order';
}

// Defensive defaults.
$items       = isset( $items ) && is_array( $items ) ? $items : array();
$refund_info = isset( $refund_info ) && is_array( $refund_info ) ? $refund_info : null;
$shop_url    = isset( $shop_url ) ? $shop_url : home_url( '/' );
$support_url = isset( $support_url ) ? $support_url : 'mailto:support@babypasa.com';

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E17 — "Items returning to us" order items list -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #f3f4f6;margin:0 0 14px;">
	<tr>
		<td colspan="2" style="background:#f3f4f6;padding:10px 16px;border-bottom:1px solid #ebebeb;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				Items returning to us
			</p>
		</td>
	</tr>
	<?php
	// CLIENT PLACEHOLDER: repeated <tr> per item — {{product_name}} → $bp_item['name']; {{product_qty}} → $bp_item['qty'] (loop $items).
	$bp_item_count = count( $items );
	$bp_item_i     = 0;
	foreach ( $items as $bp_item ) :
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

<?php
// CONDITIONAL: Version A (paid online — $refund_info set) vs Version B (COD/unpaid — null).
if ( $refund_info ) :

	// CLIENT PLACEHOLDER: {{refund_method}} → $refund_info['method'] (falls back to bp_email_refund_label( $order )).
	$bp_refund_method = ! empty( $refund_info['method'] ) ? $refund_info['method'] : bp_email_refund_label( $order );
	// CLIENT PLACEHOLDER: {{refund_note}} → $refund_info['note'] (falls back to bp_email_refund_note( $order )).
	$bp_refund_note = isset( $refund_info['note'] ) && '' !== $refund_info['note'] ? $refund_info['note'] : bp_email_refund_note( $order );
	// CLIENT PLACEHOLDER: timeline → $refund_info['timeline'] (default "3–5 business days").
	$bp_refund_timeline = ! empty( $refund_info['timeline'] ) ? $refund_info['timeline'] : '3–5 business days';
	?>

	<!-- CLIENT TEMPLATE: E17 Version A — two-option card (Re-order + Request a refund) -->
	<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
		What would you like to do?
	</p>
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 14px;">
		<tr>

			<!-- Re-order option (pink) -->
			<td class="opt-cell opt-left tile-cell tile-left" style="width:50%;vertical-align:top;padding-right:6px;">
				<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
					<tr>
						<td style="background:#fce7f3;border-radius:8px;padding:16px;border:1px solid #fbcfe8;text-align:center;">
							<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 10px;">
								<tr>
									<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
											<path d="M1 4v6h6" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M3.51 15a9 9 0 102.13-9.36L1 10" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</td>
								</tr>
							</table>
							<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
								Re-order
							</p>
							<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;line-height:1.5;">
								Update your address<br />and place a new order
							</p>
							<table class="opt-cta cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
								<tr>
									<td style="background:#ec4899;border-radius:5px;text-align:center;">
										<a href="<?php echo esc_url( $shop_url ); // CLIENT PLACEHOLDER: {{shop_url}} → $shop_url (home_url('/')). ?>" target="_blank" style="display:inline-block;padding:8px 20px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ffffff !important;text-decoration:none;">
											Shop Again
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</td>

			<!-- Request a refund option (neutral) -->
			<td class="opt-cell opt-right tile-cell tile-right" style="width:50%;vertical-align:top;padding-left:6px;">
				<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
					<tr>
						<td style="background:#f9fafb;border-radius:8px;padding:16px;border:1px solid #f3f4f6;text-align:center;">
							<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 10px;">
								<tr>
									<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
											<path d="M1 4v6h6" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M23 20v-6h-6" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M20.49 9A9 9 0 005.64 5.64L1 10M23 14l-4.64 4.36A9 9 0 013.51 15" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</td>
								</tr>
							</table>
							<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#9d174d;">
								Request a refund
							</p>
							<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#6b7280;line-height:1.5;">
								Processed 3&ndash;5 days<br />after parcel is received
							</p>
							<table class="opt-cta" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
								<tr>
									<td style="background:#f3f4f6;border-radius:5px;border:1px solid #e5e7eb;text-align:center;">
										<a href="<?php echo esc_url( $support_url ); // CLIENT PLACEHOLDER: {{support_url}} → $support_url (support mailto). ?>" style="display:inline-block;padding:8px 20px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#374151 !important;text-decoration:none;">
											Contact Us
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

	<!-- CLIENT TEMPLATE: E17 Version A — refund details table (styled like customer-cancelled-order.php) -->
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 4px;">
		<tr>
			<td style="padding:14px 16px;">
				<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
					<tr>
						<td style="padding:6px 0;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
							Refund amount
						</td>
						<td style="padding:6px 0;text-align:right;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#9d174d;white-space:nowrap;">
							<?php
							// CLIENT PLACEHOLDER: Rs. {{order_total}} → $refund_info['amount'] (already a wc_price() string — echo, do NOT re-wrap).
							echo wp_kses_post( $refund_info['amount'] );
							?>
						</td>
					</tr>
					<tr>
						<td style="padding:6px 0;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
							Refund method
						</td>
						<td style="padding:6px 0;text-align:right;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#374151;">
							<?php echo esc_html( $bp_refund_method ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding:6px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;">
							Processed after parcel received
						</td>
						<td style="padding:6px 0;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#374151;white-space:nowrap;">
							<?php echo esc_html( $bp_refund_timeline ); ?>
						</td>
					</tr>
				</table>
				<?php if ( '' !== $bp_refund_note ) : ?>
					<p style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;line-height:1.6;">
						<?php echo esc_html( $bp_refund_note ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

<?php else : ?>

	<!-- CLIENT TEMPLATE: E17 Version B — COD/unpaid: single full-width Re-order card, NO refund mention -->
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 4px;">
		<tr>
			<td style="padding:24px;text-align:center;">
				<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 10px;">
					<tr>
						<td style="width:36px;height:36px;background:#ec4899;border-radius:18px;text-align:center;vertical-align:middle;">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
								<path d="M1 4v6h6" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
								<path d="M3.51 15a9 9 0 102.13-9.36L1 10" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
							</svg>
						</td>
					</tr>
				</table>
				<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#9d174d;">
					Would you like to re-order?
				</p>
				<p style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.6;">
					Update your delivery address and we&rsquo;ll get it to you.<br />
					Free delivery across Nepal, every time.
				</p>
				<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto;">
					<tr>
						<td style="background:#ec4899;border-radius:6px;text-align:center;">
							<a href="<?php echo esc_url( $shop_url ); // CLIENT PLACEHOLDER: {{shop_url}} → $shop_url (home_url('/')). ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
								Shop Again
							</a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::email_footer() Close body cell + support line + feature strip + pink footer + copyright.
 */
do_action( 'woocommerce_email_footer', $email );

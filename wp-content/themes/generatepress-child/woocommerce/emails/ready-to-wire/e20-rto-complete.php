<?php
/**
 * RTO-complete email — BabyPasa client design (E20 RTO Complete / parcel back at warehouse).
 *
 * CLIENT TEMPLATE: E20 (woocommerce/Email Template and Logo/17. RTO Complete).
 *
 * READY-TO-WIRE: nothing on the site includes this template yet. A future
 * plugin renders it via:
 *
 *   wc_get_template_html( 'emails/ready-to-wire/e20-rto-complete.php', array( ...vars ) );
 *
 * Conditional layout (two versions, per DEV_NOTE) — SHORTER than E17 (no options card):
 *  - Version A ($refund_info !== null, i.e. order was paid online): a refund
 *    details block (amount / method / timeline / optional note) plus the line
 *    "You'll receive a confirmation email once processed." (E21 follows).
 *  - Version B ($refund_info === null, i.e. COD / unpaid): a simple "Return
 *    complete" confirmation block, with NO refund mention anywhere.
 *
 * Expected variables (set by the future sender before rendering):
 *
 * @var WC_Order      $order        The order whose parcel is back at the warehouse.
 * @var string        $email_heading Hero heading, e.g. "We've received your returned parcel".
 * @var array         $return_items Items received back (read from `_return_items` meta set by
 *                                   E18; fallback to all order items). Each: name, qty.
 * @var array|null    $refund_info  null for COD/unpaid (Version B). Otherwise keys:
 *                                   amount   (string — PRE-FORMATTED via wc_price; echo, do NOT re-wrap),
 *                                   method   (string — refund label),
 *                                   note     (string — refund note; rendered only if non-empty),
 *                                   timeline (string — e.g. "3–5 business days").
 * @var WC_Email|null $email        Email object (may be null — sender may use raw wp_mail()).
 *
 * Trigger / wiring summary (from DEV_NOTE_E20_rto_complete.txt):
 * - TRIGGER: Upaya webhook status "RTO Complete". State transition RTO ->
 *   RTO_COMPLETE (update_post_meta `_upaya_email_state` = 'RTO_COMPLETE'); only
 *   advance if the current state is exactly 'RTO'.
 * - Send guard: `_e20_sent` order meta; set `_e20_sent` = '1' after send (one-shot).
 * - $return_items: read `_return_items` (json_decode), fallback to all order items.
 *   $refund_info is built ONLY when $order->is_paid() is true.
 * - "RTO Complete" is currently NOT in the Upaya plugin's NOTABLE_STATUSES
 *   (deliberately narrowed to out-for-delivery + delivered); wiring E20 requires
 *   re-adding it + the RTO state machine in class-upaya-webhook-processor.php.
 * - IMPORTANT: E20 says the refund is BEING PROCESSED — it is not issued yet.
 *   Admin inspects the parcel and issues the refund in WooCommerce, which fires
 *   E21 (customer-refunded-order.php, woocommerce_order_refunded). An optional
 *   internal "[ACTION NEEDED] returned parcel received" alert can be sent too.
 * - Subject: "We've received your returned parcel — Order #{{order_number}}".
 * - Next in sequence: E21 Refund Processed (LIVE as customer-refunded-order.php).
 *
 * REFUND DATA: this template require_once's bp-email-helpers.php and prefers the
 * sender-supplied $refund_info['method'] / ['note']. If the sender omits either
 * (empty), the template falls back to bp_email_refund_label($order) /
 * bp_email_refund_note($order). $refund_info['amount'] is already a wc_price()
 * string — it is echoed via wp_kses_post, NOT re-wrapped in wc_price().
 *
 * NOTE FOR WIRING: emails/email-header.php decides the hero icon/subline via a
 * switch on $email->id. When this email gets a real WC_Email class, add a case
 * to email-header.php's hero switch for this email id (client E20 hero: a
 * warehouse / check icon, subline "Your parcel is back with us."). Until then
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

// Gateway-aware refund helpers (bp_email_refund_label / bp_email_refund_note),
// shared with E15 cancelled, E21 refunded and the E17/E20 return flow.
require_once get_stylesheet_directory() . '/woocommerce/emails/bp-email-helpers.php';

$email = isset( $email ) ? $email : null;

// CLIENT TEMPLATE: E20 hero heading (fallback if sender omits it).
if ( empty( $email_heading ) ) {
	$email_heading = "We've received your returned parcel";
}

// Defensive defaults.
$return_items = isset( $return_items ) && is_array( $return_items ) ? $return_items : array();
$refund_info  = isset( $refund_info ) && is_array( $refund_info ) ? $refund_info : null;

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E20 — "Returned items received" list -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #f3f4f6;margin:0 0 14px;">
	<tr>
		<td colspan="2" style="background:#f3f4f6;padding:10px 16px;border-bottom:1px solid #ebebeb;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				Returned items received
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

	<!-- CLIENT TEMPLATE: E20 Version A — refund details block (styled like customer-cancelled-order.php) -->
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 4px;">
		<tr>
			<td style="padding:16px;">

				<!-- Refund header -->
				<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:12px;">
					<tr>
						<td class="refund-icon-td" style="vertical-align:middle;padding-right:8px;">
							<table border="0" cellpadding="0" cellspacing="0" role="presentation">
								<tr>
									<td style="width:28px;height:28px;background:#ec4899;border-radius:6px;text-align:center;vertical-align:middle;">
										<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
											<path d="M1 4v6h6" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M23 20v-6h-6" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M20.49 9A9 9 0 005.64 5.64L1 10M23 14l-4.64 4.36A9 9 0 013.51 15" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</td>
								</tr>
							</table>
						</td>
						<td style="vertical-align:middle;">
							<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#9d174d;">
								Your refund is being processed
							</p>
						</td>
					</tr>
				</table>

				<!-- Refund rows -->
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
							Expected within
						</td>
						<td style="padding:6px 0;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#374151;white-space:nowrap;">
							<?php echo esc_html( $bp_refund_timeline ); ?>
						</td>
					</tr>
				</table>

				<p style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;line-height:1.6;">
					<?php
					if ( '' !== $bp_refund_note ) {
						echo esc_html( $bp_refund_note ) . ' ';
					}
					?>
					You&rsquo;ll receive a confirmation email once it&rsquo;s processed.
				</p>

			</td>
		</tr>
	</table>

<?php else : ?>

	<!-- CLIENT TEMPLATE: E20 Version B — COD/unpaid: simple "Return complete" confirmation, NO refund mention -->
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;margin:0 0 4px;">
		<tr>
			<td style="padding:20px;text-align:center;">
				<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#1f2937;">
					Return complete
				</p>
				<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;line-height:1.6;">
					We&rsquo;ve received your items and your return has been completed. Thank you for shopping with BabyPasa.
				</p>
			</td>
		</tr>
	</table>

<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::email_footer() Close body cell + support line + feature strip + pink footer + copyright.
 */
do_action( 'woocommerce_email_footer', $email );

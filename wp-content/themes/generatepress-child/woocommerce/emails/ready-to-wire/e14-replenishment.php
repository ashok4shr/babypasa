<?php
/**
 * Ready-to-wire email template — Replenishment Reminder.
 *
 * CLIENT TEMPLATE: E14 (woocommerce/Email Template and Logo/8. Replenishment Reminder).
 *
 * INERT: no sender is wired to this template yet. Render it from a future
 * plugin with:
 *   wc_get_template_html(
 *       'emails/ready-to-wire/e14-replenishment.php',
 *       array( 'email_heading' => $heading, 'replenish_items' => $items, ... )
 *   );
 *
 * Expected variables:
 *   string   $email_heading   Hero heading, e.g. "Time to stock up, {first}?".
 *   array    $replenish_items List of replenishable items. Each item is an array:
 *                               'name'             => string  Product name.
 *                               'days_since_order' => int     Whole days since last ordered.
 *                               'reorder_url'      => string  Add-to-cart URL for this item.
 *                               'in_stock'         => bool    Whether the product is purchasable now.
 *   string   $reorder_all_url Bulk reorder endpoint URL (/reorder/{order_id}).
 *   WC_Email $email           Email object (may be null in previews).
 *
 * Wiring summary (see ready-to-wire/README.md and DEV_NOTE_E14_replenishment.txt):
 *   - Scheduled via wp_schedule_single_event() from the "delivered" handler,
 *     ONLY for orders containing replenishable products.
 *   - Replenishable flag = product meta `_is_replenishable` ('yes'); interval =
 *     `_replenishment_days` (defaults: diapers 25, wipes 20, formula 28,
 *     general 30). Schedule for the minimum interval across the order's items.
 *   - Guard against resending with the `_e14_sent` order meta flag.
 *   - Per-item reorder_url = add_query_arg( array( 'add-to-cart' => $pid,
 *     'quantity' => $qty ), wc_get_cart_url() ).
 *   - reorder_all_url = a custom /reorder/{order_id} endpoint that adds all
 *     replenishable items to the cart (verify order ownership, clear cart first).
 *   - Out-of-stock items show a "Back soon" label instead of the Reorder button.
 *   - When wiring, add a `customer_replenishment_reminder` (or chosen id) case
 *     to email-header.php's hero switch for the refresh icon + subline.
 *
 * @package GeneratePress_Child\WooCommerce\Emails\ReadyToWire
 */

defined( 'ABSPATH' ) || exit;

$email           = $email ?? null;
$replenish_items = isset( $replenish_items ) && is_array( $replenish_items ) ? $replenish_items : array();
$bp_item_count   = count( $replenish_items );

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E14 — product list -->
<p style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
	Your essentials
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#f9fafb;border-radius:8px;border:1px solid #f3f4f6;overflow:hidden;margin:0 0 16px;">
	<?php
	$bp_i = 0;
	foreach ( $replenish_items as $bp_item ) {
		++$bp_i;
		// Drop the bottom border on the last row (email-client-safe: no :last-child).
		$bp_border = ( $bp_i === $bp_item_count ) ? '' : 'border-bottom:1px solid #f3f4f6;';
		$bp_name   = isset( $bp_item['name'] ) ? $bp_item['name'] : '';
		$bp_days   = isset( $bp_item['days_since_order'] ) ? (int) $bp_item['days_since_order'] : 0;
		$bp_url    = isset( $bp_item['reorder_url'] ) ? $bp_item['reorder_url'] : '';
		$bp_stock  = ! empty( $bp_item['in_stock'] );
		?>
		<tr>
			<td class="prod-name-td" style="padding:14px 16px;vertical-align:middle;<?php echo esc_attr( $bp_border ); ?>">
				<p style="margin:0 0 3px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#1f2937;">
					<?php
					// CLIENT PLACEHOLDER: {{product_name}} → item 'name'.
					echo esc_html( $bp_name );
					?>
				</p>
				<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
					<?php
					// CLIENT PLACEHOLDER: {{days_since_order}} → item 'days_since_order'.
					printf(
						/* translators: %d: whole days since last ordered */
						esc_html__( 'Last ordered %d days ago', 'generatepress-child' ),
						$bp_days
					);
					?>
				</p>
			</td>
			<td class="prod-btn-td" style="padding:14px 16px;text-align:right;vertical-align:middle;white-space:nowrap;<?php echo esc_attr( $bp_border ); ?>">
				<?php if ( $bp_stock && $bp_url ) : ?>
					<!-- CLIENT PLACEHOLDER: {{product_reorder_url}} → item 'reorder_url'. -->
					<a class="reorder-btn" href="<?php echo esc_url( $bp_url ); ?>" target="_blank" style="display:inline-block;background:#ec4899;color:#ffffff !important;font-size:12px;font-weight:700;padding:7px 16px;border-radius:5px;text-decoration:none;font-family:Arial,Helvetica,sans-serif;white-space:nowrap;">
						Reorder
					</a>
				<?php else : ?>
					<!-- Out-of-stock fallback per DEV_NOTE_E14. -->
					<span style="display:inline-block;background:#f3f4f6;color:#9ca3af;font-size:12px;font-weight:700;padding:7px 16px;border-radius:5px;font-family:Arial,Helvetica,sans-serif;white-space:nowrap;">
						Back soon
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}
	?>
</table>

<!-- CLIENT TEMPLATE: E14 — Reorder Everything CTA -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;text-align:center;">
						<a href="<?php echo esc_url( $reorder_all_url ); ?>" target="_blank" style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
							Reorder Everything
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<!-- CLIENT TEMPLATE: E14 — free delivery nudge -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;">
	<tr>
		<td style="padding:14px 16px;vertical-align:middle;width:44px;">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">
				<rect x="1" y="6" width="13" height="10" rx="1" stroke="#ec4899" stroke-width="1.8" fill="none"/>
				<path d="M14 9h4.5L21 12.5V16H14V9Z" stroke="#ec4899" stroke-width="1.8" fill="none" stroke-linejoin="round"/>
				<circle cx="5.5" cy="17.5" r="1.5" fill="#ec4899"/>
				<circle cx="17.5" cy="17.5" r="1.5" fill="#ec4899"/>
			</svg>
		</td>
		<td style="padding:14px 16px 14px 8px;vertical-align:middle;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#be185d;line-height:1.6;">
				Free delivery on every order, every time &mdash; straight to your door across Nepal.
			</p>
		</td>
	</tr>
</table>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer (support line, feature strip, footer band).
 */
do_action( 'woocommerce_email_footer', $email );

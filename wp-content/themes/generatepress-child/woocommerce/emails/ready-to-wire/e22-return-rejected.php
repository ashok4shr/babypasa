<?php
/**
 * Return-rejected email — BabyPasa client design (E22 Return Rejected).
 *
 * Rendered by BP_Email_Return_Rejected (babypasa-returns plugin) via:
 *   wc_get_template_html( 'emails/ready-to-wire/e22-return-rejected.php', array( ...vars ) );
 *
 * Expected variables:
 *
 * @var WC_Order      $order         The order whose return was rejected.
 * @var string        $email_heading Hero heading.
 * @var array         $return_items  Items the customer asked to return. Each: name, qty.
 * @var string        $reject_reason Optional admin-supplied reason for the rejection.
 * @var string        $support_url   Support mailto, e.g. "mailto:support@babypasa.com".
 * @var WC_Email|null $email         Email object.
 *
 * Hero icon/subline for this email id (bp_return_rejected) are defined in
 * emails/email-header.php's switch.
 *
 * @package GeneratePress_Child\WooCommerce\Emails\ReadyToWire
 * @version 10.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email = isset( $email ) ? $email : null;

if ( empty( $email_heading ) ) {
	$email_heading = 'About your return request';
}

$return_items  = isset( $return_items ) && is_array( $return_items ) ? $return_items : array();
$reject_reason = isset( $reject_reason ) ? trim( (string) $reject_reason ) : '';
$support_url   = isset( $support_url ) ? $support_url : 'mailto:support@babypasa.com';

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero band, opens body cell).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<!-- CLIENT TEMPLATE: E22 — explanatory line -->
<p style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.6;">
	We&rsquo;ve reviewed your return request for order
	<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>, and unfortunately we&rsquo;re
	unable to approve it at this time.
</p>

<!-- CLIENT TEMPLATE: E22 — items the customer requested to return -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #f3f4f6;margin:0 0 14px;">
	<tr>
		<td colspan="2" style="background:#f3f4f6;padding:10px 16px;border-bottom:1px solid #ebebeb;">
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;">
				Items in your request
			</p>
		</td>
	</tr>
	<?php
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

<?php if ( '' !== $reject_reason ) : ?>
<!-- CLIENT TEMPLATE: E22 — reason box (only when admin supplied a reason) -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fef2f2;border-radius:8px;border:1px solid #fecaca;margin:0 0 16px;">
	<tr>
		<td style="padding:14px 16px;">
			<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:0.5px;">
				Reason
			</p>
			<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#7f1d1d;line-height:1.5;">
				<?php echo esc_html( $reject_reason ); ?>
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- CLIENT TEMPLATE: E22 — support line -->
<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#374151;line-height:1.6;">
	If you believe this was a mistake or have any questions, our team is happy to help &mdash;
	just <a href="<?php echo esc_url( $support_url ); ?>" style="color:#ec4899;font-weight:700;text-decoration:none;">contact support</a>.
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Close body cell + support line + feature strip + pink footer + copyright.
 */
do_action( 'woocommerce_email_footer', $email );

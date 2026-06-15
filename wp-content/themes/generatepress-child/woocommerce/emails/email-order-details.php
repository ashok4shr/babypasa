<?php
/**
 * Order details table shown in emails — BabyPasa client design (E03 order summary card).
 *
 * Pink card header (order number + date), client-styled item rows
 * (rendered by email-order-items.php), totals rows with a FREE badge for
 * zero-cost delivery, and a pink Total row.
 *
 * All WooCommerce action hooks from the stock template are preserved.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Action hook to add custom content before order details in email.
 *
 * @param WC_Order $order Order object.
 * @param bool     $sent_to_admin Whether it's sent to admin or customer.
 * @param bool     $plain_text Whether it's a plain text email.
 * @param WC_Email $email Email object.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<!-- CLIENT TEMPLATE: E03 — Order summary card -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="border-radius:8px;overflow:hidden;border:1px solid #fbcfe8;margin:0 0 16px;">

	<!-- Card header: order # + date -->
	<tr>
		<td colspan="2" style="background:#ec4899;padding:10px 16px;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td>
						<span class="order-hdr-num" style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ffffff;">
							<?php
							// CLIENT PLACEHOLDER: {{order_number}} → $order->get_order_number().
							if ( $sent_to_admin ) {
								echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '" style="color:#ffffff;text-decoration:none;">Order #' . esc_html( $order->get_order_number() ) . '</a>';
							} else {
								echo 'Order #' . esc_html( $order->get_order_number() );
							}
							?>
						</span>
					</td>
					<td align="right">
						<span class="order-hdr-date" style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#fce7f3;">
							<?php
							// CLIENT PLACEHOLDER: {{order_date}} → wc_format_datetime( $order->get_date_created() ).
							echo esc_html( wc_format_datetime( $order->get_date_created() ) );
							?>
						</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<?php
	// Product rows — rendered by the overridden emails/email-order-items.php.
	echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$order,
		array(
			'show_sku'      => $sent_to_admin,
			'show_image'    => false,
			'image_size'    => array( 48, 48 ),
			'plain_text'    => $plain_text,
			'sent_to_admin' => $sent_to_admin,
		)
	);
	?>

	<?php
	$item_totals       = $order->get_order_item_totals();
	$item_totals_count = count( $item_totals );

	if ( $item_totals ) {
		$i = 0;
		foreach ( $item_totals as $total_type => $total ) {
			++$i;
			$is_last = ( $i === $item_totals_count );

			if ( $is_last ) :
				// Total row — pink band (client design).
				?>
				<tr class="order-totals order-totals-<?php echo esc_attr( $total['type'] ?? $total_type ); ?> order-totals-last">
					<th scope="row" style="padding:13px 16px;background:#fce7f3;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;color:#9d174d;text-align:left;">
						<?php echo wp_kses_post( $total['label'] ); ?>
					</th>
					<td style="padding:13px 16px;background:#fce7f3;text-align:right;white-space:nowrap;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#9d174d;">
						<?php
						// CLIENT PLACEHOLDER: {{order_total}} → formatted order total (wc_price via get_order_item_totals).
						echo wp_kses_post( $total['value'] );
						?>
					</td>
				</tr>
				<?php
			elseif ( 'shipping' === $total_type && 0.0 === (float) $order->get_shipping_total() ) :
				// Delivery row with FREE badge (client design).
				?>
				<tr class="order-totals order-totals-shipping">
					<th scope="row" style="padding:10px 16px;background:#ffffff;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:400;color:#6b7280;text-align:left;">
						Delivery
					</th>
					<td style="padding:10px 16px;background:#ffffff;border-bottom:1px solid #fbcfe8;text-align:right;">
						<span style="background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:700;font-family:Arial,Helvetica,sans-serif;">
							FREE
						</span>
					</td>
				</tr>
				<?php
			else :
				?>
				<tr class="order-totals order-totals-<?php echo esc_attr( $total['type'] ?? $total_type ); ?>">
					<th scope="row" style="padding:10px 16px;background:#ffffff;border-bottom:1px solid #fbcfe8;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:400;color:#6b7280;text-align:left;">
						<?php
						echo wp_kses_post( $total['label'] );
						echo isset( $total['meta'] ) ? ' ' . wp_kses_post( $total['meta'] ) : '';
						?>
					</th>
					<td style="padding:10px 16px;background:#ffffff;border-bottom:1px solid #fbcfe8;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;white-space:nowrap;">
						<?php echo wp_kses_post( $total['value'] ); ?>
					</td>
				</tr>
				<?php
			endif;
		}
	}
	?>

</table>

<?php if ( $order->get_customer_note() ) : ?>
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
		<tr>
			<td style="background:#f9fafb;border:1px solid #f3f4f6;border-radius:8px;padding:14px;">
				<p style="margin:0 0 6px;font-size:10px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
					<?php esc_html_e( 'Customer note', 'woocommerce' ); ?>
				</p>
				<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;line-height:1.6;">
					<?php echo wp_kses( nl2br( wc_wptexturize_order_note( $order->get_customer_note() ) ), array( 'br' => array() ) ); ?>
				</p>
			</td>
		</tr>
	</table>
<?php endif; ?>

<?php
/**
 * Action hook to add custom content after order details in email.
 *
 * @param WC_Order $order Order object.
 * @param bool     $sent_to_admin Whether it's sent to admin or customer.
 * @param bool     $plain_text Whether it's a plain text email.
 * @param WC_Email $email Email object.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
?>

<?php
/**
 * Return-request form (My Account → request-return endpoint).
 *
 * Rendered by BP_Returns_Request::render_form(). Available:
 *
 * @var WC_Order $order Eligible order the customer is requesting a return for.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;
?>
<h2 style="color:#9d174d;"><?php esc_html_e( 'Request a return', 'babypasa-returns' ); ?></h2>
<p>
	<?php
	printf(
		/* translators: %s: order number */
		esc_html__( 'Select the item(s) you would like to return from order #%s, and tell us why. Our team will review your request within 1–2 business days.', 'babypasa-returns' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bp-return-form">
	<input type="hidden" name="action" value="bp_request_return" />
	<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
	<?php wp_nonce_field( 'bp_request_return_' . $order->get_id(), 'bp_return_nonce' ); ?>

	<table class="shop_table" style="margin-bottom:18px;">
		<thead>
			<tr>
				<th style="width:36px;">&nbsp;</th>
				<th><?php esc_html_e( 'Item', 'babypasa-returns' ); ?></th>
				<th style="text-align:right;"><?php esc_html_e( 'Qty', 'babypasa-returns' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
				<tr>
					<td style="text-align:center;vertical-align:middle;">
						<input type="checkbox" name="return_items[]" value="<?php echo esc_attr( $item_id ); ?>" id="bp-return-item-<?php echo esc_attr( $item_id ); ?>" checked="checked" />
					</td>
					<td>
						<label for="bp-return-item-<?php echo esc_attr( $item_id ); ?>">
							<?php echo esc_html( $item->get_name() ); ?>
						</label>
					</td>
					<td style="text-align:right;">&times;&nbsp;<?php echo esc_html( $item->get_quantity() ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="form-row form-row-wide">
		<label for="bp-return-reason"><?php esc_html_e( 'Reason for return', 'babypasa-returns' ); ?></label>
		<textarea name="return_reason" id="bp-return-reason" rows="4" class="input-text" style="width:100%;" placeholder="<?php esc_attr_e( 'Tell us what went wrong (optional)', 'babypasa-returns' ); ?>"></textarea>
	</p>

	<p class="form-row">
		<button type="submit" class="button" style="background:#ec4899;color:#fff;border-radius:6px;padding:12px 28px;font-weight:700;">
			<?php esc_html_e( 'Submit return request', 'babypasa-returns' ); ?>
		</button>
	</p>
</form>

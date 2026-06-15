<?php
/**
 * Additional Customer Details — BabyPasa client design (tile box).
 *
 * Extra customer data (filterable by plugins), shown below the order
 * details. Restyled as a client-design gray tile.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 9.7.0
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( ! empty( $fields ) ) : ?>
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="margin:0 0 16px;">
		<tr>
			<td style="background:#f9fafb;border:1px solid #f3f4f6;border-radius:8px;padding:14px;">
				<p style="margin:0 0 8px;font-size:10px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
					<?php esc_html_e( 'Customer details', 'woocommerce' ); ?>
				</p>
				<?php foreach ( $fields as $field ) : ?>
					<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;line-height:1.6;">
						<strong style="color:#374151;"><?php echo wp_kses_post( $field['label'] ); ?>:</strong>
						<span class="text"><?php echo wp_kses_post( $field['value'] ); ?></span>
					</p>
				<?php endforeach; ?>
			</td>
		</tr>
	</table>
<?php endif; ?>

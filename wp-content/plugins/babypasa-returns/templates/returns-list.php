<?php
/**
 * "Returns & Exchanges" My Account tab.
 *
 * Rendered by BP_Returns_Request::render_account_returns(). Available:
 *
 * @var array     $active   In-progress returns:
 *                          [ ['order'=>WC_Order,'state'=>string,'label'=>string,'items'=>array], ... ]
 * @var WC_Order[] $eligible Orders still eligible for a new return request.
 *
 * @package BabyPasa_Returns
 */

defined( 'ABSPATH' ) || exit;
?>

<!-- Start a new return -->
<details class="bp-returns-new" style="margin:0 0 28px;">
	<summary style="display:inline-block;background:#ec4899;color:#fff;border-radius:6px;padding:10px 20px;font-weight:700;cursor:pointer;list-style:none;">
		<?php esc_html_e( 'Request a return', 'babypasa-returns' ); ?>
	</summary>
	<div style="margin-top:14px;">
		<?php if ( empty( $eligible ) ) : ?>
			<p style="margin:0;color:#6b7280;">
				<?php esc_html_e( 'You have no orders eligible for a return right now. Returns can be requested on completed orders within the return window.', 'babypasa-returns' ); ?>
			</p>
		<?php else : ?>
			<p style="margin:0 0 10px;color:#374151;">
				<?php esc_html_e( 'Choose the order you would like to return:', 'babypasa-returns' ); ?>
			</p>
			<table class="shop_table shop_table_responsive">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order', 'babypasa-returns' ); ?></th>
						<th><?php esc_html_e( 'Date', 'babypasa-returns' ); ?></th>
						<th><?php esc_html_e( 'Total', 'babypasa-returns' ); ?></th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $eligible as $order ) :
						$req_url = wc_get_endpoint_url( BP_Returns_Request::ENDPOINT, $order->get_id(), wc_get_page_permalink( 'myaccount' ) );
						?>
						<tr>
							<td data-title="<?php esc_attr_e( 'Order', 'babypasa-returns' ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></td>
							<td data-title="<?php esc_attr_e( 'Date', 'babypasa-returns' ); ?>"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
							<td data-title="<?php esc_attr_e( 'Total', 'babypasa-returns' ); ?>"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
							<td>
								<a href="<?php echo esc_url( $req_url ); ?>" class="button" style="background:#ec4899;color:#fff;border-radius:6px;">
									<?php esc_html_e( 'Request a return', 'babypasa-returns' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</details>

<h3 style="color:#9d174d;margin:0 0 12px;"><?php esc_html_e( 'Your returns', 'babypasa-returns' ); ?></h3>

<?php if ( empty( $active ) ) : ?>
	<p style="margin:0;color:#6b7280;">
		<?php esc_html_e( 'You have no returns in progress.', 'babypasa-returns' ); ?>
	</p>
<?php else : ?>
	<table class="shop_table shop_table_responsive">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order', 'babypasa-returns' ); ?></th>
				<th><?php esc_html_e( 'Items', 'babypasa-returns' ); ?></th>
				<th><?php esc_html_e( 'Status', 'babypasa-returns' ); ?></th>
				<th>&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $active as $row ) :
				$order = $row['order'];
				$names = array();
				foreach ( $row['items'] as $it ) {
					$names[] = $it['name'] . ' × ' . (int) $it['qty'];
				}
				?>
				<?php $bp_ref = BP_Returns_State::get_display_reference( $order ); ?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Order', 'babypasa-returns' ); ?>">
						#<?php echo esc_html( $order->get_order_number() ); ?>
						<?php if ( ( '#' . $order->get_order_number() ) !== $bp_ref ) : ?>
							<br><small style="color:#6b7280;"><?php esc_html_e( 'Ref:', 'babypasa-returns' ); ?> <?php echo esc_html( $bp_ref ); ?></small>
						<?php endif; ?>
					</td>
					<td data-title="<?php esc_attr_e( 'Items', 'babypasa-returns' ); ?>"><?php echo esc_html( implode( ', ', $names ) ); ?></td>
					<td data-title="<?php esc_attr_e( 'Status', 'babypasa-returns' ); ?>"><?php echo esc_html( $row['label'] ); ?></td>
					<td>
						<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button">
							<?php esc_html_e( 'View order', 'babypasa-returns' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

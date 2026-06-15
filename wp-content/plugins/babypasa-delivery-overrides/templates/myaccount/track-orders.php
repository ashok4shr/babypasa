<?php
/**
 * My Account — Track Orders page template.
 *
 * Variables available (set by BP_Order_Tracking_Account::render_endpoint()):
 *   $customer_orders  array       WC_Order[] — orders with _upaya_submitted = '1'
 *   $manager          UPAYA_Order_Manager
 *
 * @package BabyPasa_Delivery_Overrides
 */

defined( 'ABSPATH' ) || exit;
?>

<?php if ( empty( $customer_orders ) ) : ?>

	<?php wc_print_notice(
		__( 'No tracked orders found. Orders appear here once they have been dispatched with Upaya Cargo.', 'babypasa-delivery-overrides' ),
		'notice'
	); ?>

<?php else : ?>

<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
	<thead>
		<tr>
			<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number">
				<?php esc_html_e( 'Order', 'babypasa-delivery-overrides' ); ?>
			</th>
			<th class="woocommerce-orders-table__header">
				<?php esc_html_e( 'Date', 'babypasa-delivery-overrides' ); ?>
			</th>
			<th class="woocommerce-orders-table__header">
				<?php esc_html_e( 'Upaya Tracking ID', 'babypasa-delivery-overrides' ); ?>
			</th>
			<th class="woocommerce-orders-table__header">
				<?php esc_html_e( 'Status', 'babypasa-delivery-overrides' ); ?>
			</th>
			<th class="woocommerce-orders-table__header">
				<?php esc_html_e( 'Est. Delivery', 'babypasa-delivery-overrides' ); ?>
			</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $customer_orders as $order ) : ?>
			<?php
			/** @var WC_Order $order */
			$order_id   = $order->get_id();
			$upaya_id   = $order->get_meta( '_upaya_order_id' );
			$view_url   = wc_get_account_endpoint_url( 'view-order' ) . $order_id;

			$tracking_status   = '';
			$tracking_delivery = '';

			if ( ! empty( $upaya_id ) ) {
				$tracking = $manager->get_tracking_info( $order_id );

				if ( ! is_wp_error( $tracking ) && is_array( $tracking ) ) {
					$tracking_status   = $tracking['status']            ?? '';
					$tracking_delivery = $tracking['estimated_delivery'] ?? '';
				}

				// Fallback: when the live API returns no status, show the most
				// recent status Upaya pushed via webhook (stored in order meta).
				if ( '' === $tracking_status ) {
					$tracking_status = (string) $order->get_meta( '_upaya_last_status_label' );
				}
			}
			?>
			<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?>">

				<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number"
					data-title="<?php esc_attr_e( 'Order', 'babypasa-delivery-overrides' ); ?>">
					<a href="<?php echo esc_url( $view_url ); ?>">
						#<?php echo esc_html( $order->get_order_number() ); ?>
					</a>
				</td>

				<td class="woocommerce-orders-table__cell"
					data-title="<?php esc_attr_e( 'Date', 'babypasa-delivery-overrides' ); ?>">
					<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
				</td>

				<td class="woocommerce-orders-table__cell"
					data-title="<?php esc_attr_e( 'Upaya Tracking ID', 'babypasa-delivery-overrides' ); ?>">
					<?php if ( ! empty( $upaya_id ) ) : ?>
						<code><?php echo esc_html( $upaya_id ); ?></code>
					<?php else : ?>
						<span class="bp-tracking-awaiting"><?php esc_html_e( '—', 'babypasa-delivery-overrides' ); ?></span>
					<?php endif; ?>
				</td>

				<td class="woocommerce-orders-table__cell"
					data-title="<?php esc_attr_e( 'Status', 'babypasa-delivery-overrides' ); ?>">
					<?php if ( '' !== $tracking_status ) : ?>
						<span class="bp-tracking-status"><?php echo esc_html( $tracking_status ); ?></span>
					<?php elseif ( ! empty( $upaya_id ) ) : ?>
						<span class="bp-tracking-awaiting"><?php esc_html_e( 'Awaiting update', 'babypasa-delivery-overrides' ); ?></span>
					<?php else : ?>
						<span class="bp-tracking-awaiting"><?php esc_html_e( 'Not submitted', 'babypasa-delivery-overrides' ); ?></span>
					<?php endif; ?>
				</td>

				<td class="woocommerce-orders-table__cell"
					data-title="<?php esc_attr_e( 'Est. Delivery', 'babypasa-delivery-overrides' ); ?>">
					<?php echo '' !== $tracking_delivery ? esc_html( $tracking_delivery ) : '—'; ?>
				</td>

			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php endif; ?>

<?php
/**
 * Settings page view — action buttons and orders-sync section.
 *
 * Variables available from UPAYA_Admin::output_settings():
 *   string              $nonce
 *   UPAYA_Location_Cache $location_cache
 *   int                 $pending_count
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;
?>

<h2><?php esc_html_e( 'API Tools', 'upaya-cargo-woocommerce' ); ?></h2>

<table class="form-table" role="presentation">
	<tbody>

		<tr>
			<th scope="row"><?php esc_html_e( 'Webhook URL', 'upaya-cargo-woocommerce' ); ?></th>
			<td>
				<code id="upaya-webhook-url"><?php echo esc_html( UPAYA_Webhook::get_url() ); ?></code>
				<p class="description">
					<?php esc_html_e( 'Enter this URL in the Upaya Cargo dashboard to receive live shipment status updates.', 'upaya-cargo-woocommerce' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'API Connection', 'upaya-cargo-woocommerce' ); ?></th>
			<td>
				<button type="button" id="upaya-test-connection"
					class="button button-secondary"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Test API Connection', 'upaya-cargo-woocommerce' ); ?>
				</button>
				<span id="upaya-test-connection-result" class="upaya-ajax-result" aria-live="polite"></span>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Location Cache', 'upaya-cargo-woocommerce' ); ?></th>
			<td>
				<button type="button" id="upaya-flush-cache"
					class="button button-secondary"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Flush Location Cache', 'upaya-cargo-woocommerce' ); ?>
				</button>
				<span id="upaya-flush-cache-result" class="upaya-ajax-result" aria-live="polite"></span>
			</td>
		</tr>

	</tbody>
</table>

<h2><?php esc_html_e( 'Orders Sync', 'upaya-cargo-woocommerce' ); ?></h2>

<table class="form-table" role="presentation">
	<tbody>

		<tr>
			<th scope="row"><?php esc_html_e( 'Pending Submissions', 'upaya-cargo-woocommerce' ); ?></th>
			<td>
				<strong id="upaya-pending-count"><?php echo absint( $pending_count ); ?></strong>
				<?php esc_html_e( 'order(s) awaiting Upaya submission.', 'upaya-cargo-woocommerce' ); ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Manual Sync', 'upaya-cargo-woocommerce' ); ?></th>
			<td>
				<button type="button" id="upaya-sync-pending"
					class="button button-secondary"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Manually Sync Pending Orders', 'upaya-cargo-woocommerce' ); ?>
				</button>
				<span id="upaya-sync-result" class="upaya-ajax-result" aria-live="polite"></span>
				<p class="description">
					<?php esc_html_e( 'Processes up to 20 pending orders per click.', 'upaya-cargo-woocommerce' ); ?>
				</p>
			</td>
		</tr>

	</tbody>
</table>

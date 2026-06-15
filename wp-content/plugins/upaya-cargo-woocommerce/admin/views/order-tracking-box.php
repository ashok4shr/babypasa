<?php
/**
 * Order tracking meta box view.
 *
 * Variables available from UPAYA_Meta_Box::render_meta_box():
 *   int    $order_id
 *   string $upaya_id
 *   bool   $submitted
 *   string $display_status   pending|submitted|failed
 *   string $nonce
 *   array|\WP_Error $tracking
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

$badge_map = [
	'submitted' => [ 'label' => __( 'Submitted', 'upaya-cargo-woocommerce' ), 'class' => 'upaya-badge upaya-badge--success' ],
	'failed'    => [ 'label' => __( 'Failed',    'upaya-cargo-woocommerce' ), 'class' => 'upaya-badge upaya-badge--error' ],
	'pending'   => [ 'label' => __( 'Pending',   'upaya-cargo-woocommerce' ), 'class' => 'upaya-badge upaya-badge--warning' ],
];

$badge = $badge_map[ $display_status ] ?? $badge_map['pending'];
?>

<div id="upaya-meta-box-content"
	data-order-id="<?php echo absint( $order_id ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>">

	<p>
		<strong><?php esc_html_e( 'Submission Status:', 'upaya-cargo-woocommerce' ); ?></strong>
		<span class="<?php echo esc_attr( $badge['class'] ); ?>">
			<?php echo esc_html( $badge['label'] ); ?>
		</span>
	</p>

	<?php if ( $upaya_id ) : ?>
		<p>
			<strong><?php esc_html_e( 'Upaya Order ID:', 'upaya-cargo-woocommerce' ); ?></strong><br>
			<code><?php echo esc_html( $upaya_id ); ?></code>
		</p>
	<?php endif; ?>

	<?php
	// ── Tracking section ──────────────────────────────────────────────
	if ( ! is_wp_error( $tracking ) && ! empty( $tracking ) ) :
		$track_status   = esc_html( $tracking['status']             ?? '' );
		$track_est_date = esc_html( $tracking['estimated_delivery'] ?? '' );
		$track_items    = $tracking['items'] ?? [];
	?>
		<hr>
		<p><strong><?php esc_html_e( 'Tracking', 'upaya-cargo-woocommerce' ); ?></strong></p>

		<?php if ( $track_status ) : ?>
			<p>
				<?php esc_html_e( 'Status:', 'upaya-cargo-woocommerce' ); ?>
				<strong><?php echo $track_status; ?></strong>
			</p>
		<?php endif; ?>

		<?php if ( $track_est_date ) : ?>
			<p>
				<?php esc_html_e( 'Est. Delivery:', 'upaya-cargo-woocommerce' ); ?>
				<strong><?php echo $track_est_date; ?></strong>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $track_items ) ) : ?>
			<table class="widefat striped upaya-tracking-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Item',  'upaya-cargo-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Qty',   'upaya-cargo-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Price', 'upaya-cargo-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $track_items as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['name']     ?? '' ); ?></td>
							<td><?php echo esc_html( $item['quantity'] ?? '' ); ?></td>
							<td><?php echo esc_html( $item['price']    ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; // end tracking section ?>

	<p class="upaya-meta-actions">
		<?php if ( ! $submitted || 'failed' === $display_status ) : ?>
			<button type="button" class="button button-secondary upaya-btn-resubmit">
				<?php esc_html_e( 'Re-submit to Upaya', 'upaya-cargo-woocommerce' ); ?>
			</button>
		<?php endif; ?>

		<?php if ( $upaya_id ) : ?>
			<button type="button" class="button button-secondary upaya-btn-refresh-tracking">
				<?php esc_html_e( 'Refresh Tracking', 'upaya-cargo-woocommerce' ); ?>
			</button>
		<?php endif; ?>
	</p>

	<div id="upaya-meta-message" aria-live="polite"></div>

</div>

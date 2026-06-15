<?php
/**
 * Checkout — Saved address picker template.
 * Renders above the billing form when the logged-in user has saved addresses.
 *
 * Variables available: $addresses (array[])
 *
 * @package BabyPasa_Address_Book
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="bp-checkout-picker" id="bp-checkout-picker">

	<h3 class="bp-checkout-picker__title">
		<?php esc_html_e( 'Your Saved Addresses', 'babypasa-address-book' ); ?>
	</h3>

	<div class="bp-checkout-picker__cards" role="list">
		<?php foreach ( $addresses as $addr ) : ?>
			<div class="bp-picker-card<?php echo $addr['is_default'] ? ' bp-picker-card--default' : ''; ?>"
				role="listitem button"
				tabindex="0"
				data-address-id="<?php echo esc_attr( $addr['id'] ); ?>"
				data-address="<?php echo esc_attr( wp_json_encode( $addr ) ); ?>"
				title="<?php echo esc_attr( sprintf(
					/* translators: %s: address nickname */
					__( 'Fill billing form with %s address', 'babypasa-address-book' ),
					$addr['nickname']
				) ); ?>">

				<span class="bp-picker-card__label"><?php echo esc_html( $addr['nickname'] ); ?></span>
				<span class="bp-picker-card__area"><?php echo esc_html( $addr['city'] . ', ' . $addr['state'] ); ?></span>
				<?php if ( $addr['address_1'] ) : ?>
					<span class="bp-picker-card__address"><?php echo esc_html( $addr['address_1'] ); ?></span>
				<?php endif; ?>
				<span class="bp-picker-card__phone"><?php echo esc_html( $addr['phone'] ); ?></span>

			</div>
		<?php endforeach; ?>
	</div>

	<p class="bp-checkout-picker__hint">
		<?php esc_html_e( 'Click a card to fill your billing details instantly.', 'babypasa-address-book' ); ?>
	</p>

</div>

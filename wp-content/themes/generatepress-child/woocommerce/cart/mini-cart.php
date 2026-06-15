<?php
/**
 * Mini-cart drawer template override.
 *
 * Extends the native WooCommerce mini-cart with inline quantity controls and
 * a per-item remove button so the AJAX drawer can update the cart without a
 * page reload. All native WooCommerce action hooks are preserved so
 * third-party plugins continue to work.
 *
 * Overrides: woocommerce/templates/cart/mini-cart.php
 * @version 10.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_mini_cart' );
?>

<?php if ( WC()->cart && ! WC()->cart->is_empty() ) : ?>

	<ul class="woocommerce-mini-cart cart_list product_list_widget <?php echo esc_attr( $args['list_class'] ); ?>">
		<?php
		do_action( 'woocommerce_before_mini_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
			$_product        = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id      = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
				continue;
			}

			if ( ! apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}

			$product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
			$thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
			$product_price     = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
			$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
			?>
			<li class="woocommerce-mini-cart-item <?php echo esc_attr( apply_filters( 'woocommerce_mini_cart_item_class', 'mini_cart_item', $cart_item, $cart_item_key ) ); ?>">

				<?php /* Remove button — plain <button> so WooCommerce's cart.js does not intercept it */ ?>
				<button type="button"
				        class="bp-mc-remove"
				        data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
				        aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ); ?>">&times;</button>

				<?php /* Thumbnail + name */ ?>
				<?php if ( empty( $product_permalink ) ) : ?>
					<div class="bp-mc-item-image"><?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="bp-mc-item-details">
						<span class="bp-mc-item-name"><?php echo wp_kses_post( $product_name ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( $product_permalink ); ?>" class="bp-mc-item-link">
						<div class="bp-mc-item-image"><?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					</a>
					<div class="bp-mc-item-details">
						<a href="<?php echo esc_url( $product_permalink ); ?>" class="bp-mc-item-name"><?php echo wp_kses_post( $product_name ); ?></a>
				<?php endif; ?>

					<?php /* Variation attributes */ ?>
					<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					<?php /* Quantity controls + line subtotal */ ?>
					<div class="bp-mc-item-row">
						<div class="bp-mc-qty-wrap">
							<button type="button"
							        class="bp-mc-qty-minus"
							        data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
							        aria-label="<?php esc_attr_e( 'Decrease quantity', 'woocommerce' ); ?>"
							        <?php echo ( $cart_item['quantity'] <= 1 ) ? 'disabled' : ''; ?>>&#8722;</button>
							<input type="number"
							       class="bp-mc-qty-input"
							       value="<?php echo esc_attr( $cart_item['quantity'] ); ?>"
							       min="1"
							       step="1"
							       data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
							       aria-label="<?php echo esc_attr( sprintf( __( 'Quantity for %s', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ); ?>" />
							<button type="button"
							        class="bp-mc-qty-plus"
							        data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
							        aria-label="<?php esc_attr_e( 'Increase quantity', 'woocommerce' ); ?>">&#43;</button>
						</div>

						<span class="bp-mc-line-total">
							<?php echo apply_filters( 'woocommerce_widget_cart_item_quantity', '<span class="quantity">' . wp_kses_post( $product_price ) . '</span>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
					</div>

				</div><?php /* end .bp-mc-item-details */ ?>

			</li>
		<?php endforeach; ?>

		<?php do_action( 'woocommerce_mini_cart_contents' ); ?>
	</ul>

	<div class="bp-mc-footer">
		<p class="woocommerce-mini-cart__total total">
			<?php
			/**
			 * Hook: woocommerce_widget_shopping_cart_total.
			 * @hooked woocommerce_widget_shopping_cart_subtotal - 10
			 */
			do_action( 'woocommerce_widget_shopping_cart_total' );
			?>
		</p>

		<?php do_action( 'woocommerce_widget_shopping_cart_before_buttons' ); ?>

		<p class="woocommerce-mini-cart__buttons buttons">
			<?php do_action( 'woocommerce_widget_shopping_cart_buttons' ); ?>
		</p>

		<?php do_action( 'woocommerce_widget_shopping_cart_after_buttons' ); ?>
	</div>

<?php else : ?>

	<div class="bp-mc-empty-state">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="currentColor" aria-hidden="true">
			<path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2zm6 16H6V8h2v2c0 .55.45 1 1 1s1-.45 1-1V8h4v2c0 .55.45 1 1 1s1-.45 1-1V8h2v12z"/>
		</svg>
		<p class="woocommerce-mini-cart__empty-message"><?php esc_html_e( 'Your cart is empty.', 'woocommerce' ); ?></p>
		<a href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>" class="button wc-forward bp-mc-shop-btn">
			<?php esc_html_e( 'Continue shopping', 'woocommerce' ); ?>
		</a>
	</div>

<?php endif; ?>

<?php do_action( 'woocommerce_after_mini_cart' ); ?>

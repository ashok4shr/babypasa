<?php
/**
 * Cart shipping-cost display tweaks.
 *
 * Hides the per-method shipping price in the cart (classic + block cart) and
 * replaces it with a "calculated during checkout" notice. This belongs to the
 * delivery domain: actual shipping cost is resolved by Upaya Cargo at checkout,
 * so the cart must not display a misleading figure.
 *
 * Migrated from functions.php — 2026-06-05
 *
 * @package BabyPasa_Delivery_Overrides
 */

defined( 'ABSPATH' ) || exit;

class BP_Cart_Shipping_Display {

	public function __construct() {
		// Classic cart: strip the price from each shipping method label.
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ $this, 'hide_shipping_cost_in_cart' ], 10, 2 );

		// Block cart: hide the shipping price value + inject the notice via CSS/JS.
		add_action( 'wp_footer', [ $this, 'replace_shipping_text_in_block_cart' ] );
	}

	/**
	 * Classic cart — replace the method's price with a notice, keep the name.
	 *
	 * @param string $label
	 * @param object $method WC_Shipping_Rate.
	 * @return string
	 */
	public function hide_shipping_cost_in_cart( $label, $method ) {
		if ( is_cart() ) {
			$label = $method->get_label() . ': <em>Delivery fee will be calculated during checkout</em>';
		}
		return $label;
	}

	/**
	 * Block cart — hide the shipping price value and inject the notice.
	 *
	 * The block cart renders client-side, so the notice is injected with a small
	 * inline script that re-runs on cart mutations (quantity changes, etc.).
	 */
	public function replace_shipping_text_in_block_cart() {
		if ( ! is_cart() ) {
			return;
		}
		?>
		<style>
			/* Hide the price value in the shipping row */
			.wp-block-woocommerce-cart-order-summary-shipping-block .wc-block-components-totals-item__value {
				display: none !important;
			}
			/* Hide the shipping calculator if it appears */
			.wc-block-components-totals-shipping__via {
				display: none !important;
			}
		</style>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				function injectShippingNotice() {
					const shippingItem = document.querySelector(
						'.wp-block-woocommerce-cart-order-summary-shipping-block .wc-block-components-totals-item'
					);

					if ( shippingItem && ! shippingItem.querySelector('.custom-shipping-notice') ) {
						// Remove any existing price
						const priceEl = shippingItem.querySelector('.wc-block-components-totals-item__value');
						if ( priceEl ) priceEl.style.display = 'none';

						// Inject the custom message
						const notice = document.createElement('em');
						notice.className = 'custom-shipping-notice';
						notice.style.cssText = 'font-size: 0.85rem; color: #888; display: block; margin-top: 4px;';
						notice.textContent = 'Delivery fee will be calculated during checkout';
						shippingItem.appendChild( notice );
					}
				}

				injectShippingNotice();

				// Re-run when cart updates (quantity changes etc.)
				const observer = new MutationObserver( injectShippingNotice );
				observer.observe( document.body, { childList: true, subtree: true } );
			});
		</script>
		<?php
	}
}

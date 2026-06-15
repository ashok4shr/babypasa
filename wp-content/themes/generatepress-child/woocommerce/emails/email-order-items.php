<?php
/**
 * Email Order Items — BabyPasa client design (E03 item rows).
 *
 * Two-column rows: product name + "Qty: n" stacked left, line total right.
 * All WooCommerce hooks and filters from the stock template are preserved.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.7.0
 */

defined( 'ABSPATH' ) || exit;

$margin_side = is_rtl() ? 'left' : 'right';

foreach ( $items as $item_id => $item ) :
	$product       = $item->get_product();
	$sku           = '';
	$purchase_note = '';
	$image         = '';

	if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
		continue;
	}

	if ( is_object( $product ) ) {
		$sku           = $product->get_sku();
		$purchase_note = $product->get_purchase_note();
		$image         = $product->get_image( $image_size );
	}

	// Refund-aware quantity display (stock behaviour preserved).
	$qty          = $item->get_quantity();
	$refunded_qty = $order->get_qty_refunded_for_item( $item_id );

	if ( $refunded_qty ) {
		$qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * -1 ) ) . '</ins>';
	} else {
		$qty_display = esc_html( $qty );
	}

	?>
	<!-- CLIENT TEMPLATE: E03 — product row -->
	<tr class="item-row <?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $order ) ); ?>">
		<td style="padding:12px 16px;border-bottom:1px solid #fbcfe8;background:#ffffff;vertical-align:top;word-wrap:break-word;">
			<?php
			if ( $show_image ) {
				/**
				 * Email Order Item Thumbnail hook.
				 *
				 * @param string                $image The image HTML.
				 * @param WC_Order_Item_Product $item  The item being displayed.
				 * @since 2.1.0
				 */
				echo wp_kses_post( apply_filters( 'woocommerce_order_item_thumbnail', $image, $item ) );
			}
			?>
			<p class="item-name" style="margin:0;font-weight:600;color:#1f2937;font-size:13px;font-family:Arial,Helvetica,sans-serif;">
				<?php
				/**
				 * Order Item Name hook.
				 *
				 * @param string                $item_name The item name HTML.
				 * @param WC_Order_Item_Product $item      The item being displayed.
				 * @since 2.1.0
				 */
				// CLIENT PLACEHOLDER: {{product_name}} → $item->get_name() (filterable).
				echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );

				// SKU (admin emails only).
				if ( $show_sku && $sku ) {
					echo wp_kses_post( ' <span style="font-weight:400;color:#6b7280;font-size:12px;">(#' . $sku . ')</span>' );
				}
				?>
			</p>
			<?php
			/**
			 * Allow other plugins to add additional product information.
			 *
			 * @param int                   $item_id    The item ID.
			 * @param WC_Order_Item_Product $item       The item object.
			 * @param WC_Order              $order      The order object.
			 * @param bool                  $plain_text Whether the email is plain text or not.
			 * @since 2.3.0
			 */
			do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, $plain_text );

			$item_meta = wc_display_item_meta(
				$item,
				array(
					'before'       => '',
					'after'        => '',
					'separator'    => '<br>',
					'echo'         => false,
					'label_before' => '<span>',
					'label_after'  => ':</span> ',
				)
			);
			if ( $item_meta ) {
				echo '<div class="email-order-item-meta" style="margin:4px 0 0;font-size:12px;color:#6b7280;font-family:Arial,Helvetica,sans-serif;line-height:1.5;">';
				// Using wp_kses instead of wp_kses_post to remove all block elements.
				echo wp_kses(
					$item_meta,
					array(
						'br'   => array(),
						'span' => array(),
						'a'    => array(
							'href'   => true,
							'target' => true,
							'rel'    => true,
							'title'  => true,
						),
					)
				);
				echo '</div>';
			}

			/**
			 * Allow other plugins to add additional product information.
			 *
			 * @param int                   $item_id    The item ID.
			 * @param WC_Order_Item_Product $item       The item object.
			 * @param WC_Order              $order      The order object.
			 * @param bool                  $plain_text Whether the email is plain text or not.
			 * @since 2.3.0
			 */
			do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, $plain_text );
			?>
			<p class="item-qty" style="margin:4px 0 0;color:#9d174d;font-size:12px;font-family:Arial,Helvetica,sans-serif;">
				<?php
				// CLIENT PLACEHOLDER: {{product_qty}} → $item->get_quantity() (refund-aware, filterable).
				echo 'Qty: ' . wp_kses_post( apply_filters( 'woocommerce_email_order_item_quantity', $qty_display, $item ) );
				?>
			</p>
		</td>
		<td class="item-price" style="padding:12px 16px;border-bottom:1px solid #fbcfe8;background:#ffffff;vertical-align:top;text-align:right;white-space:nowrap;font-weight:600;color:#1f2937;font-size:13px;font-family:Arial,Helvetica,sans-serif;">
			<?php
			// CLIENT PLACEHOLDER: Rs. {{product_total}} → $order->get_formatted_line_subtotal( $item ) (store currency formatting).
			echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) );
			?>
		</td>
	</tr>
	<?php

	if ( $show_purchase_note && $purchase_note ) {
		?>
		<tr>
			<td colspan="2" style="padding:8px 16px;border-bottom:1px solid #fbcfe8;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280;">
				<?php echo wp_kses_post( wpautop( do_shortcode( $purchase_note ) ) ); ?>
			</td>
		</tr>
		<?php
	}
	?>

<?php endforeach; ?>

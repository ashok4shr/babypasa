<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // WPCS: XSS ok.
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'bp-single-product-wrapper', $product ); ?>>

	<div class="bp-product-top-container">
		<div class="bp-product-gallery-column">
			<?php
			/**
			 * Hook: woocommerce_before_single_product_summary.
			 *
			 * @hooked woocommerce_show_product_sale_flash - 10
			 * @hooked woocommerce_show_product_images - 20
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>

		<div class="bp-product-info-column summary entry-summary">
			<h1 class="product_title entry-title"><?php the_title(); ?></h1>
			
			<div class="bp-product-review-summary">
				<a href="#reviews" class="woocommerce-review-link" rel="nofollow">Be the first to review this product</a>
			</div>

			<div class="bp-product-price-row">
				<span class="bp-single-price"><?php echo $product->get_price_html(); ?></span>
				<?php
				// Savings badge. For simple products we compute it server-side; for
				// variable products the .bp-savings span is filled by single-product.js
				// as the shopper picks a variation (found_variation event).
				if ( ! $product->is_type( 'variable' ) && $product->is_on_sale() ) {
					$regular = (float) wc_get_price_to_display( $product, array( 'price' => $product->get_regular_price() ) );
					$active  = (float) wc_get_price_to_display( $product );
					if ( $regular > 0 && $active > 0 && $active < $regular ) {
						$pct = round( ( ( $regular - $active ) / $regular ) * 100 );
						printf(
							'<span class="bp-savings">%s</span>',
							wp_kses_post( sprintf(
								/* translators: 1: amount saved 2: percentage */
								__( 'Save %1$s (%2$d%%)', 'generatepress-child' ),
								wc_price( $regular - $active ),
								$pct
							) )
						);
					}
				} elseif ( $product->is_type( 'variable' ) ) {
					echo '<span class="bp-savings" hidden></span>';
				}
				?>
			</div>

			<div class="bp-product-meta-row">
				<div class="bp-stock-status <?php echo $product->is_in_stock() ? 'in-stock' : 'out-of-stock'; ?>">
					<span class="bp-stock-icon">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
					</span>
					<?php echo $product->is_in_stock() ? esc_html__( 'In stock', 'generatepress-child' ) : esc_html__( 'Out of stock', 'generatepress-child' ); ?>
					<?php
					// Low-stock urgency nudge: only when WC manages stock and qty is 1–4.
					if ( $product->is_in_stock() && $product->managing_stock() ) {
						$qty = $product->get_stock_quantity();
						if ( is_numeric( $qty ) && (int) $qty >= 1 && (int) $qty <= 4 ) {
							printf(
								'<span class="bp-low-stock-urgency">%s</span>',
								esc_html( sprintf(
									/* translators: %d: remaining stock count */
									__( 'Only %d left — Buy Now!', 'generatepress-child' ),
									(int) $qty
								) )
							);
						}
					}
					?>
				</div>
				<div class="bp-sku-row">
					<strong>SKU#:</strong> <?php echo ( $sku = $product->get_sku() ) ? $sku : 'N/A'; ?>
				</div>
			</div>

			<div class="bp-product-overview">
				<h3>Overview</h3>
				<div class="bp-short-description">
					<?php echo apply_filters( 'woocommerce_short_description', $post->post_excerpt ); ?>
				</div>
			</div>

			<div class="bp-price-drop-notify">
				<?php 
				$current_user_id = get_current_user_id();
				$product_id = $product->get_id();
				$meta_key = '_bp_price_alert_' . $product_id;
				$is_subscribed = $current_user_id ? get_user_meta( $current_user_id, $meta_key, true ) : false;
				
				if ( $is_subscribed ) : ?>
					<a href="#" class="bp-notify-link bp-notified" data-product_id="<?php echo esc_attr( $product_id ); ?>" onclick="return false;">Added to Price drop notification</a>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'price-drop-alerts' ) ); ?>" class="bp-show-alerts-link">Show alerts</a>
				<?php else : ?>
					<a href="#" class="bp-notify-link" data-product_id="<?php echo esc_attr( $product_id ); ?>">Notify me when the price drops</a>
				<?php endif; ?>
			</div>

			<div class="bp-single-add-to-cart">
				<?php if ( $product instanceof WC_Product_Variable ) :
					$available_variations = $product->get_available_variations();
					$variations_json      = $available_variations === false ? 'false' : esc_attr( wp_json_encode( $available_variations ) );
				?>
				<form class="variations_form cart"
				      action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>"
				      method="post"
				      enctype="multipart/form-data"
				      data-product_id="<?php echo absint( $product->get_id() ); ?>"
				      data-product_variations="<?php echo $variations_json; ?>">

					<table class="variations bp-variations-table" cellspacing="0" role="presentation">
						<tbody>
							<?php foreach ( $product->get_variation_attributes() as $attribute_name => $options ) : ?>
							<tr>
								<th class="label bp-variation-label">
									<label for="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
										<?php echo wp_kses_post( wc_attribute_label( $attribute_name ) ); ?>
									</label>
								</th>
								<td class="value bp-variation-value">
									<?php
									wc_dropdown_variation_attribute_options( array(
										'options'   => $options,
										'attribute' => $attribute_name,
										'product'   => $product,
									) );
									?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<a class="reset_variations" href="#"><?php esc_html_e( 'Clear selection', 'woocommerce' ); ?></a>

					<div class="single_variation_wrap">
						<div class="woocommerce-variation single_variation"></div>

						<div class="woocommerce-variation-add-to-cart variations_button">
							<div class="bp-qty-label">Qty</div>
							<div class="bp-quantity-wrapper">
								<button type="button" class="bp-qty-btn bp-minus" aria-label="Decrease quantity">-</button>
								<?php
								woocommerce_quantity_input( array(
									'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
									'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
									'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // WPCS: CSRF ok, input var ok.
								) );
								?>
								<button type="button" class="bp-qty-btn bp-plus" aria-label="Increase quantity">+</button>
							</div>
							<button type="submit" class="single_add_to_cart_button button alt">
								<?php echo esc_html( $product->single_add_to_cart_text() ); ?>
							</button>
							<input type="hidden" name="add-to-cart" value="<?php echo absint( $product->get_id() ); ?>" />
							<input type="hidden" name="variation_id" class="variation_id" value="0" />
						</div>
					</div>

				</form>
				<?php else : ?>
				<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
					<div class="bp-qty-label">Qty</div>
					<div class="bp-quantity-wrapper">
						<button type="button" class="bp-qty-btn bp-minus" aria-label="Decrease quantity">-</button>
						<?php
						woocommerce_quantity_input( array(
							'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
							'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
							'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // WPCS: CSRF ok, input var ok.
						) );
						?>
						<button type="button" class="bp-qty-btn bp-plus" aria-label="Increase quantity">+</button>
					</div>
					<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>
				</form>
				<?php endif; ?>
			</div>

			<div class="bp-delivery-info-box">
				<div class="bp-delivery-icon">
					<svg viewBox="0 0 24 24" width="32" height="32" fill="currentColor"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
				</div>
				<div class="bp-delivery-text">
					<h4>Delivery: 1-2 Working Days</h4>
					<p>Expected Delivery Date is <?php echo date('jS F, Y', strtotime('+2 days')); ?></p>
				</div>
			</div>

			<div class="bp-product-actions-links">
				<a href="#" class="bp-action-link bp-wishlist-btn" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="bp-inline-icon"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
					<span class="bp-text-default">ADD TO WISH LIST</span>
					<span class="bp-text-active" style="display:none;">Wishlist ✔</span>
				</a>
				<a href="#" class="bp-action-link bp-compare-btn" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="bp-inline-icon"><polyline points="16 3 21 3 21 8"></polyline><line x1="4" y1="20" x2="21" y2="3"></line><polyline points="21 16 21 21 16 21"></polyline><line x1="15" y1="15" x2="21" y2="21"></line><line x1="4" y1="4" x2="9" y2="9"></line></svg>
					<span class="bp-text-default">ADD TO COMPARE</span>
					<span class="bp-text-active" style="display:none;">Compare ✔</span>
				</a>
				
			</div>
		</div>
	</div>

	<div class="bp-product-bottom-container">
		<?php
		$bp_videos = json_decode( get_post_meta( $product->get_id(), '_bp_product_videos', true ), true );
		if ( ! empty( $bp_videos ) ) : ?>
		<div class="bp-videos-section">
			<h2 class="bp-section-title">Videos</h2>
			<div class="bp-videos-grid">
				<?php foreach ( $bp_videos as $bp_video_url ) : ?>
				<div class="bp-video-thumb" data-video="<?php echo esc_url( $bp_video_url ); ?>">
					<video preload="metadata" muted>
						<source src="<?php echo esc_url( $bp_video_url ); ?>#t=0.1" />
					</video>
					<button class="bp-video-thumb-play" aria-label="Play video">
						<svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					</button>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="bp-product-tabs" id="bp-product-tabs">
			<?php $bp_review_count = (int) get_comments_number(); ?>
			<div class="bp-tabs-nav" role="tablist" aria-label="<?php esc_attr_e( 'Product information', 'generatepress-child' ); ?>">
				<button type="button" class="bp-tab-btn is-active" id="bp-tab-details" role="tab" aria-selected="true" aria-controls="bp-panel-details">
					<?php esc_html_e( 'Details', 'generatepress-child' ); ?>
				</button>
				<button type="button" class="bp-tab-btn" id="bp-tab-reviews" role="tab" aria-selected="false" aria-controls="bp-panel-reviews" tabindex="-1">
					<?php esc_html_e( 'Reviews', 'generatepress-child' ); ?>
					<?php if ( $bp_review_count > 0 ) : ?><span class="bp-tab-count"><?php echo esc_html( $bp_review_count ); ?></span><?php endif; ?>
				</button>
			</div>

			<div class="bp-tab-panel is-active bp-description-section" id="bp-panel-details" role="tabpanel" aria-labelledby="bp-tab-details">
				<?php the_content(); ?>
			</div>

			<div class="bp-tab-panel bp-reviews-section" id="bp-panel-reviews" role="tabpanel" aria-labelledby="bp-tab-reviews" hidden>
				<div id="reviews">
					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
					?>
				</div>
			</div>
		</div>

		<?php
		// Related Products Slider
		$related_ids = wc_get_related_products( $product->get_id(), 10 );
		if ( ! empty( $related_ids ) ) {
			bp_render_product_slider_section( 'RELATED PRODUCTS', '', false, $related_ids );
		}

		// Upsells / Random Products Slider
		$upsell_ids = $product->get_upsell_ids();
		if ( empty( $upsell_ids ) ) {
			// Get random products if no upsells defined
			$random_query = new WP_Query( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 10,
				'orderby'        => 'rand',
				'post__not_in'   => array( $product->get_id() ),
			) );
			$upsell_ids = wp_list_pluck( $random_query->posts, 'ID' );
		}
		
		if ( ! empty( $upsell_ids ) ) {
			bp_render_product_slider_section( 'WE FOUND OTHER PRODUCTS YOU MIGHT LIKE!', '', false, $upsell_ids );
		}
		?>
	</div>

	<div class="bp-video-modal" id="bp-video-modal" role="dialog" aria-modal="true" aria-label="Product video" hidden>
		<div class="bp-video-modal-overlay"></div>
		<div class="bp-video-modal-content">
			<button class="bp-video-modal-close" aria-label="Close video">&times;</button>
			<video class="bp-video-player" controls preload="none"></video>
		</div>
	</div>

</div>

<?php do_action( 'woocommerce_after_single_product' ); ?>

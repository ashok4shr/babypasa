<?php
/**
 * Single Product Image — BabyPasa custom slider override.
 *
 * Replaces WooCommerce's default flexslider/photoswipe gallery with a
 * lightweight, dependency-free slider (see assets/js/product-gallery.js).
 *
 * COMPATIBILITY: every slide is still produced via wc_get_gallery_image_html()
 * and passed through the `woocommerce_single_product_image_thumbnail_html`
 * filter — exactly as WooCommerce core does — so third-party plugins
 * (product video, 360° view, etc.) can continue to inject markup.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

// wc_get_gallery_image_html was added in WC 3.3.2.
if ( ! function_exists( 'wc_get_gallery_image_html' ) ) {
	return;
}

global $product;

$columns           = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
$post_thumbnail_id = $product->get_image_id();
$gallery_ids       = $product->get_gallery_image_ids();

// Ordered list of attachment IDs: featured image first, then gallery images.
$slide_ids = array();
if ( $post_thumbnail_id ) {
	$slide_ids[] = $post_thumbnail_id;
}
foreach ( $gallery_ids as $gallery_id ) {
	if ( (int) $gallery_id !== (int) $post_thumbnail_id ) {
		$slide_ids[] = $gallery_id;
	}
}

$has_images   = ! empty( $slide_ids );
$slide_count  = count( $slide_ids );
$has_multiple = $slide_count > 1;

// Keep the .woocommerce-product-gallery class so existing CSS and any plugin
// JS that targets it still resolve; layer our own bp-gallery hooks on top.
$wrapper_classes = apply_filters(
	'woocommerce_single_product_image_gallery_classes',
	array(
		'bp-gallery',
		'woocommerce-product-gallery',
		'woocommerce-product-gallery--' . ( $has_images ? 'with-images' : 'without-images' ),
		'woocommerce-product-gallery--columns-' . absint( $columns ),
		'images',
	)
);
?>
<div
	class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>"
	data-columns="<?php echo esc_attr( $columns ); ?>"
	data-slide-count="<?php echo esc_attr( $slide_count ); ?>"
>
	<div class="bp-gallery__stage<?php echo $has_multiple ? '' : ' bp-gallery__stage--single'; ?>"
	     role="region"
	     aria-roledescription="<?php esc_attr_e( 'carousel', 'generatepress-child' ); ?>"
	     aria-label="<?php esc_attr_e( 'Product gallery', 'generatepress-child' ); ?>"
	     tabindex="0">

		<div class="bp-gallery__viewport">
			<div class="bp-gallery__track">
				<?php
				if ( $has_images ) :
					$index = 0;
					foreach ( $slide_ids as $attachment_id ) :
						$index++;
						// $main = true on every slide so each carries the full-size
						// data-large_image attribute the magnifier zoom relies on.
						$html = wc_get_gallery_image_html( $attachment_id, true );
						?>
						<div class="bp-gallery__slide"
						     role="group"
						     aria-roledescription="<?php esc_attr_e( 'slide', 'generatepress-child' ); ?>"
						     aria-label="<?php echo esc_attr( sprintf( /* translators: 1: current slide 2: total slides */ __( '%1$d of %2$d', 'generatepress-child' ), $index, $slide_count ) ); ?>"
						     <?php echo $index > 1 ? 'aria-hidden="true"' : ''; ?>>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $html, $attachment_id );
							?>
						</div>
						<?php
					endforeach;
				else :
					$placeholder = sprintf(
						'<div class="woocommerce-product-gallery__image--placeholder"><img src="%s" alt="%s" class="wp-post-image" /></div>',
						esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ),
						esc_attr__( 'Awaiting product image', 'woocommerce' )
					);
					?>
					<div class="bp-gallery__slide" role="group" aria-label="<?php esc_attr_e( 'Product image', 'generatepress-child' ); ?>">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $placeholder, $post_thumbnail_id );
						?>
					</div>
					<?php
				endif;
				?>
			</div>

			<?php if ( $has_multiple ) : ?>
				<button type="button" class="bp-gallery__nav bp-gallery__nav--prev" aria-label="<?php esc_attr_e( 'Previous image', 'generatepress-child' ); ?>">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
				</button>
				<button type="button" class="bp-gallery__nav bp-gallery__nav--next" aria-label="<?php esc_attr_e( 'Next image', 'generatepress-child' ); ?>">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</button>
			<?php endif; ?>

			<?php if ( $has_images ) : ?>
				<button type="button" class="bp-gallery__zoom-btn" aria-label="<?php esc_attr_e( 'Zoom image (full screen)', 'generatepress-child' ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
				</button>
			<?php endif; ?>

			<?php if ( $has_multiple ) : ?>
				<div class="bp-gallery__counter" aria-hidden="true">
					<span class="bp-gallery__counter-current">1</span> / <?php echo esc_html( $slide_count ); ?>
				</div>
			<?php endif; ?>
		</div><!-- .bp-gallery__stage -->
	</div>

	<?php if ( $has_multiple ) : ?>
		<div class="bp-gallery__thumbs" role="tablist" aria-label="<?php esc_attr_e( 'Product image thumbnails', 'generatepress-child' ); ?>">
			<?php
			$thumb_index = 0;
			foreach ( $slide_ids as $attachment_id ) :
				$thumb_html = wp_get_attachment_image(
					$attachment_id,
					'woocommerce_gallery_thumbnail',
					false,
					array(
						'class'   => 'bp-gallery__thumb-img',
						'loading' => 'lazy',
						'alt'     => '',
					)
				);

				// Fall back to the WooCommerce placeholder when an attachment can no
				// longer resolve, so the strip keeps one thumb per slide (1:1 index
				// alignment) and the active highlight never drops out mid-gallery.
				if ( '' === $thumb_html ) {
					$thumb_html = sprintf(
						'<img src="%s" alt="" class="bp-gallery__thumb-img" loading="lazy" />',
						esc_url( wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' ) )
					);
				}
				?>
				<button
					type="button"
					class="bp-gallery__thumb<?php echo 0 === $thumb_index ? ' is-active' : ''; ?>"
					data-index="<?php echo esc_attr( $thumb_index ); ?>"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'View image %d', 'generatepress-child' ), $thumb_index + 1 ) ); ?>"
					<?php echo 0 === $thumb_index ? 'aria-current="true"' : ''; ?>
				>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $thumb_html;
					?>
				</button>
				<?php
				$thumb_index++;
			endforeach;
			?>
		</div><!-- .bp-gallery__thumbs -->
	<?php endif; ?>
</div>

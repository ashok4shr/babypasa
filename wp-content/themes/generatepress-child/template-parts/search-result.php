<?php
/**
 * Template part for displaying a single search result in the AJAX dropdown.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

global $product;

if ( empty( $product ) ) {
    return;
}

$product_id = $product->get_id();
$product_title = $product->get_name();
$product_url = $product->get_permalink();
$product_price = $product->get_price_html();

$product_image = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), 'thumbnail' );
$img_url = $product_image ? $product_image[0] : ( function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src() : '' );

$is_on_sale = $product->is_on_sale();
$is_in_stock = $product->is_in_stock();
?>

<a href="<?php echo esc_url( $product_url ); ?>" class="bp-result-item">
    <div class="bp-result-img-wrapper">
        <?php if ( $img_url ) : ?>
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $product_title ); ?>" class="bp-result-img">
        <?php endif; ?>
        
        <?php if ( ! $is_in_stock ) : ?>
            <span class="bp-result-badge bp-badge-out-of-stock">Out of Stock</span>
        <?php elseif ( $is_on_sale ) : ?>
            <span class="bp-result-badge bp-badge-sale">Sale</span>
        <?php endif; ?>
    </div>
    
    <div class="bp-result-content">
        <div class="bp-result-title"><?php echo esc_html( $product_title ); ?></div>
        <div class="bp-result-price"><?php echo wp_kses_post( $product_price ); ?></div>
    </div>
</a>

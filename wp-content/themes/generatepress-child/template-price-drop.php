<?php
/**
 * Template Name: Price Drop Alert
 *
 * This template displays products that are on sale by reusing the shop archive layout.
 */

defined( 'ABSPATH' ) || exit;

// 1. Setup Custom Query for On-Sale Products
$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

$args = array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
    // We use the same method as the [sale_products] shortcode
    'post__in'       => array_merge( array( 0 ), wc_get_product_ids_on_sale() ),
);

global $wp_query;
$wp_query = new WP_Query( $args );

// IMPORTANT: Tell WooCommerce we have products! This fixes the empty loop issue.
if ( function_exists( 'wc_set_loop_prop' ) ) {
    wc_set_loop_prop( 'total', $wp_query->found_posts );
    wc_set_loop_prop( 'is_shortcode', false );
}

// 2. Inject the Price Drop Banner
add_action( 'bp_before_archive_layout', 'bp_inject_price_drop_banner' );
function bp_inject_price_drop_banner() {
    ?>
    <div class="bp-price-drop-banner">
        <div class="bp-banner-container">
            <img src="<?php echo esc_url( home_url('/wp-content/uploads/2026/04/Category_Image.png') ); ?>" alt="Price Drop Alert Banner">
        </div>
    </div>
    <?php
}

// 3. Override the Archive Title
add_filter( 'woocommerce_show_page_title', '__return_true' );
add_filter( 'woocommerce_page_title', 'bp_override_price_drop_title' );
function bp_override_price_drop_title( $title ) {
    return 'Price Drop Alert';
}

// 4. Load the Archive Template directly
require get_stylesheet_directory() . '/woocommerce/archive-product.php';

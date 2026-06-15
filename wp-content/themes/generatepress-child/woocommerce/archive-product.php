<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<div class="bp-archive-container">
	<?php 
	/**
	 * Hook: woocommerce_before_main_content.
	 *
	 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
	 * @hooked woocommerce_breadcrumb - 20
	 * @hooked WC_Structured_Data::generate_website_data() - 30
	 */
	// We remove default wrapper because we use our own struct
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	
	//do_action( 'woocommerce_before_main_content' ); 
	?>

	<?php do_action( 'bp_before_archive_layout' ); ?>

	<div class="bp-archive-layout">
		
		<aside id="secondary" class="bp-shop-sidebar" aria-label="Shop Sidebar">
			<div class="bp-sidebar-block bp-shopping-options">
				<h3 class="bp-sidebar-title">SHOPPING OPTIONS</h3>
				<div class="bp-filter-widgets">
					<?php 
					// Dynamic Sidebar for WooCommerce Filters
					if ( is_active_sidebar( 'woocommerce-sidebar' ) ) {
						dynamic_sidebar( 'woocommerce-sidebar' );
					} else {
						echo '<p class="bp-sidebar-empty-msg">Add filters to WooCommerce Sidebar in Appearance > Widgets.</p>';
					}
					?>
				</div>
			</div>

			<div class="bp-sidebar-block bp-compare">
				<h3 class="bp-sidebar-title">COMPARE PRODUCTS <span class="bp-count"></span></h3>
				<div class="bp-compare-content">
					<p class="bp-sidebar-empty">You have no items to compare.</p>
				</div>
			</div>

			<div class="bp-sidebar-block bp-wishlist">
				<h3 class="bp-sidebar-title">MY WISH LIST <span class="bp-count"></span></h3>
				<div class="bp-wishlist-content">
					<p class="bp-sidebar-empty">You have no items in your wish list.</p>
				</div>
			</div>

			<div class="bp-sidebar-logo">
				<?php 
				if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
					$logo_id = get_theme_mod( 'custom_logo' );
					$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
					echo '<img src="' . esc_url( $logo_url ) . '" alt="BabyPasa Mascot" class="bp-mascot-img">';
				}
				?>
			</div>
		</aside>

		<main id="primary" class="bp-shop-main">
			<header class="woocommerce-products-header">
				<?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
					<h1 class="woocommerce-products-header__title page-title"><?php woocommerce_page_title(); ?></h1>
				<?php endif; ?>

				<?php
				/**
				 * Hook: woocommerce_archive_description.
				 *
				 * @hooked woocommerce_taxonomy_archive_description - 10
				 * @hooked woocommerce_product_archive_description - 10
				 */
				do_action( 'woocommerce_archive_description' );
				?>
			</header>

			<?php
			if ( woocommerce_product_loop() ) {

				/**
				 * Hook: woocommerce_before_shop_loop.
				 *
				 * @hooked woocommerce_output_all_notices - 10
				 * @hooked woocommerce_result_count - 20
				 * @hooked woocommerce_catalog_ordering - 30
				 */
				// We reorganize this hook to match the custom toolbar structure later via functions.php
				do_action( 'woocommerce_before_shop_loop' );

				woocommerce_product_loop_start();

				if ( wc_get_loop_prop( 'total' ) ) {
					while ( have_posts() ) {
						the_post(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

						/**
						 * Hook: woocommerce_shop_loop.
						 */
						do_action( 'woocommerce_shop_loop' );

						wc_get_template_part( 'content', 'product' );
					}
				}

				woocommerce_product_loop_end();

				/**
				 * Hook: woocommerce_after_shop_loop.
				 *
				 * @hooked woocommerce_pagination - 10
				 */
				do_action( 'woocommerce_after_shop_loop' );
			} else {
				/**
				 * Hook: woocommerce_no_products_found.
				 *
				 * @hooked wc_no_products_found - 10
				 */
				do_action( 'woocommerce_no_products_found' );
			}
			?>
		</main>
	</div>

	<?php
	/**
	 * Hook: woocommerce_after_main_content.
	 */
	do_action( 'woocommerce_after_main_content' );
	?>
</div>

<?php
get_footer( 'shop' );

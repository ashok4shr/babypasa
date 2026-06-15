<?php
/**
 * Template Name: Custom Homepage
 * 
 * The template for displaying the custom front page.
 *
 * @package GeneratePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header(); ?>

<div id="primary" <?php generate_do_attr( 'content' ); ?>>
	<main id="main" <?php generate_do_attr( 'main' ); ?>>
		
		<?php
		/**
		 * 1. HERO CAROUSEL
		 */
		$hero_slides = '';
		$slides = get_option('bp_hero_slides', array());
		if (is_array($slides)) {
			foreach ($slides as $index => $slide) {
				$img = isset($slide['img']) ? $slide['img'] : '';
				$link = isset($slide['link']) ? $slide['link'] : '';
				if ( ! empty( $img ) ) {
					$alt = 'Hero ' . ($index + 1);
					$slide_content = '<div class="bp-hero-slide"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '"></div>';
					if ( ! empty( $link ) ) {
						$slide_content = '<a href="' . esc_url( $link ) . '" class="bp-hero-slide">' . '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '"></a>';
					}
					$hero_slides .= $slide_content;
				}
			}
		}

		if ( ! empty( $hero_slides ) ) : ?>
			<section class="bp-hero-section">
				<div class="bp-slider-container bp-hero-slider" data-slider="hero">
					<div class="bp-slider-track">
						<?php echo $hero_slides; ?>
					</div>
					<button class="bp-slider-btn bp-prev-btn" aria-label="Previous">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>
					</button>
					<button class="bp-slider-btn bp-next-btn" aria-label="Next">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
					</button>
					<div class="bp-slider-dots"></div>
				</div>
			</section>
		<?php endif; ?>

		<?php
		/**
		 * Section Renders (Moved to inc/woocommerce-functions.php)
		 */

		$banner_features = '';
		$icons = array(
			// Truck icon
			'<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
			// Gift icon
			'<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><path d="M20 6h-2.18c.11-.31.18-.65.18-1 0-1.66-1.34-3-3-3-1.05 0-1.96.54-2.5 1.35l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm3 16H4v-2h8v2zm0-4H4v-2h8v2zm0-4H4V8h8v4zm9 8h-8v-2h8v2zm0-4h-8v-2h8v2zm0-4h-8V8h8v4z"/></svg>',
			// Dollar icon
			'<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>'
		);

		for ( $i = 1; $i <= 3; $i++ ) {
			$title = get_option( 'bp_banner_title_' . $i, '' );
			$desc  = get_option( 'bp_banner_desc_' . $i, '' );
			if ( empty($title) && $i === 1 ) $title = 'DELIVERY ALL OVER NEPAL';
			if ( empty($desc) && $i === 1 ) $desc = 'Enjoy Delivery all over Nepal!';
			if ( empty($title) && $i === 2 ) $title = 'GIFT ON YOUR BEHALF';
			if ( empty($desc) && $i === 2 ) $desc = 'Find the Perfect Gift - We\'ll Deliver It Directly to Your Loved Ones!';
			if ( empty($title) && $i === 3 ) $title = 'HASSLE - FREE SHOPPING';
			if ( empty($desc) && $i === 3 ) $desc = 'Seamless ordering, secure payments, and easy returns';

			$banner_features .= '<div class="bp-banner-item">';
			$banner_features .= '<div class="bp-banner-icon">' . $icons[$i-1] . '</div>';
			$banner_features .= '<div class="bp-banner-text">';
			$banner_features .= '<h4>' . esc_html( $title ) . '</h4>';
			$banner_features .= '<p>' . esc_html( $desc ) . '</p>';
			$banner_features .= '</div>';
			$banner_features .= '</div>';
		}
		?>

		<div class="bp-container">
			<?php
			// === BABYPASA PRODUCT PICKER: START ===
			// Admin-selected, ordered product IDs per section (Settings > Homepage Settings).
			// When empty, the helper falls back to the original tag / latest query unchanged.
			$bp_sec1_ids = get_option( 'bp_section_1_products', array() );
			$bp_sec1_ids = is_array( $bp_sec1_ids ) ? $bp_sec1_ids : array();
			$bp_sec2_ids = get_option( 'bp_section_2_products', array() );
			$bp_sec2_ids = is_array( $bp_sec2_ids ) ? $bp_sec2_ids : array();
			$bp_sec3_ids = get_option( 'bp_section_3_products', array() );
			$bp_sec3_ids = is_array( $bp_sec3_ids ) ? $bp_sec3_ids : array();
			// === BABYPASA PRODUCT PICKER: END ===

			// 2. DAILY ESSENTIALS
			bp_render_product_slider_section( get_option('bp_section_1_title', 'DAILY ESSENTIALS'), 'daily-essentials', false, $bp_sec1_ids );

			// Allow plugins to inject content after the Daily Essentials section.
			// BP Ads Manager hooks here to render banner ads (bp_after_daily_essentials action).
			do_action( 'bp_after_daily_essentials' );

			// 3. NEW PRODUCTS (latest)
			bp_render_product_slider_section( get_option('bp_section_2_title', 'NEW PRODUCTS'), '', true, $bp_sec2_ids );

			// Allow plugins to inject content after the New / Latest Products section.
			// BP Ads Manager hooks here to render banner ads (bp_after_new_products action).
			do_action( 'bp_after_new_products' );

			// 4. TRENDING PRODUCTS
			bp_render_product_slider_section( get_option('bp_section_3_title', 'TRENDING PRODUCTS'), 'trending-product', false, $bp_sec3_ids );

			// Allow plugins to inject content after the Trending Products section.
			// BP Ads Manager hooks here to render banner ads (bp_after_trending_products action).
			do_action( 'bp_after_trending_products' );
			?>
			<section class="bp-red-banner">
			<div class="bp-container">
				<div class="bp-banner-grid">
					<?php echo $banner_features; ?>
				</div>
			</div>
		</section>
		</div>

		<?php
		/**
		 * 5. RED INFORMATION BANNER
		 */
		
		?>
		

	</main>
</div>

<?php 
// Reset loop if needed
get_footer();

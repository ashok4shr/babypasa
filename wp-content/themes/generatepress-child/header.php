<?php
/**
 * The template for displaying the header.
 *
 * @package GeneratePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?> <?php generate_do_microdata( 'body' ); ?>>
	<?php
	/**
	 * wp_body_open hook.
	 *
	 * @since 2.3
	 */
	do_action( 'wp_body_open' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WP hook.

	/**
	 * generate_before_header hook.
	 *
	 * @since 0.1
	 *
	 * @hooked generate_do_skip_to_content_link - 2
	 * @hooked generate_top_bar - 5
	 * @hooked generate_add_navigation_before_header - 5
	 */
	do_action( 'generate_before_header' );

	/**
	 * generate_header hook.
	 *
	 * @since 1.3.42
	 *
	 * @hooked generate_construct_header - 10
	 */
	?>
	<header id="masthead" class="bp-custom-header">
		<?php
		// === BABYPASA HEADER CUSTOMIZER: START ===
		// Top-bar visibility + text are managed in Appearance > Customize > BabyPasa Header.
		$bp_topbar_enabled = function_exists( 'bp_get_topbar_enabled' ) ? bp_get_topbar_enabled() : true;
		$bp_topbar_text    = function_exists( 'bp_get_topbar_text' ) ? bp_get_topbar_text() : 'Welcome to BabyPasa – Weaving Joyful Moments Together!';
		if ( $bp_topbar_enabled && '' !== trim( $bp_topbar_text ) ) :
		?>
		<div class="bp-top-bar">
			<div class="bp-container">
				<p><?php echo esc_html( $bp_topbar_text ); ?></p>
			</div>
		</div>
		<?php endif; // === BABYPASA HEADER CUSTOMIZER: END === ?>
	</header>

	<div class="bp-bottom-header">
		<div class="bp-container">
			<button class="bp-mobile-menu-toggle" aria-label="Toggle Menu">
				<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
			</button>

			<!-- Logo — left on desktop, absolute-centred on mobile -->
			<div class="bp-logo">
				<?php
				if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
					the_custom_logo();
				} else {
					echo '<a href="' . esc_url( home_url( '/' ) ) . '" rel="home"><img src="' . esc_url( get_stylesheet_directory_uri() . '/images/logo-placeholder.png' ) . '" alt="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '"></a>';
				}
				?>
			</div>

			<div class="bp-mobile-overlay"></div>
			<nav class="bp-navigation">
				<div class="bp-mobile-menu-header">
					<span class="bp-mobile-menu-title">Menu</span>
					<button class="bp-mobile-menu-close" aria-label="Close Menu">&times;</button>
				</div>

				<!-- Search trigger inside mobile drawer (hidden on desktop) -->
				<div class="bp-mobile-search-row">
					<button class="bp-search-toggle"
					        type="button"
					        aria-label="<?php esc_attr_e( 'Search products', 'generatepress-child' ); ?>"
					        aria-expanded="false"
					        aria-controls="bp-search-drawer">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
						<?php esc_html_e( 'Search products', 'generatepress-child' ); ?>
					</button>
				</div>

				<?php
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'bp-menu',
					'fallback_cb'    => false,
				) );
				?>
			</nav>
			
			<div class="bp-header-icons">

				<!-- Search trigger -->
				<button class="bp-search-toggle"
				        type="button"
				        aria-label="<?php esc_attr_e( 'Open search', 'generatepress-child' ); ?>"
				        aria-expanded="false"
				        aria-controls="bp-search-drawer">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
				</button>

				<!-- Account link — always the My Account page -->
				<a class="bp-account-link"
				   aria-label="<?php esc_attr_e( 'Account', 'generatepress-child' ); ?>"
				   href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) ); ?>">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
				</a>

				<!-- Cart -->
				<div class="bp-mini-cart">
					<button id="mini-cart-trigger"
					        type="button"
					        aria-expanded="false"
					        aria-controls="mini-cart-drawer"
					        title="<?php esc_attr_e( 'View your shopping cart', 'woocommerce' ); ?>">
						<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2zm6 16H6V8h2v2c0 .55.45 1 1 1s1-.45 1-1V8h4v2c0 .55.45 1 1 1s1-.45 1-1V8h2v12z"/></svg>
						<?php $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?>
						<span class="bp-cart-count"<?php echo $cart_count === 0 ? ' style="display:none"' : ''; ?>><?php echo $cart_count > 0 ? esc_html( $cart_count ) : ''; ?></span>
					</button>
				</div>

			</div>
		</div>
	</div>
	<?php

	/**
	 * generate_after_header hook.
	 *
	 * @since 0.1
	 *
	 * @hooked generate_featured_page_header - 10
	 */
	do_action( 'generate_after_header' );
	?>

	<div <?php generate_do_attr( 'page' ); ?>>
		<?php
		/**
		 * generate_inside_site_container hook.
		 *
		 * @since 2.4
		 */
		do_action( 'generate_inside_site_container' );
		?>
		<div <?php generate_do_attr( 'site-content' ); ?>>
			<?php
			/**
			 * generate_inside_container hook.
			 *
			 * @since 0.1
			 */
			do_action( 'generate_inside_container' );

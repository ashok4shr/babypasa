<?php
/**
 * My Account Navigation — BabyPasa Tab Bar
 *
 * Desktop : horizontal tab strip with SVG icons + labels.
 * Mobile  : a styled dropdown (custom <select> shell) that navigates on change.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package BabyPasa\MyAccount
 * @version 9.3.0 (WooCommerce base)
 */

defined( 'ABSPATH' ) || exit;

/**
 * SVG icon map keyed by WooCommerce account endpoint slug.
 * Add new slugs here as new menu items are registered.
 *
 * @return string[]
 */
if ( ! function_exists( 'bp_myaccount_nav_icons' ) ) {
	function bp_myaccount_nav_icons() {
		return array(
			// ── Core WooCommerce endpoints ───────────────────────────
			'dashboard'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>',
			'orders'            => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"/></svg>',
			'downloads'         => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zm-8 2V5h2v6h1.17L12 13.17 9.83 11H11zm-6 7h14v2H5z"/></svg>',
			'edit-address'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
			'payment-methods'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>',
			'edit-account'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>',
			'customer-logout'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>',

			// ── Custom / plugin endpoints ────────────────────────────
			'track-orders'      => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9 1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
			'wishlist'          => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
			'saved-addresses'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
			'price-drop-alerts' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="17" height="17" fill="currentColor"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>',
		);
	}
}

do_action( 'woocommerce_before_account_navigation' );

$icons    = bp_myaccount_nav_icons();
$menu     = wc_get_account_menu_items();

// Find the currently active item label for the mobile dropdown button.
$active_label = '';
$active_icon  = '';
foreach ( $menu as $endpoint => $label ) {
	if ( wc_is_current_account_menu_item( $endpoint ) ) {
		$active_label = $label;
		$active_icon  = isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : $icons['edit-account'];
		break;
	}
}
?>

<nav class="bp-myaccount-tabs" aria-label="<?php esc_attr_e( 'Account pages', 'woocommerce' ); ?>">

	<!-- ── Desktop / tablet: horizontal tab strip ─────────────────── -->
	<div class="bp-myaccount-tabs__inner" role="tablist">
		<?php foreach ( $menu as $endpoint => $label ) :
			$is_active = wc_is_current_account_menu_item( $endpoint );
			$icon      = isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : $icons['edit-account'];
			?>
			<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
			   class="bp-myaccount-tab <?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>"
			   role="tab"
			   <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
				<span class="bp-myaccount-tab__icon" aria-hidden="true">
					<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="bp-myaccount-tab__label"><?php echo esc_html( $label ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- ── Mobile: custom dropdown ───────────────────────────────── -->
	<div class="bp-myaccount-tabs__mobile" aria-hidden="true">
		<button type="button"
		        class="bp-tab-dropdown-btn"
		        aria-expanded="false"
		        aria-controls="bp-tab-dropdown-list">
			<span class="bp-tab-dropdown-btn__icon" aria-hidden="true">
				<?php echo $active_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
			<span class="bp-tab-dropdown-btn__label"><?php echo esc_html( $active_label ); ?></span>
			<span class="bp-tab-dropdown-btn__arrow" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
			</span>
		</button>
		<ul class="bp-tab-dropdown-list" id="bp-tab-dropdown-list" role="listbox" hidden>
			<?php foreach ( $menu as $endpoint => $label ) :
				$is_active = wc_is_current_account_menu_item( $endpoint );
				$icon      = isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : $icons['edit-account'];
				?>
				<li role="option" <?php echo $is_active ? 'aria-selected="true"' : ''; ?>>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
					   class="bp-tab-dropdown-item <?php echo $is_active ? 'is-active' : ''; ?>">
						<span class="bp-tab-dropdown-item__icon" aria-hidden="true">
							<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
						<span><?php echo esc_html( $label ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>

<script>
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn  = document.querySelector( '.bp-tab-dropdown-btn' );
		var list = document.getElementById( 'bp-tab-dropdown-list' );
		if ( ! btn || ! list ) return;

		function openDropdown() {
			list.hidden = false;
			btn.setAttribute( 'aria-expanded', 'true' );
			btn.classList.add( 'is-open' );
		}

		function closeDropdown() {
			list.hidden = true;
			btn.setAttribute( 'aria-expanded', 'false' );
			btn.classList.remove( 'is-open' );
		}

		btn.addEventListener( 'click', function () {
			btn.getAttribute( 'aria-expanded' ) === 'true' ? closeDropdown() : openDropdown();
		} );

		// Close on outside click.
		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.bp-myaccount-tabs__mobile' ) ) {
				closeDropdown();
			}
		} );

		// Close on Escape.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) closeDropdown();
		} );
	} );
}() );
</script>

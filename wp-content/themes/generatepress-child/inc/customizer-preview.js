/**
 * Live preview for the BabyPasa Header Customizer section.
 * Updates the preview frame instantly (postMessage transport) without a reload.
 */
( function ( $ ) {
	var api = wp.customize;
	var root = document.documentElement;

	function setVar( name, value ) {
		if ( value ) {
			root.style.setProperty( name, value );
		} else {
			root.style.removeProperty( name );
		}
	}

	// Top bar text.
	api( 'bp_topbar_text', function ( setting ) {
		setting.bind( function ( value ) {
			$( '.bp-top-bar p' ).text( value );
		} );
	} );

	// Top bar visibility.
	api( 'bp_topbar_enable', function ( setting ) {
		setting.bind( function ( value ) {
			$( '.bp-top-bar' ).css( 'display', value ? '' : 'none' );
		} );
	} );

	// Colour settings => CSS custom properties.
	var colorVars = {
		bp_topbar_bg: '--bp-topbar-bg',
		bp_topbar_text_color: '--bp-topbar-color',
		bp_navbar_bg: '--bp-navbar-bg',
		bp_navbar_link_color: '--bp-navbar-link',
		bp_navbar_link_hover_color: '--bp-navbar-link-hover',
		bp_navbar_link_hover_bg: '--bp-navbar-link-hover-bg'
	};

	$.each( colorVars, function ( settingId, cssVar ) {
		api( settingId, function ( setting ) {
			setting.bind( function ( value ) {
				setVar( cssVar, value );
			} );
		} );
	} );

	// Logo width — max-width cap; the logo fills the bar height (CSS handles aspect).
	api( 'bp_logo_width', function ( setting ) {
		setting.bind( function ( value ) {
			var px = parseInt( value, 10 );
			setVar( '--bp-logo-width', px ? px + 'px' : '' );
		} );
	} );
} )( jQuery );

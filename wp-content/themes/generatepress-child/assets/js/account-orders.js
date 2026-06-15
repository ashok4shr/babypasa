/**
 * My Account → Orders: open the "Track Order" action in a new tab.
 *
 * WooCommerce's orders.php template renders each order action as a plain anchor
 * with no `target` attribute. Rather than override the template, this small
 * progressive enhancement adds the new-tab behaviour (and a safe `rel`) to the
 * Track Order links after the page loads.
 */
( function () {
	'use strict';

	function enhance() {
		var links = document.querySelectorAll( '.woocommerce-orders-table a.track-order' );
		links.forEach( function ( link ) {
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', enhance );
	} else {
		enhance();
	}
} )();

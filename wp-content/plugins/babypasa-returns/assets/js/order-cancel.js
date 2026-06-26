/**
 * BabyPasa Returns — customer order cancellation (My Account → Orders).
 *
 * Intercepts clicks on the "Cancel Order" action (rendered by WooCommerce with
 * the CSS class "bp-cancel-order"), confirms, then POSTs to admin-ajax. The
 * order ID is carried in the link's href fragment: #bp-cancel-{id}.
 */
( function () {
	'use strict';

	if ( typeof BPOrderCancel === 'undefined' ) {
		return;
	}

	function showNotice( message, type ) {
		var ok = 'success' === type;
		var el = document.createElement( 'div' );
		el.setAttribute( 'role', 'alert' );
		el.style.cssText =
			'margin:0 0 16px;padding:12px 14px;border-radius:8px;font-size:14px;border:1px solid ' +
			( ok ? '#bbf7d0;background:#dcfce7;color:#166534' : '#fecaca;background:#fee2e2;color:#991b1b' );
		el.textContent = message;

		var container = document.querySelector( '.woocommerce-MyAccount-content' ) || document.body;
		container.insertBefore( el, container.firstChild );
		el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest ? e.target.closest( '.bp-cancel-order' ) : null;
		if ( ! link ) {
			return;
		}

		e.preventDefault();

		// Guard against double-submission while a request is in flight.
		if ( link.getAttribute( 'aria-disabled' ) === 'true' ) {
			return;
		}

		var match = ( link.getAttribute( 'href' ) || '' ).match( /bp-cancel-(\d+)/ );
		if ( ! match ) {
			return;
		}
		var orderId = match[ 1 ];

		if ( ! window.confirm( BPOrderCancel.confirm ) ) {
			return;
		}

		var originalLabel = link.textContent;
		link.setAttribute( 'aria-disabled', 'true' );
		link.style.pointerEvents = 'none';
		link.style.opacity = '0.6';
		link.textContent = BPOrderCancel.checking;

		function restore() {
			link.removeAttribute( 'aria-disabled' );
			link.style.pointerEvents = '';
			link.style.opacity = '';
			link.textContent = originalLabel;
		}

		var body = new URLSearchParams();
		body.append( 'action', 'babypasa_cancel_order' );
		body.append( 'nonce', BPOrderCancel.nonce );
		body.append( 'order_id', orderId );

		fetch( BPOrderCancel.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( res && res.success ) {
					showNotice( ( res.data && res.data.message ) || BPOrderCancel.success, 'success' );
					setTimeout( function () {
						window.location.reload();
					}, 1500 );
				} else {
					showNotice( ( res && res.data && res.data.message ) || BPOrderCancel.error, 'error' );
					restore();
				}
			} )
			.catch( function () {
				showNotice( BPOrderCancel.error, 'error' );
				restore();
			} );
	} );
} )();

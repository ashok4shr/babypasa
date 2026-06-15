/**
 * Babypasa Newsletter — front-end subscription form.
 *
 * Notification system mirrors babypasa-wishlist-compare exactly:
 *
 *   Container : .bp-notification-container  (fixed top-right, z-index 999999)
 *   Toast     : .bp-notification            (320 px, border-left accent)
 *   Show      : add .bp-show   → translateX(0)   + opacity 1  (300 ms cubic)
 *   Auto-hide : 5 000 ms timeout
 *   Hide      : remove .bp-show, add .bp-hiding → translateX(120%) + opacity 0 (300 ms)
 *   Remove    : DOM removal 300 ms after .bp-hiding
 *   Dismiss   : .bp-notification-close button cancels the timer
 *   Dedup     : skip if data-notif-type already present in container
 */
/* global bpnlData */

( function () {
	'use strict';

	function showBpNotification( title, message, notifType ) {
		var container = document.querySelector( '.bp-notification-container' );
		if ( ! container ) {
			container = document.createElement( 'div' );
			container.className = 'bp-notification-container';
			document.body.appendChild( container );
		}

		if ( notifType && container.querySelector( '[data-notif-type="' + notifType + '"]' ) ) {
			return;
		}

		var id   = 'bp-toast-' + Date.now();
		var html = '<div class="bp-notification" id="' + id + '"'
			+ ( notifType ? ' data-notif-type="' + notifType + '"' : '' )
			+ ' role="alert">'
			+ '<button class="bp-notification-close" aria-label="Close">&times;</button>'
			+ '<div class="bp-notification-title">' + title + '</div>'
			+ '<div class="bp-notification-message">' + message + '</div>'
			+ '</div>';

		container.insertAdjacentHTML( 'beforeend', html );

		var toast = document.getElementById( id );
		void toast.offsetWidth;
		toast.classList.add( 'bp-show' );

		var timer = setTimeout( function () {
			toast.classList.remove( 'bp-show' );
			toast.classList.add( 'bp-hiding' );
			setTimeout( function () {
				if ( toast.parentNode ) { toast.parentNode.removeChild( toast ); }
			}, 300 );
		}, 5000 );

		toast.querySelector( '.bp-notification-close' ).addEventListener( 'click', function () {
			clearTimeout( timer );
			toast.classList.remove( 'bp-show' );
			toast.classList.add( 'bp-hiding' );
			setTimeout( function () {
				if ( toast.parentNode ) { toast.parentNode.removeChild( toast ); }
			}, 300 );
		} );
	}

	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! form.classList.contains( 'bpnl-form' ) ) { return; }
		e.preventDefault();

		var emailInput = form.querySelector( 'input[name="bpnl_email"]' );
		var submitBtn  = form.querySelector( 'button[type="submit"]' );
		if ( ! emailInput ) { return; }

		var email = emailInput.value.trim();
		if ( ! email ) {
			showBpNotification( 'Newsletter', '<p>Please enter your email address.</p>', 'bpnl-subscribe' );
			return;
		}

		// ── Loading state ────────────────────────────────────────────────
		var originalText     = submitBtn.textContent;
		submitBtn.disabled   = true;
		submitBtn.textContent = 'SUBSCRIBING...';
		submitBtn.style.opacity = '0.75';

		fetch( bpnlData.ajaxUrl, {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body:    new URLSearchParams( {
				action: 'bpnl_subscribe',
				nonce:  bpnlData.nonce,
				email:  email,
			} ).toString(),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( response ) {
				submitBtn.disabled    = false;
				submitBtn.textContent = originalText;
				submitBtn.style.opacity = '';

				if ( response.success ) {
					emailInput.value = '';
				}
				showBpNotification( 'Newsletter', '<p>' + response.data.message + '</p>', 'bpnl-subscribe' );
			} )
			.catch( function () {
				submitBtn.disabled    = false;
				submitBtn.textContent = originalText;
				submitBtn.style.opacity = '';
				showBpNotification( 'Connection Error', '<p>Could not connect. Please try again.</p>' );
			} );
	} );
} )();

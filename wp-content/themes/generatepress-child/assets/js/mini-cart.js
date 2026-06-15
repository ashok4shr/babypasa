/**
 * Mini-cart drawer — vanilla JS, event-delegation model.
 *
 * Delegated listeners are attached once to the stable #mini-cart-drawer root,
 * so they survive any inner-HTML replacement (WooCommerce fragment refreshes,
 * qty updates, remove operations) without re-binding.
 *
 * Depends on: wc-cart-fragments (loaded by WooCommerce).
 * Localised: bpMiniCart { wcAjaxUrl, ajaxUrl, nonce, updateNonce, i18n }
 */
( function () {
	'use strict';

	/* ── Params ──────────────────────────────────────────────────────────── */

	const params = window.bpMiniCart || {};
	const wcAjax = ( endpoint ) => params.wcAjaxUrl.replace( '%%endpoint%%', endpoint );

	let trigger, drawer, overlay, closeBtn;
	let inflight = 0;
	let qtyTimers = {};

	/* ── Loading state ───────────────────────────────────────────────────── */

	function setLoading( on ) {
		inflight = Math.max( 0, inflight + ( on ? 1 : -1 ) );
		drawer.classList.toggle( 'is-loading', inflight > 0 );
	}

	/* ── Focus helpers ───────────────────────────────────────────────────── */

	function focusableEls( el ) {
		return Array.from(
			el.querySelectorAll(
				'a[href],button:not([disabled]),input:not([disabled]),[tabindex]:not([tabindex="-1"])'
			)
		).filter( ( n ) => n.offsetParent !== null );
	}

	/* ── Open / close ────────────────────────────────────────────────────── */

	function openDrawer() {
		drawer.classList.add( 'is-open' );
		overlay.classList.add( 'is-open' );
		drawer.setAttribute( 'aria-hidden', 'false' );
		overlay.setAttribute( 'aria-hidden', 'false' );
		trigger.setAttribute( 'aria-expanded', 'true' );
		document.body.classList.add( 'bp-mc-no-scroll' );
		const els = focusableEls( drawer );
		if ( els.length ) els[0].focus();
	}

	function closeDrawer() {
		drawer.classList.remove( 'is-open' );
		overlay.classList.remove( 'is-open' );
		drawer.setAttribute( 'aria-hidden', 'true' );
		overlay.setAttribute( 'aria-hidden', 'true' );
		trigger.setAttribute( 'aria-expanded', 'false' );
		document.body.classList.remove( 'bp-mc-no-scroll' );
		trigger.focus();
	}

	/* ── Focus trap ──────────────────────────────────────────────────────── */

	function trapFocus( e ) {
		if ( ! drawer.classList.contains( 'is-open' ) || e.key !== 'Tab' ) return;
		const els = focusableEls( drawer );
		if ( ! els.length ) return;
		const first = els[0], last = els[ els.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault(); last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault(); first.focus();
		}
	}

	/* ── Fragment helpers ────────────────────────────────────────────────── */

	function applyDrawerFragment( html ) {
		const existing = drawer.querySelector( '.widget_shopping_cart_content' );
		if ( existing ) {
			existing.outerHTML = html;
		} else {
			const inner = drawer.querySelector( '.mini-cart-drawer__inner' );
			if ( inner ) inner.insertAdjacentHTML( 'beforeend', html );
		}
	}

	function refreshFragments() {
		setLoading( true );
		return fetch( wcAjax( 'get_refreshed_fragments' ), {
			method: 'GET',
			credentials: 'same-origin',
		} )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				if ( data && data.fragments ) applyAllFragments( data.fragments );
			} )
			.catch( () => {} )
			.finally( () => setLoading( false ) );
	}

	/* ── Cart operations ─────────────────────────────────────────────────── */

	function applyAllFragments( fragments ) {
		Object.keys( fragments ).forEach( ( sel ) => {
			if ( sel === 'div.widget_shopping_cart_content' ) {
				applyDrawerFragment( fragments[ sel ] );
			} else {
				const el = document.querySelector( sel );
				if ( el ) el.outerHTML = fragments[ sel ];
			}
		} );
	}

	function updateQty( key, qty ) {
		setLoading( true );
		const body = new FormData();
		body.append( 'action', 'bp_update_cart_qty' );
		body.append( 'cart_item_key', key );
		body.append( 'quantity', qty );
		body.append( 'nonce', params.updateNonce );
		return fetch( params.ajaxUrl, { method: 'POST', credentials: 'same-origin', body } )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				if ( data && data.success && data.data && data.data.fragments ) {
					applyAllFragments( data.data.fragments );
				} else {
					return refreshFragments();
				}
			} )
			.catch( () => {} )
			.finally( () => setLoading( false ) );
	}

	function removeItem( key ) {
		setLoading( true );
		// WooCommerce reads cart_item_key from $_POST; method must be POST.
		// On success the endpoint calls get_refreshed_fragments() internally
		// and returns the same { fragments, cart_hash } shape.
		const body = new FormData();
		body.append( 'cart_item_key', key );
		return fetch( wcAjax( 'remove_from_cart' ), {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				if ( data && data.fragments ) {
					Object.keys( data.fragments ).forEach( ( sel ) => {
						if ( sel === 'div.widget_shopping_cart_content' ) {
							applyDrawerFragment( data.fragments[ sel ] );
						} else {
							const el = document.querySelector( sel );
							if ( el ) el.outerHTML = data.fragments[ sel ];
						}
					} );
				} else {
					// Fallback: fetch fresh fragments independently
					return refreshFragments();
				}
			} )
			.catch( () => {} )
			.finally( () => setLoading( false ) );
	}

	function scheduleQtyUpdate( key, qty ) {
		clearTimeout( qtyTimers[ key ] );
		qtyTimers[ key ] = setTimeout( () => {
			delete qtyTimers[ key ];
			qty <= 0 ? removeItem( key ) : updateQty( key, qty );
		}, 300 );
	}

	/* ── Delegated click handler (attached once to drawer root) ─────────── */

	function onDrawerClick( e ) {
		/* Remove × */
		const removeBtn = e.target.closest( '.bp-mc-remove' );
		if ( removeBtn ) {
			e.preventDefault();
			const key = removeBtn.dataset.cartItemKey;
			if ( key ) removeItem( key );
			return;
		}

		/* Qty − */
		const minusBtn = e.target.closest( '.bp-mc-qty-minus' );
		if ( minusBtn ) {
			e.preventDefault();
			const key   = minusBtn.dataset.cartItemKey;
			const input = drawer.querySelector( `.bp-mc-qty-input[data-cart-item-key="${ key }"]` );
			if ( ! input ) return;
			const currentQty = parseInt( input.value, 10 );
			if ( currentQty <= 1 ) return; // already at minimum
			const newQty = currentQty - 1;
			input.value  = newQty;
			// Disable the minus button immediately when hitting 1
			if ( newQty <= 1 ) minusBtn.disabled = true;
			scheduleQtyUpdate( key, newQty );
			return;
		}

		/* Qty + */
		const plusBtn = e.target.closest( '.bp-mc-qty-plus' );
		if ( plusBtn ) {
			e.preventDefault();
			const key   = plusBtn.dataset.cartItemKey;
			const input = drawer.querySelector( `.bp-mc-qty-input[data-cart-item-key="${ key }"]` );
			if ( ! input ) return;
			const newQty = parseInt( input.value, 10 ) + 1;
			input.value  = newQty;
			scheduleQtyUpdate( key, newQty );
			return;
		}
	}

	/* Delegated change handler for qty number inputs */
	function onDrawerChange( e ) {
		const input = e.target.closest( '.bp-mc-qty-input' );
		if ( ! input ) return;
		const key = input.dataset.cartItemKey;
		const qty = Math.max( 0, parseInt( input.value, 10 ) || 0 );
		input.value = qty;
		scheduleQtyUpdate( key, qty );
	}

	/* ── Init ────────────────────────────────────────────────────────────── */

	document.addEventListener( 'DOMContentLoaded', function () {
		trigger  = document.getElementById( 'mini-cart-trigger' );
		drawer   = document.getElementById( 'mini-cart-drawer' );
		overlay  = document.getElementById( 'mini-cart-overlay' );
		closeBtn = document.getElementById( 'mini-cart-close' );

		if ( ! trigger || ! drawer || ! overlay ) return;

		/* Open */
		trigger.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			openDrawer();
		} );

		/* Close: header ×, overlay, Escape */
		if ( closeBtn ) closeBtn.addEventListener( 'click', closeDrawer );
		overlay.addEventListener( 'click', closeDrawer );
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && drawer.classList.contains( 'is-open' ) ) closeDrawer();
			trapFocus( e );
		} );

		/* Delegated handlers — survive any inner-HTML replacement */
		drawer.addEventListener( 'click', onDrawerClick );
		drawer.addEventListener( 'change', onDrawerChange );

		/* Auto-open + refresh when WooCommerce fires added_to_cart */
		document.body.addEventListener( 'added_to_cart', function () {
			openDrawer();
			refreshFragments();
		} );
	} );
} )();

/* ── Add-to-cart button loading state ─────────────────────────────────────
 *
 * Targets three button types:
 *   [data-bp-cart-btn="true"]   – explicit <button> in bp_render_product_card()
 *                                  (variable products, grid context)
 *   .add_to_cart_button         – WooCommerce loop <a> tag (simple products,
 *                                  archive list view, and grid simple products)
 *   .single_add_to_cart_button  – single product page <button>
 *
 * NOTE: woocommerce_template_loop_add_to_cart() outputs an <a> element, not a
 * <button>. Setting .disabled = true has no effect on anchors. Double-click
 * protection is handled by the classList.contains guard in setLoading() plus
 * pointer-events: none in CSS.
 *
 * Clearing: the babypasa-wishlist-compare plugin fires only the standard
 * WooCommerce added_to_cart event (wishlist-compare.js lines 69 and 111) —
 * there is no separate custom plugin event. One listener covers all paths.
 * ─────────────────────────────────────────────────────────────────────────── */
( function () {
	'use strict';

	var LOADING_CLASS = 'bp-loading';
	var BTN_SELECTOR  = '[data-bp-cart-btn="true"], .add_to_cart_button, .single_add_to_cart_button';

	function setLoading( btn ) {
		if ( btn.classList.contains( LOADING_CLASS ) ) return; // guard: already loading
		btn.classList.add( LOADING_CLASS );
		btn.disabled = true; // no-op for <a> tags; effective for <button>
		btn.setAttribute( 'aria-busy', 'true' );
		btn.setAttribute( 'aria-label', 'Adding to cart' );
	}

	function clearLoading() {
		document.querySelectorAll( '.' + LOADING_CLASS ).forEach( function ( btn ) {
			btn.classList.remove( LOADING_CLASS );
			btn.disabled = false;
			btn.setAttribute( 'aria-busy', 'false' );
			btn.setAttribute( 'aria-label', 'Add to cart' );
		} );
	}

	// Set loading state on click — native DOM event, vanilla listener is fine
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( BTN_SELECTOR );
		if ( ! btn ) return;

		// Fix: single-product "Add to Cart" stuck spinning — 2026-06-05
		// The single-product submit button's loading lifecycle is owned entirely by
		// babypasa-wishlist-compare's `form.cart` submit handler, which AJAX-adds to
		// cart and uses `.bp-loading` as its OWN "already submitting" guard. This
		// click listener fires BEFORE the form's submit default-action, so adding
		// `.bp-loading` (or disabling the button) here trips that guard — wishlist
		// preventDefault()s the native submit and then bails before sending the
		// AJAX, leaving the spinner stuck forever. So leave that button alone and
		// let wishlist-compare.js manage its spinner (it adds/removes `.bp-loading`
		// around its request); mini-cart's `added_to_cart` listener still clears it.
		if ( btn.classList.contains( 'single_add_to_cart_button' ) ) return;

		// Fix: quick-add "Select options" button stuck spinning — this button only OPENS
		// the variation modal (quick-add.js); it never performs an AJAX add itself, so the
		// added_to_cart event that clears .bp-loading never fires for it and the spinner
		// stays forever (even after the modal closes). Leave its state to quick-add.js.
		if ( btn.classList.contains( 'bp-quick-add-btn' ) ) return;

		setLoading( btn );
	} );

	// added_to_cart and ajax_request_not_valid are jQuery custom events fired via
	// $(document.body).trigger(). jQuery's trigger() does NOT call native
	// dispatchEvent() for custom names, so native addEventListener() never fires.
	// Must bind through jQuery here even though the rest of this file is vanilla.
	jQuery( document.body ).on( 'added_to_cart ajax_request_not_valid', clearLoading );

} () );
/**
 * Refactor: Sticky Add to Cart — mobile always-on / desktop scroll-triggered — 2026-06-05
 * ----------------------------------------------------------------------------------------
 * Single Product page sticky Add-to-Cart.
 *
 * The ACTUAL `.bp-single-add-to-cart` form div is what gets pinned — not a clone —
 * so its real quantity input, variation selects and submit button all move with it
 * and keep working untouched. Because the div lives low inside .bp-product-info-column
 * while the description/reviews/related sit in a SIBLING container, pure CSS
 * `position: sticky` can't work (a sticky box is confined to its parent). We instead
 * lift the div out of flow with `position: fixed` so it is NOT bound by its parent,
 * leaving a placeholder to hold the in-flow gap.
 *
 * Two modes:
 *   • Mobile (≤990px): the div is ALWAYS fixed directly below the sticky nav. Handled
 *     purely in CSS (so there's no load flicker); JS only feeds it the live nav height
 *     and the bar height (for content padding).
 *   • Desktop (≥991px): inline by default. An IntersectionObserver on a sentinel at the
 *     div's original spot pins the div (adds .bp-atc-fixed) once that spot scrolls out
 *     of view, and releases it (back to inline) when the spot scrolls back in.
 *
 * `--bp-nav-height` = the live bottom edge of .bp-bottom-header (which is position:
 * sticky; top:0 on all breakpoints), so the pinned bar sits directly under the nav at
 * every scroll position — including scroll 0, where the non-sticky .bp-top-bar is still
 * above the nav. Vanilla JS; IntersectionObserver primary; the only scroll listener is
 * rAF-throttled and merely refreshes a CSS variable.
 */
(function () {
	'use strict';

	// .bp-bottom-header min-height (header-style.css). Desktop nav height is stable,
	// so it also serves as the IntersectionObserver top inset on desktop.
	var DESKTOP_NAV = 70;
	var DESKTOP_MQ  = '(min-width: 991px)';

	function init() {
		var bar = document.querySelector( '.bp-single-add-to-cart' );
		if ( ! bar ) {
			return;
		}
		if ( bar.getAttribute( 'data-bp-atc-init' ) === '1' ) {
			return;
		}
		bar.setAttribute( 'data-bp-atc-init', '1' );

		// Persistent zero-height sentinel at the bar's original location. The bar
		// itself can't be observed once it's fixed (it leaves that spot), so we watch
		// this marker to know when the inline position scrolls out of / back into view.
		var sentinel = document.createElement( 'div' );
		sentinel.className = 'bp-atc-sentinel';
		sentinel.setAttribute( 'aria-hidden', 'true' );
		bar.parentNode.insertBefore( sentinel, bar );

		var placeholder = null;

		// ── CSS vars ────────────────────────────────────────────────────────────
		function setNavHeight() {
			var nav = document.querySelector( '.bp-bottom-header' );
			// getBoundingClientRect().bottom = the nav's live viewport position:
			// (top-bar + nav) at scroll 0, just the nav (≈70) once the top-bar has
			// scrolled away and the sticky nav pins to top:0.
			var h = nav ? Math.max( 0, Math.round( nav.getBoundingClientRect().bottom ) ) : DESKTOP_NAV;
			document.documentElement.style.setProperty( '--bp-nav-height', h + 'px' );
		}
		function setBarHeight() {
			document.documentElement.style.setProperty( '--bp-atc-bar-height', ( bar.offsetHeight || 64 ) + 'px' );
		}

		// ── Desktop pin / unpin (placeholder preserves the in-flow gap) ─────────
		function pin() {
			if ( bar.classList.contains( 'bp-atc-fixed' ) ) {
				return;
			}
			if ( ! placeholder ) {
				placeholder = document.createElement( 'div' );
				placeholder.className = 'bp-atc-placeholder';
				placeholder.setAttribute( 'aria-hidden', 'true' );
			}
			// Measure the natural (inline) height BEFORE pinning so the gap matches.
			placeholder.style.height = bar.offsetHeight + 'px';
			bar.parentNode.insertBefore( placeholder, bar.nextSibling );
			bar.classList.add( 'bp-atc-fixed' );
		}
		function unpin() {
			if ( ! bar.classList.contains( 'bp-atc-fixed' ) ) {
				return;
			}
			bar.classList.remove( 'bp-atc-fixed' );
			if ( placeholder && placeholder.parentNode ) {
				placeholder.parentNode.removeChild( placeholder );
			}
		}

		// ── Desktop observer wiring (only active ≥991px) ────────────────────────
		var io = null;
		function enableDesktop() {
			if ( io || ! ( 'IntersectionObserver' in window ) ) {
				return;
			}
			io = new IntersectionObserver( function ( entries ) {
				// Inline spot out of view (scrolled above the nav) → pin; back → release.
				if ( ! entries[0].isIntersecting ) {
					pin();
				} else {
					unpin();
				}
			}, {
				root: null,
				rootMargin: '-' + DESKTOP_NAV + 'px 0px 0px 0px',
				threshold: 0
			} );
			io.observe( sentinel );
		}
		function disableDesktop() {
			if ( io ) {
				io.disconnect();
				io = null;
			}
			// Hand control back to CSS: on mobile the media query pins the div itself,
			// so the JS .bp-atc-fixed class + placeholder must be cleared.
			unpin();
		}

		// ── Mode switch on the 991px breakpoint ─────────────────────────────────
		var mq = window.matchMedia( DESKTOP_MQ );
		function applyMode() {
			if ( mq.matches ) {
				enableDesktop();  // desktop: scroll-triggered fixed
			} else {
				disableDesktop(); // mobile: CSS keeps the div fixed always
			}
			setBarHeight();
		}
		if ( mq.addEventListener ) {
			mq.addEventListener( 'change', applyMode );
		} else if ( mq.addListener ) {
			mq.addListener( applyMode ); // Safari < 14
		}

		setNavHeight();
		setBarHeight();
		applyMode();

		// rAF-throttled scroll keeps the nav offset live so the pinned bar tracks the nav.
		var ticking = false;
		window.addEventListener( 'scroll', function () {
			if ( ticking ) {
				return;
			}
			ticking = true;
			requestAnimationFrame( function () {
				setNavHeight();
				ticking = false;
			} );
		}, { passive: true } );

		window.addEventListener( 'resize', function () {
			setNavHeight();
			setBarHeight();
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();

/* ============================================================================
 * Replaced by sticky refactor — 2026-06-05
 * Original (pre-refactor) implementation preserved below (non-executing) for
 * rollback. It pinned the ENTIRE .bp-single-add-to-cart block to top:70px on any
 * scroll, identically on mobile and desktop, via a naive scroll listener.
 * ============================================================================
 *
 * (function () {
 * 	'use strict';
 * 	var HEADER_OFFSET = 70;
 * 	function init() {
 * 		var bar = document.querySelector( '.bp-single-add-to-cart' );
 * 		if ( ! bar ) { return; }
 * 		if ( bar.getAttribute( 'data-bp-atc-init' ) === '1' ) { return; }
 * 		bar.setAttribute( 'data-bp-atc-init', '1' );
 * 		var placeholder = null;
 * 		var sticky      = false;
 * 		var initialTop  = bar.offsetTop;
 * 		function topOffset() {
 * 			var adminBar = document.getElementById( 'wpadminbar' );
 * 			var adminH   = 0;
 * 			if ( adminBar ) { adminH = adminBar.offsetHeight || 0; }
 * 			return HEADER_OFFSET + adminH;
 * 		}
 * 		function stick() {
 * 			if ( sticky ) { return; }
 * 			placeholder = document.createElement( 'div' );
 * 			placeholder.className = 'bp-atc-placeholder';
 * 			placeholder.setAttribute( 'aria-hidden', 'true' );
 * 			placeholder.style.height = bar.offsetHeight + 'px';
 * 			bar.parentNode.insertBefore( placeholder, bar.nextSibling );
 * 			bar.style.top = topOffset() + 'px';
 * 			bar.classList.add( 'bp-atc-sticky' );
 * 			sticky = true;
 * 		}
 * 		function unstick() {
 * 			if ( ! sticky ) { return; }
 * 			bar.classList.remove( 'bp-atc-sticky' );
 * 			bar.style.top = '';
 * 			if ( placeholder && placeholder.parentNode ) { placeholder.parentNode.removeChild( placeholder ); }
 * 			placeholder = null;
 * 			sticky = false;
 * 		}
 * 		function onScroll() {
 * 			if ( window.scrollY > 0 ) { stick(); } else { unstick(); }
 * 		}
 * 		function onResize() {
 * 			if ( ! sticky ) { return; }
 * 			placeholder.style.height = bar.offsetHeight + 'px';
 * 			bar.style.top = topOffset() + 'px';
 * 		}
 * 		window.addEventListener( 'scroll', onScroll, { passive: true } );
 * 		window.addEventListener( 'resize', onResize );
 * 		onScroll();
 * 	}
 * 	if ( document.readyState === 'loading' ) {
 * 		document.addEventListener( 'DOMContentLoaded', init );
 * 	} else {
 * 		init();
 * 	}
 * })();
 */

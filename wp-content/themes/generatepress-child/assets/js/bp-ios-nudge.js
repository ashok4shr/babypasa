/**
 * BabyPasa — iOS "Add to Home Screen" nudge.
 *
 * iOS Safari does not support the automatic PWA install prompt.
 * This script shows a dismissible banner explaining how to install manually.
 *
 * Shown only when ALL of the following are true:
 *   - Device is iOS (iPhone / iPad / iPod)
 *   - Current page is the homepage ( / )
 *   - Browser is Safari (not already running as standalone PWA)
 *   - User has not dismissed it in the last 1 day
 *
 * Dismissing (× button) suppresses the nudge for 1 day.
 * The Safari Share button always works regardless — browser native, unaffected.
 *
 * Vanilla JS — no dependencies.
 */
( function () {
    'use strict';

    var STORAGE_KEY  = 'bp_ios_nudge_dismissed';
    var DISMISS_DAYS = 1; // re-show after 1 day if user dismissed

    // ── Guards ────────────────────────────────────────────────────────────────

    // Homepage only
    if ( window.location.pathname !== '/' ) return;

    // Only iOS — or desktop when WP_DEBUG is on (local dev testing bypass)
    var isIOS = /iPad|iPhone|iPod/.test( navigator.userAgent ) && ! window.MSStream;
    if ( ! isIOS && ! window.bpNudgeDebug ) return;

    // Not already installed as a PWA (window.navigator.standalone = true when installed)
    if ( window.navigator.standalone === true ) return;

    // Not dismissed within the cooldown period
    var lastDismissed = localStorage.getItem( STORAGE_KEY );
    if ( lastDismissed ) {
        var daysSince = ( Date.now() - parseInt( lastDismissed, 10 ) ) / 86400000;
        if ( daysSince < DISMISS_DAYS ) return;
    }

    // ── Show after 3 s (let the page settle first) ────────────────────────────
    // If an ad popup (bp-ads-manager) is still open, wait until it closes first.
    window.addEventListener( 'load', function () {
        setTimeout( function () {
            waitForAdsThenShow( injectNudge );
        }, 3000 );
    } );

    function waitForAdsThenShow( callback ) {
        function adIsOpen() {
            return !! document.querySelector( '.bp-popup-overlay.bp-popup-visible' );
        }
        if ( ! adIsOpen() ) {
            callback();
            return;
        }
        var poll = setInterval( function () {
            if ( ! adIsOpen() ) {
                clearInterval( poll );
                setTimeout( callback, 600 ); // wait for ad overlay fade-out
            }
        }, 500 );
    }

    // ── Build & inject ────────────────────────────────────────────────────────
    function injectNudge() {
        var el = document.createElement( 'div' );
        el.id  = 'bp-ios-nudge';
        el.setAttribute( 'role', 'banner' );
        el.setAttribute( 'aria-label', 'Install BabyPasa app' );

        // Share icon SVG (matches iOS Safari's share button shape)
        var shareIcon = '<svg class="bp-ios-nudge__share-svg" viewBox="0 0 24 24" '
            + 'width="14" height="14" fill="currentColor" aria-hidden="true">'
            + '<path d="M16 5l-1.42 1.42-1.59-1.59V16h-1.98V4.83L9.42 6.42 8 5l4-4 4 4z'
            + 'M20 10v11c0 1.1-.9 2-2 2H6c-1.11 0-2-.9-2-2V10c0-1.11.89-2 2-2h3v2H6v11h12V10h-3V8h3c1.1 0 2 .89 2 2z"/>'
            + '</svg>';

        el.innerHTML = [
            '<div class="bp-ios-nudge__inner">',
            '  <div class="bp-ios-nudge__content">',
            '    <span class="bp-ios-nudge__icon" aria-hidden="true">📲</span>',
            '    <div class="bp-ios-nudge__text">',
            '      <strong>Install BabyPasa</strong>',
            '      <span>Tap ' + shareIcon + ' then &ldquo;Add to Home Screen&rdquo;</span>',
            '    </div>',
            '  </div>',
            '  <button class="bp-ios-nudge__close" aria-label="Dismiss install prompt">',
            '    &times;',
            '  </button>',
            '</div>',
        ].join( '' );

        document.body.appendChild( el );

        // Trigger CSS slide-up transition
        requestAnimationFrame( function () {
            requestAnimationFrame( function () {
                el.classList.add( 'bp-ios-nudge--visible' );
            } );
        } );

        // Dismiss on close button
        el.querySelector( '.bp-ios-nudge__close' ).addEventListener( 'click', function () {
            dismiss( el );
        } );
    }

    function dismiss( el ) {
        el.classList.remove( 'bp-ios-nudge--visible' );
        localStorage.setItem( STORAGE_KEY, Date.now().toString() );
        setTimeout( function () {
            if ( el.parentNode ) el.parentNode.removeChild( el );
        }, 320 );
    }

}() );

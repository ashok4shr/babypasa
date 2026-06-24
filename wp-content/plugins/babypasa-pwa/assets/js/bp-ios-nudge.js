/**
 * BabyPasa — Unified PWA Install Prompt.
 *
 * Config injected by WordPress as inline vars before this script:
 *   bpNudgeDebug    — true when WP_DEBUG is on (forces iOS path on desktop for testing)
 *   bpNudgeScope    — install-path prefix ('/' production, '/babypasa/' local dev)
 *   bpNudgeSiteIcon — site logo URL for the card
 *   bpNudgeSiteName — site name for the card title
 *
 * Android / Chrome / Edge / desktop:
 *   Intercepts `beforeinstallprompt`. Shows a card with an Install button.
 *   Clicking Install triggers the browser's native install dialog immediately.
 *
 * iOS Safari:
 *   No install API available. Shows the same card design but with
 *   Share → "Add to Home Screen" instructions instead.
 *
 * Both platforms:
 *   - Shows on any page (not just homepage)
 *   - Skipped if already running as a standalone PWA
 *   - 3-day dismiss cooldown stored in localStorage
 *   - Waits for any ad popup (bp-ads-manager) to close before appearing
 *   - Slides up from the bottom after a 3 s delay
 *
 * Vanilla JS — no dependencies.
 */
( function () {
    'use strict';

    var STORAGE_KEY  = 'bp_install_dismissed';
    var DISMISS_DAYS = 3;

    var siteName = window.bpNudgeSiteName || 'BabyPasa';
    var siteIcon = window.bpNudgeSiteIcon || '';

    var isIOS = /iPad|iPhone|iPod/.test( navigator.userAgent ) && ! window.MSStream;

    // Already running as an installed PWA — never show
    var isStandalone = window.navigator.standalone === true
                    || window.matchMedia( '(display-mode: standalone)' ).matches;
    if ( isStandalone ) return;

    // Check dismiss cooldown
    var lastDismissed = localStorage.getItem( STORAGE_KEY );
    if ( lastDismissed ) {
        var daysSince = ( Date.now() - parseInt( lastDismissed, 10 ) ) / 86400000;
        if ( daysSince < DISMISS_DAYS ) return;
    }

    // ── Android / Chrome: capture native install prompt ───────────────────────
    var deferredPrompt = null;

    window.addEventListener( 'beforeinstallprompt', function ( e ) {
        e.preventDefault();      // stop the mini-infobar from auto-appearing
        deferredPrompt = e;
    } );

    // ── Decide what to show after page settles ────────────────────────────────
    window.addEventListener( 'load', function () {
        setTimeout( function () {
            waitForAdsThenShow( function () {
                // Debug mode on a non-iOS device: simulate the Android prompt so the
                // Install card renders on desktop Chrome during local development.
                if ( window.bpNudgeDebug && ! deferredPrompt && ! isIOS ) {
                    deferredPrompt = {
                        prompt      : function () { console.log( '[BabyPasa PWA] Debug: install prompt triggered.' ); },
                        userChoice  : Promise.resolve( { outcome: 'accepted' } ),
                    };
                }

                if ( deferredPrompt ) {
                    // Android / Chrome / Edge — native install available (or debug simulation)
                    injectCard( 'android' );
                } else if ( isIOS ) {
                    // iOS Safari — manual Share → Add to Home Screen instructions
                    injectCard( 'ios' );
                }
                // Other browsers with no install API (Firefox Android etc.) — skip silently
            } );
        }, 3000 );
    } );

    // ── Wait for bp-ads-manager popup to close before showing ─────────────────
    function waitForAdsThenShow( callback ) {
        function adIsOpen() {
            return !! document.querySelector( '.bp-popup-overlay.bp-popup-visible' );
        }
        if ( ! adIsOpen() ) { callback(); return; }
        var poll = setInterval( function () {
            if ( ! adIsOpen() ) {
                clearInterval( poll );
                setTimeout( callback, 600 );
            }
        }, 500 );
    }

    // ── Build & inject the install card ───────────────────────────────────────
    function injectCard( type ) {
        if ( document.getElementById( 'bp-install-prompt' ) ) return;

        var el = document.createElement( 'div' );
        el.id  = 'bp-install-prompt';
        el.setAttribute( 'role', type === 'ios' ? 'banner' : 'dialog' );
        el.setAttribute( 'aria-label', 'Install ' + siteName );

        // Logo — image if available, emoji fallback
        var logoHtml = siteIcon
            ? '<img src="' + siteIcon + '" class="bp-install-prompt__logo" alt="" aria-hidden="true">'
            : '<div class="bp-install-prompt__logo bp-install-prompt__logo--emoji" aria-hidden="true">🛍️</div>';

        // iOS Share icon SVG (matches Safari's share button shape)
        var shareIcon = '<svg class="bp-install-prompt__share-svg" viewBox="0 0 24 24"'
            + ' width="13" height="13" fill="currentColor" aria-hidden="true">'
            + '<path d="M16 5l-1.42 1.42-1.59-1.59V16h-1.98V4.83L9.42 6.42 8 5l4-4 4 4z'
            + 'M20 10v11c0 1.1-.9 2-2 2H6c-1.11 0-2-.9-2-2V10c0-1.11.89-2 2-2h3v2H6v11h12V10h-3V8h3c1.1 0 2 .89 2 2z"/>'
            + '</svg>';

        // Subtitle & action buttons differ per platform
        var subtitle, actions;

        if ( type === 'android' ) {
            subtitle = 'Add to home screen for quick access';
            actions  = '<div class="bp-install-prompt__actions">'
                     + '  <button class="bp-install-prompt__btn bp-install-prompt__btn--install" type="button">Install</button>'
                     + '  <button class="bp-install-prompt__btn bp-install-prompt__btn--later"   type="button">Later</button>'
                     + '</div>';
        } else {
            subtitle = 'Tap ' + shareIcon + ' then &ldquo;Add to Home Screen&rdquo;';
            actions  = '<button class="bp-install-prompt__close" aria-label="Dismiss">&times;</button>';
        }

        el.innerHTML = [
            '<div class="bp-install-prompt__inner">',
            '  ' + logoHtml,
            '  <div class="bp-install-prompt__body">',
            '    <strong class="bp-install-prompt__title">Install ' + siteName + '</strong>',
            '    <span class="bp-install-prompt__subtitle">' + subtitle + '</span>',
            '  </div>',
            '  ' + actions,
            '</div>',
        ].join( '' );

        document.body.appendChild( el );

        // Trigger CSS slide-up transition (double rAF ensures class is applied after paint)
        requestAnimationFrame( function () {
            requestAnimationFrame( function () {
                el.classList.add( 'bp-install-prompt--visible' );
                // BABYPASA 2026-06-24 (Task 2): flag body so desktop CSS can offset
                // page content (body.pwa-banner-active padding-bottom). No-op on mobile.
                document.body.classList.add( 'pwa-banner-active' );
            } );
        } );

        // ── Button handlers ───────────────────────────────────────────────────
        if ( type === 'android' ) {
            el.querySelector( '.bp-install-prompt__btn--install' ).addEventListener( 'click', function () {
                dismiss( el );
                // Trigger the browser's native install dialog
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then( function () {
                    deferredPrompt = null;
                } );
            } );
            el.querySelector( '.bp-install-prompt__btn--later' ).addEventListener( 'click', function () {
                dismiss( el );
            } );
        } else {
            el.querySelector( '.bp-install-prompt__close' ).addEventListener( 'click', function () {
                dismiss( el );
            } );
        }
    }

    // ── Dismiss ───────────────────────────────────────────────────────────────
    function dismiss( el ) {
        el.classList.remove( 'bp-install-prompt--visible' );
        // BABYPASA 2026-06-24 (Task 2): clear the body offset flag on dismiss.
        document.body.classList.remove( 'pwa-banner-active' );
        localStorage.setItem( STORAGE_KEY, Date.now().toString() );
        setTimeout( function () {
            if ( el.parentNode ) el.parentNode.removeChild( el );
        }, 350 );
    }

}() );

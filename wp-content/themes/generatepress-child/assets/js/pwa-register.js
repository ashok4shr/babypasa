/**
 * BabyPasa — PWA Service Worker Registration + Push Subscription
 *
 * Config injected by WordPress via wp_localize_script as window.bpPWA:
 *   bpPWA.swUrl          — absolute URL to sw.js
 *   bpPWA.debug          — true when WP_DEBUG is on
 *   bpPWA.ajaxUrl        — WordPress admin-ajax.php URL
 *   bpPWA.nonce          — AJAX nonce (bp_push_nonce)
 *   bpPWA.vapidPublicKey — base64url VAPID public key for pushManager.subscribe()
 *
 * Vanilla JS — no jQuery, no dependencies.
 */
( function () {
    'use strict';

    if ( ! ( 'serviceWorker' in navigator ) ) return;

    var cfg      = window.bpPWA         || {};
    var swUrl    = cfg.swUrl            || '/sw.js';
    var swScope  = cfg.scope            || '/';   // '/' on production, '/babypasa/' on local dev
    var debug    = cfg.debug            === true;
    var ajaxUrl  = cfg.ajaxUrl          || '';
    var nonce    = cfg.nonce            || '';
    var vapidKey = cfg.vapidPublicKey   || '';

    // ── Logging (debug mode only) ─────────────────────────────────────────────
    function log() {
        if ( ! debug ) return;
        var args = Array.prototype.slice.call( arguments );
        args.unshift( '[BabyPasa SW]' );
        console.log.apply( console, args );
    }

    // ── Service Worker Registration ───────────────────────────────────────────
    function register() {
        navigator.serviceWorker
            .register( swUrl, { scope: swScope } )
            .then( function ( reg ) {
                log( 'Registered. Scope:', reg.scope );

                // Watch for new SW versions
                reg.addEventListener( 'updatefound', function () {
                    var newWorker = reg.installing;
                    if ( ! newWorker ) return;
                    log( 'New SW installing…' );
                    newWorker.addEventListener( 'statechange', function () {
                        log( 'SW state:', newWorker.state );
                    } );
                } );

                // Kick off subscription flow once SW is active
                navigator.serviceWorker.ready.then( function ( activeReg ) {
                    maybeSubscribe( activeReg );
                } );
            } )
            .catch( function ( err ) {
                log( 'Registration failed:', err );
            } );
    }

    // ── Push Subscription flow ────────────────────────────────────────────────

    function maybeSubscribe( reg ) {
        // Browser must support push
        if ( ! ( 'PushManager' in window ) )    return;
        if ( ! ( 'Notification' in window ) )   return;
        // VAPID key must be configured
        if ( ! vapidKey )                        return;
        // Don't subscribe on transactional pages (cart/checkout/my-account)
        if ( isTransactionalPage() )             return;

        var permission = Notification.permission;

        if ( permission === 'denied' ) {
            log( 'Push permission denied — respecting user choice.' );
            return;
        }

        if ( permission === 'granted' ) {
            // Already allowed — subscribe silently
            subscribeUser( reg );
            return;
        }

        // Permission is 'default' — show our soft prompt first
        maybeShowSoftPrompt( reg );
    }

    function subscribeUser( reg ) {
        reg.pushManager.getSubscription().then( function ( existing ) {
            if ( existing ) {
                // Already subscribed — ensure it's saved server-side
                log( 'Already subscribed, syncing to server.' );
                saveSubscription( existing );
                return;
            }

            reg.pushManager.subscribe( {
                userVisibleOnly      : true,
                applicationServerKey : urlBase64ToUint8Array( vapidKey ),
            } ).then( function ( subscription ) {
                log( 'Subscribed:', subscription.endpoint );
                saveSubscription( subscription );
            } ).catch( function ( err ) {
                log( 'Subscribe failed:', err );
            } );
        } );
    }

    function saveSubscription( subscription ) {
        var sub  = subscription.toJSON();
        var body = new FormData();
        body.append( 'action',   'bp_push_subscribe' );
        body.append( 'nonce',    nonce );
        body.append( 'endpoint', sub.endpoint );
        body.append( 'p256dh',   sub.keys.p256dh );
        body.append( 'auth',     sub.keys.auth );

        fetch( ajaxUrl, { method: 'POST', body: body } )
            .then( function ( r ) { return r.json(); } )
            .then( function ( r ) { log( 'Subscription saved:', r ); } )
            .catch( function ( err ) { log( 'Save failed:', err ); } );
    }

    // ── Soft permission prompt ────────────────────────────────────────────────

    var PROMPT_KEY      = 'bp_push_dismissed';
    var PROMPT_COOLDOWN = 30; // days before re-showing after dismiss

    function maybeShowSoftPrompt( reg ) {
        var lastDismissed = localStorage.getItem( PROMPT_KEY );
        if ( lastDismissed ) {
            var daysSince = ( Date.now() - parseInt( lastDismissed, 10 ) ) / 86400000;
            if ( daysSince < PROMPT_COOLDOWN ) return;
        }

        // Show after 5 seconds — let the user read a bit of the page first.
        // If an ad popup is still open at that point, wait until it closes.
        setTimeout( function () {
            waitForAdsThenShow( injectSoftPrompt, reg );
        }, 5000 );
    }

    /**
     * If bp-ads-manager has a visible popup (.bp-popup-visible), poll every 500 ms
     * until it closes, then run callback. If no ad is open, run immediately.
     */
    function waitForAdsThenShow( callback, arg ) {
        function adIsOpen() {
            return !! document.querySelector( '.bp-popup-overlay.bp-popup-visible' );
        }
        if ( ! adIsOpen() ) {
            callback( arg );
            return;
        }
        var poll = setInterval( function () {
            if ( ! adIsOpen() ) {
                clearInterval( poll );
                // Small extra pause so the ad overlay fully fades out first
                setTimeout( function () { callback( arg ); }, 600 );
            }
        }, 500 );
    }

    function injectSoftPrompt( reg ) {
        if ( document.getElementById( 'bp-push-prompt' ) ) return; // already injected

        var el = document.createElement( 'div' );
        el.id  = 'bp-push-prompt';
        el.setAttribute( 'role', 'dialog' );
        el.setAttribute( 'aria-label', 'Enable push notifications' );

        el.innerHTML = [
            '<div class="bp-push-prompt__inner">',
            '  <span class="bp-push-prompt__icon" aria-hidden="true">🔔</span>',
            '  <div class="bp-push-prompt__text">',
            '    <strong>Order updates &amp; deals</strong>',
            '    <span>We only send what matters.</span>',
            '  </div>',
            '  <div class="bp-push-prompt__actions">',
            '    <button class="bp-push-prompt__deny"  type="button">Not now</button>',
            '    <button class="bp-push-prompt__allow" type="button">Allow</button>',
            '  </div>',
            '</div>',
        ].join( '' );

        document.body.appendChild( el );

        requestAnimationFrame( function () {
            requestAnimationFrame( function () {
                el.classList.add( 'bp-push-prompt--visible' );
            } );
        } );

        // Allow → trigger native browser permission dialog
        el.querySelector( '.bp-push-prompt__allow' ).addEventListener( 'click', function () {
            dismissPrompt( el );
            Notification.requestPermission().then( function ( permission ) {
                log( 'Permission response:', permission );
                if ( permission === 'granted' ) {
                    navigator.serviceWorker.ready.then( subscribeUser );
                }
            } );
        } );

        // Not now → dismiss and remember
        el.querySelector( '.bp-push-prompt__deny' ).addEventListener( 'click', function () {
            dismissPrompt( el );
        } );
    }

    function dismissPrompt( el ) {
        el.classList.remove( 'bp-push-prompt--visible' );
        localStorage.setItem( PROMPT_KEY, Date.now().toString() );
        setTimeout( function () {
            if ( el.parentNode ) el.parentNode.removeChild( el );
        }, 320 );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function isTransactionalPage() {
        var path = window.location.pathname;
        return path.startsWith( '/cart' )
            || path.startsWith( '/checkout' )
            || path.startsWith( '/my-account' );
    }

    /**
     * Convert a base64url string to a Uint8Array.
     * Required by pushManager.subscribe() for the applicationServerKey.
     */
    function urlBase64ToUint8Array( base64String ) {
        var padding = '='.repeat( ( 4 - base64String.length % 4 ) % 4 );
        var base64  = ( base64String + padding ).replace( /-/g, '+' ).replace( /_/g, '/' );
        var raw     = window.atob( base64 );
        var output  = new Uint8Array( raw.length );
        for ( var i = 0; i < raw.length; i++ ) {
            output[ i ] = raw.charCodeAt( i );
        }
        return output;
    }

    // ── Boot ─────────────────────────────────────────────────────────────────
    if ( document.readyState === 'complete' ) {
        register();
    } else {
        window.addEventListener( 'load', register );
    }

}() );

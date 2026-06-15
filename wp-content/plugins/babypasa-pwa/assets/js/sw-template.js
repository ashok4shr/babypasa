/**
 * BabyPasa Service Worker — template file.
 *
 * DO NOT add 'use strict' or any config variables here.
 * This file is output by PHP (class-bp-pwa-core.php → serve_sw()) which prepends:
 *
 *   'use strict';
 *   var BP_SCOPE         = '/';            // install-path prefix
 *   var BP_OFFLINE_URL   = '/offline/';    // offline fallback URL
 *   var BP_ICON_URL      = 'https://…';    // push notification icon
 *   var BP_CACHE_VERSION = '2.2.0-1700…';  // rotates cache names on SW/plugin updates
 *
 * All hardcoded paths use _base (= BP_SCOPE with trailing slash removed) so the
 * SW works correctly whether WordPress is at the domain root (/) or a subdirectory
 * (/babypasa/ on local dev, for example).
 *
 * Caching strategies:
 *   Network-first → theme CSS/JS (so edits/deploys show immediately; falls
 *                   back to cache when offline) AND HTML navigations
 *                   (with BP_OFFLINE_URL fallback)
 *   Cache-first   → media uploads / product images (rarely change, want speed)
 *   Bypass        → WooCommerce transactional routes, admin, all AJAX
 */

// Strip trailing slash for path-prefix comparisons.
var _base = BP_SCOPE.replace( /\/$/, '' ); // '' on production, '/babypasa' on local

// ── Cache names ───────────────────────────────────────────────────────────────
var CACHE_NAMES = {
    precache : 'bp-precache-' + BP_CACHE_VERSION,
    static   : 'bp-runtime-static-' + BP_CACHE_VERSION,
    pages    : 'bp-runtime-pages-' + BP_CACHE_VERSION
};

// ── Precache list ─────────────────────────────────────────────────────────────
// Cached during SW install so they're available offline immediately.
var PRECACHE_URLS = [
    BP_SCOPE,       // homepage (e.g. '/' or '/babypasa/')
    BP_OFFLINE_URL  // offline fallback page (e.g. '/offline/' or '/babypasa/offline/')
];

// ── Bypass check — routes that must NEVER be served from cache ────────────────
function shouldBypass( url ) {
    var u    = new URL( url );
    var path = u.pathname;

    // WordPress admin & auth
    if ( path.startsWith( _base + '/wp-admin' ) )     return true;
    if ( path.startsWith( _base + '/wp-login.php' ) ) return true;
    if ( path.includes( 'admin-ajax.php' ) )          return true;

    // Social-login (Nextend) OAuth callback endpoints.
    // Root cause of the post-Google-login blank page: the OAuth return navigation
    // must always hit the network so the server can run the login + 302 redirect.
    // If the SW answered it from cache (or fell back to the offline shell), the
    // user saw a blank screen — most visibly inside the standalone PWA where there
    // is no address bar to retry. These callbacks land on either:
    //   • /wp-login.php?loginSocial=google      (default redirect behavior — already
    //                                             covered by the wp-login.php rule above)
    //   • /wp-json/nextend-social-login/v1/...   (REST "rest_redirect" behavior)
    // and any hop in the flow may carry loginSocial / code / state / redirect params.
    if ( path.indexOf( '/wp-json/nextend-social-login/' ) !== -1 ) return true;
    if ( u.searchParams.has( 'loginSocial' ) ) return true;
    if ( u.searchParams.has( 'loginGoogle' ) ) return true;
    if ( u.searchParams.has( 'code' ) )        return true;
    if ( u.searchParams.has( 'state' ) )       return true;
    if ( u.searchParams.has( 'redirect' ) )    return true;

    // WooCommerce transactional pages
    if ( path.startsWith( _base + '/cart' ) )         return true;
    if ( path.startsWith( _base + '/checkout' ) )     return true;
    if ( path.startsWith( _base + '/my-account' ) )   return true;

    // WooCommerce AJAX & nonce-bearing query params
    if ( u.searchParams.has( 'wc-ajax' ) )     return true;
    if ( u.searchParams.has( 'nonce' ) )       return true;
    if ( u.searchParams.has( 'add-to-cart' ) ) return true;

    return false;
}

// ── Route matchers ────────────────────────────────────────────────────────────
function isThemeStatic( url ) {
    return new URL( url ).pathname.startsWith( _base + '/wp-content/themes/' );
}

function isProductImage( url ) {
    return new URL( url ).pathname.startsWith( _base + '/wp-content/uploads/' );
}

// ─────────────────────────────────────────────────────────────────────────────
// Install — precache shell assets
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener( 'install', function ( event ) {
    event.waitUntil(
        caches
            .open( CACHE_NAMES.precache )
            .then( function ( cache ) { return cache.addAll( PRECACHE_URLS ); } )
            .then( function () { return self.skipWaiting(); } )
    );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Activate — delete stale caches from previous SW versions
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener( 'activate', function ( event ) {
    var allowed = new Set( Object.values( CACHE_NAMES ) );
    event.waitUntil(
        caches.keys()
            .then( function ( keys ) {
                return Promise.all(
                    keys
                        .filter( function ( key ) { return ! allowed.has( key ); } )
                        .map(    function ( key ) { return caches.delete( key ); } )
                );
            } )
            .then( function () { return self.clients.claim(); } )
    );
} );

// ─────────────────────────────────────────────────────────────────────────────
// Fetch — routing logic
// ─────────────────────────────────────────────────────────────────────────────
self.addEventListener( 'fetch', function ( event ) {
    var request = event.request;
    var url     = request.url;

    if ( request.method !== 'GET' )                    return;
    if ( ! url.startsWith( self.location.origin ) )    return;
    if ( shouldBypass( url ) )                         return;

    // Theme CSS/JS → network-first: always fetch the latest from the server when
    // online (the ?ver= query is server-ignored, so cache-first could otherwise
    // pin stale bytes); fall back to cache only when offline.
    if ( isThemeStatic( url ) ) {
        event.respondWith( networkFirstStatic( request, CACHE_NAMES.static ) );
        return;
    }

    // Uploads / product images → cache-first for speed (they rarely change).
    if ( isProductImage( url ) ) {
        event.respondWith( cacheFirst( request, CACHE_NAMES.static ) );
        return;
    }

    if ( request.mode === 'navigate' ) {
        event.respondWith( networkFirstWithFallback( request ) );
        return;
    }
} );

// ─────────────────────────────────────────────────────────────────────────────
// Strategy: Cache-first
// ─────────────────────────────────────────────────────────────────────────────
function cacheFirst( request, cacheName ) {
    return caches.open( cacheName ).then( function ( cache ) {
        return cache.match( request ).then( function ( cached ) {
            if ( cached ) return cached;
            return fetch( request )
                .then( function ( response ) {
                    if ( response && response.status === 200 && response.type !== 'opaque' ) {
                        cache.put( request, response.clone() );
                    }
                    return response;
                } )
                .catch( function () {
                    return new Response( '', { status: 408, statusText: 'Network timeout' } );
                } );
        } );
    } );
}

// ─────────────────────────────────────────────────────────────────────────────
// Strategy: Network-first for static assets (cache fallback when offline)
// ─────────────────────────────────────────────────────────────────────────────
function networkFirstStatic( request, cacheName ) {
    return caches.open( cacheName ).then( function ( cache ) {
        return fetch( request )
            .then( function ( response ) {
                if ( response && response.status === 200 && response.type !== 'opaque' ) {
                    cache.put( request, response.clone() );
                }
                return response;
            } )
            .catch( function () {
                return cache.match( request ).then( function ( cached ) {
                    return cached || new Response( '', { status: 408, statusText: 'Network timeout' } );
                } );
            } );
    } );
}

// ─────────────────────────────────────────────────────────────────────────────
// Strategy: Network-first with offline fallback
// ─────────────────────────────────────────────────────────────────────────────
function networkFirstWithFallback( request ) {
    return caches.open( CACHE_NAMES.pages ).then( function ( cache ) {
        return fetch( request )
            .then( function ( response ) {
                if ( response && response.status === 200 ) {
                    cache.put( request, response.clone() );
                }
                return response;
            } )
            .catch( function () {
                return cache.match( request ).then( function ( cached ) {
                    if ( cached ) return cached;
                    return caches.match( BP_OFFLINE_URL ).then( function ( offline ) {
                        return offline || new Response(
                            '<html><body style="font-family:sans-serif;text-align:center;padding:60px 20px">'
                          + '<h1 style="color:#FF2A61">You\'re Offline</h1>'
                          + '<p style="color:#666;margin:16px 0 24px">Check your connection and try again.</p>'
                          + '<button onclick="location.reload()" style="background:#FF2A61;color:#fff;border:none;'
                          + 'padding:12px 28px;border-radius:8px;font-size:1rem;cursor:pointer">Try Again</button>'
                          + '</body></html>',
                            { headers: { 'Content-Type': 'text/html' } }
                        );
                    } );
                } );
            } );
    } );
}

// ─────────────────────────────────────────────────────────────────────────────
// Push notifications
// ─────────────────────────────────────────────────────────────────────────────

self.addEventListener( 'push', function ( event ) {
    if ( ! event.data ) return;

    var data = {};
    try { data = event.data.json(); } catch ( e ) { data = { title: 'BabyPasa', body: event.data.text() }; }

    var title   = data.title || 'BabyPasa';
    var options = {
        body    : data.body  || '',
        icon    : data.icon  || BP_ICON_URL,
        badge   : BP_ICON_URL,
        data    : { url: data.url || BP_SCOPE },
        vibrate : [ 100, 50, 100 ],
        requireInteraction: false,
    };

    event.waitUntil( self.registration.showNotification( title, options ) );
} );

self.addEventListener( 'notificationclick', function ( event ) {
    event.notification.close();

    var targetUrl = self.location.origin + (
        ( event.notification.data && event.notification.data.url )
            ? event.notification.data.url
            : BP_SCOPE
    );

    event.waitUntil(
        clients.matchAll( { type: 'window', includeUncontrolled: true } )
            .then( function ( clientList ) {
                for ( var i = 0; i < clientList.length; i++ ) {
                    var c = clientList[ i ];
                    if ( c.url === targetUrl && 'focus' in c ) return c.focus();
                }
                if ( clients.openWindow ) return clients.openWindow( targetUrl );
            } )
    );
} );

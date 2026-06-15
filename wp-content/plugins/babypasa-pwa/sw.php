<?php
/**
 * BabyPasa PWA — Service Worker endpoint.
 *
 * This is a plain PHP file (not routed through WordPress rewrites) so the browser
 * receives the script directly with no intermediate redirect — a hard requirement
 * for service worker registration.
 *
 * The Service-Worker-Allowed header lets this file (served from /wp-content/plugins/)
 * register a SW that controls the full site scope (/ on production, /babypasa/ locally).
 *
 * PHP injects runtime config (scope, offline URL, icon URL) at the top of the output
 * before streaming sw-template.js.
 */

// ── Bootstrap WordPress ───────────────────────────────────────────────────────
// Walk up from the plugin directory to find wp-load.php (works regardless of
// how deep plugins/ is nested — handles non-standard WordPress layouts too).
$dir = __DIR__;
while ( ! file_exists( $dir . '/wp-load.php' ) ) {
    $parent = dirname( $dir );
    if ( $parent === $dir ) break; // reached filesystem root
    $dir = $parent;
}

if ( ! file_exists( $dir . '/wp-load.php' ) ) {
    http_response_code( 500 );
    header( 'Content-Type: application/javascript' );
    exit( '// BabyPasa PWA: wp-load.php not found.' );
}

require_once $dir . '/wp-load.php';

// ── Resolve dynamic values ────────────────────────────────────────────────────
$scope       = wp_parse_url( home_url( '/' ), PHP_URL_PATH ); // '/' or '/babypasa/'
$offline_url = home_url( '/offline/' );
$icon_url    = get_stylesheet_directory_uri() . '/assets/images/pwa-icons/icon-192x192.png';

// Cache-busting token for the SW's runtime caches. When this string changes, the
// SW's activate handler deletes every previously named cache (see CACHE_NAMES in
// sw-template.js), giving installed apps a clean slate. It rotates whenever the SW
// logic changes (template mtime) or the plugin version bumps. Day-to-day theme
// CSS/JS edits are served network-first (see sw-template.js), so they surface
// immediately when online and don't require a version bump here.
$cache_version = ( defined( 'BP_PWA_VERSION' ) ? BP_PWA_VERSION : '0' )
    . '-' . ( @filemtime( __DIR__ . '/assets/js/sw-template.js' ) ?: '0' );

// ── Output headers ────────────────────────────────────────────────────────────
header( 'Content-Type: application/javascript; charset=utf-8' );
header( 'Cache-Control: no-cache, no-store, must-revalidate' );
// Allow the SW (physically in /wp-content/plugins/) to control the full site scope.
// Without this header the browser would restrict scope to /wp-content/plugins/babypasa-pwa/.
header( 'Service-Worker-Allowed: ' . $scope );

// ── Inject config then stream the SW template ─────────────────────────────────
echo "'use strict';\n\n";
echo "// ── Runtime config injected by WordPress ────────────────────────────────\n";
echo "var BP_SCOPE         = " . wp_json_encode( $scope )         . ";\n";
echo "var BP_OFFLINE_URL   = " . wp_json_encode( $offline_url )   . ";\n";
echo "var BP_ICON_URL      = " . wp_json_encode( $icon_url )      . ";\n";
echo "var BP_CACHE_VERSION = " . wp_json_encode( $cache_version ) . ";\n\n";

readfile( __DIR__ . '/assets/js/sw-template.js' );
exit;

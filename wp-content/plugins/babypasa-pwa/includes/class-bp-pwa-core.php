<?php
/**
 * BabyPasa PWA — Core.
 *
 * Handles:
 *   - Rewrite rules for /manifest.json, /sw.js, /offline/
 *   - Dynamic manifest output (scope/start_url resolve correctly in any install path)
 *   - Dynamic service-worker output (config injected by PHP, no root file needed)
 *   - Offline page output (self-contained HTML, precached by the SW)
 *   - <head> PWA meta tags
 *   - Frontend asset enqueuing (pwa-register.js, ios-nudge.js, CSS)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BP_PWA_Core {

    public function __construct() {
        add_action( 'init',              [ __CLASS__, 'register_rewrites' ] );
        add_filter( 'query_vars',        [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'handle_endpoints' ] );
        add_action( 'wp_head',           [ $this, 'inject_head_tags' ], 1 );
        add_action( 'wp_enqueue_scripts',[ $this, 'enqueue_assets' ], 99 );
    }

    // ── Rewrite rules ─────────────────────────────────────────────────────────

    /**
     * Called on 'init' and also on plugin activation (static so it can be called
     * before the constructor runs).
     */
    public static function register_rewrites(): void {
        // Note: sw.js is served by sw.php (a real file) — no rewrite needed for it.
        add_rewrite_rule( '^manifest\.json$', 'index.php?bp_manifest=1', 'top' );
        add_rewrite_rule( '^offline/?$',      'index.php?bp_offline=1',  'top' );
    }

    public function add_query_vars( array $vars ): array {
        $vars[] = 'bp_manifest';
        $vars[] = 'bp_offline';
        return $vars;
    }

    public function handle_endpoints(): void {
        if ( get_query_var( 'bp_manifest' ) ) $this->serve_manifest();
        if ( get_query_var( 'bp_offline' )  ) $this->serve_offline();
    }

    // ── manifest.json ─────────────────────────────────────────────────────────

    private function serve_manifest(): void {
        $icons_base = get_stylesheet_directory_uri() . '/assets/images/pwa-icons';

        // Install-path prefix: '/' on production, '/babypasa/' on local dev.
        $scope     = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $start_url = rtrim( $scope, '/' ) . '/?source=pwa';

        $manifest = [
            'name'             => get_bloginfo( 'name' ),
            'short_name'       => 'BabyPasa',
            'description'      => 'Baby and kids products — Weaving Joyful Moments Together',
            'lang'             => 'en-US',
            'dir'              => 'ltr',
            'start_url'        => $start_url,
            'scope'            => $scope,
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'theme_color'      => '#FF2A61',
            'background_color' => '#ffffff',
            'categories'       => [ 'shopping' ],
            'icons'            => [
                [ 'src' => $icons_base . '/icon-192x192.png',          'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
                [ 'src' => $icons_base . '/icon-512x512.png',          'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
                [ 'src' => $icons_base . '/icon-maskable-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable' ],
                [ 'src' => $icons_base . '/icon-maskable-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
            ],
            'shortcuts'        => [
                [
                    'name'        => 'Shop All',
                    'short_name'  => 'Shop',
                    'description' => 'Browse all baby and kids products',
                    'url'         => home_url( '/shop/?source=pwa-shortcut' ),
                ],
                [
                    'name'        => 'My Account',
                    'short_name'  => 'Account',
                    'description' => 'View your orders and account details',
                    'url'         => home_url( '/my-account/?source=pwa-shortcut' ),
                ],
                [
                    'name'        => 'Sale Items',
                    'short_name'  => 'Sale',
                    'description' => 'See current discounts and price drops',
                    'url'         => home_url( '/price-drop/?source=pwa-shortcut' ),
                ],
            ],
        ];

        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'Cache-Control: public, max-age=3600' );
        echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        exit;
    }

    // ── /offline/ page ────────────────────────────────────────────────────────

    private function serve_offline(): void {
        $logo_url  = '';
        if ( has_custom_logo() ) {
            $logo_id  = get_theme_mod( 'custom_logo' );
            $logo_src = wp_get_attachment_image_src( $logo_id, 'full' );
            $logo_url = $logo_src ? $logo_src[0] : '';
        }
        if ( ! $logo_url ) {
            $logo_url = get_stylesheet_directory_uri() . '/images/logo-placeholder.png';
        }

        $site_name = esc_html( get_bloginfo( 'name' ) );
        $home_url  = esc_url( home_url( '/' ) );

        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, nofollow' );
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#FF2A61">
    <meta name="robots" content="noindex, nofollow">
    <title>You're Offline &mdash; <?php echo $site_name; ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f7f7f8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1a1a1a;
        }
        .bp-offline-card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 440px;
            width: 100%;
            padding: 48px 40px;
            text-align: center;
        }
        .bp-offline-logo { margin-bottom: 28px; }
        .bp-offline-logo img { max-height: 44px; width: auto; }
        .bp-offline-logo span { font-size: 1.25rem; font-weight: 700; color: #FF2A61; letter-spacing: -0.02em; }
        .bp-offline-icon { color: #FF2A61; margin-bottom: 24px; line-height: 1; }
        .bp-offline-title { font-size: 1.5rem; font-weight: 700; color: #1a1a1a; margin-bottom: 12px; letter-spacing: -0.02em; }
        .bp-offline-text { color: #666; font-size: 0.95rem; line-height: 1.65; margin-bottom: 32px; }
        .bp-offline-btn {
            display: inline-block; background: #FF2A61; color: #fff; border: none;
            border-radius: 8px; padding: 14px 36px; font-size: 1rem; font-weight: 600;
            cursor: pointer; letter-spacing: 0.02em; transition: background 0.18s ease; -webkit-appearance: none;
        }
        .bp-offline-btn:hover, .bp-offline-btn:focus { background: #e0244f; outline: none; }
        .bp-offline-back { display: block; margin-top: 20px; color: #FF2A61; text-decoration: none; font-size: 0.875rem; opacity: 0.85; }
        .bp-offline-back:hover { opacity: 1; }
        @media (max-width: 480px) { .bp-offline-card { padding: 36px 24px; } .bp-offline-title { font-size: 1.25rem; } }
    </style>
</head>
<body>
    <div class="bp-offline-card">
        <div class="bp-offline-logo">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo $site_name; ?>"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
            <span style="display:none;"><?php echo $site_name; ?></span>
        </div>
        <div class="bp-offline-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22.99 9C19.15 5.16 13.8 3.76 8.84 4.78L11 6.94c3.07-.28 6.27.67 8.62 2.98L22.99 9zm-4 4c-1.29-1.29-2.87-2.14-4.57-2.56L17 13.1l.04-.04-.05.05zM2 3.05L5.07 6.1C3.6 6.82 2.22 7.78 1 9l1.99 2c1.24-1.24 2.73-2.12 4.32-2.64l2.13 2.13C7.7 10.89 6.27 11.73 5 13l1.99 2c1.29-1.29 2.93-2.02 4.69-2.15L18.03 20l1.42-1.42L3.41 1.63 2 3.05zM9 17l3 3 3-3c-1.65-1.66-4.34-1.66-6 0z"/>
            </svg>
        </div>
        <h1 class="bp-offline-title">You're Offline</h1>
        <p class="bp-offline-text">
            It looks like you've lost your internet connection.<br>
            Check your connection and try again.
        </p>
        <button class="bp-offline-btn" onclick="window.location.reload()">Try Again</button>
        <a href="<?php echo $home_url; ?>" class="bp-offline-back">&larr; Back to <?php echo $site_name; ?></a>
    </div>
</body>
</html>
        <?php
        exit;
    }

    // ── <head> tags ───────────────────────────────────────────────────────────

    public function inject_head_tags(): void {
        if ( is_admin() ) return;

        $apple_icon = get_stylesheet_directory_uri() . '/assets/images/pwa-icons/icon-180x180.png';
        ?>
    <link rel="manifest" href="<?php echo esc_url( home_url( '/manifest.json' ) ); ?>">
    <meta name="theme-color" content="#FF2A61">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="BabyPasa">
    <link rel="apple-touch-icon" href="<?php echo esc_url( $apple_icon ); ?>">
        <?php
    }

    // ── Frontend assets ───────────────────────────────────────────────────────

    public function enqueue_assets(): void {
        $plugin = BP_PWA_URL . 'assets';
        $path   = BP_PWA_DIR . 'assets';
        $debug  = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $scope  = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

        // Push permission prompt styles
        wp_enqueue_style( 'bp-push-prompt', $plugin . '/css/bp-push-prompt.css', [], filemtime( $path . '/css/bp-push-prompt.css' ) );

        // Install prompt — Android (native) + iOS (manual instructions), same card design
        wp_enqueue_style(  'bp-ios-nudge', $plugin . '/css/bp-ios-nudge.css', [], filemtime( $path . '/css/bp-ios-nudge.css' ) );
        wp_enqueue_script( 'bp-ios-nudge', $plugin . '/js/bp-ios-nudge.js',  [], filemtime( $path . '/js/bp-ios-nudge.js' ), true );

        // Resolve site logo for the install card (custom logo → PWA icon fallback).
        $logo_id   = get_theme_mod( 'custom_logo' );
        $logo_src  = $logo_id ? wp_get_attachment_image_src( $logo_id, 'thumbnail' ) : false;
        $site_icon = $logo_src
            ? $logo_src[0]
            : get_stylesheet_directory_uri() . '/assets/images/pwa-icons/icon-192x192.png';

        // Inject config before the install prompt script.
        wp_add_inline_script(
            'bp-ios-nudge',
            'var bpNudgeDebug    = ' . ( $debug ? 'true' : 'false' ) . '; '
            . 'var bpNudgeScope    = ' . wp_json_encode( $scope ) . '; '
            . 'var bpNudgeSiteIcon = ' . wp_json_encode( $site_icon ) . '; '
            . 'var bpNudgeSiteName = ' . wp_json_encode( get_bloginfo( 'name' ) ) . ';',
            'before'
        );

        // Service worker registration + push subscription
        wp_enqueue_script( 'bp-pwa-register', $plugin . '/js/pwa-register.js', [], filemtime( $path . '/js/pwa-register.js' ), true );

        // Pass runtime config to JS. All values resolved by PHP so they work in
        // both local dev and production without any manual changes after migration.
        wp_localize_script(
            'bp-pwa-register',
            'bpPWA',
            [
                // sw.php is a real file in the plugin — no redirect, no rewrite rule needed.
                // Service-Worker-Allowed header in sw.php lets it control the full site scope.
                'swUrl'          => BP_PWA_URL . 'sw.php',
                'scope'          => $scope,
                'debug'          => $debug,
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'bp_push_nonce' ),
                // Only expose the PUBLIC key to JS. Private key stays in wp_options/wp-config.php.
                'vapidPublicKey' => BP_PWA_Settings::get_vapid_public(),
            ]
        );
    }
}

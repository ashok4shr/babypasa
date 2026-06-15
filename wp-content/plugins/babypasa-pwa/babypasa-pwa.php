<?php
/**
 * Plugin Name: BabyPasa PWA
 * Description: Complete Progressive Web App — manifest, service worker, offline page, iOS install nudge, and push notifications. All settings are stored in the database so everything migrates automatically with your site.
 * Version:     2.0.0
 * Author:      Ashok Shrestha
 * Text Domain: babypasa-pwa
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BP_PWA_VERSION', '2.2.0' );
define( 'BP_PWA_DIR',     plugin_dir_path( __FILE__ ) );
define( 'BP_PWA_URL',     plugin_dir_url( __FILE__ ) );

// ── Composer autoloader (web-push-php) ───────────────────────────────────────
$bp_pwa_autoloader = BP_PWA_DIR . 'vendor/autoload.php';
if ( ! file_exists( $bp_pwa_autoloader ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>BabyPasa PWA:</strong> Composer dependencies missing. ';
        echo 'Run <code>composer install</code> inside <code>wp-content/plugins/babypasa-pwa/</code>.';
        echo '</p></div>';
    } );
    return;
}
require_once $bp_pwa_autoloader;

// ── Load includes ─────────────────────────────────────────────────────────────
require_once BP_PWA_DIR . 'includes/class-bp-pwa-core.php';
require_once BP_PWA_DIR . 'includes/class-bp-pwa-settings.php';
require_once BP_PWA_DIR . 'includes/class-bp-push-db.php';
require_once BP_PWA_DIR . 'includes/class-bp-push-sender.php';
require_once BP_PWA_DIR . 'includes/class-bp-push-admin.php';
require_once BP_PWA_DIR . 'includes/class-bp-push-triggers.php';
require_once BP_PWA_DIR . 'includes/class-bp-pwa-auth-redirect.php';

// ── Activation ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'bp_pwa_activate' );
function bp_pwa_activate(): void {
    // Create subscriptions table
    BP_Push_DB::create_table();

    // Migrate VAPID keys from wp-config.php constants to wp_options on first activation.
    // After this they live in the DB and travel with any migration.
    if ( defined( 'BP_VAPID_PUBLIC_KEY' ) && ! get_option( 'bp_pwa_vapid_public' ) ) {
        update_option( 'bp_pwa_vapid_public', BP_VAPID_PUBLIC_KEY );
    }
    if ( defined( 'BP_VAPID_PRIVATE_KEY' ) && ! get_option( 'bp_pwa_vapid_private' ) ) {
        update_option( 'bp_pwa_vapid_private', BP_VAPID_PRIVATE_KEY );
    }
    if ( defined( 'BP_VAPID_SUBJECT' ) && ! get_option( 'bp_pwa_vapid_subject' ) ) {
        update_option( 'bp_pwa_vapid_subject', BP_VAPID_SUBJECT );
    }

    // Register rewrite rules then flush so manifest.json / sw.js / offline/ routes work immediately.
    BP_PWA_Core::register_rewrites();
    flush_rewrite_rules();
}

// ── Deactivation ─────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

// ── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'bp_pwa_boot' );
function bp_pwa_boot(): void {
    new BP_PWA_Core();
    new BP_PWA_Settings();
    new BP_Push_Admin();

    // Harden the social-login (Google) post-auth redirect so the OAuth return
    // never lands on the bare homepage (which the SW precaches → blank in PWA).
    // Hooks Nextend's nsl_google*last_location_redirect filters; standard
    // username/password login never fires those filters, so it is unaffected.
    new BP_PWA_Auth_Redirect();

    // AJAX: save push subscription (logged-in + guests)
    add_action( 'wp_ajax_bp_push_subscribe',          'bp_push_handle_subscribe' );
    add_action( 'wp_ajax_nopriv_bp_push_subscribe',   'bp_push_handle_subscribe' );

    // AJAX: remove push subscription
    add_action( 'wp_ajax_bp_push_unsubscribe',        'bp_push_handle_unsubscribe' );
    add_action( 'wp_ajax_nopriv_bp_push_unsubscribe', 'bp_push_handle_unsubscribe' );

    // WooCommerce order triggers
    if ( class_exists( 'WooCommerce' ) ) {
        new BP_Push_Triggers();
    }
}

// -------------------------------------------------------------------------
// Nextend Social Login — PWA standalone "Continue…" auto-follow fallback
// Migrated from functions.php — 2026-06-05
// -------------------------------------------------------------------------
// In an installed PWA (display-mode: standalone), the Google OAuth popup can
// return to a near-blank intermediate page whose <body> contains only a single
// "Continue…" anchor. This is NSL's handlePopupRedirectAfterAuthentication()
// (wp-content/plugins/nextend-facebook-connect/includes/provider.php ~line 401).
// Its inline script tries window.close() / BroadcastChannel, both of which
// silently no-op in standalone mode, leaving the user stranded on the link.
//
// This snippet auto-follows that lone anchor. It is scoped strictly to NSL
// callback requests (the ?loginSocial=… query var is present) so it never runs
// on ordinary pages, only fires inside a standalone PWA, and only when the body
// really is "nearly empty with one link" — so it can't hijack a normal page.
//
// IMPORTANT CAVEAT: NSL prints that "Continue…" page as raw HTML followed by
// exit(), so it loads NO wp_footer — this hook will not fire on that exact page
// in the popup flow. It therefore acts as a safety net for any *themed* NSL
// callback variant. The provider.php hard-redirect fallback handles the popup
// page itself (see the "Continue…" blank-page fix in provider.php).
add_action( 'wp_footer', 'bp_nsl_pwa_continue_autofollow', 99 );
if ( ! function_exists( 'bp_nsl_pwa_continue_autofollow' ) ) {
    function bp_nsl_pwa_continue_autofollow() {
        // Only on Nextend Social Login OAuth callback URLs.
        if ( ! isset( $_GET['loginSocial'] ) ) {
            return;
        }
        ?>
        <script>
        (function () {
            // Only act inside an installed PWA (standalone / fullscreen / minimal-ui).
            var isStandalone = window.navigator.standalone === true || ( window.matchMedia && (
                window.matchMedia( '(display-mode: standalone)' ).matches ||
                window.matchMedia( '(display-mode: fullscreen)' ).matches ||
                window.matchMedia( '(display-mode: minimal-ui)' ).matches
            ) );
            if ( ! isStandalone ) {
                return;
            }

            function tryFollow() {
                if ( ! document.body ) {
                    return false;
                }
                var anchors  = document.body.querySelectorAll( 'a[href]' );
                var bodyText = ( document.body.textContent || '' ).replace( /\s+/g, ' ' ).trim();
                // "Nearly empty" page: exactly one link and almost no other text.
                if ( anchors.length === 1 && anchors[0].href && bodyText.length <= 40 ) {
                    window.location.replace( anchors[0].href );
                    return true;
                }
                return false;
            }

            if ( document.readyState === 'loading' ) {
                document.addEventListener( 'DOMContentLoaded', tryFollow );
            } else {
                tryFollow();
            }
        })();
        </script>
        <?php
    }
}

// ── AJAX: subscribe ───────────────────────────────────────────────────────────
if ( ! function_exists( 'bp_push_handle_subscribe' ) ) {
    function bp_push_handle_subscribe(): void {
        check_ajax_referer( 'bp_push_nonce', 'nonce' );

        $endpoint = sanitize_text_field( wp_unslash( $_POST['endpoint'] ?? '' ) );
        $p256dh   = sanitize_text_field( wp_unslash( $_POST['p256dh']   ?? '' ) );
        $auth     = sanitize_text_field( wp_unslash( $_POST['auth']     ?? '' ) );

        if ( ! $endpoint || ! $p256dh || ! $auth ) {
            wp_send_json_error( [ 'message' => 'Missing subscription data.' ], 400 );
        }

        $saved = BP_Push_DB::save_subscription( get_current_user_id(), $endpoint, $p256dh, $auth );
        if ( $saved ) {
            wp_send_json_success( [ 'message' => 'Subscribed.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Database error saving subscription.' ], 500 );
        }
    }
}

// ── AJAX: unsubscribe ─────────────────────────────────────────────────────────
if ( ! function_exists( 'bp_push_handle_unsubscribe' ) ) {
    function bp_push_handle_unsubscribe(): void {
        check_ajax_referer( 'bp_push_nonce', 'nonce' );

        $endpoint = sanitize_text_field( wp_unslash( $_POST['endpoint'] ?? '' ) );
        if ( ! $endpoint ) {
            wp_send_json_error( [ 'message' => 'Missing endpoint.' ], 400 );
        }

        BP_Push_DB::delete_subscription( $endpoint );
        wp_send_json_success( [ 'message' => 'Unsubscribed.' ] );
    }
}

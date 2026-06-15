<?php
/**
 * BabyPasa PWA — Social-login post-auth redirect.
 *
 * ── Root cause of the "blank page after Continue with Google" bug ────────────
 * Two compounding issues produced the blank screen in both the browser and the
 * installed PWA:
 *
 *   1. Empty / unusable redirect_to after Google OAuth.
 *      The "Continue with Google" button lives inside the My Account auth card.
 *      Nextend captures the *current* page URL as its `redirect` argument, but
 *      Nextend's own NextendSocialLogin::isAllowedRedirectUrl() rejects any URL
 *      that starts with the login URL (wp-login.php / the account/login page).
 *      With no usable redirect, NextendSocialProvider::getLastLocationRedirectTo()
 *      falls back to bare site_url() — the homepage "/". Because the PWA service
 *      worker PRECACHES the homepage ("/") on install, the post-OAuth navigation
 *      to "/" could be answered from the cache/offline shell, rendering blank
 *      (especially in standalone PWA mode where there is no address bar to retry).
 *
 *   2. The OAuth callback endpoints were not all on the SW bypass list.
 *      (Fixed separately in assets/js/sw-template.js → shouldBypass().)
 *
 * ── The fix in this file ─────────────────────────────────────────────────────
 * We hook Nextend's final, always-applied redirect filter
 * `nsl_{providerId}last_location_redirect` (and the earlier
 * `nsl_{providerId}default_last_location_redirect`) for the Google provider.
 * When Nextend has NOT resolved an explicit, allowed destination (i.e. it is
 * about to send the user to the bare homepage / site root), we force the user to
 * the WooCommerce My Account page instead — a deterministic, never-precached,
 * always-fresh page. This gives a reliable landing page in both the browser and
 * the installed PWA.
 *
 * This ONLY affects social-login redirects: the `nsl_google*` filters are fired
 * exclusively from Nextend's social-login flow, so standard WooCommerce
 * username/password login is completely untouched.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BP_PWA_Auth_Redirect {

    /**
     * Provider IDs whose post-login redirect we want to harden.
     * Google is the only active social provider on this store, but listing them
     * keeps the hook wiring trivial should another provider be enabled later.
     *
     * @var string[]
     */
    private $providers = [ 'google' ];

    public function __construct() {
        foreach ( $this->providers as $provider_id ) {
            // Final filter Nextend applies unconditionally right before redirecting.
            // Priority 99 so we run after Nextend's own defaults but still allow a
            // genuinely-requested, allowed redirect (e.g. ?redirect_to=/cart/) to win.
            add_filter(
                'nsl_' . $provider_id . 'last_location_redirect',
                [ $this, 'force_account_redirect' ],
                99,
                2
            );

            // Earlier "default" filter — covers the case where Nextend never
            // resolves an explicit redirect and would fall through to site root.
            add_filter(
                'nsl_' . $provider_id . 'default_last_location_redirect',
                [ $this, 'force_account_redirect' ],
                99,
                2
            );
        }
    }

    /**
     * Replace an empty / site-root redirect with the My Account page.
     *
     * @param string $redirect_to           Destination Nextend resolved.
     * @param string $requested_redirect_to The redirect explicitly requested in the
     *                                       login flow (empty when none was usable).
     *
     * @return string
     */
    public function force_account_redirect( $redirect_to, $requested_redirect_to = '' ) {
        // If the visitor genuinely requested a specific, non-home destination,
        // respect it — don't override an intentional ?redirect_to=/cart/ etc.
        if ( ! empty( $requested_redirect_to ) ) {
            return $redirect_to;
        }

        $account_url = $this->get_account_url();
        if ( '' === $account_url ) {
            // WooCommerce/account page unavailable — leave Nextend's value intact.
            return $redirect_to;
        }

        $home  = untrailingslashit( home_url( '/' ) );
        $site  = untrailingslashit( site_url() );
        $value = untrailingslashit( (string) $redirect_to );

        // Only override when Nextend is about to dump the user on the bare home /
        // site root (the failure mode that renders blank from the PWA precache).
        if ( '' === $value || $value === $home || $value === $site ) {
            return $account_url;
        }

        return $redirect_to;
    }

    /**
     * Resolve the WooCommerce My Account permalink, with a safe home fallback.
     *
     * @return string Account URL, or '' if it cannot be resolved.
     */
    private function get_account_url() {
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $url = wc_get_page_permalink( 'myaccount' );
            if ( $url ) {
                return $url;
            }
        }

        return '';
    }
}

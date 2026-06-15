# BabyPasa Google Login

Lightweight "Continue with Google" for WooCommerce using the OAuth 2.0
**Authorization Code flow with a full-page redirect** — no popup window, so none
of the Nextend popup-bridge "Continue..." blank-page problems (desktop vs
standalone PWA vs Chrome-with-PWA-installed) can occur.

Zero external dependencies: WordPress HTTP API + a single settings option.

## How it works

```
[ Continue with Google ]  →  /bp-google-auth/            (we build the auth URL)
        →  accounts.google.com  (full-page redirect, user picks account)
        →  /bp-google-auth/callback/?code=…&state=…       (Google returns)
        →  validate state → exchange code for id_token (server-to-server)
        →  match user by verified email (or create a customer)
        →  log in → redirect to My Account (or the page they came from)
```

The whole browser tab navigates — there is no `window.opener`, no
`window.close()`, no `BroadcastChannel`, and no intermediate page.

## Setup (one-time)

1. **Google Cloud Console → APIs & Services → Credentials** — create or reuse an
   **OAuth 2.0 Client ID** of type *Web application* (you can reuse the same
   project as Nextend).
2. Add this **Authorized redirect URI** exactly (shown on the settings page too):

   ```
   https://<your-domain>/bp-google-auth/callback/
   ```
3. **Settings → Google Login** — paste the **Client ID** + **Client Secret**,
   tick **Enable**, Save.

## Testing alongside Nextend (recommended)

Both plugins can run at once. Put the new button on any page/post with:

```
[bp_google_login]
```

Test the exact failing scenario: install the PWA, then open the site in a
**regular Chrome tab** (not the PWA) and sign in with the new button. It should
land you on My Account with no "Continue..." page — and behave identically on
desktop and inside the standalone PWA.

`redirect_to="https://…"` (on-site only) overrides the post-login destination.

## Cutover (after testing passes)

1. In the theme's `woocommerce/myaccount/form-login.php`, replace the Nextend
   shortcode inside `.bp-auth-social`:
   - from: `do_shortcode('[nextend_social_login provider="google"]')`
   - to:   `do_shortcode('[bp_google_login]')`
2. Deactivate **Nextend Social Login**.
3. Now obsolete and removable:
   - the `provider.php` "Continue..." hard-redirect edit,
   - `BP_PWA_Auth_Redirect` (babypasa-pwa) — it only hooks `nsl_google*` filters,
   - `bp_nsl_pwa_continue_autofollow` (babypasa-pwa).

   (Leave them while NSL is still active — they're harmless and only fire on NSL
   callbacks.)

## Notes

- **Account linking** is by *verified* Google email (`email_verified` must be
  true), so existing customers — including everyone NSL created — sign in with no
  migration. The Google `sub` is stored in user meta (`_bp_google_sub`).
- **Security:** single-use CSRF `state` (server-side transient), exact
  `redirect_uri` match, server-to-server token exchange, and `aud`/`iss`/`exp`/
  `email_verified` claim validation. ID-token signature verification is skipped
  deliberately — the token comes straight from Google's token endpoint over an
  authenticated HTTPS channel, never via the browser.
- If you change your domain/permalinks and the routes 404, just visit
  **Settings → Permalinks** once to re-flush rewrite rules (or re-activate the plugin).

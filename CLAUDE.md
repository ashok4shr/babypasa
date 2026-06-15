# babypasa — Version Control & Engineering Guide

## Project overview
**babypasa** is a production **WooCommerce** store running on WordPress with the
**GeneratePress** theme. Customizations live in a set of in-house plugins and a
**GeneratePress child theme**. This repository tracks **only** that custom code so it
can be edited locally and deployed to production via a clean `git pull`.

The repo root mirrors the live WordPress root, so tracked files sit at their real
runtime paths (e.g. `wp-content/plugins/...`, `wp-content/themes/generatepress-child/`).
A `git pull` on prod updates the files in place — no build step, no path rewriting.

## What this repo tracks vs. excludes

**Tracked (custom code only):**
- The custom MU-plugin `wp-content/mu-plugins/babypasa-seo.php`
- The custom plugins under `wp-content/plugins/` listed below
- The child theme `wp-content/themes/generatepress-child/`

**Deliberately excluded (and why):**
- **WordPress core** (`wp-admin/`, `wp-includes/`, root `wp-*.php`) — managed by WP updates.
- **`wp-config.php`** and any secrets/credentials — environment-specific, sensitive.
- **Third-party plugins** — WooCommerce, Rank Math SEO, WP Mail SMTP, Zoho ZeptoMail
  (`transmail`), All-in-One WP Migration, Child Theme Configurator, File Manager Advanced.
  Managed via the WP plugin updater, not source control.
- **Parent `generatepress` theme** and bundled `twentytwenty*` themes — third-party.
- **`ConnectIPS/` and `UpayaDelivery/`** in `wp-content/plugins/` — these are **Magento 2
  modules** (upstream reference for the WP ports), not WordPress plugins; they do not run
  in WP and are not tracked.
- **`wp-content/uploads/`** (media), **`wp-content/ai1wm-backups/`** (migration dumps),
  **`wp-content/upgrade/`** (transient), **`*.log`**, **`*.zip`/DB dumps/backups**,
  **`node_modules/`**, **`vendor/`** — not source.

The `.gitignore` uses a **whitelist** model: ignore everything, then re-include only the
approved paths, and re-assert ignores for secrets/dumps/archives/build deps even inside
tracked directories.

## Tracked custom plugins

| Slug | Main file | Purpose |
|---|---|---|
| `upaya-cargo-woocommerce` | `upaya-cargo-woocommerce.php` | Upaya Cargo logistics: live shipping rates at checkout, automatic order submission, real-time tracking. |
| `babypasa-delivery-overrides` | `babypasa-delivery-overrides.php` | Free-delivery product flag, area-based shipping-cost overrides, My Account order tracking — sits on top of Upaya without modifying it. |
| `babypasa-connectips` | `babypasa-connectips.php` | ConnectIPS payment gateway (RSA-signed, direct redirect). Sets Upaya `cod_amount = 0` on paid orders. |
| `babypasa-admin-order-enhancements` | `babypasa-admin-order-enhancements.php` | Admin order screen: unified address form with Upaya delivery-area selector, auto-calculated shipping, payment-status tracking. |
| `babypasa-returns` | `babypasa-returns.php` | Customer-initiated returns + Upaya RTO (return-to-origin) flow; wires client-design emails E16–E20. |
| `babypasa-address-book` | `babypasa-address-book.php` | Saved addresses in My Account, fast-fill at checkout. |
| `babypasa-wishlist-compare` | `babypasa-wishlist-compare.php` | WooCommerce Wishlist (My Account) and Compare (max 3 items). |
| `babypasa-pwa` | `babypasa-pwa.php` | Full PWA: manifest, service worker, offline page, iOS install nudge, push notifications. DB-stored settings migrate with the site. |
| `babypasa-google-login` | `babypasa-google-login.php` | "Continue with Google" via OAuth 2.0 full-page redirect (avoids Nextend popup-bridge issues in PWA/Chrome). |
| `babypasa-newsletter` | `babypasa-newsletter.php` | Newsletter subscription management. |
| `bp-ads-manager` | `bp-ads-manager.php` | Popup and banner ad management (custom DB table, no CPT). |
| `bp-contact-form` | `bp-contact-form.php` | AJAX contact form with honeypot, rate limiting, admin inbox, email notifications. |
| `babypasa-faq` | `babypasa-faq.php` | FAQ management: custom admin UI, accordion display, category grouping. |
| `price-drop-notification` | `price-drop-notification.php` | Logged-in users subscribe to price-drop alerts per product. |

**MU-plugin:** `wp-content/mu-plugins/babypasa-seo.php` — *Babypasa SEO Enhancements*:
technical SEO schema + meta supplementing Rank Math free tier; schema URLs derive from
`home_url()` so it behaves identically on localhost and production.

## Child theme
`wp-content/themes/generatepress-child/` is the only theme tracked. It declares
`Template: generatepress`, so it **depends on the parent GeneratePress theme** being
installed on every environment (the parent is updated via WP, not tracked here). All
theme-level customization belongs in the child theme — never edit the parent.

## Local → prod workflow
1. Edit custom code **locally** (this repo).
2. `git add` / `git commit` the change.
3. `git push origin main`.
4. On production: `git pull` in the WordPress root updates the tracked files in place.

> The production remote `main` may diverge from local history. Reconcile before pushing
> (see "Remote" below). Never `git pull` over uncommitted prod edits without checking.

## Engineering conventions (honor on all future work)
- **Child-theme-only theme edits** — never modify the parent GeneratePress theme.
- **No WordPress core or WooCommerce core modifications** — extend via hooks only.
- **Idiomatic hooks** — use actions/filters; avoid template overrides unless necessary.
- **Security** — sanitize input, escape output, verify nonces, use capability checks. Follow WPCS.
- **Assets** — enqueue via `wp_enqueue_script` / `wp_enqueue_style`; version with `filemtime`.
- **Minimal diffs** — match surrounding style; don't reformat untouched code.
- **Direct third-party plugin edits** — avoid; if unavoidable, annotate with a clear
  `// BABYPASA EDIT — lost on plugin update` warning comment so the change is recoverable.

## Guardrails
- **Never commit secrets:** `wp-config.php`, `.env`, API credentials (Upaya / ConnectIPS),
  `*.key` / `*.pem` / `*.crt`, auth tokens.
- **Never commit** uploads, caches, logs, DB dumps, backups, `node_modules`, or `vendor`.
- **Never** track WordPress core or third-party plugins/themes.

# CLAUDE.md — Upaya Cargo WooCommerce

Nepal-only WooCommerce integration: live delivery rates, automatic order submission to Upaya, webhook status sync, and on-site tracking.

---

## File map

| File | Role |
|---|---|
| `upaya-cargo-woocommerce.php` | Bootstrap, constants, DB table creation |
| `includes/class-upaya-core.php` | Singleton loader — boots all subsystems at `plugins_loaded:20` |
| `includes/class-upaya-api.php` | HTTP client wrapping all Upaya API endpoints |
| `includes/class-upaya-checkout.php` | All checkout customisations (see below) |
| `includes/class-upaya-shipping-method.php` | WC shipping method; calls `/order-rates`, caches 10 min |
| `includes/class-upaya-order-manager.php` | Order submission, description/client_note limits (≤200 / ≤255) + item-split, tracking, DB row |
| `includes/class-upaya-location-cache.php` | Caches `/locations` in WP transient (12 h TTL) |
| `includes/class-upaya-webhook.php` | `POST /wp-json/upaya-cargo/v1/webhook` REST route |
| `includes/class-upaya-webhook-processor.php` | Validates payload → updates order → fires email |
| `includes/emails/class-upaya-status-email.php` | `WC_Email` for delivery status notifications (lazy-loaded) |
| `admin/class-upaya-admin.php` | WC settings tab (API key, fallback rate, webhook, cache flush) |
| `admin/class-upaya-meta-box.php` | Admin order meta box: status badge, tracking, resubmit |

**API base:** `https://portal-api.upaya.com.np/api/v1/client`  
**Auth:** `X-API-Key` header — set in WooCommerce → Settings → Shipping → Upaya Cargo.  
**Endpoints used:** `GET /locations`, `POST /order-rates`, `POST /add-order`, `GET /track-order/{id}`

---

## Checkout fields — billing priorities

| Field | Priority | Notes |
|---|---|---|
| `billing_hub_area` | 49 | Combined Hub › Area SelectWoo; writes `billing_state` + `billing_city` via JS |
| `billing_state` | 50 | Hidden — JS-populated hub name |
| `billing_city` | 55 | Hidden — JS-populated area name |
| `billing_address_1` | 60 | |
| `billing_address_2` | 65 | |
| `billing_landmark` | 66 | Nearest Landmark (optional) |
| `billing_postcode` | 70 | |
| `billing_phone` | 80 | Mobile Number — required, 10 digits. Set in **two** places: `override_billing_phone_field()` (`woocommerce_billing_fields`) for the server render, **and** the `phone` block in `override_default_address_fields()` for the locale JSON. Both are required — see note below |
| `billing_alternate_phone` | 81 | Alternate Mobile Number (optional) |
| `billing_email` | 85 | |

Shipping section mirrors billing (`shipping_hub_area` at 49). WC re-sorts each fieldset by `priority` after `woocommerce_checkout_fields`, so DOM order = priority order on all viewports. Both phone fields are `form-row-wide`.

> **Phone-field gotcha (don't regress):** `phone` is a WooCommerce *default address field* AND a *locale field*. After the server renders the form, `address-i18n.js` re-applies each locale field's `label`/`required`/`priority` from the country-locale JSON and **re-sorts the billing rows client-side**. That JSON is built from `woocommerce_get_country_locale_default` → `get_default_address_fields()`, NOT from `woocommerce_billing_fields`/`woocommerce_checkout_fields`. So phone must be configured in **both**: `override_billing_phone_field()` (server) and the `phone` block in `override_default_address_fields()` (locale). Omit the locale side and Mobile Number renders correctly for a split second, then the JS snaps it back to priority 100 (bottom). Omit `required` in the locale block and the JS marks phone *not* required.

`copy_billing_to_shipping_on_save()` copies billing → shipping on `woocommerce_checkout_create_order` when "Ship to different address?" is unchecked.

---

## Order meta keys

All order meta is read/written via **WooCommerce CRUD** (`$order->get_meta()` / `$order->update_meta_data()` + `$order->save()`), never `get_post_meta`/`update_post_meta`, so values land in the canonical store (HPOS table when enabled, post meta otherwise). Webhook lookups use `wc_get_orders()` meta_query (HPOS-aware).

| Key | Description |
|---|---|
| `_billing_alternate_phone` | Alternate mobile number |
| `_upaya_landmark` | Nearest landmark |
| `_upaya_submitted` | `1` after first successful `/add-order` call |
| `_upaya_order_id` | Upaya tracking ID(s) — comma-separated when chunked |
| `_upaya_reference_id` | `orderReferenceId`(s) returned by `/add-order` — used as a fallback webhook lookup key |

---

## Webhook

**URL:** `POST /wp-json/upaya-cargo/v1/webhook` (copied from WC → Settings → Upaya Cargo).  
**Required payload fields:** `tracking_code`, `status`, `order_reference_id`.  
**Auth (optional):** `X-Upaya-Webhook-Secret` header + domain allowlist — both in admin settings.  
**Unknown orders:** return HTTP 200 with `{"success":false}` so Upaya stops retrying.

### Status → WC order status (`STATUS_MAP`)

| Upaya status | WC status |
|---|---|
| `delivered` | `completed` |
| `cancelled` | `cancelled` |
| `failed-pickup` | `on-hold` |
| `on-field-failed-delivery` | `on-hold` |
| `hold` | `on-hold` |
| `loss-and-damage` | `on-hold` |

All other statuses: order note + customer email only (no WC status change).  
Guard: `on-hold` is never applied when current WC status is `completed`, `cancelled`, or `refunded`.

### Customer email (`NOTABLE_STATUSES`)

Email fires for: `dispatched-with-rider`, `out-for-delivery`, `delivered`, `failed-pickup`, `on-field-failed-delivery`, `cancelled`, `returned-to-vendor`, `loss-and-damage`, `hold`, `partially-delivered`.  
Toggle: `upaya_webhook_notify_customer` option (default `yes`).

### Full Upaya status vocabulary (from Magento reference + API docs)

These are all statuses Upaya may push via webhook. Statuses not in `STATUS_MAP` produce a note + email only.

`pending` · `unassigned-pickup` · `assigned-pickup` · `picked-up-by-rider` · `inbound-at-warehouse` · `midmile-sortation` · `prepared-for-transit` · `in-transit-to-hub` · `received-at-hub` · `in-hub` · `hub-transfer-initiated` · `hub-transfer-in-transit` · `hub-transferred` · `ready-for-dispatch` · `dispatched-with-rider` · `out-for-delivery` · `delivered` · `failed-pickup` · `on-field-failed-delivery` · `delivery-rescheduled` · `attempted-delivery` · `hold` · `loss-and-damage` · `partially-delivered` · `followup-for-return` · `return-processed-from-hub` · `return-received-at-central-facility` · `confirmed-for-return` · `out-for-return` · `on-field-failed-return` · `return-to-origin-initiated` · `return-in-transit` · `returned-to-vendor` · `cancelled` · `dispose`

---

## Tracking

Tracking is **API-only** — Upaya has no public tracking URL. The `/track-order/{id}` endpoint requires `X-API-Key`, identical to the Magento reference implementation.

Customer-facing tracking is the on-site `/my-account/track-orders/` endpoint. The "Track Order" action in the orders table links there by default. Override with:

```php
add_filter( 'bp_upaya_tracking_url', fn( $url, $code, $order ) => "https://example.com/track/{$code}", 10, 3 );
```

---

## Common changes

**Field order:** Adjust `priority` in `modify_checkout_fields()` (`class-upaya-checkout.php`). Exception: `billing_phone` priority/label live in `override_billing_phone_field()` (on `woocommerce_billing_fields`).

**New checkout field:** Add to `modify_checkout_fields()` → save in `save_checkout_fields()` → show in `display_fields_in_admin()` (admin) + `display_fields_in_order_details()` (customer order page) + `add_fields_to_emails()` (emails) → add to `build_payloads()` if it goes to Upaya.

**New Upaya status:** Add to `STATUS_MESSAGES`, add WC mapping to `STATUS_MAP` if it needs a status change, add to `NOTABLE_STATUSES` if it warrants a customer email.

**Flush location cache:** WooCommerce → Settings → Shipping → Upaya Cargo → "Flush Location Cache", or programmatically: `(new UPAYA_Location_Cache($api,$logger))->flush()`.

**Email template:** HTML in `templates/emails/upaya-status-update.php`; plain text in `templates/emails/plain/upaya-status-update.php`. Filter `upaya_status_email_template_path` to override from a custom plugin.

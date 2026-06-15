# Ready-to-wire email templates

Fully-styled WooCommerce email **body** templates implementing client designs.
They render through the shared `emails/email-header.php` / `emails/email-footer.php`
/ `emails/email-styles.php` partials, so the logo, pink rule, hero, support line,
feature strip, and footer band all come from the shared design.

**Status:** E16–E20 are now **WIRED** by the `babypasa-returns` plugin (return /
RTO flow). E05/E10/E13/E14 remain **inert** — no sender/cron is hooked up yet.

| File | Client template | Feature | Status |
|---|---|---|---|
| `e05-abandoned-cart.php` | E05 | Abandoned cart recovery | inert |
| `e10-in-transit-too-long.php` | E10 | "Still in transit" reassurance | inert |
| `e13-review-request.php` | E13 | Post-delivery review request | inert |
| `e14-replenishment.php` | E14 | Replenishment reminder | inert |
| `e16-failed-delivery.php` | E16 | Failed delivery attempt (positive retry) | **wired** (`babypasa-returns`) |
| `e17-rto-initiated.php` | E17 | RTO initiated (parcel on its way back) | **wired** (`babypasa-returns`) |
| `e18-return-requested.php` | E18 | Customer return request received | **wired** (`babypasa-returns`) |
| `e19-return-approved.php` | E19 | Return request approved | **wired** (`babypasa-returns`) |
| `e20-rto-complete.php` | E20 | RTO complete (parcel back at warehouse) | **wired** (`babypasa-returns`) |

## E16–E20 are live (babypasa-returns plugin)

The `babypasa-returns` plugin supplies the senders (`WC_Email` classes with ids
`bp_failed_delivery` / `bp_rto_initiated` / `bp_return_requested` /
`bp_return_approved` / `bp_rto_complete`), the RTO state machine, the customer
"Request a return" UI, and the admin "Approve return" order action. The hero
icon/subline cases for these ids already exist in `email-header.php`. See that
plugin's `CLAUDE.md` for the flow, webhook status map, order meta, and settings.
The plugin passes each template exactly the variables documented below.
(The remaining inert templates, E05/E10/E13/E14, still need their own senders —
wiring notes for those follow further down.)

## How to render

Each template expects its variables to be supplied by the (future) sender, then
rendered through WooCommerce's template loader so the child-theme override and the
shared header/footer partials resolve correctly:

```php
$html = wc_get_template_html(
    'emails/ready-to-wire/e13-review-request.php',
    array(
        'order'            => $order,
        'email_heading'    => 'How are you finding it, ' . $first_name . '?',
        'product_name'     => $product_name,
        'product_qty'      => $qty,
        'days_since_order' => $days,
        'review_link'      => get_permalink( $product_id ) . '#reviews',
        'email'            => $wc_email_instance, // or null for a raw wp_mail() send
    )
);
```

All four call `do_action( 'woocommerce_email_header', $email_heading, $email )` and
`do_action( 'woocommerce_email_footer', $email )`, so the logo, pink rule, hero,
support line, feature strip, and footer band come from the shared partials — the
template files contain only the body content unique to each email.

### Hero icon / subline

`emails/email-header.php` chooses the hero icon and subline with a `switch` on
`$email->id`. These templates have **no registered `WC_Email` class yet**, so the
header falls back to the default check icon with no subline. **When you wire a real
email class, add a `case` for its email id** to that switch with the client hero:

| Template | Hero icon | Subline |
|---|---|---|
| E05 | shopping bag | "Your cart is saved and waiting for you. / Your little one's essentials are just a click away!" |
| E10 | clock-search | "Order #N is in transit, {first}. / We're actively monitoring it with Upaya City Cargo." |
| E13 | heart | "It's been a few days since your {product} arrived. / We'd love to know what you think!" |
| E14 | refresh | "It's been a while since your last order. / Your little one's essentials might be running low soon!" |
| E16 | truck / redelivery | "We'll try again soon — here's your delivery info." |
| E17 | package / return | "Your order is on its way back to us." |
| E18 | return-box / check | "We're reviewing your request." |
| E19 | check / approved | "Here's how to send it back." |
| E20 | warehouse / check | "Your parcel is back with us." |

## Variable contracts

### `e05-abandoned-cart.php`
- `string $email_heading` — hero heading.
- `array $cart_items` — each: `name` (string), `qty` (int), `unit_price` (float), `line_total` (float).
- `float $cart_total` — formatted with `wc_price()` in-template.
- `string $cart_url` — `wc_get_cart_url()`.
- `string $first_name` — customer first name.
- `WC_Email|null $email`.

### `e10-in-transit-too-long.php`
- `WC_Order $order` — items looped via `$order->get_items()`.
- `string $email_heading`.
- `string $tracking_code`.
- `string $track_url` — e.g. `wc_get_account_endpoint_url( 'track-orders' )`.
- `WC_Email|null $email`.

### `e13-review-request.php`
- `WC_Order $order`.
- `string $email_heading`.
- `string $product_name`, `int $product_qty`, `int $days_since_order`.
- `string $review_link` — `get_permalink( $product_id ) . '#reviews'` (all 5 stars link here).
- `WC_Email|null $email`.

### `e14-replenishment.php`
- `string $email_heading`.
- `array $replenish_items` — each: `name` (string), `days_since_order` (int), `reorder_url` (string), `in_stock` (bool — `false` shows a "Back soon" label instead of the Reorder button).
- `string $reorder_all_url`.
- `WC_Email|null $email`.

### `e16-failed-delivery.php`
- `WC_Order $order`.
- `string $email_heading`.
- `string $tracking_code` — order meta `_upaya_tracking_code`.
- `array $address` — keys `line1`, `line2`, `city`, `district`.
- `array $items` — each: `name` (string), `qty` (int).
- `string $track_url` — e.g. `home_url( '/my-account/track-orders/' )`.
- `string $support_url` — support mailto (e.g. `mailto:support@babypasa.com`).
- `int $attempts` — delivery attempts so far (logged only; not rendered).
- `WC_Email|null $email`.

### `e17-rto-initiated.php`
- `WC_Order $order`.
- `string $email_heading`.
- `array $items` — each: `name` (string), `qty` (int).
- `array|null $refund_info` — `null` for COD/unpaid (Version B, no refund block). Otherwise keys: `amount` (string, **pre-formatted** via `wc_price()` — echo, do not re-wrap), `method` (string), `note` (string — rendered only if non-empty), `timeline` (string, e.g. `"3–5 business days"`).
- `string $shop_url` — `home_url( '/' )`.
- `string $support_url` — support mailto.
- `WC_Email|null $email`.
- Reuses `bp_email_refund_label()` / `bp_email_refund_note()` from `bp-email-helpers.php` (prefers the passed `$refund_info` values, falling back to the helpers).

### `e18-return-requested.php`
- `WC_Order $order` — its order number is injected into pending-instruction #3.
- `string $email_heading`.
- `array $return_items` — each: `name` (string), `qty` (int) — may be a subset (partial return).
- `WC_Email|null $email`.

### `e19-return-approved.php`
- `WC_Order $order` — its order number is injected into pack-instruction #2.
- `string $email_heading`.
- `array $return_items` — each: `name` (string), `qty` (int) — read from `_return_items` meta (E18), fallback to all order items.
- `string $branch_url` — Upaya branch-locator URL (Option 2 "Find a Branch").
- `string $pickup_url` — pickup-request mailto with pre-filled subject (Option 1 "Request Pickup").
- `string $support_url` — support mailto.
- `WC_Email|null $email`.

### `e20-rto-complete.php`
- `WC_Order $order`.
- `string $email_heading`.
- `array $return_items` — each: `name` (string), `qty` (int) — read from `_return_items` meta (E18), fallback to all order items.
- `array|null $refund_info` — `null` for COD/unpaid (Version B, simple "Return complete" block). Otherwise keys: `amount` (string, **pre-formatted** via `wc_price()` — echo, do not re-wrap), `method` (string), `note` (string — rendered only if non-empty), `timeline` (string).
- `WC_Email|null $email`.
- Reuses `bp_email_refund_label()` / `bp_email_refund_note()` from `bp-email-helpers.php` (prefers the passed `$refund_info` values, falling back to the helpers).

## Wiring logic (from the client DEV_NOTEs)

### E05 — Abandoned cart
- On `woocommerce_cart_updated` (logged-in, non-empty cart): stamp a "cart updated" user meta and (re)schedule a single event **90 minutes** out.
- On `woocommerce_checkout_order_created`: cancel the scheduled event (suppress when the order completes).
- Send handler: skip if `_babypasa_e05_sent` user meta is set; rebuild items from the persistent-cart user meta; after send set `_babypasa_e05_sent` and schedule a reset **+24h** (max one send per user per 24h).

### E10 — In transit too long
- **CRITICAL:** the Upaya webhook handler must stamp `_upaya_last_webhook_at` on **every** webhook hit — including the suppressed events (Arrived At / Received At) that send no customer email — so the clock tracks real carrier activity. (The webhook processor currently emails only for out-for-delivery / delivered; this stamping is an additional change to make when E10 is wired.)
- Daily cron `babypasa_daily_transit_check` at **09:00 NPT** (03:15 UTC), scheduled on activation / cleared on deactivation. No weekend skip — Upaya runs 7 days.
- Handler: orders with `_upaya_email_state = IN_TRANSIT` whose `_upaya_last_webhook_at` (fallback: order date) is **≥ 3 days** old.
- Guards: state still IN_TRANSIT; order not cancelled/refunded/failed; `_e10_sent` not set. After send set `_e10_sent`; optionally `wp_mail()` an internal "stuck order" alert to support.

### E13 — Review request
- Scheduled via `wp_schedule_single_event()` **+3 days** from the "delivered" handler.
- Guard with `_e13_sent` order meta.
- `$review_link = get_permalink( $product_id ) . '#reviews'` (or the review plugin's submission URL). Pick the first / highest-value item to feature.
- `$days_since_order = (int) ( ( time() - $order->get_date_created()->getTimestamp() ) / DAY_IN_SECONDS )`.

### E14 — Replenishment reminder
- Scheduled from the "delivered" handler, **only** for orders containing replenishable products.
- Replenishable flag = product meta `_is_replenishable` (`yes`); interval = `_replenishment_days` (defaults: **diapers 25, wipes 20, formula 28, general 30** days). Use the **minimum** interval across the order's items.
- Guard with `_e14_sent` order meta.
- Per-item `reorder_url = add_query_arg( array( 'add-to-cart' => $pid, 'quantity' => $qty ), wc_get_cart_url() )`.
- `reorder_all_url` = a custom `/reorder/{order_id}` endpoint that clears the cart, verifies order ownership, adds all replenishable items, and redirects to checkout (needs a rewrite rule + flush on activation).
- Out-of-stock items render a "Back soon" label instead of the Reorder button.

## Return / RTO flow (E16 → E21)

Two failure/return paths converge on the same refund email (E21):

```
E16 Failed delivery ─┐
                     ├─► E17 RTO initiated ───────────────┐
                     │   (Upaya couldn't deliver)          │
                     │                                     ├─► E20 RTO complete ─► E21 Refund processed
E18 Return requested ┴─► E19 Return approved ──────────────┘   (parcel back at      (LIVE —
   (customer-initiated)    (admin approves)                     warehouse)           customer-refunded-order.php)
```

- **E16 → E17** is the **logistics** path: Upaya can't deliver, the order goes
  back to origin. E16 never says "return/failed" (positive tone); when all
  attempts are exhausted the webhook advances to RTO and E17 fires.
- **E18 → E19** is the **customer-initiated** path: the customer received the
  parcel and asks to return it, then admin approves. Never fire both paths for
  the same order.
- Both paths land at **E20** when Upaya confirms the parcel is back at the
  warehouse, then at **E21** (already LIVE as `customer-refunded-order.php`) when
  admin issues the actual refund. E17/E20 only *announce* the refund — they never
  issue it.

**Why these are inert (what's missing to wire them):**

- **E16 / E17 / E20 are Upaya-webhook-triggered** on the statuses
  **"Follow Up for Return"** (E16), **"Return Process"** (E17, `RTO` state) and
  **"RTO Complete"** (E20, `RTO_COMPLETE` state). Those statuses are currently
  **NOT in the plugin's `NOTABLE_STATUSES`**, which was deliberately narrowed to
  out-for-delivery + delivered. Wiring them requires re-adding those statuses
  **plus an RTO state machine** in `class-upaya-webhook-processor.php` (state
  guards so `RTO`/`FOLLOW_UP` can only advance to `RTO_COMPLETE`, ignoring stale
  late delivery events). Send guards/meta: `_e16_last_sent` (timestamp, 12h
  cooldown — E16 may resend), `_e17_sent` (one-shot), `_e20_sent` (one-shot);
  E16 also bumps `_delivery_attempts`.
- **E18 / E19 need a custom return-request system that does not exist yet:** a My
  Account "Request Return" button (fires E18, stores `_return_requested` /
  `_return_items` / `_return_reason` / `_return_requested_at`, guard `_e18_sent`)
  and an admin "Approve Return" order action (fires E19, sets `_return_approved`,
  guard `_e19_sent`). Could instead be backed by a returns plugin
  (e.g. WP Desk's `wcrw_new_return_request`) mapped to the `name`/`qty` item shape.

### E17 & E20 — refund helpers

Both reuse `bp_email_refund_label()` / `bp_email_refund_note()` from
`bp-email-helpers.php` (the same gateway-aware helpers used by E15 cancelled and
E21 refunded). The sender passes a `$refund_info` array whose `amount` is already
formatted via `wc_price()`; the templates prefer the passed `method`/`note` and
fall back to the helpers when those are empty. When `$refund_info` is `null`
(COD/unpaid) the refund block is omitted entirely.

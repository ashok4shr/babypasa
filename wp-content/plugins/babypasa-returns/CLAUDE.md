# CLAUDE.md — BabyPasa Returns & RTO

Wires the client return/RTO emails **E16–E20** to real senders and adds a
customer return-request system. Companion to the live WooCommerce refund email
**E21** (`customer-refunded-order.php`, in the child theme).

## What it does

Two paths converge on the same warehouse-received event and, ultimately, a
manual refund (E21):

```
Logistics RTO (Upaya webhook):
  failed delivery attempt        → E16  (no state change, 12h cooldown)
  return-to-origin-initiated     → E17  (state → RTO; skipped if customer return in progress)
  returned-to-vendor (warehouse) → E20  (state → RTO_COMPLETE)

Customer return (My Account):
  "Request a return" submitted    → E18  (state → REQUESTED)
  admin "Approve return" action   → E19  (state → APPROVED)
  returned-to-vendor (warehouse)  → E20  (state → RTO_COMPLETE)

Then: admin issues refund in WooCommerce → E21 (woocommerce_order_refunded).
```

## File map

| File | Role |
|---|---|
| `babypasa-returns.php` | Bootstrap, constants, boot at `plugins_loaded:25`, activation rewrite flush |
| `includes/class-bp-returns-core.php` | Loader — instantiates the subsystems |
| `includes/class-bp-returns-state.php` | RTO state machine + shared meta keys + return-items/refund-info builders |
| `includes/class-bp-returns-emails.php` | Registers the 5 `WC_Email` classes; `::get( $id )` accessor for triggering |
| `includes/emails/class-bp-email-base.php` | Base `WC_Email` — renders a child-theme body template through the shared header/footer |
| `includes/emails/class-bp-email-*.php` | E16/E17/E18/E19/E20 subclasses (id, template, subject/heading, vars) |
| `includes/class-bp-returns-webhook-router.php` | Listens to `bp_upaya_status_processed` → E16/E17/E20 |
| `includes/class-bp-returns-request.php` | `request-return` endpoint + view-order button + submit (E18) + admin approve action (E19) |
| `templates/return-form.php` | The customer return-request form |

Email **bodies** live in the child theme:
`wp-content/themes/generatepress-child/woocommerce/emails/ready-to-wire/e16..e20-*.php`
(rendered via `wc_get_template_html()` with `template_base` = the child theme's
`woocommerce/` folder, so the shared `email-header.php` / `email-footer.php`
wrap them). The hero icon/subline for each comes from the `$email->id` switch in
`email-header.php` (ids `bp_failed_delivery`, `bp_rto_initiated`,
`bp_return_requested`, `bp_return_approved`, `bp_rto_complete`).

## Integration with the Upaya plugin

The only Upaya-plugin edit is a single decoupling action in
`class-upaya-webhook-processor.php::process()`:

```php
do_action( 'bp_upaya_status_processed', $order, $upaya_status, $tracking_code, $readable );
```

It fires for **every** processed status (not just `NOTABLE_STATUSES`). **This
edit is lost on a plugin update — re-add it** (it carries a NOTE comment).
Without it, E16/E17/E20 never fire (E18/E19 are independent of Upaya).

Webhook status → email (slug match in `class-bp-returns-webhook-router.php`):

| Upaya slug(s) | Email | State |
|---|---|---|
| `on-field-failed-delivery`, `attempted-delivery`, `followup-for-return` | E16 | unchanged |
| `return-to-origin-initiated`, `confirmed-for-return`, `out-for-return` | E17 | → RTO |
| `returned-to-vendor`, `return-received-at-central-facility` | E20 | → RTO_COMPLETE |

Adjust those `STATUSES_E16/E17/E20` consts if Upaya's real slugs differ.

## Order meta (all via WC CRUD, HPOS-safe)

| Key | Meaning |
|---|---|
| `_bp_return_state` | `REQUESTED` → `APPROVED` (customer) / `RTO` → `RTO_COMPLETE` (logistics) |
| `_bp_return_items` | JSON `[{name,qty}]` the customer chose to return |
| `_bp_return_reason` | Customer's stated reason |
| `_bp_return_requested_at` / `_bp_return_approved_at` | Timestamps |
| `_bp_delivery_attempts` | Failed-delivery counter (E16) |
| `_bp_e16_last_sent` | Last E16 timestamp (12h cooldown) |
| `_bp_e17_sent` / `_bp_e18_sent` / `_bp_e19_sent` / `_bp_e20_sent` | One-shot send guards |

## Settings, filters, options

- Emails appear under **WooCommerce → Settings → Emails** ("Return/RTO: …"),
  each with enable/subject/heading.
- `bp_returns_notify_customer` option (default `yes`) — master toggle.
- `bp_returns_window_days` filter (default `7`) — customer return eligibility window after completion.
- `bp_returns_order_eligible` filter — final say on per-order eligibility.
- `bp_returns_branch_url` filter / `bp_returns_branch_url` option (default `https://upayacargo.com/branches`) — E19 "Find a branch" link. **Verify the real Upaya branch URL.**
- `bp_returns_support_url` filter (default `mailto:support@babypasa.com`).
- Refund method/note reuse the child-theme helpers `bp_email_refund_label()` / `bp_email_refund_note()` (`emails/bp-email-helpers.php`).

## Customer flow detail

Eligibility: the order is the current user's, status `completed`, no return
state yet, within `bp_returns_window_days`. The "Request a return" button shows
on the My Account view-order page (`woocommerce_order_details_after_order_table`);
it links to `/my-account/request-return/{order_id}/`, which renders
`templates/return-form.php`. Submit → `admin-post.php?action=bp_request_return`
(nonce + ownership checked) → stores items/reason, sets state, fires E18.

Admin approval: the order-edit **Order actions** dropdown shows "Approve return
request (send E19)" while state is `REQUESTED`; running it sets `APPROVED` and
fires E19.

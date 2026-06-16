# BabyPasa Delivery Overrides — Implementation Notes

## Files created

| File | Purpose |
|---|---|
| `babypasa-delivery-overrides.php` | Plugin bootstrap; defines constants, boots at `plugins_loaded` priority 25 |
| `includes/class-free-delivery-product.php` | Feature 1 — product meta field, rate override, product-page and cart badges |
| `includes/class-area-override.php` | Feature 2 — admin settings tab, area-matching rule engine, rate override |
| `assets/css/delivery-overrides.css` | Green "Free Delivery" badge styles (product page + cart inline variant) |
| `assets/js/area-overrides-admin.js` | Add/remove rule rows in the admin settings table |

No files outside this plugin directory were modified.

---

## Hooks used

### Feature 1 — `BP_Free_Delivery_Product`

| Hook | Type | Purpose |
|---|---|---|
| `woocommerce_product_options_general_product_data` | action | Adds "Offer Free Delivery" checkbox to the product General tab |
| `woocommerce_process_product_meta` | action | Saves `_bp_free_delivery` product meta on publish/update |
| `woocommerce_package_rates` (priority 10) | filter | Zeros Upaya Cargo cost when ALL cart items carry the flag |
| `woocommerce_single_product_summary` (priority 29) | action | Renders badge on single-product page (before add-to-cart button) |
| `woocommerce_cart_item_name` (priority 10) | filter | Appends inline badge to item names in cart and checkout order review |
| `wp_enqueue_scripts` | action | Enqueues `delivery-overrides.css` on the frontend |

### Feature 2 — `BP_Area_Override`

| Hook | Type | Purpose |
|---|---|---|
| `woocommerce_settings_tabs_array` (priority 50) | filter | Adds "Delivery Overrides" tab to WooCommerce → Settings |
| `woocommerce_settings_tabs_bp_delivery_overrides` | action | Renders the settings form |
| `woocommerce_update_settings_bp_delivery_overrides` | action | Validates and saves override rules to `bp_area_delivery_overrides` option |
| `admin_enqueue_scripts` | action | Loads `area-overrides-admin.js` only on the Delivery Overrides settings page |
| `woocommerce_package_rates` (priority 20) | filter | Applies first matching area rule to the Upaya Cargo rate |

---

## Scenario 3 — per-product area-based free delivery (district-level)

> Note: the older sections above predate this scenario; the authoritative behaviour
> is the code. In particular the free-delivery checkbox lives on the product **Shipping**
> tab and a package is free when **any** item qualifies (one free item → free shipment).

A product can ship free **only inside admin-selected districts** (e.g. Kathmandu only,
excluding Bhaktapur — even though both sit under the Kathmandu Hub, because matching is
at the **district** level, not the hub level).

- **Meta:** `_bp_free_delivery_areas` — array of district tokens on the parent product
  (variations inherit via the variation→parent fallback). Empty = no area-based free
  delivery (fail-safe).
- **Admin UI:** a `wc-enhanced-select` multi-select on the product **Shipping** tab,
  directly below "Offer Free Delivery". Districts are derived from the live Upaya
  location list (`UPAYA_Location_Cache::get_locations()` → last `-` segment of each area
  name). Saved tokens are merged into the options so a selection survives a cold cache.
- **Matching:** extends `BP_Free_Delivery_Product::override_rate_if_any_free`
  (`woocommerce_package_rates`, priority 10). The destination district is the last `-`
  segment of `$package['destination']['city']` (= `billing_city`). An item qualifies when
  that district is in its `_bp_free_delivery_areas`. Free-everywhere (`_bp_free_delivery`)
  still wins, so a product with both set is free everywhere.
- **Badges:** product page shows `Free Delivery in <districts>`; the cart/checkout inline
  badge appears only when the chosen destination district actually matches.
- **Rollback:** define `BP_FREE_DELIVERY_AREAS_DISABLED` truthy, or return `false` from
  the `bp_free_delivery_areas_enabled` filter, to revert to free-everywhere-only.
- **District parsing override:** filter `bp_free_delivery_district_from_area`.
- **Area-level (future):** the picker and matcher are token-based. To select at area
  granularity instead of district, return the full area name from `district_from_area()`
  and feed full names into `get_selectable_districts()` — see the `AREA-LEVEL (future)`
  comments in the code. No other changes needed.
- **Admin orders:** `woocommerce_package_rates` does not fire on the admin order screen,
  so this override is not auto-applied there (same as the free-everywhere flag).

---

## How rate interception works

`woocommerce_package_rates` fires **after** all shipping methods (including Upaya) have called `calculate_shipping()` and added their rates to the package. This means:

1. Upaya fetches the live rate from its API (or transient cache) and calls `$this->add_rate()`.
2. WooCommerce collects all rates, then passes them through the `woocommerce_package_rates` filter.
3. Our filters run here — **no Upaya core code is touched**.

Priority order matters: Feature 1 runs at priority 10 and Feature 2 at priority 20. If a cart qualifies for both (all items free-delivery **and** a matching area rule), Feature 1 already zeroed the cost and Feature 2 may overwrite the label — both resolve to Rs. 0, so the result is always correct.

---

## Where area data comes from

`$package['destination']['city']` holds the `billing_city` value. Upaya's checkout JS
(`upaya-checkout.js`) writes the selected area name into the hidden `#billing_city` field
whenever the customer changes the combined Hub+Area dropdown, then fires `update_checkout`
to rebuild shipping. WooCommerce populates `$package['destination']` from the customer
session, so `city` = the area name exactly as it appears in Upaya's `/locations` API
(e.g. `"Kathmandu-Naya Baneshwor-Kathmandu"`).

Use **match type "Contains"** with a short keyword like `"Kathmandu"` to match all
Kathmandu sub-areas without needing to list each one individually.

---

## Testing

### Feature 1 — Free Delivery product

1. In WP Admin → Products → edit any product → General tab → check **Offer Free Delivery** → Update.
2. Add **only** that product to the cart. Go to checkout and select any delivery area.
3. The shipping row should read **"Free Delivery — Rs. 0"**.
4. Add a second product (without the flag) to the same cart.
5. The shipping cost should revert to the normal Upaya rate.
6. The **"Free Delivery"** green badge should appear on the product page (above the add-to-cart button) and next to the item name in the cart/checkout order review.

### Feature 2 — Area-based override

1. Go to **WooCommerce → Settings → Delivery Overrides**.
2. Click **+ Add Rule** and enter:
   - Area Name: `Kathmandu`
   - Match Type: `Contains`
   - Override Price: `0`
   - Label: `Free delivery inside Kathmandu Valley`
   - Enabled: checked
3. Save changes.
4. At checkout select an area whose name contains "Kathmandu" (e.g. `Kathmandu-Naya Baneshwor-Kathmandu`). The shipping rate should update immediately to **"Free delivery inside Kathmandu Valley — Rs. 0"**.
5. Select an area outside Kathmandu (e.g. Pokhara). The normal Upaya rate is shown.
6. Uncheck **Enabled** on the rule, save, and confirm the normal rate returns for Kathmandu areas.

### Edge cases to verify

- Cart with only free-delivery products **and** a Kathmandu destination: both overrides fire; cost stays Rs. 0.
- Multiple area rules: only the **first** matching rule is applied (top of the list wins).
- No area selected yet (city is empty): no override is applied; Upaya rate or fallback shown.

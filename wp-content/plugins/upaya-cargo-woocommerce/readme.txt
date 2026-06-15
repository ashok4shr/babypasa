=== Upaya Cargo Shipping for WooCommerce ===
Contributors:      Ashok Shrestha
Tags:              woocommerce, shipping, nepal, upaya, cargo
Requires at least: 5.8
Tested up to:      6.5
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Upaya Cargo shipping integration for WooCommerce — live rates at checkout,
automatic order submission, and real-time tracking.

== Description ==

Upaya Cargo Shipping for WooCommerce connects your store to the Upaya Cargo
delivery network in Nepal. Features include:

* **Live shipping rates** — calls the Upaya /order-rates API at checkout so
  customers always see accurate delivery costs.
* **Automatic order submission** — when an order moves to "Processing", the
  plugin submits it to Upaya and saves the tracking ID to the order.
* **Real-time tracking** — view live tracking status and estimated delivery
  date directly inside the WooCommerce order edit screen.
* **COD support** — the correct `cod_amount` is sent to Upaya when the
  customer chooses Cash on Delivery.
* **Retry logic** — failed submissions are retried automatically after 1 hour.
* **Debug logging** — verbose API logs written to WooCommerce > Status > Logs.

= Service types supported =

* Door To Door Delivery
* Door To Branch Delivery
* Branch To Branch Delivery
* Activation Delivery
* Bulk Delivery

= Requirements =

* WordPress 5.8+
* WooCommerce 6.0+
* PHP 7.4+
* An active Upaya Cargo merchant account and API key

== Installation ==

1. Upload the `upaya-cargo-woocommerce` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce → Settings → Upaya Cargo** and enter your API key.
4. Click **Test API Connection** to verify the key is working.
5. Go to **WooCommerce → Settings → Shipping**, open the shipping zone for
   Nepal, and add **Upaya Cargo** as a shipping method.
6. Configure the method instance settings (service type, default weight, etc.).

== Frequently Asked Questions ==

= Where do I get my API key? =

Log in to your Upaya Cargo merchant portal and navigate to the API / Developer
section. Contact Upaya Cargo support if you cannot find it.

= The shipping rate does not appear at checkout. =

Ensure your API key is saved and the Test Connection button confirms success.
Also check that the Upaya Cargo method is added to the correct shipping zone
and that the fallback rate is not set to 0 (which hides the method on API
failure).

= How do I see the debug logs? =

Enable **Debug Logging** in WooCommerce → Settings → Upaya Cargo, then view
logs at WooCommerce → Status → Logs, filtering by source `upaya-cargo`.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.

old webhook: https://babypasa.com/upaya/webhook/status

=== BabyPasa ConnectIPS Gateway ===
Contributors: Ashok Shrestha
Tags: woocommerce, payment, connectips, nepal
Requires at least: 6.1
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

ConnectIPS payment gateway for the BabyPasa WooCommerce store.

== Description ==

Integrates WooCommerce with the ConnectIPS internet banking gateway (NCHL, Nepal).

Key features:
* RSA-SHA256 token signing via merchant-issued CREDITOR.pfx certificate
* Direct redirect flow: customer is sent straight to ConnectIPS without an intermediate receipt page
* Server-to-server payment validation via the validatetxn API
* Sets order to 'processing' (not 'completed') on success — automatically triggers the Upaya Cargo plugin to submit the delivery order with cod_amount = 0
* AES-256-CBC encryption for all secrets stored in the database (PEM key, passphrase, auth password)
* Full debug logging via WooCommerce → Status → Logs (source: babypasa-connectips)
* UAT/sandbox and production environments

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/.
2. Activate in WooCommerce → Plugins.
3. Configure under WooCommerce → Settings → Payments → ConnectIPS:
   a. Enter Merchant ID, Application ID, Application Name (from NCHL).
   b. Upload your CREDITOR.pfx file and enter the passphrase.
   c. Enter the Basic Auth password (provided by NCHL).
   d. Copy the Success URL and Failure URL and send them to the ConnectIPS support team.
4. Enable the gateway.

== Callback URLs ==

These are generated automatically and displayed in the gateway settings page.
Provide them to the ConnectIPS integration support team before going live.

* Success: https://yoursite.com/?wc-api=babypasa_connectips_success
* Failure: https://yoursite.com/?wc-api=babypasa_connectips_failure

== Upaya Cargo Integration ==

When a ConnectIPS payment is validated successfully, this plugin calls
`$order->payment_complete()`, which transitions the order to 'processing'.
This fires `woocommerce_order_status_processing`, which Upaya Cargo listens to
in order to auto-submit the delivery order.

Because the payment method is 'babypasa_connectips' (not 'cod'), the Upaya plugin
automatically sends cod_amount = 0. This plugin also registers a redundant
`upaya_payload_cod_amount` filter as an explicit safety net.

== Changelog ==

= 1.0.0 =
* Initial release.

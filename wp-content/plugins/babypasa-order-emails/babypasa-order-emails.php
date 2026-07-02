<?php
/**
 * Plugin Name: BabyPasa Order Emails
 * Description: Two customer transactional emails built on the shared BabyPasa email design — an Invoice (auto on order completion + manual resend, with a branded admin-editable PDF attachment) and a delayed Feedback / review request (Action Scheduler). Both appear in WooCommerce → Settings → Emails; the PDF is configured under WooCommerce → Invoice PDF.
 * Version:     1.1.0
 * Author:      BabyPasa
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * What this plugin adds:
 *
 *   Invoice (id: bp_invoice)
 *     - Auto-sends to the customer when an order transitions to "completed"
 *       (self-registered on woocommerce_order_status_completed_notification,
 *       the same way core WooCommerce emails hook themselves).
 *     - Manual "Send Baby Pasa invoice to customer" entry in the order-edit
 *       Order actions dropdown.
 *
 *   Feedback / review request (id: bp_feedback)
 *     - Scheduled N days (default 3, bp_feedback_delay_days filter) after
 *       completion via WooCommerce's bundled Action Scheduler. Re-checks order
 *       status at send time and never sends for cancelled/refunded orders.
 *     - Manual "Send feedback request to customer" Order action.
 *
 * Email bodies live in the child theme
 * (woocommerce/emails/bp-invoice.php and customer-feedback-request.php) so they
 * render through the shared email-header.php / email-footer.php design, exactly
 * like the returns (E16–E20) and Upaya status emails.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

define( 'BP_OE_VERSION', '1.1.0' );
define( 'BP_OE_FILE', __FILE__ );
define( 'BP_OE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BP_OE_URL', plugin_dir_url( __FILE__ ) );

require_once BP_OE_DIR . 'includes/class-bp-oe-core.php';

// Boot after WooCommerce is ready (mirrors babypasa-returns' plugins_loaded:25).
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		BP_OE_Core::instance();
	},
	25
);

// Activation: pre-create the protected invoice-PDF storage directory.
register_activation_hook(
	__FILE__,
	static function () {
		require_once BP_OE_DIR . 'includes/pdf/class-bp-invoice-pdf-storage.php';
		BP_Invoice_PDF_Storage::bootstrap();
	}
);

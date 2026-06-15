<?php
/**
 * Upaya Cargo delivery status update — plain-text email template.
 *
 * Plain-text counterpart of the E11 (out for delivery) / E12 (delivered)
 * client designs.
 *
 * Variables available:
 *   WC_Order $order           Order object.
 *   string   $email_heading   Email heading.
 *   string   $upaya_status    Upaya status slug (e.g. 'out-for-delivery', 'delivered').
 *   string   $tracking_code   Upaya tracking code (may be empty / multi-line).
 *   string   $readable_status Human-readable status message.
 *   bool     $sent_to_admin   Whether the email is sent to the admin.
 *   bool     $plain_text      Whether the email is plain text (true here).
 *   WC_Email $email           Email object.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

$bp_is_delivered = ( 'delivered' === $upaya_status );
$bp_is_ofd       = in_array( $upaya_status, array( 'dispatched-with-rider', 'out-for-delivery' ), true );

// Plugin's existing filter convention — no hardcoded domain.
$bp_track_url = apply_filters( 'bp_upaya_tracking_url', wc_get_account_endpoint_url( 'track-orders' ), $tracking_code, $order );

echo '= ' . esc_html( $email_heading ) . " =\n\n"; // phpcs:ignore WordPress.XSS.EscapeOutput

printf(
	/* translators: %s: customer first name */
	esc_html__( 'Hi %s,', 'upaya-cargo-woocommerce' ),
	esc_html( $order->get_billing_first_name() )
);
echo "\n\n";

printf(
	/* translators: %s: order number */
	esc_html__( 'Order: #%s', 'upaya-cargo-woocommerce' ),
	esc_html( $order->get_order_number() )
);
echo "\n";

if ( $bp_is_delivered ) {
	esc_html_e( 'Your order has been delivered.', 'upaya-cargo-woocommerce' );
} elseif ( $bp_is_ofd ) {
	esc_html_e( 'Your order is out for delivery.', 'upaya-cargo-woocommerce' );
} else {
	echo esc_html( $readable_status );
}
echo "\n";

if ( $tracking_code ) {
	printf(
		/* translators: %s: tracking code */
		esc_html__( 'Tracking code: %s', 'upaya-cargo-woocommerce' ),
		esc_html( $tracking_code )
	);
	echo "\n";
}

echo "\n";

if ( $bp_is_delivered ) {
	esc_html_e( 'What was delivered:', 'upaya-cargo-woocommerce' );
} else {
	esc_html_e( "What's being delivered today:", 'upaya-cargo-woocommerce' );
}
echo "\n";

foreach ( $order->get_items() as $item_id => $item ) {
	if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
		continue;
	}
	echo '- ' . esc_html( wp_strip_all_tags( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) ) ) . ' x ' . esc_html( $item->get_quantity() ) . "\n";
}

echo "\n";

if ( $bp_is_delivered ) {
	printf(
		/* translators: %s: order URL */
		esc_html__( 'View your order: %s', 'upaya-cargo-woocommerce' ),
		esc_url( $order->get_view_order_url() )
	);
} else {
	printf(
		/* translators: %s: tracking URL */
		esc_html__( 'Track your order: %s', 'upaya-cargo-woocommerce' ),
		esc_url( $bp_track_url )
	);
}
echo "\n\n";

esc_html_e( 'Thank you for shopping with us!', 'upaya-cargo-woocommerce' );
echo "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

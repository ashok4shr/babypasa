<?php
/**
 * Upaya Cargo delivery status update — plain-text email template.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

echo "= " . esc_html( $email_heading ) . " =\n\n"; // phpcs:ignore WordPress.XSS.EscapeOutput

printf(
	/* translators: %s: customer first name */
	esc_html__( 'Hi %s,', 'upaya-cargo-woocommerce' ),
	esc_html( $order->get_billing_first_name() )
);
echo "\n\n";

esc_html_e( "Here's an update on your order:", 'upaya-cargo-woocommerce' );
echo "\n\n";

printf(
	/* translators: %s: order number */
	esc_html__( 'Order: #%s', 'upaya-cargo-woocommerce' ),
	esc_html( $order->get_order_number() )
);
echo "\n";

printf(
	/* translators: %s: status message */
	esc_html__( 'Status: %s', 'upaya-cargo-woocommerce' ),
	esc_html( $readable_status )
);
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

printf(
	/* translators: %s: order URL */
	esc_html__( 'View your order: %s', 'upaya-cargo-woocommerce' ),
	esc_url( $order->get_view_order_url() )
);
echo "\n\n";

esc_html_e( 'Thank you for shopping with us!', 'upaya-cargo-woocommerce' );
echo "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

do_action( 'woocommerce_email_order_details',   $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta',      $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

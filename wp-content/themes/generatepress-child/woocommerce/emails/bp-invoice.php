<?php
/**
 * Invoice email body — BabyPasa Order Emails plugin (id: bp_invoice).
 *
 * Renders an invoice/receipt through the shared client design: the standard
 * WooCommerce order-detail hooks output the order number/date, line items with
 * quantities and totals, shipping, payment method and grand total, plus the
 * billing/customer details — all wrapped by email-header.php / email-footer.php.
 *
 * Expected variables (supplied by BP_OE_Email_Base::get_content_html()):
 *   WC_Order  $order              The completed order.
 *   string    $email_heading      Hero heading.
 *   string    $additional_content Admin-configured trailing content (may be empty).
 *   bool      $sent_to_admin      Always false for this customer email.
 *   bool      $plain_text         Always false (HTML-only design).
 *   WC_Email  $email              The email object.
 *
 * @package GeneratePress_Child\WooCommerce\Emails
 */

defined( 'ABSPATH' ) || exit;

$email = $email ?? null;

/*
 * @hooked WC_Emails::email_header() Output the email header (logo, pink rule, hero).
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p style="margin:0 0 16px;">
	<?php
	if ( '' !== $order->get_billing_first_name() ) {
		/* translators: %s: Customer first name */
		printf( esc_html__( 'Hi %s,', 'generatepress-child' ), esc_html( $order->get_billing_first_name() ) );
	} else {
		esc_html_e( 'Hi,', 'generatepress-child' );
	}
	?>
</p>

<p style="margin:0 0 16px;">
	<?php
	/* translators: %s: Order date */
	printf( esc_html__( 'Please find your invoice for the order placed on %s below. Thank you for shopping with BabyPasa.Com!', 'generatepress-child' ), esc_html( wc_format_datetime( $order->get_date_created() ) ) );
	?>
</p>

<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table (items, qty,
 *         subtotal, shipping/delivery charge, payment method, grand total).
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 */
do_action( 'woocommerce_email_order_details', $order, false, false, $email );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, false, false, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer (billing/shipping) details.
 * @hooked WC_Emails::email_address() Shows email address.
 */
do_action( 'woocommerce_email_customer_details', $order, false, false, $email );

/**
 * Admin-configured additional content (Settings → Emails → Baby Pasa invoice).
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );

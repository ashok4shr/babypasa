<?php
/**
 * Checkout Form — two-column layout override.
 * Left: billing details + order notes.
 * Right: order review (sticky).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<div class="upaya-checkout-wrapper">

		<!-- ── Left column: billing details + order notes ─────────────────── -->
		<div class="upaya-checkout-left">

			<?php if ( $checkout->get_checkout_fields() ) : ?>

				<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

				<div class="col2-set" id="customer_details">
					<div class="col-1">
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>

					<div class="col-2">
						<?php do_action( 'woocommerce_checkout_shipping' ); ?>
					</div>
				</div>

				<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<?php endif; ?>

		</div><!-- .upaya-checkout-left -->

		<!-- ── Right column: order review ────────────────────────────────── -->
		<div class="upaya-checkout-right">

			<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

			<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>

			<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

			<?php
			// #payment (and #place_order) are rendered inside #order_review by the
			// woocommerce_checkout_order_review action, and WooCommerce's checkout.js
			// looks them up via $('#order_review').find(...). So this wrapper must
			// stay intact — the mobile "order summary" accordion in checkout.js only
			// collapses the line-items table, never the payment/place-order box.
			?>
			<div id="order_review" class="woocommerce-checkout-review-order">
				<?php do_action( 'woocommerce_checkout_order_review' ); ?>
			</div>

			<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

		</div><!-- .upaya-checkout-right -->

	</div><!-- .upaya-checkout-wrapper -->

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

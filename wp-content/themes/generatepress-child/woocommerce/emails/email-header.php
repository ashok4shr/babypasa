<?php
/**
 * Email Header — BabyPasa client design (2026 migration).
 *
 * Implements the client header: centered logo, 4px pink rule, then the
 * pink hero band (optional icon circle + heading + subline). Opens the
 * white body cell that email-footer.php closes.
 *
 * Receives: $email_heading (string), $email (WC_Email|null).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WooCommerce's WC_Emails::email_header() passes only $email_heading to this
// template — not the $email object. bp_capture_email_header_object() (in the
// theme functions.php) stashes the object from the do_action() second arg so
// the hero switch below can resolve it.
if ( ! isset( $email ) || ! is_object( $email ) ) {
	$email = isset( $GLOBALS['bp_email_header_object'] ) ? $GLOBALS['bp_email_header_object'] : null;
}

$store_name    = get_bloginfo( 'name', 'display' );
$bp_logo_url   = get_stylesheet_directory_uri() . '/assets/images/email-logo.jpg';
$bp_email_id   = ( is_object( $email ) && ! empty( $email->id ) ) ? $email->id : '';
$bp_order      = ( is_object( $email ) && ! empty( $email->object ) && is_a( $email->object, 'WC_Order' ) ) ? $email->object : null;
$bp_first_name = $bp_order ? $bp_order->get_billing_first_name() : '';
$bp_order_num  = $bp_order ? $bp_order->get_order_number() : '';

/*
 * Client hero icons — WHITE monochrome glyphs on the pink circle.
 *
 * Rendered as Unicode TEXT-presentation symbols (not colour emoji) so they
 * honour `color:#ffffff` and show white across Gmail / Outlook / Apple Mail /
 * Yahoo with no image assets — matching the white line-icons in the client
 * "Email Template and Logo" designs. (Inline <svg> was stripped by Gmail/Outlook;
 * colour emoji ignore CSS colour — hence text glyphs.)
 *
 * A few pictographic concepts have no dedicated white text glyph, so the closest
 * symbol is used (flagged "approx" below): truck/bike → movement arrow ➤,
 * package → box ▣, padlock → reset arrow ↻. VS15 (&#65038;) forces text (not
 * emoji) presentation on glyphs that have an emoji variant.
 * Source: client HTML templates E01–E21.
 */
$bp_icon = static function ( $glyph, $size = 28 ) {
	return '<span style="display:inline-block;font-size:' . (int) $size . 'px;line-height:52px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;">' . $glyph . '</span>';
};

// Pictographic icons (truck/bike/box/house/lock/card) have no clean white text
// glyph, so they use white PNG line-icons generated from the client SVGs
// (assets/images/email-icons/, ~30px centred on the 52px pink circle), referenced
// by absolute URL. The rest stay lightweight Unicode text glyphs (white via color).
$bp_icon_img = static function ( $file ) {
	$url = get_stylesheet_directory_uri() . '/assets/images/email-icons/' . $file;
	return '<img src="' . esc_url( $url ) . '" width="30" height="30" alt="" style="display:inline-block;vertical-align:middle;border:0;" />';
};

$bp_icon_check       = $bp_icon_img( 'check.png' );       // ✔ generic positive (order processing).
$bp_icon_cross       = $bp_icon_img( 'cross.png' );       // ✖ cancelled / failed order.
$bp_icon_lock        = $bp_icon_img( 'lock.png' );        // 🔒 password reset (E02).
$bp_icon_bike        = $bp_icon_img( 'bike.png' );        // 🚴 out for delivery (E11).
$bp_icon_home        = $bp_icon_img( 'home.png' );        // 🏠 delivered (E12).
$bp_icon_cardfail    = $bp_icon_img( 'card.png' );        // 💳 payment failed (E04).
$bp_icon_checkcircle = $bp_icon_img( 'checkcircle.png' ); // ✔ refund processed / return approved (E21/E19).
$bp_icon_truck       = $bp_icon_img( 'truck.png' );       // 🚚 in transit / failed delivery (E07/E16).
$bp_icon_return      = $bp_icon_img( 'return.png' );      // ↩ RTO initiated (E17).
$bp_icon_inbox       = $bp_icon_img( 'inbox.png' );       // ✉ return request received (E18).
$bp_icon_warehouse   = $bp_icon_img( 'package.png' );     // 📦 parcel received back at warehouse (E20).

// Defaults: check icon, no subline (covers stock WooCommerce emails with no bespoke client design).
$bp_hero_icon = $bp_icon_check;
$bp_hero_sub  = '';
$bp_hero_pad  = '32px 36px';

switch ( $bp_email_id ) {
	case 'customer_new_account':
		// CLIENT TEMPLATE: E01 — no icon circle, larger hero padding.
		$bp_hero_icon = '';
		$bp_hero_pad  = '34px 36px';
		$bp_hero_sub  = 'You&rsquo;re now part of Nepal&rsquo;s most trusted baby store.<br />Everything your little one needs &mdash; delivered to your door.';
		break;

	case 'customer_reset_password':
		// CLIENT TEMPLATE: E02.
		$bp_hero_icon  = $bp_icon_lock;
		$bp_reset_user = ( is_object( $email ) && ! empty( $email->object ) && is_a( $email->object, 'WP_User' ) ) ? $email->object : null;
		// CLIENT PLACEHOLDER: {{first_name}} → reset user's first name (falls back to display name).
		$bp_reset_name = $bp_reset_user ? ( $bp_reset_user->first_name ? $bp_reset_user->first_name : $bp_reset_user->display_name ) : '';
		$bp_hero_sub   = 'We received a request to reset the password<br />for your BabyPasa.Com account' . ( $bp_reset_name ? ', <strong>' . esc_html( $bp_reset_name ) . '</strong>' : '' ) . '.';
		break;

	case 'customer_processing_order':
		// CLIENT TEMPLATE: E03.
		// CLIENT PLACEHOLDER: {{first_name}} → $order->get_billing_first_name().
		$bp_hero_sub = 'Thank you for shopping with BabyPasa.Com' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '.<br />We&rsquo;re getting your order ready!';
		break;

	case 'customer_cancelled_order':
		// CLIENT TEMPLATE: E15 — hero padding 30px, X icon.
		$bp_hero_icon = $bp_icon_cross;
		$bp_hero_pad  = '30px 36px';
		// CLIENT PLACEHOLDER: {{order_number}} / {{first_name}} → order getters.
		$bp_hero_sub  = 'Order <strong>#' . esc_html( $bp_order_num ) . '</strong> has been cancelled' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '.<br />We&rsquo;re sorry it didn&rsquo;t work out this time.';
		break;

	case 'customer_failed_order':
		// CLIENT TEMPLATE: E04 — payment-card icon, 30px hero padding.
		$bp_hero_icon = $bp_icon_cardfail;
		$bp_hero_pad  = '30px 36px';
		// CLIENT PLACEHOLDER: {{first_name}} → $order->get_billing_first_name().
		$bp_hero_sub  = 'Don&rsquo;t worry' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . ' &mdash; this happens sometimes.<br />Your cart is saved and ready for you.';
		break;

	case 'customer_refunded_order':
	case 'customer_partially_refunded_order':
		// CLIENT TEMPLATE: E21 — check-in-circle icon.
		$bp_hero_icon = $bp_icon_checkcircle;
		// CLIENT PLACEHOLDER: {{order_number}} / {{first_name}} → order getters.
		$bp_hero_sub  = 'We&rsquo;ve processed your refund for order <strong>#' . esc_html( $bp_order_num ) . '</strong>' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '.<br />Here are the details.';
		break;

	case 'bp_failed_delivery':
		// CLIENT TEMPLATE: E16 — failed delivery attempt (positive retry).
		$bp_hero_icon = $bp_icon_truck;
		$bp_hero_pad  = '30px 36px';
		// CLIENT PLACEHOLDER: {{order_number}} / {{first_name}} → order getters.
		$bp_hero_sub  = 'Our delivery agent visited your address for order <strong>#' . esc_html( $bp_order_num ) . '</strong>' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . ', but couldn&rsquo;t complete the delivery.';
		break;

	case 'bp_rto_initiated':
		// CLIENT TEMPLATE: E17 — RTO initiated.
		$bp_hero_icon = $bp_icon_return;
		$bp_hero_sub  = 'We weren&rsquo;t able to complete delivery for order <strong>#' . esc_html( $bp_order_num ) . '</strong>' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '.<br />Here&rsquo;s what you can do next.';
		break;

	case 'bp_return_requested':
		// CLIENT TEMPLATE: E18 — customer return request received.
		$bp_hero_icon = $bp_icon_inbox;
		$bp_hero_sub  = 'Thanks for letting us know' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '. We&rsquo;re reviewing your request for order <strong>#' . esc_html( $bp_order_num ) . '</strong> and will be in touch shortly.';
		break;

	case 'bp_return_approved':
		// CLIENT TEMPLATE: E19 — return approved.
		$bp_hero_icon = $bp_icon_checkcircle;
		$bp_hero_sub  = 'Great news' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . ' &mdash; we&rsquo;re ready to process your return for order <strong>#' . esc_html( $bp_order_num ) . '</strong>.<br />Here&rsquo;s what to do next.';
		break;

	case 'bp_return_rejected':
		// CLIENT TEMPLATE: E22 — return request declined.
		$bp_hero_icon = $bp_icon_cross;
		$bp_hero_pad  = '30px 36px';
		$bp_hero_sub  = 'We&rsquo;ve reviewed your return request for order <strong>#' . esc_html( $bp_order_num ) . '</strong>' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . ', but we&rsquo;re unable to approve it this time.';
		break;

	case 'bp_rto_complete':
		// CLIENT TEMPLATE: E20 — returned parcel received at warehouse.
		$bp_hero_icon = $bp_icon_warehouse;
		$bp_hero_sub  = 'Your returned items for order <strong>#' . esc_html( $bp_order_num ) . '</strong> have arrived at our warehouse' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '. We&rsquo;re on it.';
		break;

	case 'bp_price_drop':
		// Price-drop alert (price-drop-notification plugin). Reuses the white
		// gift line-icon as a "deal/offer" glyph (no dedicated price-tag asset).
		$bp_hero_icon = $bp_icon_img( 'feat-gift.png' );
		$bp_hero_sub  = 'A product you&rsquo;re watching just got cheaper.<br />Grab it before it&rsquo;s gone!';
		break;

	case 'cancelled_order':
	case 'failed_order':
		$bp_hero_icon = $bp_icon_cross;
		break;

	case 'upaya_delivery_status':
		// CLIENT TEMPLATES: E11 (out for delivery) / E12 (delivered).
		// The plugin email class exposes the current Upaya status slug (public $upaya_status).
		$bp_upaya_status = ( is_object( $email ) && ! empty( $email->upaya_status ) ) ? $email->upaya_status : '';
		if ( 'delivered' === $bp_upaya_status ) {
			$bp_hero_icon = $bp_icon_home;
			$bp_hero_sub  = 'Order <strong>#' . esc_html( $bp_order_num ) . '</strong> is at your door' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '.<br />We hope your little one loves every bit of it!';
		} elseif ( 'picked-up-by-rider' === $bp_upaya_status ) {
			// CLIENT TEMPLATE E06: picked up — white package PNG.
			$bp_hero_icon = $bp_icon_img( 'package.png' );
			$bp_hero_sub  = 'Great news' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . ' &mdash; order <strong>#' . esc_html( $bp_order_num ) . '</strong> has been collected by our delivery partner.';
		} elseif ( in_array( $bp_upaya_status, array( 'in-transit-to-hub', 'in-transit' ), true ) ) {
			// CLIENT TEMPLATE E07: in transit — truck icon.
			$bp_hero_icon = $bp_icon_truck;
			$bp_hero_sub  = 'Order <strong>#' . esc_html( $bp_order_num ) . '</strong> is moving through our delivery network' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '.';
		} else {
			$bp_hero_icon = $bp_icon_bike;
			$bp_hero_sub  = 'Order <strong>#' . esc_html( $bp_order_num ) . '</strong> is heading your way today' . ( $bp_first_name ? ', <strong>' . esc_html( $bp_first_name ) . '</strong>' : '' ) . '.<br />Get ready to receive your little one&rsquo;s essentials!';
		}
		break;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="x-apple-disable-message-reformatting" />
	<title><?php echo esc_html( $store_name ); ?></title>
	<!--[if mso]>
	<noscript><xml><o:OfficeDocumentSettings>
		<o:PixelsPerInch>96</o:PixelsPerInch>
	</o:OfficeDocumentSettings></xml></noscript>
	<![endif]-->
	<?php
	/*
	 * Responsive layer, hard-coded in <head>.
	 *
	 * WooCommerce's CSS inliner DOES preserve @media blocks, but it re-injects
	 * them only at send time — and a mail transport that re-parses the body
	 * (e.g. the ZeptoMail / SMTP mailer plugins) can drop that injected <style>,
	 * which is why mobile breaks on real clients while the WC preview looks fine.
	 * Shipping the @media rules verbatim here guarantees the mobile layout
	 * survives regardless of the inliner/transport. @media rules cannot be
	 * inlined onto elements, so the inliner leaves this block untouched.
	 *
	 * KEEP IN SYNC with the @media blocks in emails/email-styles.php (single
	 * source for the inlined base rules; this is the transport-proof copy).
	 * The fluid/hybrid wrapper below is the no-media-query fallback for clients
	 * that ignore @media entirely (Outlook desktop).
	 */
	?>
	<style type="text/css">
		/* ── TABLET (481px – 768px) ── */
		@media only screen and (min-width: 481px) and (max-width: 768px) {
			.email-wrap            { width: 100% !important; }
			.logo-pad              { padding: 22px 24px 14px !important; }
			.hero-pad              { padding: 28px 24px !important; }
			.section-pad           { padding: 20px 24px 0 !important; }
			.body-pad              { padding: 20px 24px !important; }
			.bp-body               { padding: 20px 24px !important; }
			.footer-pad            { padding: 18px 24px 22px !important; }
			.feat-title            { font-size: 9px !important; }
			.feat-sub              { font-size: 9px !important; }
			.trk-label,
			.trk-label-dim         { font-size: 9px !important; }
		}
		/* ── MOBILE (≤ 480px) ── */
		@media only screen and (max-width: 480px) {
			.email-wrap            { width: 100% !important; max-width: 100% !important; }
			.logo-pad              { padding: 20px 16px 14px !important; }
			.hero-pad              { padding: 26px 16px !important; }
			.section-pad           { padding: 16px 16px 0 !important; }
			.body-pad              { padding: 16px !important; }
			.bp-body               { padding: 16px !important; }
			.footer-pad            { padding: 18px 16px 22px !important; }
			.hero-h1               { font-size: 19px !important; }
			.hero-sub              { font-size: 14px !important; }
			.body-copy             { font-size: 14px !important; }
			.tile-cell,
			.tile-cell-b           { display: block !important; width: 100% !important; max-width: none !important; }
			.tile-left,
			.tile-left-b           { padding-right: 0 !important; padding-bottom: 10px !important; }
			.tile-right,
			.tile-right-b          { padding-left: 0 !important; }
			.trk-label,
			.trk-label-dim         { font-size: 9px !important; }
			.trk-conn              { padding-top: 11px !important; }
			.stars                 { font-size: 22px !important; letter-spacing: 2px !important; }
			.feat-cell,
			.feat-cell-last        { display: block !important; width: 100% !important; max-width: none !important; border-right: none !important; border-bottom: 1px solid rgba(255,255,255,0.25) !important; }
			.feat-cell-last        { border-bottom: none !important; }
			.cta-btn,
			.cta-wrap              { width: 100% !important; text-align: center !important; }
			.cta-btn a,
			.cta-wrap a            { display: block !important; padding: 14px 20px !important; }
			.social-td             { display: block !important; width: 100% !important; padding: 0 0 8px !important; text-align: center !important; }
			.order-hdr-num         { display: block !important; }
			.order-hdr-date        { display: block !important; font-size: 11px !important; margin-top: 2px; }
			.item-row td           { padding: 10px 12px !important; }
			.price-col             { display: none !important; }
			.banner-icon-td        { display: block !important; text-align: center !important; padding-bottom: 10px !important; }
			.banner-text-td        { display: block !important; text-align: center !important; }
			.refund-icon-td        { display: none !important; }
			.product-icon-td       { display: none !important; }
			.thankyou-icon-td      { display: none !important; }
		}
	</style>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">

<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
	<tr>
		<td align="center" style="padding:28px 12px;">

			<!-- Outlook (desktop) ignores @media + max-width — this ghost table pins it to 600px so desktop is unchanged. -->
				<!--[if mso]><table border="0" cellpadding="0" cellspacing="0" width="600" align="center" role="presentation"><tr><td><![endif]-->
				<table class="email-wrap" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="width:100%;max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">

				<!-- 1. LOGO -->
				<tr>
					<td class="logo-pad" align="center" style="background:#ffffff;padding:24px 36px 18px;">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" style="display:inline-block;">
							<img src="<?php echo esc_url( $bp_logo_url ); ?>"
								alt="<?php echo esc_attr( $store_name ); ?> &mdash; Weaving Joyful Moments Together"
								width="130"
								style="display:inline-block;max-width:130px;height:auto;" />
						</a>
					</td>
				</tr>

				<!-- pink rule -->
				<tr>
					<td style="background:#ec4899;height:4px;font-size:0;line-height:0;">&nbsp;</td>
				</tr>

				<!-- 2. HERO -->
				<tr>
					<td class="hero-pad" align="center" style="background:#fce7f3;padding:<?php echo esc_attr( $bp_hero_pad ); ?>;">
						<?php if ( $bp_hero_icon ) : ?>
						<table border="0" cellpadding="0" cellspacing="0" role="presentation" width="52" style="width:52px;margin:0 auto 16px;">
							<tr>
								<!-- HTML width/height attrs keep the badge a true 52x52 square so border-radius renders a circle; Gmail's mobile app collapses CSS-only width on a <td>, turning the badge into a vertical capsule. -->
								<td width="52" height="52" align="center" valign="middle" style="width:52px;min-width:52px;height:52px;background:#ec4899;border-radius:26px;text-align:center;vertical-align:middle;">
									<?php echo $bp_hero_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static emoji span markup defined above. ?>
								</td>
							</tr>
						</table>
						<?php endif; ?>
						<h1 class="hero-h1" style="margin:0<?php echo $bp_hero_sub ? ' 0 8px' : ''; ?>;font-family:Arial,Helvetica,sans-serif;font-size:<?php echo 'customer_new_account' === $bp_email_id ? '24px' : '22px'; ?>;font-weight:700;color:#9d174d;line-height:1.3;">
							<?php echo esc_html( $email_heading ); ?>
						</h1>
						<?php if ( $bp_hero_sub ) : ?>
						<p class="hero-sub" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#be185d;line-height:1.7;">
							<?php echo wp_kses_post( $bp_hero_sub ); ?>
						</p>
						<?php endif; ?>
					</td>
				</tr>

				<?php
				// CLIENT TEMPLATE: E01 places the feature strip directly after the
				// hero; all other designs carry it before the footer band instead
				// (rendered by email-footer.php).
				if ( 'customer_new_account' === $bp_email_id ) {
					wc_get_template( 'emails/bp-feature-strip.php' );
				}
				?>

				<!-- 3. BODY (closed by email-footer.php) -->
				<tr>
					<td class="bp-body" valign="top" style="background:#ffffff;padding:24px 36px;">
						<div class="bp-body-inner" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.8;">
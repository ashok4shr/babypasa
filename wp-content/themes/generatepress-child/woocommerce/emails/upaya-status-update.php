<?php
/**
 * Upaya Cargo delivery status update — HTML email template (child theme override).
 *
 * Implements client designs:
 *   E11 (5. Out for Delivery / E11_out_for_delivery.html) — out-for-delivery state.
 *   E12 (6. Delivered / E12_delivered.html)               — delivered state.
 *
 * The shared header (`woocommerce_email_header`) renders the logo, pink rule and
 * pink hero (per-status icon + h1 + subline) and OPENS the body cell
 * (`<tr><td class="bp-body"><div class="bp-body-inner">`). All content below goes
 * inside that div. The shared footer (`woocommerce_email_footer`) closes it and
 * renders the support line, feature strip, pink footer band and copyright.
 *
 * Variables available:
 *   WC_Order $order           Order object.
 *   string   $email_heading   Email heading.
 *   string   $upaya_status    Upaya status slug (e.g. 'out-for-delivery', 'delivered').
 *   string   $tracking_code   Upaya tracking code (may be empty / multi-line).
 *   string   $readable_status Human-readable status message.
 *   bool     $sent_to_admin   Whether the email is sent to the admin.
 *   bool     $plain_text      Whether the email is plain text (false here).
 *   WC_Email $email           Email object.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

$bp_is_delivered = ( 'delivered' === $upaya_status );
$bp_is_ofd       = in_array( $upaya_status, array( 'dispatched-with-rider', 'out-for-delivery' ), true );
$bp_is_transit   = in_array( $upaya_status, array( 'in-transit-to-hub', 'in-transit' ), true ); // CLIENT TEMPLATE E07.
$bp_is_pickup    = ( 'picked-up-by-rider' === $upaya_status ); // CLIENT TEMPLATE E06.

// 5-step journey: 1 Order placed, 2 Picked up, 3 In transit, 4 Out for delivery, 5 Delivered.
$bp_active_step = $bp_is_delivered ? 5 : ( $bp_is_transit ? 3 : ( $bp_is_pickup ? 2 : 4 ) );

// Plugin's existing filter convention — no hardcoded domain.
$bp_track_url = apply_filters( 'bp_upaya_tracking_url', wc_get_account_endpoint_url( 'track-orders' ), $tracking_code, $order );

do_action( 'woocommerce_email_header', $email_heading, $email );

if ( ! $bp_is_delivered && ! $bp_is_ofd && ! $bp_is_transit && ! $bp_is_pickup ) :
	/*
	 * Defensive fallback — the webhook only fires this email for the designed
	 * states (picked up / in transit / out for delivery / delivered), but the
	 * template must not break for any other status.
	 */
	?>
	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"
	       style="background:#fce7f3;border-radius:8px;margin:0 0 24px;">
		<tr>
			<td style="padding:16px;">
				<p style="margin:0;
				          font-family:Arial,Helvetica,sans-serif;
				          font-size:15px;font-weight:700;color:#9d174d;">
					<?php echo esc_html( $readable_status ); // CLIENT PLACEHOLDER: status message → $readable_status. ?>
				</p>
				<?php if ( $tracking_code ) : ?>
				<p style="margin:8px 0 0;
				          font-family:'Courier New',Courier,monospace;
				          font-size:16px;font-weight:700;
				          color:#9d174d;letter-spacing:1px;">
					<?php echo nl2br( esc_html( $tracking_code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via esc_html() before nl2br(). CLIENT PLACEHOLDER: {{tracking_code}} → $tracking_code. ?>
				</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
		<tr>
			<td align="center">
				<table class="cta-btn cta-wrap" border="0" cellpadding="0"
				       cellspacing="0" role="presentation">
					<tr>
						<td style="background:#ec4899;border-radius:6px;
						           text-align:center;">
							<a href="<?php echo esc_url( $bp_track_url ); ?>"
							   target="_blank"
							   style="display:inline-block;
							          padding:14px 48px;
							          font-family:Arial,Helvetica,sans-serif;
							          font-size:15px;font-weight:700;
							          color:#ffffff !important;
							          text-decoration:none;
							          letter-spacing:0.3px;">
								Track Your Order
							</a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	<?php
	do_action( 'woocommerce_email_footer', $email );
	return;
endif;
?>

<?php
/*
 * ══════════════════════════════════
 *  JOURNEY TRACKER (client E11/E12)
 *  9-cell table: step/conn/step/conn/step/conn/step/conn/step
 *  E11 active step 4 ("Now" badge), E12 active step 5 ("Done" badge).
 * ══════════════════════════════════
 */
$bp_steps = array(
	1 => 'Order<br />placed',
	2 => 'Picked<br />up',
	3 => 'In<br />transit',
	4 => 'Out for<br />delivery',
	5 => 'Delivered',
);
?>
<p style="margin:0 0 16px;
          font-family:Arial,Helvetica,sans-serif;
          font-size:11px;font-weight:700;color:#9d174d;
          text-transform:uppercase;letter-spacing:0.5px;">
	Delivery journey
</p>

<table border="0" cellpadding="0" cellspacing="0"
       width="100%" role="presentation" style="margin:0 0 20px;table-layout:fixed;">
	<tr>
		<?php
		foreach ( $bp_steps as $bp_step => $bp_step_label ) :
			$bp_is_active_step = ( $bp_step === $bp_active_step );
			$bp_is_completed   = ( $bp_step <= $bp_active_step ); // Active step is also filled with a check.
			?>

		<!-- ─ Step <?php echo (int) $bp_step; ?> (<?php echo $bp_is_active_step ? 'ACTIVE' : ( $bp_is_completed ? 'completed' : 'upcoming' ); ?>) ─ -->
		<td class="trk-step"
		    style="width:14%;text-align:center;
		           vertical-align:top;padding:0 2px;">
			<table border="0" cellpadding="0" cellspacing="0"
			       role="presentation" style="margin:0 auto 8px;">
				<tr>
					<?php if ( $bp_is_completed ) : ?>
					<td style="width:30px;height:30px;
					           background:#ec4899;<?php echo $bp_is_active_step ? '
					           border:3px solid #9d174d;' : ''; ?>
					           border-radius:15px;
					           text-align:center;vertical-align:middle;">
						<!-- white check PNG (glyphs render as dark emoji in Gmail) -->
						<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/check.png' ); ?>"
						     width="16" height="16" alt=""
						     style="display:inline-block;vertical-align:middle;border:0;" />
					</td>
					<?php else : ?>
					<td style="width:30px;height:30px;
					           background:#ffffff;
					           border:2px solid #fbcfe8;
					           border-radius:15px;
					           font-size:0;line-height:0;">&nbsp;</td>
					<?php endif; ?>
				</tr>
			</table>
			<?php if ( $bp_is_completed ) : ?>
			<p class="trk-label"
			   style="margin:0<?php echo $bp_is_active_step ? ' 0 4px' : ''; ?>;font-size:10px;font-weight:700;
			          color:#9d174d;line-height:1.3;
			          font-family:Arial,Helvetica,sans-serif;">
				<?php echo wp_kses( $bp_step_label, array( 'br' => array() ) ); ?>
			</p>
			<?php else : ?>
			<p class="trk-label-dim"
			   style="margin:0;font-size:10px;color:#be185d;
			          line-height:1.3;
			          font-family:Arial,Helvetica,sans-serif;">
				<?php echo wp_kses( $bp_step_label, array( 'br' => array() ) ); ?>
			</p>
			<?php endif; ?>
			<?php if ( $bp_is_active_step ) : ?>
			<table border="0" cellpadding="0" cellspacing="0"
			       role="presentation" style="margin:0 auto;">
				<tr>
					<td style="background:#ec4899;border-radius:4px;
					           padding:2px 6px;text-align:center;">
						<span style="font-family:Arial,Helvetica,sans-serif;
						             font-size:9px;font-weight:700;
						             color:#ffffff;">
							<?php echo $bp_is_delivered ? 'Done' : 'Now'; // E12 "Done" / E11 "Now" badge. ?>
						</span>
					</td>
				</tr>
			</table>
			<?php endif; ?>
		</td>

			<?php if ( $bp_step < 5 ) : ?>
		<!-- ─ Connector <?php echo (int) $bp_step; ?>→<?php echo (int) ( $bp_step + 1 ); ?> ─ -->
		<td class="trk-conn"
		    style="width:7.5%;vertical-align:top;padding-top:14px;">
			<table border="0" cellpadding="0" cellspacing="0"
			       width="100%" role="presentation">
				<tr>
					<td style="height:2px;background:<?php echo ( $bp_step < $bp_active_step ) ? '#ec4899' : '#fbcfe8'; ?>;
					           font-size:0;line-height:0;">&nbsp;</td>
				</tr>
			</table>
		</td>
			<?php endif; ?>

		<?php endforeach; ?>
	</tr>
</table>

<?php if ( ! $bp_is_delivered && $tracking_code ) : ?>
<!-- ══════════════════════════════════
     TRACKING CODE (client E11 — out-for-delivery only)
     ══════════════════════════════════ -->
<table border="0" cellpadding="0" cellspacing="0"
       width="100%" role="presentation"
       style="background:#fce7f3;border-radius:8px;margin:0 0 14px;">
	<tr>
		<td style="padding:14px 16px;">
			<p style="margin:0 0 4px;
			          font-family:Arial,Helvetica,sans-serif;
			          font-size:10px;font-weight:700;color:#9d174d;
			          text-transform:uppercase;letter-spacing:0.5px;">
				Tracking code
			</p>
			<p style="margin:0;
			          font-family:'Courier New',Courier,monospace;
			          font-size:20px;font-weight:700;
			          color:#9d174d;letter-spacing:2px;">
				<?php echo nl2br( esc_html( $tracking_code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via esc_html() before nl2br(). CLIENT PLACEHOLDER: {{tracking_code}} → $tracking_code. ?>
			</p>
		</td>
		<td style="padding:14px 16px;text-align:right;
		           vertical-align:middle;width:60px;">
			<table border="0" cellpadding="0" cellspacing="0"
			       role="presentation" style="margin-left:auto;">
				<tr>
					<td style="background:#ec4899;border-radius:6px;
					           padding:10px;text-align:center;">
						<a href="<?php echo esc_url( $bp_track_url ); // CLIENT PLACEHOLDER: track-orders URL → $bp_track_url. ?>"
						   target="_blank"
						   style="text-decoration:none;">
							<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/search.png' ); ?>"
							     width="20" height="20" alt="Track"
							     style="display:block;border:0;" />
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php endif; ?>

<?php if ( $bp_is_ofd ) : ?>
<!-- ══════════════════════════════════
     PHONE BANNER (client E11 — out-for-delivery only; E07 in-transit omits it)
     ══════════════════════════════════ -->
<table border="0" cellpadding="0" cellspacing="0"
       width="100%" role="presentation"
       style="background:#fce7f3;border-radius:8px;
              border:1px solid #fbcfe8;margin:0 0 14px;">
	<tr>
		<td class="banner-icon-td"
		    style="padding:14px 0 14px 16px;
		           vertical-align:middle;width:52px;">
			<table border="0" cellpadding="0" cellspacing="0"
			       role="presentation">
				<tr>
					<td style="width:36px;height:36px;
					           background:#ec4899;border-radius:18px;
					           text-align:center;vertical-align:middle;">
						<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/phone.png' ); ?>" width="20" height="20" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
					</td>
				</tr>
			</table>
		</td>
		<td class="banner-text-td"
		    style="padding:14px 16px 14px 12px;
		           vertical-align:middle;">
			<p style="margin:0 0 4px;
			          font-family:Arial,Helvetica,sans-serif;
			          font-size:13px;font-weight:700;color:#9d174d;">
				Keep your phone handy!
			</p>
			<p style="margin:0;
			          font-family:Arial,Helvetica,sans-serif;
			          font-size:12px;color:#be185d;line-height:1.6;">
				Our delivery partner will call or SMS you just
				before they arrive so you don't miss a thing.
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- ══════════════════════════════════
     PAYMENT + DELIVERY PARTNER TILES (client E11/E12)
     ══════════════════════════════════ -->
<table border="0" cellpadding="0" cellspacing="0"
       width="100%" role="presentation" style="margin:0 0 14px;">
	<tr>

		<!-- Payment -->
		<td class="tile-cell tile-left"
		    style="width:50%;vertical-align:top;padding-right:6px;">
			<table border="0" cellpadding="0" cellspacing="0"
			       width="100%" role="presentation">
				<tr>
					<td style="background:#f9fafb;border-radius:8px;
					           padding:14px;border:1px solid #f3f4f6;">
						<table border="0" cellpadding="0" cellspacing="0"
						       role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;
								           padding-right:6px;">
									<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/card-pink.png' ); ?>" width="15" height="15" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
								</td>
								<td style="font-family:Arial,Helvetica,sans-serif;
								           font-size:10px;font-weight:700;
								           color:#9d174d;text-transform:uppercase;
								           letter-spacing:0.5px;">
									Payment method
								</td>
							</tr>
						</table>
						<p style="margin:0 0 10px;
						          font-family:Arial,Helvetica,sans-serif;
						          font-size:13px;font-weight:700;
						          color:#374151;">
							<?php echo esc_html( $order->get_payment_method_title() ); // CLIENT PLACEHOLDER: {{payment_method}} → $order->get_payment_method_title(). ?>
						</p>
						<p style="margin:0 0 8px;
						          font-family:Arial,Helvetica,sans-serif;
						          font-size:12px;color:#6b7280;">
							Payment status:
						</p>
						<?php
						// Use date_paid (money actually received) — NOT is_paid(), which only
						// reflects order *status*. COD orders sit in "processing" without payment,
						// so is_paid() wrongly reads as Paid; date_paid is set only on
						// payment_complete() (e.g. ConnectIPS) or once delivered/completed.
						?>
						<?php if ( $order->get_date_paid() ) : ?>
						<span style="background:#dcfce7;color:#15803d;
						             padding:4px 12px;border-radius:4px;
						             font-family:Arial,Helvetica,sans-serif;
						             font-size:11px;font-weight:700;">
							Paid
						</span>
						<?php else : ?>
						<span style="background:#fef9c3;color:#854d0e;
						             padding:4px 12px;border-radius:4px;
						             font-family:Arial,Helvetica,sans-serif;
						             font-size:11px;font-weight:700;">
							Unpaid
						</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</td>

		<!-- Delivery Partner (E11) / Delivered By (E12) -->
		<td class="tile-cell tile-right"
		    style="width:50%;vertical-align:top;padding-left:6px;">
			<table border="0" cellpadding="0" cellspacing="0"
			       width="100%" role="presentation">
				<tr>
					<td style="background:#fce7f3;border-radius:8px;
					           padding:14px;border:1px solid #fbcfe8;">
						<table border="0" cellpadding="0" cellspacing="0"
						       role="presentation" style="margin-bottom:10px;">
							<tr>
								<td style="width:20px;vertical-align:middle;
								           padding-right:6px;">
									<?php if ( $bp_is_delivered ) : ?>
									<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/pin-pink.png' ); ?>" width="15" height="15" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
									<?php else : ?>
									<img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/truck-pink.png' ); ?>" width="15" height="15" alt="" style="display:inline-block;vertical-align:middle;border:0;" />
									<?php endif; ?>
								</td>
								<td style="font-family:Arial,Helvetica,sans-serif;
								           font-size:10px;font-weight:700;
								           color:#9d174d;text-transform:uppercase;
								           letter-spacing:0.5px;">
									<?php echo $bp_is_delivered ? 'Delivered by' : 'Delivery partner'; ?>
								</td>
							</tr>
						</table>
						<p style="margin:0 0 10px;
						          font-family:Arial,Helvetica,sans-serif;
						          font-size:13px;font-weight:700;
						          color:#9d174d;">
							Upaya City Cargo
						</p>
						<p style="margin:0;
						          font-family:Arial,Helvetica,sans-serif;
						          font-size:12px;color:#be185d;line-height:1.6;">
							<?php if ( $bp_is_delivered ) : ?>
							Delivered with care.<br />Thank you for trusting us!
							<?php elseif ( $bp_is_pickup ) : ?>
							Collected and on its way into our delivery network.
							<?php elseif ( $bp_is_transit ) : ?>
							You&rsquo;ll receive a call &amp; SMS before your order arrives.
							<?php else : ?>
							Delivering to you today with care.
							<?php endif; ?>
						</p>
					</td>
				</tr>
			</table>
		</td>

	</tr>
</table>

<!-- ══════════════════════════════════
     ORDER ITEMS (client E11/E12)
     ══════════════════════════════════ -->
<?php
// Collect visible items first so the last row can drop its border-bottom.
$bp_items = array();
foreach ( $order->get_items() as $item_id => $item ) {
	if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
		continue;
	}
	$bp_items[] = $item;
}
$bp_item_count = count( $bp_items );
?>
<table border="0" cellpadding="0" cellspacing="0"
       width="100%" role="presentation"
       style="border-radius:8px;overflow:hidden;
              border:1px solid #f3f4f6;margin:0 0 24px;">
	<tr>
		<td colspan="2"
		    style="background:#f3f4f6;padding:10px 16px;
		           border-bottom:1px solid #ebebeb;">
			<p style="margin:0;
			          font-family:Arial,Helvetica,sans-serif;
			          font-size:11px;font-weight:700;color:#9d174d;
			          text-transform:uppercase;letter-spacing:0.5px;">
				<?php echo $bp_is_delivered ? 'What was delivered' : ( $bp_is_pickup ? "What's in your order" : ( $bp_is_transit ? "What's on its way to you" : "What's being delivered today" ) ); ?>
			</p>
		</td>
	</tr>
	<?php
	foreach ( $bp_items as $bp_index => $item ) :
		$bp_row_border = ( $bp_index === $bp_item_count - 1 ) ? '' : 'border-bottom:1px solid #f3f4f6;';
		?>
	<tr>
		<td style="padding:10px 16px;
		           font-family:Arial,Helvetica,sans-serif;
		           font-size:13px;font-weight:600;color:#1f2937;
		           <?php echo esc_attr( $bp_row_border ); ?>">
			<?php echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) ); // CLIENT PLACEHOLDER: {{product_name}} → item name. ?>
		</td>
		<td style="padding:10px 16px;text-align:right;
		           font-family:Arial,Helvetica,sans-serif;
		           font-size:12px;font-weight:600;color:#9d174d;
		           white-space:nowrap;
		           <?php echo esc_attr( $bp_row_border ); ?>">
			&times;&nbsp;<?php echo esc_html( $item->get_quantity() ); // CLIENT PLACEHOLDER: {{product_qty}} → item quantity. ?>
		</td>
	</tr>
	<?php endforeach; ?>
</table>

<?php if ( $bp_is_delivered ) : ?>
<!-- ══════════════════════════════════
     FIVE-STAR REVIEW TEASER (client E12 — delivered only)
     Soft nudge — no links.
     ══════════════════════════════════ -->
<table border="0" cellpadding="0" cellspacing="0"
       width="100%" role="presentation"
       style="background:#fce7f3;border-radius:8px;
              border:1px solid #fbcfe8;margin:0 0 24px;">
	<tr>
		<td align="center"
		    style="padding:20px 24px;">
			<!-- 5 stars using Unicode ★ — renders in all email clients -->
			<p class="stars"
			   style="margin:0 0 10px;
			          font-size:26px;color:#ec4899;
			          letter-spacing:4px;line-height:1;">
				&#9733;&#9733;&#9733;&#9733;&#9733;
			</p>
			<p style="margin:0 0 6px;
			          font-family:Arial,Helvetica,sans-serif;
			          font-size:14px;font-weight:700;color:#9d174d;">
				Loving your order?
			</p>
			<p style="margin:0;
			          font-family:Arial,Helvetica,sans-serif;
			          font-size:12px;color:#be185d;line-height:1.7;">
				Watch out for a short email from us in a couple of
				days — we'd love to hear how your little one is
				enjoying it!
			</p>
		</td>
	</tr>
</table>
<?php endif; ?>

<!-- ══════════════════════════════════
     CTA (client E11/E12)
     ══════════════════════════════════ -->
<?php
$bp_cta_url   = $bp_is_delivered ? $order->get_view_order_url() : $bp_track_url;
$bp_cta_label = $bp_is_delivered ? 'View Your Order' : 'Track Your Order';
?>
<table border="0" cellpadding="0" cellspacing="0"
       width="100%" role="presentation">
	<tr>
		<td align="center">
			<table class="cta-btn cta-wrap" border="0" cellpadding="0"
			       cellspacing="0" role="presentation">
				<tr>
					<td style="background:#ec4899;border-radius:6px;
					           text-align:center;">
						<a href="<?php echo esc_url( $bp_cta_url ); // CLIENT PLACEHOLDER: CTA URL → $bp_track_url (E11) / $order->get_view_order_url() (E12). ?>"
						   target="_blank"
						   style="display:inline-block;
						          padding:14px 48px;
						          font-family:Arial,Helvetica,sans-serif;
						          font-size:15px;font-weight:700;
						          color:#ffffff !important;
						          text-decoration:none;
						          letter-spacing:0.3px;">
							<?php echo esc_html( $bp_cta_label ); ?>
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php
do_action( 'woocommerce_email_footer', $email );
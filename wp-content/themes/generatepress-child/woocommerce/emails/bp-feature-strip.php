<?php
/**
 * BabyPasa feature strip — client design shared element.
 *
 * Pink strip with three feature cells (Delivery · Gift · Hassle-free).
 * Rendered as a full-width row inside the 600px email card table:
 * after the hero for the welcome email (E01), before the footer band
 * for all other emails. Loaded via wc_get_template() from
 * email-header.php / email-footer.php.
 *
 * @package GeneratePress_Child\WooCommerce\Emails
 */

defined( 'ABSPATH' ) || exit;

// Absolute PNG icon base. Inline SVGs are stripped by Gmail/Outlook, so the
// feature glyphs ship as white line-icon PNGs (rendered from the original SVGs).
$bp_icons_uri = esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/' );
?>
<!-- FEATURE STRIP (client design) -->
<tr>
	<td style="background:#ec4899;padding:0;">
		<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
			<tr>
				<td class="feat-cell" style="width:33.33%;text-align:center;padding:20px 12px;vertical-align:top;border-right:1px solid rgba(255,255,255,0.25);">
					<img src="<?php echo $bp_icons_uri; // already escaped. ?>feat-truck.png" width="28" height="28" alt="" style="display:inline-block;margin-bottom:8px;border:0;" />
					<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
						Delivery all over Nepal
					</p>
					<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
						Enjoy delivery all over Nepal!
					</p>
				</td>
				<td class="feat-cell" style="width:33.34%;text-align:center;padding:20px 12px;vertical-align:top;border-right:1px solid rgba(255,255,255,0.25);">
					<img src="<?php echo $bp_icons_uri; // already escaped. ?>feat-gift.png" width="28" height="28" alt="" style="display:inline-block;margin-bottom:8px;border:0;" />
					<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
						Gift on your behalf
					</p>
					<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
						We&rsquo;ll deliver directly<br />to your loved ones!
					</p>
				</td>
				<td class="feat-cell-last" style="width:33.33%;text-align:center;padding:20px 12px;vertical-align:top;">
					<img src="<?php echo $bp_icons_uri; // already escaped. ?>shield-check.png" width="28" height="28" alt="" style="display:inline-block;margin-bottom:8px;border:0;" />
					<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
						Hassle-free shopping
					</p>
					<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
						Secure payments<br />and easy returns
					</p>
				</td>
			</tr>
		</table>
	</td>
</tr>

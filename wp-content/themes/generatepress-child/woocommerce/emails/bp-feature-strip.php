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
<!--
	FEATURE STRIP (client design) — hybrid/fluid layout.

	The three cells are inline-block <div>s (not table cells), each width:100% but
	capped at max-width:184px. On the 600px card they sit 3-across; on a narrow
	screen they reflow to a vertical stack WITHOUT a media query — required because
	some clients (e.g. Gmail rendering a non-Google account) strip the <head>
	<style> and ignore @media. Outlook (no inline-block reflow) uses the [if mso]
	ghost table to keep 3 columns. The .feat-cell @media rules in email-styles.php
	still apply as an enhancement where supported. font-size:0 on the wrapper
	removes the whitespace gaps between inline-blocks.
-->
<tr>
	<td style="background:#ec4899;padding:0;">
		<div style="font-size:0;line-height:0;text-align:center;">
			<!--[if mso]><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td width="33%" valign="top"><![endif]-->
			<div class="feat-cell" style="display:inline-block;width:100%;max-width:184px;vertical-align:top;box-sizing:border-box;text-align:center;padding:20px 12px;border-right:1px solid rgba(255,255,255,0.25);">
				<img src="<?php echo $bp_icons_uri; // already escaped. ?>feat-truck.png" width="28" height="28" alt="" style="display:inline-block;margin-bottom:8px;border:0;" />
				<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
					Delivery all over Nepal
				</p>
				<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
					Enjoy delivery all over Nepal!
				</p>
			</div><!--[if mso]></td><td width="33%" valign="top"><![endif]--><div class="feat-cell" style="display:inline-block;width:100%;max-width:184px;vertical-align:top;box-sizing:border-box;text-align:center;padding:20px 12px;border-right:1px solid rgba(255,255,255,0.25);">
				<img src="<?php echo $bp_icons_uri; // already escaped. ?>feat-gift.png" width="28" height="28" alt="" style="display:inline-block;margin-bottom:8px;border:0;" />
				<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
					Gift on your behalf
				</p>
				<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
					We&rsquo;ll deliver directly<br />to your loved ones!
				</p>
			</div><!--[if mso]></td><td width="34%" valign="top"><![endif]--><div class="feat-cell-last" style="display:inline-block;width:100%;max-width:184px;vertical-align:top;box-sizing:border-box;text-align:center;padding:20px 12px;">
				<img src="<?php echo $bp_icons_uri; // already escaped. ?>shield-check.png" width="28" height="28" alt="" style="display:inline-block;margin-bottom:8px;border:0;" />
				<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
					Hassle-free shopping
				</p>
				<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
					Secure payments<br />and easy returns
				</p>
			</div>
			<!--[if mso]></td></tr></table><![endif]-->
		</div>
	</td>
</tr>

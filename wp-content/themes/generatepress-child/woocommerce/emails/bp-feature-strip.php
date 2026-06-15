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
?>
<!-- FEATURE STRIP (client design) -->
<tr>
	<td style="background:#ec4899;padding:0;">
		<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
			<tr>
				<td class="feat-cell" style="width:33.33%;text-align:center;padding:20px 12px;vertical-align:top;border-right:1px solid rgba(255,255,255,0.25);">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;margin-bottom:8px;" aria-hidden="true">
						<rect x="1" y="6" width="13" height="10" rx="1" stroke="#ffffff" stroke-width="1.6" fill="none"/>
						<path d="M14 9h4.5L21 12.5V16H14V9Z" stroke="#ffffff" stroke-width="1.6" fill="none" stroke-linejoin="round"/>
						<circle cx="5.5" cy="17.5" r="1.5" fill="#ffffff"/>
						<circle cx="17.5" cy="17.5" r="1.5" fill="#ffffff"/>
					</svg>
					<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
						Delivery all over Nepal
					</p>
					<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
						Enjoy delivery all over Nepal!
					</p>
				</td>
				<td class="feat-cell" style="width:33.34%;text-align:center;padding:20px 12px;vertical-align:top;border-right:1px solid rgba(255,255,255,0.25);">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;margin-bottom:8px;" aria-hidden="true">
						<rect x="3" y="9" width="18" height="12" rx="1" stroke="#ffffff" stroke-width="1.6" fill="none"/>
						<rect x="3" y="6" width="18" height="3" rx="0.5" stroke="#ffffff" stroke-width="1.6" fill="none"/>
						<line x1="12" y1="6" x2="12" y2="21" stroke="#ffffff" stroke-width="1.4"/>
						<path d="M9 6C9 6 8 3 11 3s3 3 1 3" stroke="#ffffff" stroke-width="1.4" fill="none" stroke-linecap="round"/>
						<path d="M15 6C15 6 16 3 13 3s-3 3-1 3" stroke="#ffffff" stroke-width="1.4" fill="none" stroke-linecap="round"/>
					</svg>
					<p class="feat-title" style="margin:0 0 4px;font-size:10px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.5px;font-family:Arial,Helvetica,sans-serif;">
						Gift on your behalf
					</p>
					<p class="feat-sub" style="margin:0;font-size:10px;color:#fce7f3;line-height:1.5;font-family:Arial,Helvetica,sans-serif;">
						We&rsquo;ll deliver directly<br />to your loved ones!
					</p>
				</td>
				<td class="feat-cell-last" style="width:33.33%;text-align:center;padding:20px 12px;vertical-align:top;">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;margin-bottom:8px;" aria-hidden="true">
						<path d="M12 3L4 6.5V12c0 4.5 3.3 8.3 8 9.5 4.7-1.2 8-5 8-9.5V6.5L12 3Z" stroke="#ffffff" stroke-width="1.6" fill="none" stroke-linejoin="round"/>
						<path d="M8.5 12l2.5 2.5 4.5-4.5" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
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

<?php
/**
 * Handles plugin activation and deactivation.
 */

namespace BabypasaNewsletter\Includes;

defined( 'ABSPATH' ) || exit;

class Activator {

	/**
	 * Creates the subscribers table and seeds default email templates.
	 */
	public static function activate(): void {
		self::create_table();
		self::set_defaults();
	}

	/**
	 * Clears any scheduled cron jobs on deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'bpnl_send_batch' );
	}

	private static function create_table(): void {
		global $wpdb;

		$table           = $wpdb->prefix . 'bpnl_subscribers';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(191) NOT NULL,
			status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
			token VARCHAR(64) NOT NULL,
			subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			unsubscribed_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY token (token),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'bpnl_db_version', BPNL_DB_VERSION );
	}

	private static function set_defaults(): void {
		$existing_welcome = get_option( 'bpnl_template_welcome', '' );

		// Seed or upgrade: overwrite if no template saved, or if it's still
		// the old plain-text template (doesn't contain the HTML doctype).
		$needs_upgrade = empty( $existing_welcome )
			|| false === strpos( $existing_welcome, '<!DOCTYPE' );

		if ( $needs_upgrade ) {
			$welcome = Email::get_default_welcome_template();
			update_option( 'bpnl_template_welcome', wp_json_encode( $welcome ) );
		}

		if ( ! get_option( 'bpnl_template_newsletter' ) ) {
			// REPLACED: old babypasa-newsletter layout with client template design
			// (full HTML document: 600px white card, pink rule, #fce7f3 hero, pink footer band).
			$newsletter_body = <<<'HTML'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="x-apple-disable-message-reformatting" />
<title></title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style type="text/css">
/* ── Reset ── */
body, table, td, p, a    { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
table, td                { mso-table-lspace:0pt; mso-table-rspace:0pt; }
img                      { border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; }
body                     { margin:0; padding:0; background-color:#f3f4f6; }

/* ── Mobile (≤ 480px) ── */
@media only screen and (max-width:480px) {
	.email-wrap  { width:100% !important; }
	.logo-pad    { padding:20px 16px 14px !important; }
	.hero-pad    { padding:26px 16px !important; }
	.body-pad    { padding:22px 16px !important; }
	.footer-pad  { padding:18px 16px 22px !important; }
	.hero-h1     { font-size:20px !important; }
	.social-td   { display:block !important; width:100% !important;
	               padding:0 0 8px !important; text-align:center !important; }
	.body-copy   { font-size:14px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">

<!-- REPLACED: old babypasa-newsletter layout with client template design -->
<!-- Outer wrapper -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
<tr>
<td align="center" style="padding:28px 12px;">

<!-- Card container 600px -->
<table class="email-wrap" border="0" cellpadding="0" cellspacing="0" width="600" role="presentation"
       style="background:#ffffff;border-radius:10px;overflow:hidden;">

	<!-- 1. LOGO -->
	<tr>
		<td class="logo-pad" align="center" style="background:#ffffff;padding:24px 36px 18px;">
			{{site_logo}}
		</td>
	</tr>

	<!-- pink accent rule -->
	<tr>
		<td style="background:#ec4899;height:4px;font-size:0;line-height:0;">&nbsp;</td>
	</tr>

	<!-- 2. HERO -->
	<tr>
		<td class="hero-pad" align="center" style="background:#fce7f3;padding:32px 36px;">
			<h1 class="hero-h1" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:700;color:#9d174d;line-height:1.3;">
				A note from {{site_name}}
			</h1>
		</td>
	</tr>

	<!-- 3. BODY -->
	<tr>
		<td class="body-pad" style="background:#ffffff;padding:28px 36px;">
			<p class="body-copy" style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;">
				Hi {{subscriber_email}},
			</p>
			<p class="body-copy" style="margin:0 0 16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;">
				Here is our latest newsletter. We hope you enjoy it!
			</p>
			<p class="body-copy" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;">
				Best,<br />The {{site_name}} Team
			</p>
		</td>
	</tr>

	<!-- 4. FOOTER — SOCIAL + BLOG + UNSUBSCRIBE -->
	<tr>
		<td class="footer-pad" align="center" style="background:#fce7f3;padding:20px 36px 26px;">
			<p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#9d174d;text-transform:uppercase;letter-spacing:0.6px;">
				Follow us for offers, tips &amp; more
			</p>
			<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 auto 12px;">
				<tr>
					<td class="social-td" style="padding:0 10px;text-align:center;">
						<a href="https://facebook.com/babypasanepal" target="_blank"
						   style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#be185d;text-decoration:none;white-space:nowrap;">
							&#x1F4D8;&nbsp;Facebook &middot; @babypasanepal
						</a>
					</td>
					<td class="social-td" style="padding:0 10px;text-align:center;">
						<a href="https://instagram.com/babypasanepal" target="_blank"
						   style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#be185d;text-decoration:none;white-space:nowrap;">
							&#x1F4F8;&nbsp;Instagram &middot; @babypasanepal
						</a>
					</td>
				</tr>
			</table>
			<a href="https://blog.babypasa.com.np" target="_blank"
			   style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;text-decoration:none;">
				&#x1F4D6;&nbsp;Read our blog for parenting tips &amp; more
			</a>
			<p style="margin:16px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#be185d;line-height:1.8;">
				If you no longer wish to receive these emails,
				<a href="{{unsubscribe_link}}" style="color:#be185d;text-decoration:underline;">click here to unsubscribe</a>.
			</p>
		</td>
	</tr>

</table>
<!-- /card container -->

<!-- Copyright below card -->
<p style="margin:14px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#9ca3af;text-align:center;">
	&copy; 2025 BabyPasa.Com &mdash; Kathmandu, Nepal
</p>

</td>
</tr>
</table>
<!-- /outer wrapper -->

</body>
</html>
HTML;

			$newsletter = array(
				'subject'  => 'Latest News from {{site_name}}',
				'body'     => $newsletter_body,
				'reply_to' => get_option( 'admin_email' ),
			);
			update_option( 'bpnl_template_newsletter', wp_json_encode( $newsletter ) );
		}
	}
}

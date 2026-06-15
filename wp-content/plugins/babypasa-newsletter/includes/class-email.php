<?php
/**
 * Email template storage, token replacement, and sending.
 *
 * Supported tokens
 * ─────────────────────────────────────────────────────────
 * {{subscriber_email}}  Recipient email address
 * {{unsubscribe_link}}  One-click unsubscribe URL
 * {{site_name}}         Blog name (get_bloginfo)
 * {{site_logo}}         <img> tag of the site logo (falls back to text)
 * {{latest_products}}   3-column table of newest WooCommerce products
 * {{shop_url}}          /shop URL
 * {{home_url}}          Home URL
 */

namespace BabypasaNewsletter\Includes;

defined( 'ABSPATH' ) || exit;

class Email {

	// ─── Template CRUD ──────────────────────────────────────────────────

	/**
	 * @return array{subject:string,body:string,reply_to:string}
	 */
	public static function get_template( string $key ): array {
		$json = get_option( $key, '' );
		if ( empty( $json ) ) {
			return array( 'subject' => '', 'body' => '', 'reply_to' => '' );
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array( 'subject' => '', 'body' => '', 'reply_to' => '' );
		}
		return array(
			'subject'  => (string) ( $data['subject']  ?? '' ),
			'body'     => (string) ( $data['body']     ?? '' ),
			'reply_to' => (string) ( $data['reply_to'] ?? '' ),
		);
	}

	/**
	 * @param array{subject:string,body:string,reply_to:string} $data
	 */
	public static function save_template( string $key, array $data ): void {
		update_option(
			$key,
			wp_json_encode(
				array(
					'subject'  => sanitize_text_field( $data['subject']  ?? '' ),
					'body'     => (string) ( $data['body'] ?? '' ),
					'reply_to' => sanitize_email( $data['reply_to'] ?? '' ),
				)
			)
		);
	}

	// ─── Send ────────────────────────────────────────────────────────────

	public static function send_welcome( object $subscriber ): bool {
		$template = self::get_template( 'bpnl_template_welcome' );
		if ( empty( $template['subject'] ) || empty( $template['body'] ) ) {
			return false;
		}
		return self::send_to( $subscriber, $template );
	}

	/**
	 * @param array{subject:string,body:string,reply_to:string} $template
	 */
	public static function send_to( object $subscriber, array $template ): bool {
		$subject = self::replace_tokens( $template['subject'], $subscriber );
		$body    = self::replace_tokens( $template['body'],    $subscriber );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $template['reply_to'] ) && is_email( $template['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $template['reply_to'] );
		}

		return wp_mail( $subscriber->email, $subject, $body, $headers );
	}

	// ─── Token replacement ───────────────────────────────────────────────

	private static function replace_tokens( string $content, object $subscriber ): string {
		$unsubscribe_url = home_url( '/?bpnl_unsubscribe=' . rawurlencode( $subscriber->token ) );

		$search  = array(
			'{{subscriber_email}}',
			'{{unsubscribe_link}}',
			'{{site_name}}',
			'{{site_logo}}',
			'{{latest_products}}',
			'{{shop_url}}',
			'{{home_url}}',
		);
		$replace = array(
			esc_html( $subscriber->email ),
			esc_url( $unsubscribe_url ),
			esc_html( get_bloginfo( 'name' ) ),
			self::get_site_logo_html(),           // trusted HTML
			self::get_latest_products_html(),     // trusted HTML
			esc_url( home_url( '/shop' ) ),
			esc_url( home_url( '/' ) ),
		);

		return str_replace( $search, $replace, $content );
	}

	// ─── Helper: site logo ───────────────────────────────────────────────

	private static function get_site_logo_html(): string {
		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

		// REPLACED: old babypasa-newsletter layout with client template design
		// (logo now sits on a white header row, 130px wide, instead of a dark gradient header).
		if ( $logo_url ) {
			return '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" '
				. 'width="130" style="display:block;margin:0 auto;width:130px;max-width:130px;height:auto;">';
		}

		return '<span style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;color:#9d174d;letter-spacing:0.5px;">'
			. esc_html( get_bloginfo( 'name' ) ) . '</span>';
	}

	// ─── Helper: latest products ─────────────────────────────────────────

	private static function get_latest_products_html(): string {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}

		$products = wc_get_products(
			array(
				'limit'   => 3,
				'status'  => 'publish',
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);

		if ( empty( $products ) ) {
			return '';
		}

		$placeholder = function_exists( 'wc_placeholder_img_src' )
			? wc_placeholder_img_src( 'woocommerce_thumbnail' )
			: '';

		$cols  = count( $products );
		// Per-card cap so the row fits the ~528px card body at 1/2/3 across. The
		// cards are inline-block divs (font-size:0 wrapper kills the gaps) so they
		// reflow to a centered vertical stack on narrow screens with NO media query
		// — required because some clients (Gmail with a non-Google account) strip
		// the <head> <style> and ignore @media. Outlook (no inline-block reflow)
		// uses the [if mso] ghost table to keep its columns. The .product-col @media
		// rule still applies as an enhancement where supported.
		$max_w = 3 === $cols ? '168px' : ( 2 === $cols ? '252px' : '100%' );
		$mso_w = 3 === $cols ? '33%' : ( 2 === $cols ? '50%' : '100%' );

		$html  = '<div style="font-size:0;line-height:0;text-align:center;">' . "\n";
		$html .= '<!--[if mso]><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%"><tr><![endif]-->' . "\n";

		foreach ( $products as $product ) {
			$image_id  = $product->get_image_id();
			$image_url = $image_id
				? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
				: $placeholder;

			$price_text = wp_strip_all_tags( html_entity_decode( $product->get_price_html() ) );

			$html .= '<!--[if mso]><td width="' . $mso_w . '" valign="top"><![endif]-->' . "\n";
			$html .= '<div class="product-col" '
				. 'style="display:inline-block;width:100%;max-width:' . $max_w . ';vertical-align:top;box-sizing:border-box;padding:0 6px 12px;text-align:center;">' . "\n";

			$html .= '<a href="' . esc_url( $product->get_permalink() ) . '" '
				. 'style="text-decoration:none;color:inherit;display:block;">' . "\n";

			$html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" '
				. 'style="border:1px solid #f3f4f6;border-radius:8px;overflow:hidden;">' . "\n";

			// Product image.
			$html .= '<tr><td style="background:#ffffff;text-align:center;padding:14px;">' . "\n";
			if ( $image_url ) {
				$html .= '<img src="' . esc_url( $image_url ) . '" '
					. 'class="product-img" width="140" alt="' . esc_attr( $product->get_name() ) . '" '
					. 'style="display:block;margin:0 auto;max-width:100%;height:auto;border-radius:8px;border:1px solid #f3f4f6;">' . "\n";
			}
			$html .= '</td></tr>' . "\n";

			// Product details.
			$html .= '<tr><td style="padding:12px 14px 14px;text-align:center;">' . "\n";
			$html .= '<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#1f2937;line-height:1.4;">'
				. esc_html( $product->get_name() ) . '</p>' . "\n";
			if ( $price_text ) {
				$html .= '<p style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#ec4899;">'
					. esc_html( $price_text ) . '</p>' . "\n";
			}
			$html .= '<span style="display:inline-block;padding:7px 18px;border:1px solid #ec4899;border-radius:6px;'
				. 'font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#ec4899;letter-spacing:0.3px;">View</span>' . "\n";
			$html .= '</td></tr>' . "\n";

			$html .= '</table>' . "\n"; // inner card table
			$html .= '</a>' . "\n";
			$html .= '</div>' . "\n";
			$html .= '<!--[if mso]></td><![endif]-->' . "\n";
		}

		$html .= '<!--[if mso]></tr></table><![endif]-->' . "\n";
		$html .= '</div>' . "\n";

		return $html;
	}

	// ─── Default template ────────────────────────────────────────────────

	/**
	 * Returns the built-in styled welcome email template.
	 * Used on first activation and by the "Restore Default" admin action.
	 *
	 * @return array{subject:string,body:string,reply_to:string}
	 */
	public static function get_default_welcome_template(): array {
		ob_start();
		?>
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
	.hero-sub    { font-size:14px !important; }
	.feat-cell,
	.feat-cell-last { display:block !important; width:100% !important;
	                  border-right:none !important;
	                  border-bottom:1px solid rgba(255,255,255,0.25) !important;
	                  padding:18px 20px !important; }
	.feat-cell-last { border-bottom:none !important; }
	.social-td   { display:block !important; width:100% !important;
	               padding:0 0 8px !important; text-align:center !important; }
	.cta-wrap    { width:100% !important; }
	.cta-wrap a  { display:block !important; padding:14px 20px !important; }
	.product-col { display:block !important; width:100% !important; max-width:none !important;
	               padding:0 0 16px !important; }
	.product-img { max-width:180px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;">

<!-- Preheader: invisible but shown in inbox previews -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;opacity:0;font-size:1px;color:#f3f4f6;">
	Welcome to {{site_name}}! You're officially part of our family. Get ready for amazing baby products and exclusive deals. &#847; &zwnj; &nbsp; &#847; &zwnj; &nbsp; &#847; &zwnj; &nbsp;
</div>

<!-- REPLACED: old babypasa-newsletter layout with client template design -->
<!-- Outer wrapper -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
<tr>
<td align="center" style="padding:28px 12px;">

<!-- BABYPASA EDIT — fluid card (was fixed width="600", which forced a rigid 600px slab on mobile). Outlook ignores max-width, so the [if mso] ghost table pins it to 600px there. — will be lost on plugin update -->
<!--[if mso]><table border="0" cellpadding="0" cellspacing="0" width="600" align="center" role="presentation"><tr><td><![endif]-->
<!-- Card container -->
<table class="email-wrap" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"
       style="width:100%;max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">

	<!-- ═══ 1. LOGO ═════════════════════════════════════════════════════ -->
	<tr>
		<td class="logo-pad" align="center" style="background:#ffffff;padding:24px 36px 18px;">
			{{site_logo}}
		</td>
	</tr>

	<!-- pink accent rule -->
	<tr>
		<td style="background:#ec4899;height:4px;font-size:0;line-height:0;">&nbsp;</td>
	</tr>

	<!-- ═══ 2. HERO ═════════════════════════════════════════════════════ -->
	<tr>
		<td class="hero-pad" align="center" style="background:#fce7f3;padding:34px 36px;">
			<div style="font-size:44px;line-height:1;margin:0 0 14px;">🍼</div>
			<h1 class="hero-h1" style="margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:24px;font-weight:700;color:#9d174d;line-height:1.3;">
				Welcome to the {{site_name}} Family!
			</h1>
			<p class="hero-sub" style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#be185d;line-height:1.7;">
				Hi <strong>{{subscriber_email}}</strong> 👋
			</p>
			<p class="hero-sub" style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#be185d;line-height:1.7;">
				Thank you for joining us! You'll be the first to know about our newest baby products,<br />
				exclusive discounts, and parenting tips &mdash; straight to your inbox.
			</p>
		</td>
	</tr>

	<!-- ═══ 3. BODY — COPY + CTA ════════════════════════════════════════ -->
	<tr>
		<td class="body-pad" style="background:#ffffff;padding:28px 36px;">
			<p class="body-copy" style="margin:0 0 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#374151;line-height:1.8;text-align:center;">
				We handpick every product with your baby's safety and comfort in mind.
				Explore our latest arrivals and find everything your little one needs.
			</p>
			<!-- CTA Button -->
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr>
					<td align="center">
						<table class="cta-wrap" border="0" cellpadding="0" cellspacing="0" role="presentation">
							<tr>
								<td style="background:#ec4899;border-radius:6px;text-align:center;">
									<a href="{{shop_url}}" target="_blank"
									   style="display:inline-block;padding:14px 48px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:700;color:#ffffff !important;text-decoration:none;letter-spacing:0.3px;">
										Shop Now
									</a>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- ═══ 4. PRODUCTS ═════════════════════════════════════════════════ -->
	<tr>
		<td class="body-pad" style="background:#ffffff;padding:0 36px 28px;">
			<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
				<tr>
					<td style="text-align:center;border-top:1px solid #fce7f3;padding:24px 0 20px;">
						<p style="margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#ec4899;">
							NEW ARRIVALS
						</p>
						<h2 style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700;color:#9d174d;">
							Trending Right Now ✨
						</h2>
					</td>
				</tr>
				<tr>
					<td>{{latest_products}}</td>
				</tr>
				<tr>
					<td style="text-align:center;padding-top:22px;">
						<a href="{{shop_url}}" style="display:inline-block;padding:11px 32px;border:1px solid #ec4899;color:#ec4899;background:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:700;text-decoration:none;border-radius:6px;letter-spacing:0.3px;">
							View All Products
						</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- ═══ 5. FEATURE STRIP (trust badges) ═════════════════════════════ -->
	<tr>
		<td style="background:#ec4899;padding:0;">
			<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
				<tr valign="top">
					<td class="feat-cell" style="width:33.33%;text-align:center;padding:22px 14px;vertical-align:top;border-right:1px solid rgba(255,255,255,0.25);">
						<div style="font-size:28px;line-height:1;margin:0 0 10px;">🚚</div>
						<p style="margin:0 0 5px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.6px;line-height:1.3;">Fast Delivery</p>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#fce7f3;line-height:1.55;">All over Nepal</p>
					</td>
					<td class="feat-cell" style="width:33.34%;text-align:center;padding:22px 14px;vertical-align:top;border-right:1px solid rgba(255,255,255,0.25);">
						<div style="font-size:28px;line-height:1;margin:0 0 10px;">✅</div>
						<p style="margin:0 0 5px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.6px;line-height:1.3;">100% Genuine</p>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#fce7f3;line-height:1.55;">Quality guaranteed</p>
					</td>
					<td class="feat-cell-last" style="width:33.33%;text-align:center;padding:22px 14px;vertical-align:top;">
						<div style="font-size:28px;line-height:1;margin:0 0 10px;">🛡️</div>
						<p style="margin:0 0 5px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;color:#ffffff;text-transform:uppercase;letter-spacing:0.6px;line-height:1.3;">Secure Payment</p>
						<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#fce7f3;line-height:1.55;">Safe &amp; encrypted</p>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- ═══ 6. FOOTER — SOCIAL + BLOG + UNSUBSCRIBE ═════════════════════ -->
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
				You received this email because you subscribed at
				<a href="{{home_url}}" style="color:#be185d;text-decoration:underline;">{{site_name}}</a>.<br />
				No longer want these emails?
				<a href="{{unsubscribe_link}}" style="color:#be185d;text-decoration:underline;">Unsubscribe</a>
			</p>
		</td>
	</tr>

</table>
<!--[if mso]></td></tr></table><![endif]-->
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
		<?php
		$body = ob_get_clean();

		return array(
			'subject'  => 'Welcome to {{site_name}} — You\'re In! 🎉',
			'body'     => $body,
			'reply_to' => get_option( 'admin_email', '' ),
		);
	}
}

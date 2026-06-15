<?php
/**
 * Email Styles — BabyPasa client design (2026 migration).
 *
 * Consolidated CSS from the client-provided HTML email templates
 * (woocommerce/Email Template and Logo/E01–E15). Single source of truth
 * for all WooCommerce + Upaya Cargo emails.
 *
 * WooCommerce inlines these rules into the markup at send time; rules
 * inside @media blocks are preserved in a <style> element in <head>.
 * The templates themselves also carry the client's inline styles, so the
 * class rules here mainly serve the responsive overrides.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
/* ── Reset (client design) ── */
body, table, td, p, a    { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td                { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img                      { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
body                     { margin: 0; padding: 0; background-color: #f3f4f6; }

/* ── Layout ── */
.email-wrap              { width: 600px; }
.logo-pad                { padding: 24px 36px 18px; }
.hero-pad                { padding: 32px 36px; }
.section-pad             { padding: 24px 36px 0; }
.body-pad                { padding: 24px 36px; }
.bp-body                 { padding: 24px 36px; }
.footer-pad              { padding: 20px 32px 24px; }

/* ── Typography helpers ── */
.hero-h1                 { margin: 0 0 8px; font-family: Arial, Helvetica, sans-serif; font-size: 22px; font-weight: 700; color: #9d174d; line-height: 1.3; }
.hero-sub                { margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #be185d; line-height: 1.7; }
.body-copy               { font-family: Arial, Helvetica, sans-serif; font-size: 15px; color: #374151; line-height: 1.8; }

/* ── Order item rows ── */
.item-row td             { padding: 12px 16px; border-bottom: 1px solid #fbcfe8; }
.item-name               { font-weight: 600; color: #1f2937; font-size: 13px; font-family: Arial, Helvetica, sans-serif; }
.item-qty                { color: #9d174d; font-size: 12px; margin-top: 2px; font-family: Arial, Helvetica, sans-serif; }
.item-price              { font-weight: 600; color: #1f2937; font-size: 13px; white-space: nowrap; text-align: right; font-family: Arial, Helvetica, sans-serif; }

/* ── Info tiles ── */
.tile-cell               { width: 50%; vertical-align: top; }
.tile-left               { padding-right: 6px; }
.tile-right              { padding-left: 6px; }
.tile-box                { background: #f9fafb; border-radius: 8px; padding: 14px; border: 1px solid #f3f4f6; }
.tile-box-pink           { background: #fce7f3; border-radius: 8px; padding: 14px; border: 1px solid #fbcfe8; }
.tile-label              { font-size: 10px; font-weight: 700; color: #9d174d; text-transform: uppercase; letter-spacing: 0.5px; font-family: Arial, Helvetica, sans-serif; vertical-align: middle; }
.tile-name               { margin: 0 0 10px; font-size: 13px; font-weight: 700; color: #374151; font-family: Arial, Helvetica, sans-serif; line-height: 1.5; }
.tile-name-pink          { margin: 0 0 10px; font-size: 13px; font-weight: 700; color: #9d174d; font-family: Arial, Helvetica, sans-serif; }
.tile-sub                { margin: 0; font-size: 12px; color: #6b7280; line-height: 1.5; font-family: Arial, Helvetica, sans-serif; }
.tile-sub-pink           { margin: 0; font-size: 12px; color: #be185d; line-height: 1.5; font-family: Arial, Helvetica, sans-serif; }

/* ── Journey tracker ── */
.trk-step                { width: 14%; text-align: center; vertical-align: top; padding: 0 2px; }
.trk-conn                { width: 7.5%; vertical-align: top; padding-top: 14px; }
.trk-label               { margin: 0; font-size: 10px; font-weight: 700; color: #9d174d; line-height: 1.3; font-family: Arial, Helvetica, sans-serif; }
.trk-label-dim           { margin: 0; font-size: 10px; color: #be185d; line-height: 1.3; font-family: Arial, Helvetica, sans-serif; }

/* ── Reset link fallback (password reset) ── */
.reset-url               { color: #ec4899; word-break: break-all; font-size: 11px; font-family: 'Courier New', Courier, monospace; }

/* ── Feature strip cells ── */
.feat-cell               { width: 33.33%; text-align: center; padding: 20px 12px; vertical-align: top; border-right: 1px solid rgba(255,255,255,0.25); }
.feat-cell-last          { width: 33.34%; text-align: center; padding: 20px 12px; vertical-align: top; }
.feat-title              { margin: 0 0 4px; font-size: 10px; font-weight: 700; color: #ffffff; text-transform: uppercase; letter-spacing: 0.5px; font-family: Arial, Helvetica, sans-serif; }
.feat-sub                { margin: 0; font-size: 10px; color: #fce7f3; line-height: 1.5; font-family: Arial, Helvetica, sans-serif; }

/* ── Social footer cells ── */
.social-td               { padding: 0 12px; text-align: center; }

/* WooCommerce default-template compatibility: body content rendered by
   stock templates (paragraphs, links, headings) picks up the client
   typography. */
.bp-body-inner           { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #374151; line-height: 1.8; }
.bp-body-inner p         { margin: 0 0 16px; }
.bp-body-inner a         { color: #ec4899; font-weight: 700; text-decoration: none; }
.bp-body-inner h2        { margin: 0 0 12px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: 700; color: #9d174d; }
.bp-body-inner h3        { margin: 0 0 10px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 700; color: #9d174d; }

/* ══════════════════════════════════════
   TABLET (481px – 768px)
   ══════════════════════════════════════ */
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

/* ══════════════════════════════════════
   MOBILE (≤ 480px)
   ══════════════════════════════════════ */
@media only screen and (max-width: 480px) {
	.email-wrap            { width: 100% !important; }
	.logo-pad              { padding: 20px 16px 14px !important; }
	.hero-pad              { padding: 26px 16px !important; }
	.section-pad           { padding: 16px 16px 0 !important; }
	.body-pad              { padding: 16px !important; }
	.bp-body               { padding: 16px !important; }
	.footer-pad            { padding: 18px 16px 22px !important; }

	/* Hero type scale */
	.hero-h1               { font-size: 19px !important; }
	.hero-sub              { font-size: 14px !important; }
	.body-copy             { font-size: 14px !important; }

	/* Stack address / payment tiles */
	.tile-cell,
	.tile-cell-b           { display: block !important; width: 100% !important; }
	.tile-left,
	.tile-left-b           { padding-right: 0 !important; padding-bottom: 10px !important; }
	.tile-right,
	.tile-right-b          { padding-left: 0 !important; }

	/* Journey tracker */
	.trk-label,
	.trk-label-dim         { font-size: 9px !important; }
	.trk-conn              { padding-top: 11px !important; }

	/* Review stars */
	.stars                 { font-size: 22px !important; letter-spacing: 2px !important; }

	/* Stack feature strip */
	.feat-cell,
	.feat-cell-last        { display: block !important; width: 100% !important; border-right: none !important; border-bottom: 1px solid rgba(255,255,255,0.25) !important; }
	.feat-cell-last        { border-bottom: none !important; }

	/* Full-width CTA */
	.cta-btn,
	.cta-wrap              { width: 100% !important; text-align: center !important; }
	.cta-btn a,
	.cta-wrap a            { display: block !important; padding: 14px 20px !important; }

	/* Social links stack vertically */
	.social-td             { display: block !important; width: 100% !important; padding: 0 0 8px !important; text-align: center !important; }

	/* Order card header stacks */
	.order-hdr-num         { display: block !important; }
	.order-hdr-date        { display: block !important; font-size: 11px !important; margin-top: 2px; }

	/* Item row adjustments */
	.item-row td           { padding: 10px 12px !important; }
	.price-col             { display: none !important; }

	/* Info banners (phone / monitoring / refund / product card) */
	.banner-icon-td        { display: block !important; text-align: center !important; padding-bottom: 10px !important; }
	.banner-text-td        { display: block !important; text-align: center !important; }
	.refund-icon-td        { display: none !important; }
	.product-icon-td       { display: none !important; }
	.thankyou-icon-td      { display: none !important; }
}

@media print {
	body { background-color: #fff; }
}
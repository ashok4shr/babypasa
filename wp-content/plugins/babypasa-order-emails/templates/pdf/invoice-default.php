<?php
/**
 * Shipped default invoice PDF template.
 *
 * Print-oriented HTML/CSS (NOT the email's table-soup markup). Dynamic values
 * come from {{merge_tags}} replaced by BP_Invoice_PDF_Generator; static labels
 * are translatable. This file is the permanent fail-safe fallback and the base
 * the structured settings + advanced raw editor build on.
 *
 * Merge tags: {{invoice_title}} {{order_number}} {{order_date}} {{invoice_date}}
 *   {{shop_logo}} {{shop_name}} {{shop_address}} {{shop_contact}} {{shop_reg_number}}
 *   {{billing_name}} {{billing_address}} {{billing_phone}} {{billing_alt_phone}}
 *   {{billing_email}} {{shipping_address_block}} {{line_items_table}}
 *   {{totals_table}} {{terms_block}} {{footer_text}} {{accent_color}}
 *   {{base_font_size}} {{logo_align}}
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<style>
	@page { margin: 22mm 16mm; }
	* { box-sizing: border-box; }
	body {
		font-family: "DejaVu Sans", sans-serif;
		font-size: {{base_font_size}};
		color: #1f2937;
		line-height: 1.5;
		margin: 0;
	}
	.bp-head { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
	.bp-head td { vertical-align: top; }
	.bp-logo { max-height: 54px; max-width: 180px; }
	.bp-head-left { text-align: {{logo_align}}; }
	.bp-head-right { text-align: right; }
	.bp-invoice-title {
		font-size: 1.9em;
		font-weight: bold;
		color: {{accent_color}};
		margin: 0 0 4px;
		letter-spacing: 1px;
	}
	.bp-meta { font-size: 0.92em; color: #6b7280; }
	.bp-meta strong { color: #1f2937; }
	.bp-shop-name { font-weight: bold; font-size: 1.05em; }
	.bp-shop-block { font-size: 0.9em; color: #4b5563; margin-top: 4px; }
	.bp-rule { height: 3px; background: {{accent_color}}; border: 0; margin: 6px 0 16px; }
	.bp-addrs { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
	.bp-addrs td { vertical-align: top; width: 50%; padding-right: 14px; }
	.bp-addr-col { }
	.bp-addr-label {
		display: block;
		font-size: 0.72em;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		color: {{accent_color}};
		font-weight: bold;
		margin-bottom: 3px;
	}
	table.bp-items { width: 100%; border-collapse: collapse; margin: 4px 0 14px; }
	table.bp-items th {
		background: {{accent_color}};
		color: #ffffff;
		text-align: left;
		padding: 7px 9px;
		font-size: 0.82em;
		text-transform: uppercase;
		letter-spacing: 0.3px;
	}
	table.bp-items td { padding: 7px 9px; border-bottom: 1px solid #e5e7eb; }
	.bp-num { text-align: right; white-space: nowrap; }
	table.bp-items th.bp-num { text-align: right; }
	table.bp-totals { width: 46%; border-collapse: collapse; margin-left: auto; }
	table.bp-totals td { padding: 5px 9px; }
	table.bp-totals td.bp-num { text-align: right; }
	table.bp-totals tr.bp-grand td {
		border-top: 2px solid {{accent_color}};
		font-weight: bold;
		font-size: 1.1em;
		color: {{accent_color}};
	}
	.bp-terms { margin-top: 22px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
	.bp-terms-title { font-size: 0.95em; color: {{accent_color}}; margin: 0 0 4px; }
	.bp-terms-body { font-size: 0.88em; color: #4b5563; }
	.bp-footer {
		margin-top: 26px;
		padding-top: 10px;
		border-top: 1px solid #e5e7eb;
		text-align: center;
		font-size: 0.85em;
		color: #6b7280;
	}
	.bp-reg { font-size: 0.85em; color: #6b7280; margin-top: 2px; }
</style>
</head>
<body>

	<table class="bp-head">
		<tr>
			<td class="bp-head-left" style="width:55%;">
				{{shop_logo}}
				<div class="bp-shop-name">{{shop_name}}</div>
				<div class="bp-shop-block">{{shop_address}}</div>
				<div class="bp-shop-block">{{shop_contact}}</div>
				<div class="bp-reg">{{shop_reg_number}}</div>
			</td>
			<td class="bp-head-right">
				<div class="bp-invoice-title">{{invoice_title}}</div>
				<div class="bp-meta">
					<strong><?php esc_html_e( 'Invoice #', 'babypasa-order-emails' ); ?></strong> {{order_number}}<br/>
					<strong><?php esc_html_e( 'Order date:', 'babypasa-order-emails' ); ?></strong> {{order_date}}<br/>
					<strong><?php esc_html_e( 'Invoice date:', 'babypasa-order-emails' ); ?></strong> {{invoice_date}}
				</div>
			</td>
		</tr>
	</table>

	<hr class="bp-rule" />

	<table class="bp-addrs">
		<tr>
			<td>
				<div class="bp-addr-col">
					<span class="bp-addr-label"><?php esc_html_e( 'Bill To', 'babypasa-order-emails' ); ?></span>
					{{billing_address}}
					<div class="bp-meta" style="margin-top:5px;">
						{{billing_phone}}<br/>
						{{billing_alt_phone}}<br/>
						{{billing_email}}
					</div>
				</div>
			</td>
			<td>{{shipping_address_block}}</td>
		</tr>
	</table>

	{{line_items_table}}

	{{totals_table}}

	{{terms_block}}

	<div class="bp-footer">{{footer_text}}</div>

</body>
</html>

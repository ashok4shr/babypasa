<?php
/**
 * Invoice PDF settings screen markup.
 *
 * Provided by BP_Invoice_PDF_Admin::render_page():
 *   array  $settings     Current structured settings.
 *   string $raw_template Saved raw template ('' = default in use).
 *   bool   $can_raw      Whether the current user may edit raw HTML.
 *   string $logo_url     Preview URL for the selected logo (or '').
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

$bp_note = isset( $_GET['bp_pdf_note'] ) ? sanitize_key( wp_unslash( $_GET['bp_pdf_note'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag.
?>
<div class="wrap bp-invoice-pdf-wrap">
	<h1><?php esc_html_e( 'Invoice PDF', 'babypasa-order-emails' ); ?></h1>

	<?php if ( 'saved' === $bp_note ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Invoice PDF settings saved.', 'babypasa-order-emails' ); ?></p></div>
	<?php elseif ( 'reset' === $bp_note ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Invoice PDF defaults restored.', 'babypasa-order-emails' ); ?></p></div>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'Controls the PDF invoice attached to the Baby Pasa invoice email and available from the order screen. The email itself is configured under WooCommerce → Settings → Emails.', 'babypasa-order-emails' ); ?>
	</p>

	<form id="bp-invoice-pdf-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'bp_invoice_pdf_save' ); ?>
		<input type="hidden" name="action" value="bp_invoice_pdf_save" />

		<h2 class="title"><?php esc_html_e( 'Content', 'babypasa-order-emails' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="bp-shop-name"><?php esc_html_e( 'Shop / legal name', 'babypasa-order-emails' ); ?></label></th>
				<td><input name="bp_invoice_pdf[shop_name]" id="bp-shop-name" type="text" class="regular-text" value="<?php echo esc_attr( $settings['shop_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-shop-address"><?php esc_html_e( 'Address block', 'babypasa-order-emails' ); ?></label></th>
				<td><textarea name="bp_invoice_pdf[shop_address]" id="bp-shop-address" rows="3" class="large-text"><?php echo esc_textarea( $settings['shop_address'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-shop-contact"><?php esc_html_e( 'Phone / email', 'babypasa-order-emails' ); ?></label></th>
				<td><textarea name="bp_invoice_pdf[shop_contact]" id="bp-shop-contact" rows="2" class="large-text"><?php echo esc_textarea( $settings['shop_contact'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-reg"><?php esc_html_e( 'VAT / PAN / reg. number', 'babypasa-order-emails' ); ?></label></th>
				<td><input name="bp_invoice_pdf[shop_reg_number]" id="bp-reg" type="text" class="regular-text" value="<?php echo esc_attr( $settings['shop_reg_number'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-title"><?php esc_html_e( 'Invoice title', 'babypasa-order-emails' ); ?></label></th>
				<td><input name="bp_invoice_pdf[invoice_title]" id="bp-title" type="text" class="regular-text" value="<?php echo esc_attr( $settings['invoice_title'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-footer"><?php esc_html_e( 'Footer text', 'babypasa-order-emails' ); ?></label></th>
				<td><input name="bp_invoice_pdf[footer_text]" id="bp-footer" type="text" class="large-text" value="<?php echo esc_attr( $settings['footer_text'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Terms & notes', 'babypasa-order-emails' ); ?></th>
				<td>
					<?php
					wp_editor(
						$settings['terms'],
						'bp_invoice_pdf_terms',
						array(
							'textarea_name' => 'bp_invoice_pdf[terms]',
							'media_buttons' => false,
							'textarea_rows' => 5,
							'teeny'         => true,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Shown at the bottom of the invoice (returns policy, thank-you note, etc.).', 'babypasa-order-emails' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Layout', 'babypasa-order-emails' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Logo', 'babypasa-order-emails' ); ?></th>
				<td>
					<input type="hidden" name="bp_invoice_pdf[logo_id]" id="bp-logo-id" value="<?php echo esc_attr( (string) $settings['logo_id'] ); ?>" />
					<img id="bp-logo-preview" src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-height:60px;<?php echo $logo_url ? '' : 'display:none;'; ?>" /><br />
					<button type="button" class="button" id="bp-logo-select"><?php esc_html_e( 'Select / upload logo', 'babypasa-order-emails' ); ?></button>
					<button type="button" class="button-link" id="bp-logo-remove" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'babypasa-order-emails' ); ?></button>
					<p class="description"><?php esc_html_e( 'Defaults to the Baby Pasa email logo when none is selected.', 'babypasa-order-emails' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-logo-pos"><?php esc_html_e( 'Logo position', 'babypasa-order-emails' ); ?></label></th>
				<td>
					<select name="bp_invoice_pdf[logo_position]" id="bp-logo-pos">
						<?php foreach ( array( 'left' => __( 'Left', 'babypasa-order-emails' ), 'center' => __( 'Center', 'babypasa-order-emails' ), 'right' => __( 'Right', 'babypasa-order-emails' ) ) as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['logo_position'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-accent"><?php esc_html_e( 'Accent colour', 'babypasa-order-emails' ); ?></label></th>
				<td><input name="bp_invoice_pdf[accent_color]" id="bp-accent" type="text" class="bp-color-field" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" data-default-color="#ec4899" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-paper"><?php esc_html_e( 'Paper size', 'babypasa-order-emails' ); ?></label></th>
				<td>
					<select name="bp_invoice_pdf[paper_size]" id="bp-paper">
						<?php foreach ( array( 'A4' => 'A4', 'Letter' => 'Letter' ) as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['paper_size'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bp-font"><?php esc_html_e( 'Font size', 'babypasa-order-emails' ); ?></label></th>
				<td>
					<select name="bp_invoice_pdf[font_size]" id="bp-font">
						<?php foreach ( array( 'small' => __( 'Small', 'babypasa-order-emails' ), 'normal' => __( 'Normal', 'babypasa-order-emails' ), 'large' => __( 'Large', 'babypasa-order-emails' ) ) as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $settings['font_size'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sections', 'babypasa-order-emails' ); ?></th>
				<td>
					<?php
					$bp_toggles = array(
						'show_sku'              => __( 'Show SKU column', 'babypasa-order-emails' ),
						'show_shipping_address' => __( 'Show shipping address block', 'babypasa-order-emails' ),
						'show_payment_method'   => __( 'Show payment method line', 'babypasa-order-emails' ),
						'show_terms'            => __( 'Show terms & notes section', 'babypasa-order-emails' ),
					);
					foreach ( $bp_toggles as $key => $label ) :
						?>
						<label style="display:block;margin-bottom:4px;">
							<input type="checkbox" name="bp_invoice_pdf[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<?php if ( $can_raw ) : ?>
			<h2 class="title"><?php esc_html_e( 'Advanced: raw PDF template', 'babypasa-order-emails' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Full control over the PDF HTML. Leave empty to use the default template with the structured settings above. Use the merge tags below; unknown tags render empty.', 'babypasa-order-emails' ); ?>
			</p>
			<textarea name="bp_invoice_pdf_template" id="bp-raw-template" rows="18" class="large-text code" spellcheck="false"><?php echo esc_textarea( $raw_template ); ?></textarea>
			<p class="description">
				<strong><?php esc_html_e( 'Merge tags:', 'babypasa-order-emails' ); ?></strong>
				<code>{{invoice_title}}</code> <code>{{order_number}}</code> <code>{{order_date}}</code> <code>{{invoice_date}}</code>
				<code>{{shop_logo}}</code> <code>{{shop_name}}</code> <code>{{shop_address}}</code> <code>{{shop_contact}}</code> <code>{{shop_reg_number}}</code>
				<code>{{billing_name}}</code> <code>{{billing_address}}</code> <code>{{billing_phone}}</code> <code>{{billing_alt_phone}}</code> <code>{{billing_email}}</code>
				<code>{{shipping_address}}</code> <code>{{shipping_address_block}}</code> <code>{{line_items_table}}</code> <code>{{totals_table}}</code>
				<code>{{subtotal}}</code> <code>{{discount_total}}</code> <code>{{shipping_total}}</code> <code>{{order_total}}</code> <code>{{payment_method}}</code>
				<code>{{terms}}</code> <code>{{terms_block}}</code> <code>{{footer_text}}</code> <code>{{accent_color}}</code> <code>{{base_font_size}}</code> <code>{{logo_align}}</code>
			</p>
		<?php endif; ?>

		<p class="submit">
			<?php submit_button( __( 'Save changes', 'babypasa-order-emails' ), 'primary', 'submit', false ); ?>
			<button type="button" class="button" id="bp-pdf-preview"><?php esc_html_e( 'Preview PDF', 'babypasa-order-emails' ); ?></button>
		</p>
	</form>

	<hr />
	<h2 class="title"><?php esc_html_e( 'Reset', 'babypasa-order-emails' ); ?></h2>
	<div class="bp-invoice-pdf-resets">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bp-reset-form" style="display:inline-block;margin-right:10px;">
			<?php wp_nonce_field( 'bp_invoice_pdf_reset' ); ?>
			<input type="hidden" name="action" value="bp_invoice_pdf_reset" />
			<input type="hidden" name="reset_target" value="settings" />
			<button type="submit" class="button bp-reset-btn"><?php esc_html_e( 'Reset structured settings', 'babypasa-order-emails' ); ?></button>
		</form>
		<?php if ( $can_raw ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bp-reset-form" style="display:inline-block;">
				<?php wp_nonce_field( 'bp_invoice_pdf_reset' ); ?>
				<input type="hidden" name="action" value="bp_invoice_pdf_reset" />
				<input type="hidden" name="reset_target" value="template" />
				<button type="submit" class="button bp-reset-btn"><?php esc_html_e( 'Reset raw template to default', 'babypasa-order-emails' ); ?></button>
			</form>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Structured settings and the raw template reset independently.', 'babypasa-order-emails' ); ?></p>
	</div>
</div>

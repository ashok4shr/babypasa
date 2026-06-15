<?php
/**
 * Admin view: Email Templates — HTML code editor + live preview.
 */

defined( 'ABSPATH' ) || exit;

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'welcome'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_tab = in_array( $active_tab, array( 'welcome', 'newsletter' ), true ) ? $active_tab : 'welcome';

$welcome_tpl    = \BabypasaNewsletter\Includes\Email::get_template( 'bpnl_template_welcome' );
$newsletter_tpl = \BabypasaNewsletter\Includes\Email::get_template( 'bpnl_template_newsletter' );

$active_count = \BabypasaNewsletter\Includes\Subscriber::count( array( 'status' => 'active' ) );
$all_active   = \BabypasaNewsletter\Includes\Subscriber::get_active_subscribers();
?>
<div class="wrap bpnl-wrap">
	<h1><?php esc_html_e( 'Email Templates', 'babypasa-newsletter' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'welcome', menu_page_url( 'bpnl-templates', false ) ) ); ?>"
		   class="nav-tab <?php echo 'welcome' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Welcome Email', 'babypasa-newsletter' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'newsletter', menu_page_url( 'bpnl-templates', false ) ) ); ?>"
		   class="nav-tab <?php echo 'newsletter' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Newsletter', 'babypasa-newsletter' ); ?>
		</a>
	</nav>

	<?php if ( 'welcome' === $active_tab ) : ?>

	<div class="bpnl-tab-content" id="tab-welcome">
		<form method="post" action="">
			<?php wp_nonce_field( 'bpnl_save_template', 'bpnl_template_nonce' ); ?>
			<input type="hidden" name="bpnl_tab" value="welcome">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpnl_subject_welcome"><?php esc_html_e( 'Subject', 'babypasa-newsletter' ); ?></label></th>
					<td>
						<input type="text" id="bpnl_subject_welcome" name="bpnl_subject"
						       value="<?php echo esc_attr( $welcome_tpl['subject'] ); ?>"
						       class="large-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpnl_reply_to_welcome"><?php esc_html_e( 'Reply-To', 'babypasa-newsletter' ); ?></label></th>
					<td>
						<input type="email" id="bpnl_reply_to_welcome" name="bpnl_reply_to"
						       value="<?php echo esc_attr( $welcome_tpl['reply_to'] ); ?>"
						       class="regular-text">
					</td>
				</tr>
			</table>

			<div class="bpnl-token-box">
				<strong><?php esc_html_e( 'Available tokens:', 'babypasa-newsletter' ); ?></strong>
				<code>{{subscriber_email}}</code>
				<code>{{unsubscribe_link}}</code>
				<code>{{site_name}}</code>
				<code>{{site_logo}}</code>
				<code>{{latest_products}}</code>
				<code>{{shop_url}}</code>
				<code>{{home_url}}</code>
			</div>

			<label class="bpnl-editor-label"><?php esc_html_e( 'Body', 'babypasa-newsletter' ); ?></label>

			<div class="bpnl-editor-wrap">
				<div class="bpnl-pane bpnl-code-pane">
					<div class="bpnl-pane-header">
						<span class="bpnl-pane-label">HTML</span>
					</div>
					<textarea
						name="bpnl_body"
						id="bpnl_body_welcome"
						class="bpnl-html-editor"
						data-preview="bpnl_preview_welcome"
						spellcheck="false"
					><?php echo esc_textarea( $welcome_tpl['body'] ); ?></textarea>
				</div>
				<div class="bpnl-pane bpnl-preview-pane">
					<div class="bpnl-pane-header">
						<span class="bpnl-pane-label"><?php esc_html_e( 'Preview', 'babypasa-newsletter' ); ?></span>
						<span class="bpnl-preview-note"><?php esc_html_e( 'tokens replaced with sample values', 'babypasa-newsletter' ); ?></span>
					</div>
					<iframe
						id="bpnl_preview_welcome"
						class="bpnl-preview-iframe"
						sandbox="allow-same-origin"
						title="<?php esc_attr_e( 'Email preview', 'babypasa-newsletter' ); ?>"
					></iframe>
				</div>
			</div>

			<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
				<?php submit_button( __( 'Save Welcome Template', 'babypasa-newsletter' ), 'primary', 'submit', false ); ?>
				<button type="submit" name="bpnl_reset_template" value="welcome" class="button button-secondary"
				        onclick="return confirm('<?php esc_attr_e( 'This will overwrite your current template with the built-in default. Continue?', 'babypasa-newsletter' ); ?>');">
					<?php esc_html_e( 'Restore Default Template', 'babypasa-newsletter' ); ?>
				</button>
			</div>
		</form>
	</div>

	<?php else : ?>

	<div class="bpnl-tab-content" id="tab-newsletter">
		<form method="post" action="" id="bpnl-template-save-form">
			<?php wp_nonce_field( 'bpnl_save_template', 'bpnl_template_nonce' ); ?>
			<input type="hidden" name="bpnl_tab" value="newsletter">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpnl_subject_newsletter"><?php esc_html_e( 'Subject', 'babypasa-newsletter' ); ?></label></th>
					<td>
						<input type="text" id="bpnl_subject_newsletter" name="bpnl_subject"
						       value="<?php echo esc_attr( $newsletter_tpl['subject'] ); ?>"
						       class="large-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpnl_reply_to_newsletter"><?php esc_html_e( 'Reply-To', 'babypasa-newsletter' ); ?></label></th>
					<td>
						<input type="email" id="bpnl_reply_to_newsletter" name="bpnl_reply_to"
						       value="<?php echo esc_attr( $newsletter_tpl['reply_to'] ); ?>"
						       class="regular-text">
					</td>
				</tr>
			</table>

			<div class="bpnl-token-box">
				<strong><?php esc_html_e( 'Available tokens:', 'babypasa-newsletter' ); ?></strong>
				<code>{{subscriber_email}}</code>
				<code>{{unsubscribe_link}}</code>
				<code>{{site_name}}</code>
			</div>

			<label class="bpnl-editor-label"><?php esc_html_e( 'Body', 'babypasa-newsletter' ); ?></label>

			<div class="bpnl-editor-wrap">
				<div class="bpnl-pane bpnl-code-pane">
					<div class="bpnl-pane-header">
						<span class="bpnl-pane-label">HTML</span>
					</div>
					<textarea
						name="bpnl_body"
						id="bpnl_body_newsletter"
						class="bpnl-html-editor"
						data-preview="bpnl_preview_newsletter"
						spellcheck="false"
					><?php echo esc_textarea( $newsletter_tpl['body'] ); ?></textarea>
				</div>
				<div class="bpnl-pane bpnl-preview-pane">
					<div class="bpnl-pane-header">
						<span class="bpnl-pane-label"><?php esc_html_e( 'Preview', 'babypasa-newsletter' ); ?></span>
						<span class="bpnl-preview-note"><?php esc_html_e( 'tokens replaced with sample values', 'babypasa-newsletter' ); ?></span>
					</div>
					<iframe
						id="bpnl_preview_newsletter"
						class="bpnl-preview-iframe"
						sandbox="allow-same-origin"
						title="<?php esc_attr_e( 'Email preview', 'babypasa-newsletter' ); ?>"
					></iframe>
				</div>
			</div>

			<?php submit_button( __( 'Save Newsletter Template', 'babypasa-newsletter' ), 'secondary', 'bpnl_save_tpl' ); ?>
		</form>

		<hr>

		<div class="bpnl-send-section">
			<h2><?php esc_html_e( 'Send Newsletter', 'babypasa-newsletter' ); ?></h2>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Recipients', 'babypasa-newsletter' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="bpnl_recipients_type" value="all" checked>
								<?php
								printf(
									/* translators: %d: active subscriber count */
									esc_html__( 'All active subscribers (%d)', 'babypasa-newsletter' ),
									(int) $active_count
								);
								?>
							</label>
							<br><br>
							<label>
								<input type="radio" name="bpnl_recipients_type" value="select">
								<?php esc_html_e( 'Select subscribers', 'babypasa-newsletter' ); ?>
							</label>
							<div id="bpnl-subscriber-select-wrap" style="display:none; margin-top:10px;">
								<select id="bpnl-subscriber-select" multiple size="10" style="min-width:320px; height:200px;">
									<?php foreach ( $all_active as $sub ) : ?>
										<option value="<?php echo esc_attr( (string) $sub->id ); ?>">
											<?php echo esc_html( $sub->email ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Hold Ctrl / Cmd to select multiple.', 'babypasa-newsletter' ); ?></p>
							</div>
						</fieldset>
					</td>
				</tr>
			</table>

			<p>
				<button type="button" id="bpnl-send-newsletter" class="button button-primary">
					<?php esc_html_e( 'Send Now', 'babypasa-newsletter' ); ?>
				</button>
				<span id="bpnl-send-spinner" class="spinner" style="float:none; visibility:hidden; margin-top:0;"></span>
			</p>

			<div id="bpnl-send-result" class="bpnl-send-result" style="display:none;"></div>
		</div>
	</div>

	<?php endif; ?>
</div>

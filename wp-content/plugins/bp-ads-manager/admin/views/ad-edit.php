<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Add / Edit ad form.
 *
 * @var object|null $ad  Existing ad row (null when creating a new ad).
 */

$is_edit      = ! empty( $ad );
$ad_id        = $is_edit ? absint( $ad->id )                            : 0;
$title        = $is_edit ? esc_attr( $ad->title )                       : '';
$type         = $is_edit ? $ad->type                                    : 'popup';
$content_mode = $is_edit ? ( $ad->content_mode ?: 'html' )              : 'html';
$content_val  = $is_edit ? $ad->content                                 : '';
$image_id     = $is_edit ? absint( $ad->image_id ?? 0 )                 : 0;
$image_url    = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
$active       = $is_edit ? (int) $ad->active                            : 1;
$device       = $is_edit ? $ad->device                                  : 'all';
$delay        = $is_edit ? absint( $ad->popup_delay )                   : 0;
$frequency    = $is_edit ? $ad->frequency                               : 'once';
$link_url     = $is_edit ? esc_url( $ad->link_url ?? '' )               : '';
$sort_order   = $is_edit ? absint( $ad->sort_order ?? 0 )               : 0;
$start_date   = $is_edit ? esc_attr( $ad->start_date ?? '' )            : '';
$end_date     = $is_edit ? esc_attr( $ad->end_date ?? '' )              : '';

// Placement (banners only). Parse stored slugs so legacy rows default to
// Trending only; new ads start with no placement selected.
$placement_choices  = BP_Ads_DB::get_placement_choices();
$placement_selected = $is_edit
	? BP_Ads_DB::parse_placement( isset( $ad->placement ) ? $ad->placement : '' )
	: array();

$page_title = $is_edit
	? __( 'Edit Ad', 'bp-ads-manager' )
	: __( 'Add New Ad', 'bp-ads-manager' );
?>
<div class="wrap bp-ads-wrap">

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-ads-manager' ) ); ?>" class="bp-back-link">
		&larr; <?php esc_html_e( 'All Ads', 'bp-ads-manager' ); ?>
	</a>

	<h1><?php echo esc_html( $page_title ); ?></h1>
	<?php if ( $is_edit ) : ?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-ads-add-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Ad', 'bp-ads-manager' ); ?>
	</a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bp-ad-edit-form">
		<?php wp_nonce_field( 'bp_save_ad' ); ?>
		<input type="hidden" name="action"   value="bp_save_ad">
		<input type="hidden" name="bp_ad_id" value="<?php echo esc_attr( $ad_id ); ?>">

		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">

				<!-- Main column -->
				<div id="post-body-content">

					<!-- Ad Content (Image or HTML) -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Ad Content', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">

							<!-- Mode toggle -->
							<div class="bp-field-row bp-content-mode-toggle">
								<label class="bp-mode-radio">
									<input type="radio" name="bp_ad_content_mode" value="image" <?php checked( $content_mode, 'image' ); ?>>
									<?php esc_html_e( 'Image', 'bp-ads-manager' ); ?>
								</label>
								<label class="bp-mode-radio">
									<input type="radio" name="bp_ad_content_mode" value="html" <?php checked( $content_mode, 'html' ); ?>>
									<?php esc_html_e( 'HTML', 'bp-ads-manager' ); ?>
								</label>
							</div>

							<!-- Image mode fields -->
							<div id="bp-image-field" class="bp-field-row" <?php echo ( 'image' !== $content_mode ) ? 'style="display:none"' : ''; ?>>
								<div id="bp-image-preview-wrap">
									<img
										id="bp-ad-image-preview"
										src="<?php echo esc_url( $image_url ); ?>"
										alt=""
										style="max-width:100%;display:<?php echo $image_url ? 'block' : 'none'; ?>;margin-bottom:8px;border-radius:3px;"
									>
								</div>
								<input type="hidden" id="bp_ad_image_id" name="bp_ad_image_id" value="<?php echo esc_attr( $image_id ); ?>">
								<button type="button" id="bp-image-upload-btn" class="button">
									<?php echo $image_id ? esc_html__( 'Change Image', 'bp-ads-manager' ) : esc_html__( 'Select Image', 'bp-ads-manager' ); ?>
								</button>
								<button type="button" id="bp-image-remove-btn" class="button" style="margin-left:8px;<?php echo $image_id ? '' : 'display:none;'; ?>">
									<?php esc_html_e( 'Remove', 'bp-ads-manager' ); ?>
								</button>
								<p class="description" style="margin-top:8px;">
									<?php esc_html_e( 'Select an image from the media library. It will be rendered via wp_get_attachment_image().', 'bp-ads-manager' ); ?>
								</p>
							</div>

							<!-- HTML mode fields -->
							<div id="bp-html-field" class="bp-field-row" <?php echo ( 'html' !== $content_mode ) ? 'style="display:none"' : ''; ?>>
								<textarea
									id="bp_ad_content"
									name="bp_ad_content"
									rows="12"
									class="large-text code"
									placeholder="<?php esc_attr_e( 'Enter raw HTML…', 'bp-ads-manager' ); ?>"
								><?php echo esc_textarea( $content_val ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Raw HTML. Supported tags: img, a, div, span, p, strong, em, h1–h3, ul, ol, li, br, hr, iframe.', 'bp-ads-manager' ); ?>
								</p>
							</div>

						</div>
					</div>

				</div><!-- /post-body-content -->

				<!-- Sidebar column -->
				<div id="postbox-container-1" class="postbox-container">

					<!-- Publish box -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Publish', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<div class="bp-field-row">
								<label>
									<input
										type="checkbox"
										name="bp_ad_active"
										value="1"
										<?php checked( $active, 1 ); ?>
									>
									<?php esc_html_e( 'Active (visible on site)', 'bp-ads-manager' ); ?>
								</label>
							</div>
							<div class="bp-submit-row">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-ads-manager' ) ); ?>" class="button">
									<?php esc_html_e( 'Cancel', 'bp-ads-manager' ); ?>
								</a>
								<button type="submit" class="button button-primary">
									<?php echo $is_edit ? esc_html__( 'Update Ad', 'bp-ads-manager' ) : esc_html__( 'Save Ad', 'bp-ads-manager' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Schedule (display date range) -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Schedule', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<div class="bp-field-row">
								<label for="bp_ad_start_date">
									<strong><?php esc_html_e( 'Display From', 'bp-ads-manager' ); ?></strong>
								</label>
								<input
									type="date"
									id="bp_ad_start_date"
									name="bp_ad_start_date"
									value="<?php echo $start_date; ?>"
									class="widefat"
								>
							</div>
							<div class="bp-field-row">
								<label for="bp_ad_end_date">
									<strong><?php esc_html_e( 'Display Until', 'bp-ads-manager' ); ?></strong>
								</label>
								<input
									type="date"
									id="bp_ad_end_date"
									name="bp_ad_end_date"
									value="<?php echo $end_date; ?>"
									class="widefat"
								>
							</div>
							<p class="description">
								<?php esc_html_e( 'Optional. The ad only shows on/after "Display From" and on/before "Display Until". Leave both blank to always show.', 'bp-ads-manager' ); ?>
							</p>
						</div>
					</div>

					<!-- Internal Label -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Internal Label', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<div class="bp-field-row">
								<input
									type="text"
									id="bp_ad_title"
									name="bp_ad_title"
									value="<?php echo $title; ?>"
									class="widefat"
									placeholder="<?php esc_attr_e( 'Optional name for the ads list…', 'bp-ads-manager' ); ?>"
								>
								<p class="description"><?php esc_html_e( 'Admin-only label. Not shown on the site.', 'bp-ads-manager' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Ad Type -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Ad Type', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<div class="bp-field-row">
								<label>
									<input type="radio" name="bp_ad_type" value="popup" <?php checked( $type, 'popup' ); ?>>
									<?php esc_html_e( 'Popup', 'bp-ads-manager' ); ?>
								</label>
							</div>
							<div class="bp-field-row">
								<label>
									<input type="radio" name="bp_ad_type" value="banner" <?php checked( $type, 'banner' ); ?>>
									<?php esc_html_e( 'Banner', 'bp-ads-manager' ); ?>
								</label>
							</div>
						</div>
					</div>

					<!-- Device Target -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Device Target', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<select name="bp_ad_device" id="bp_ad_device" class="widefat">
								<option value="all"     <?php selected( $device, 'all' ); ?>><?php esc_html_e( 'All Devices', 'bp-ads-manager' ); ?></option>
								<option value="mobile"  <?php selected( $device, 'mobile' ); ?>><?php esc_html_e( 'Mobile Only', 'bp-ads-manager' ); ?></option>
								<option value="desktop" <?php selected( $device, 'desktop' ); ?>><?php esc_html_e( 'Desktop Only', 'bp-ads-manager' ); ?></option>
							</select>
						</div>
					</div>

					<!-- Ad Link URL -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Ad Link URL', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<div class="bp-field-row">
								<input
									type="url"
									id="bp_ad_link_url"
									name="bp_ad_link_url"
									value="<?php echo $link_url; ?>"
									class="widefat"
									placeholder="https://"
								>
								<p class="description">
									<?php esc_html_e( 'Optional. When set, clicking the ad redirects the visitor to this URL.', 'bp-ads-manager' ); ?>
								</p>
							</div>
						</div>
					</div>

					<!-- Popup-only settings -->
					<div class="postbox bp-popup-fields" id="bp-popup-settings">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Popup Settings', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<div class="bp-field-row">
								<label for="bp_ad_sort_order">
									<strong><?php esc_html_e( 'Display Order', 'bp-ads-manager' ); ?></strong>
								</label>
								<input
									type="number"
									id="bp_ad_sort_order"
									name="bp_ad_sort_order"
									value="<?php echo esc_attr( $sort_order ); ?>"
									min="0"
									class="small-text"
								>
								<p class="description">
									<?php esc_html_e( 'Lower numbers show first. If multiple popups are active, they appear in this order — each one waits for the previous to be closed.', 'bp-ads-manager' ); ?>
								</p>
							</div>
							<div class="bp-field-row">
								<label for="bp_ad_popup_delay">
									<strong><?php esc_html_e( 'Popup Delay (seconds)', 'bp-ads-manager' ); ?></strong>
								</label>
								<input
									type="number"
									id="bp_ad_popup_delay"
									name="bp_ad_popup_delay"
									value="<?php echo esc_attr( $delay ); ?>"
									min="0"
									max="3600"
									class="small-text"
								>
							</div>
							<div class="bp-field-row">
								<strong><?php esc_html_e( 'Show Frequency', 'bp-ads-manager' ); ?></strong>
								<br><br>
								<label>
									<input type="radio" name="bp_ad_frequency" value="once" <?php checked( $frequency, 'once' ); ?>>
									<?php esc_html_e( 'Once per session (cookie)', 'bp-ads-manager' ); ?>
								</label>
								<br>
								<label>
									<input type="radio" name="bp_ad_frequency" value="always" <?php checked( $frequency, 'always' ); ?>>
									<?php esc_html_e( 'Every visit', 'bp-ads-manager' ); ?>
								</label>
							</div>
						</div>
					</div>

					<!-- Banner-only settings -->
					<div class="postbox bp-banner-fields" id="bp-banner-settings">
						<div class="postbox-header">
							<h2><?php esc_html_e( 'Banner Settings', 'bp-ads-manager' ); ?></h2>
						</div>
						<div class="inside">
							<div class="bp-field-row">
								<strong><?php esc_html_e( 'Placement', 'bp-ads-manager' ); ?></strong>
								<p class="description" style="margin-bottom:8px;">
									<?php esc_html_e( 'Choose one or more homepage sections to display this banner after.', 'bp-ads-manager' ); ?>
								</p>
								<?php foreach ( $placement_choices as $slug => $label ) : ?>
									<label class="bp-placement-option" style="display:block;margin-bottom:6px;">
										<input
											type="checkbox"
											name="bp_ad_placement[]"
											value="<?php echo esc_attr( $slug ); ?>"
											<?php checked( in_array( $slug, $placement_selected, true ), true ); ?>
										>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description">
									<em><?php esc_html_e( 'If none are selected, the banner shows after Trending Products by default.', 'bp-ads-manager' ); ?></em>
								</p>
							</div>
						</div>
					</div>

				</div><!-- /postbox-container-1 -->
			</div><!-- /post-body -->
		</div><!-- /poststuff -->
	</form>
</div>

<script>
(function () {
	'use strict';

	/* ── Ad Type toggle (popup vs banner settings) ─────────────────────────── */
	function toggleTypeFields() {
		var selected  = document.querySelector('input[name="bp_ad_type"]:checked');
		var type      = selected ? selected.value : 'popup';
		var popupBox  = document.getElementById('bp-popup-settings');
		var bannerBox = document.getElementById('bp-banner-settings');

		if (popupBox)  popupBox.style.display  = (type === 'popup')  ? '' : 'none';
		if (bannerBox) bannerBox.style.display = (type === 'banner') ? '' : 'none';
	}

	toggleTypeFields();
	document.querySelectorAll('input[name="bp_ad_type"]').forEach(function (radio) {
		radio.addEventListener('change', toggleTypeFields);
	});

	/* ── Content mode toggle (image vs html) ───────────────────────────────── */
	function toggleContentMode() {
		var selected   = document.querySelector('input[name="bp_ad_content_mode"]:checked');
		var mode       = selected ? selected.value : 'html';
		var imageField = document.getElementById('bp-image-field');
		var htmlField  = document.getElementById('bp-html-field');

		if (imageField) imageField.style.display = (mode === 'image') ? '' : 'none';
		if (htmlField)  htmlField.style.display  = (mode === 'html')  ? '' : 'none';
	}

	toggleContentMode();
	document.querySelectorAll('input[name="bp_ad_content_mode"]').forEach(function (radio) {
		radio.addEventListener('change', toggleContentMode);
	});

	/* ── Media uploader ────────────────────────────────────────────────────── */
	var mediaFrame;
	var uploadBtn  = document.getElementById('bp-image-upload-btn');
	var removeBtn  = document.getElementById('bp-image-remove-btn');
	var imageInput = document.getElementById('bp_ad_image_id');
	var preview    = document.getElementById('bp-ad-image-preview');

	if (uploadBtn) {
		uploadBtn.addEventListener('click', function (e) {
			e.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title:    '<?php echo esc_js( __( 'Select Ad Image', 'bp-ads-manager' ) ); ?>',
				button:   { text: '<?php echo esc_js( __( 'Use this image', 'bp-ads-manager' ) ); ?>' },
				multiple: false,
				library:  { type: 'image' }
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				imageInput.value        = attachment.id;
				preview.src             = attachment.url;
				preview.style.display   = 'block';
				removeBtn.style.display = 'inline-block';
				uploadBtn.textContent   = '<?php echo esc_js( __( 'Change Image', 'bp-ads-manager' ) ); ?>';
			});

			mediaFrame.open();
		});
	}

	if (removeBtn) {
		removeBtn.addEventListener('click', function (e) {
			e.preventDefault();
			imageInput.value        = 0;
			preview.src             = '';
			preview.style.display   = 'none';
			removeBtn.style.display = 'none';
			uploadBtn.textContent   = '<?php echo esc_js( __( 'Select Image', 'bp-ads-manager' ) ); ?>';
		});
	}
}());
</script>

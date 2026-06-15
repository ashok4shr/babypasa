<?php
/**
 * Custom Admin Panel for Homepage Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add the menu page
function bp_homepage_settings_menu() {
	add_options_page(
		'Homepage Settings',
		'Homepage Settings',
		'manage_options',
		'bp-homepage-settings',
		'bp_homepage_settings_page'
	);
}
add_action( 'admin_menu', 'bp_homepage_settings_menu' );

// Register the settings
function bp_homepage_register_settings() {
	// Register dynamic Hero slides array
	register_setting( 'bp_homepage_settings_group', 'bp_hero_slides' );

	// Register section titles
	register_setting( 'bp_homepage_settings_group', 'bp_section_1_title' ); // Daily essentials
	register_setting( 'bp_homepage_settings_group', 'bp_section_2_title' ); // New Products
	register_setting( 'bp_homepage_settings_group', 'bp_section_3_title' ); // Trending Products

	// === BABYPASA PRODUCT PICKER: START ===
	// Register per-section ordered product selections (array of published product IDs).
	for ( $s = 1; $s <= 3; $s++ ) {
		register_setting(
			'bp_homepage_settings_group',
			'bp_section_' . $s . '_products',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'bp_sanitize_product_ids',
				'default'           => array(),
			)
		);
	}
	// === BABYPASA PRODUCT PICKER: END ===

	// Register Banner settings
	for ( $i = 1; $i <= 3; $i++ ) {
		register_setting( 'bp_homepage_settings_group', 'bp_banner_title_' . $i );
		register_setting( 'bp_homepage_settings_group', 'bp_banner_desc_' . $i );
	}
}
add_action( 'admin_init', 'bp_homepage_register_settings' );

// === BABYPASA PRODUCT PICKER: START ===
/**
 * Sanitize a section's product selection.
 *
 * Accepts a comma-separated string (from the hidden picker input) or an array,
 * keeps only positive integers that resolve to a *published* WooCommerce product,
 * de-dupes while preserving order, and returns a clean array of ints.
 *
 * @param mixed $value Raw submitted value.
 * @return int[] Ordered, validated product IDs (empty array => frontend falls back to auto query).
 */
function bp_sanitize_product_ids( $value ) {
	if ( is_string( $value ) ) {
		$value = ( '' === trim( $value ) ) ? array() : explode( ',', $value );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}

	$clean = array();
	foreach ( $value as $raw ) {
		$id = absint( $raw );
		if ( $id <= 0 || in_array( $id, $clean, true ) ) {
			continue;
		}
		$product = wc_get_product( $id );
		if ( $product && 'publish' === get_post_status( $id ) ) {
			$clean[] = $id;
		}
	}
	return $clean;
}

/**
 * Bust all cached homepage slider transients (mirrors the flush in functions.php).
 * Hooked to saves of the picker options so the frontend refreshes immediately.
 */
function bp_flush_slider_cache_for_picker() {
	global $wpdb;
	$wpdb->query(
		"DELETE FROM {$wpdb->options}
		 WHERE option_name LIKE '_transient_bp_slider_%'
		    OR option_name LIKE '_transient_timeout_bp_slider_%'"
	);
}

/**
 * Record which section(s) changed (for the admin notice) and flush the slider cache.
 * Fired on both add_option_* (first save) and update_option_* (subsequent changes).
 */
function bp_picker_option_changed( $section ) {
	$labels = array(
		1 => 'Daily Essentials',
		2 => 'New Products',
		3 => 'Trending Products',
	);
	if ( isset( $labels[ $section ] ) ) {
		$changed   = get_transient( 'bp_picker_changed_sections' );
		$changed   = is_array( $changed ) ? $changed : array();
		$changed[] = $labels[ $section ];
		set_transient( 'bp_picker_changed_sections', array_unique( $changed ), 60 );
	}
	bp_flush_slider_cache_for_picker();
}

for ( $s = 1; $s <= 3; $s++ ) {
	add_action( 'update_option_bp_section_' . $s . '_products', function () use ( $s ) {
		bp_picker_option_changed( $s );
	} );
	add_action( 'add_option_bp_section_' . $s . '_products', function () use ( $s ) {
		bp_picker_option_changed( $s );
	} );
}

/**
 * Show a success notice on the settings screen listing the updated sections.
 */
function bp_picker_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'settings_page_bp-homepage-settings' !== $screen->id ) {
		return;
	}
	$changed = get_transient( 'bp_picker_changed_sections' );
	if ( ! empty( $changed ) && is_array( $changed ) ) {
		delete_transient( 'bp_picker_changed_sections' );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( 'Homepage product selection updated for: ' . implode( ', ', $changed ) . '.' )
		);
	}
}
add_action( 'admin_notices', 'bp_picker_admin_notice' );
// === BABYPASA PRODUCT PICKER: END ===

// Enqueue media uploader script
function bp_homepage_admin_scripts( $hook ) {
	if ( 'settings_page_bp-homepage-settings' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	// === BABYPASA PRODUCT PICKER: START ===
	// WooCommerce's enhanced (Select2) product search + jQuery UI Sortable for the picker UI.
	wp_enqueue_script( 'wc-enhanced-select' );
	wp_enqueue_style( 'woocommerce_admin_styles' );
	wp_enqueue_script( 'jquery-ui-sortable' );
	$picker_deps = array( 'jquery', 'wc-enhanced-select', 'jquery-ui-sortable' );
	// === BABYPASA PRODUCT PICKER: END ===
	wp_enqueue_script( 'bp-admin-media', get_stylesheet_directory_uri() . '/inc/admin-media.js', $picker_deps, filemtime( get_stylesheet_directory() . '/inc/admin-media.js' ), true );
}
add_action( 'admin_enqueue_scripts', 'bp_homepage_admin_scripts' );

// Build the Settings Page HTML
function bp_homepage_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Homepage Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'bp_homepage_settings_group' ); ?>
			<?php do_settings_sections( 'bp_homepage_settings_group' ); ?>
			
			<hr>
			
			<h2>Hero Carousel Settings</h2>
			<p>Configure dynamic hero slides. Drag and drop is not supported natively, but you can add as many as you want.</p>
			
			<div id="bp-hero-repeater-container" style="background:#f9f9f9; border:1px solid #ccc; padding:20px; max-width: 800px;">
				<?php 
				$slides = get_option( 'bp_hero_slides', array() );
				if ( ! is_array( $slides ) ) $slides = array();
				
				// Template for JS to clone
				?>
				<div class="bp-slide-row-template" style="display:none; padding:15px; background:#fff; border:1px solid #eee; margin-bottom:15px; position:relative;">
					<div style="float:right;">
						<button type="button" class="button bp-remove-slide" style="color:red; border-color:red;">Remove</button>
					</div>
					<div class="bp-image-preview-container" style="margin-bottom:10px;">
						<img src="" style="max-height: 120px; display:none; max-width: 100%; border:1px solid #ddd; padding:3px;">
					</div>
					<p>
						<label><strong>Image URL:</strong></label><br>
						<input type="text" name="" class="regular-text bp-image-url" placeholder="Image URL..." />
						<input type="button" class="button bp-upload-button" value="Upload/Select Image" />
					</p>
					<p>
						<label><strong>Link URL:</strong></label><br>
						<input type="url" name="" class="regular-text bp-link-url" placeholder="Link URL..." />
					</p>
				</div>

				<div id="bp-hero-slides-wrapper">
					<?php foreach ( $slides as $index => $slide ) : 
						$img = isset($slide['img']) ? $slide['img'] : '';
						$link = isset($slide['link']) ? $slide['link'] : '';
					?>
					<div class="bp-slide-row" style="padding:15px; background:#fff; border:1px solid #eee; margin-bottom:15px; position:relative;">
						<div style="float:right;">
							<button type="button" class="button bp-remove-slide" style="color:red; border-color:red;">Remove</button>
						</div>
						<div class="bp-image-preview-container" style="margin-bottom:10px;">
							<?php if ( ! empty($img) ) : ?>
								<img src="<?php echo esc_url($img); ?>" style="max-height: 120px; max-width: 100%; border:1px solid #ddd; padding:3px;">
							<?php else : ?>
								<img src="" style="max-height: 120px; display:none; max-width: 100%; border:1px solid #ddd; padding:3px;">
							<?php endif; ?>
						</div>
						<p>
							<label><strong>Image URL:</strong></label><br>
							<input type="text" name="bp_hero_slides[<?php echo $index; ?>][img]" value="<?php echo esc_attr($img); ?>" class="regular-text bp-image-url" placeholder="Image URL..." />
							<input type="button" class="button bp-upload-button" value="Upload/Select Image" />
						</p>
						<p>
							<label><strong>Link URL:</strong></label><br>
							<input type="url" name="bp_hero_slides[<?php echo $index; ?>][link]" value="<?php echo esc_url($link); ?>" class="regular-text bp-link-url" placeholder="Link URL..." />
						</p>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="button" id="bp-add-slide-btn" class="button button-primary">Add New Slide</button>
			</div>

			<hr>

			<?php // === BABYPASA PRODUCT PICKER: START === ?>
			<style>
				.bp-picker-block {
					max-width: 800px; margin-bottom: 20px; padding: 16px 20px;
					background: #fff; border: 1px solid #dcdcde; border-radius: 4px;
				}
				.bp-picker-block h3 { margin-top: 0; }
				.bp-picker-block .bp-picker-label { font-weight: 600; display: block; margin: 14px 0 4px; }
				.bp-product-search { width: 100%; max-width: 400px; }
				.bp-product-sortable { list-style: none; margin: 8px 0 0; padding: 0; }
				.bp-product-sortable li {
					display: flex; align-items: center; gap: 12px;
					padding: 8px 10px; margin-bottom: 6px;
					background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;
					cursor: grab;
				}
				.bp-product-sortable li.bp-sort-placeholder {
					border: 1px dashed #c3c4c7; background: #f0f0f1; height: 48px;
				}
				.bp-product-sortable li img,
				.bp-product-sortable li .bp-no-thumb {
					width: 40px; height: 40px; object-fit: cover;
					border: 1px solid #eee; border-radius: 3px; flex: 0 0 40px;
					background: #fff;
				}
				.bp-product-sortable li .bp-sort-name { flex: 1 1 auto; }
				.bp-product-sortable li .bp-remove-product {
					color: #b32d2e; text-decoration: none; font-size: 18px; line-height: 1;
					padding: 0 6px; cursor: pointer;
				}
				.bp-picker-empty { color: #646970; font-style: italic; margin: 8px 0 0; }
			</style>

			<h2>Homepage Sections</h2>
			<p>Set each slider's title and choose the products shown inside it. <strong>Leave the product list empty to use the automatic (tag / latest) selection.</strong></p>

			<?php
			$bp_picker_sections = array(
				1 => array( 'label' => 'Daily Essentials',  'default' => 'DAILY ESSENTIALS' ),
				2 => array( 'label' => 'New Products',       'default' => 'NEW PRODUCTS' ),
				3 => array( 'label' => 'Trending Products',  'default' => 'TRENDING PRODUCTS' ),
			);
			foreach ( $bp_picker_sections as $bp_si => $bp_meta ) :
				$bp_ids = get_option( 'bp_section_' . $bp_si . '_products', array() );
				$bp_ids = is_array( $bp_ids ) ? array_map( 'absint', $bp_ids ) : array();
			?>
				<div class="bp-picker-block" data-section="<?php echo esc_attr( $bp_si ); ?>">
					<h3><?php echo esc_html( $bp_meta['label'] ); ?></h3>

					<label class="bp-picker-label" for="bp_section_<?php echo esc_attr( $bp_si ); ?>_title">Section Title</label>
					<input type="text" id="bp_section_<?php echo esc_attr( $bp_si ); ?>_title" name="bp_section_<?php echo esc_attr( $bp_si ); ?>_title" value="<?php echo esc_attr( get_option( 'bp_section_' . $bp_si . '_title', $bp_meta['default'] ) ); ?>" class="regular-text" />

					<label class="bp-picker-label">Products</label>
					<select class="bp-product-search wc-product-search" multiple
						data-placeholder="Search for a product&hellip;"
						data-action="woocommerce_json_search_products"
						data-section="<?php echo esc_attr( $bp_si ); ?>"></select>

					<ul class="bp-product-sortable" data-section="<?php echo esc_attr( $bp_si ); ?>">
						<?php foreach ( $bp_ids as $bp_pid ) :
							$bp_product = wc_get_product( $bp_pid );
							if ( ! $bp_product || 'publish' !== get_post_status( $bp_pid ) ) {
								continue;
							}
							$bp_thumb = get_the_post_thumbnail_url( $bp_pid, 'thumbnail' );
						?>
							<li data-id="<?php echo esc_attr( $bp_pid ); ?>">
								<?php if ( $bp_thumb ) : ?>
									<img src="<?php echo esc_url( $bp_thumb ); ?>" alt="" />
								<?php else : ?>
									<span class="bp-no-thumb"></span>
								<?php endif; ?>
								<span class="bp-sort-name"><?php echo esc_html( $bp_product->get_name() ); ?></span>
								<a href="#" class="bp-remove-product" aria-label="Remove" title="Remove">&times;</a>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="bp-picker-empty"<?php echo empty( $bp_ids ) ? '' : ' style="display:none;"'; ?>>No products selected &mdash; using automatic selection.</p>

					<input type="hidden" name="bp_section_<?php echo esc_attr( $bp_si ); ?>_products"
						class="bp-picker-input"
						value="<?php echo esc_attr( implode( ',', $bp_ids ) ); ?>" />
				</div>
			<?php endforeach; ?>

			<hr>
			<?php // === BABYPASA PRODUCT PICKER: END === ?>

			<h2>Red Information Banner Items</h2>
			<table class="form-table">
				<?php for ( $i = 1; $i <= 3; $i++ ) : 
					$title_val = get_option( 'bp_banner_title_' . $i );
					$desc_val = get_option( 'bp_banner_desc_' . $i );
				?>
				<tr valign="top">
					<th scope="row">Feature <?php echo $i; ?></th>
					<td>
						<strong>Title:</strong><br>
						<input type="text" name="bp_banner_title_<?php echo $i; ?>" value="<?php echo esc_attr( $title_val ); ?>" class="regular-text" />
						<br><strong>Description:</strong><br>
						<textarea name="bp_banner_desc_<?php echo $i; ?>" rows="2" class="large-text"><?php echo esc_textarea( $desc_val ); ?></textarea>
					</td>
				</tr>
				<?php endfor; ?>
			</table>
			
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

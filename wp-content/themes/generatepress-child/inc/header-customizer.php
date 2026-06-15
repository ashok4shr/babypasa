<?php
/**
 * Header Customizer
 *
 * Adds a "BabyPasa Header" section to Appearance > Customize letting admins
 * change the top-bar text/visibility and the header colours, with live preview.
 *
 * Defaults match the original hardcoded values, so an un-customised site renders
 * identically. Values are read with get_theme_mod() and output as CSS custom
 * properties that override the defaults baked into header-style.css.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Default values — kept in one place so the Customizer, the front-end getters,
 * and the dynamic-CSS emitter all agree. Changing a default here changes it
 * everywhere.
 *
 * @return array
 */
function bp_header_defaults() {
	return array(
		'bp_topbar_enable'        => true,
		'bp_topbar_text'          => 'Welcome to BabyPasa – Weaving Joyful Moments Together!',
		'bp_topbar_bg'            => '#f7f7f7',
		'bp_topbar_text_color'    => '#666666',
		'bp_navbar_bg'            => '#FF2A61',
		'bp_navbar_link_color'       => '#ffffff',
		'bp_navbar_link_hover_color' => '', // empty => same as the link colour (no change on hover)
		'bp_navbar_link_hover_bg'    => '', // empty => keep the CSS fallback rgba(0,0,0,0.1)
		'bp_logo_width'           => '', // empty => automatic (keeps the 45px max-height default)
	);
}

/**
 * Map of colour settings => the CSS custom property they drive.
 * Used by the dynamic-CSS emitter and the live-preview script.
 *
 * @return array
 */
function bp_header_color_map() {
	return array(
		'bp_topbar_bg'            => '--bp-topbar-bg',
		'bp_topbar_text_color'    => '--bp-topbar-color',
		'bp_navbar_bg'               => '--bp-navbar-bg',
		'bp_navbar_link_color'       => '--bp-navbar-link',
		'bp_navbar_link_hover_color' => '--bp-navbar-link-hover',
		'bp_navbar_link_hover_bg'    => '--bp-navbar-link-hover-bg',
	);
}

/* ── Front-end getters used by header.php ──────────────────────────────── */

function bp_get_topbar_enabled() {
	$d = bp_header_defaults();
	return (bool) get_theme_mod( 'bp_topbar_enable', $d['bp_topbar_enable'] );
}

function bp_get_topbar_text() {
	$d = bp_header_defaults();
	return get_theme_mod( 'bp_topbar_text', $d['bp_topbar_text'] );
}

/* ── Sanitizers ────────────────────────────────────────────────────────── */

function bp_sanitize_checkbox( $value ) {
	return ( isset( $value ) && true === (bool) $value );
}

function bp_sanitize_logo_width( $value ) {
	// Empty => automatic sizing (no override).
	if ( '' === $value || null === $value ) {
		return '';
	}
	$value = absint( $value );
	if ( $value < 20 ) {
		$value = 20;
	}
	if ( $value > 600 ) {
		$value = 600;
	}
	return $value;
}

/* ── Register the Customizer section, settings and controls ────────────── */

function bp_header_customize_register( $wp_customize ) {
	$d = bp_header_defaults();

	$wp_customize->add_section( 'bp_header_section', array(
		'title'       => __( 'BabyPasa Header', 'generatepress-child' ),
		'description' => __( 'Customise the top bar and navigation header colours and text.', 'generatepress-child' ),
		'priority'    => 30,
	) );

	// — Top bar: show/hide —
	$wp_customize->add_setting( 'bp_topbar_enable', array(
		'default'           => $d['bp_topbar_enable'],
		'sanitize_callback' => 'bp_sanitize_checkbox',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bp_topbar_enable', array(
		'label'   => __( 'Show top bar', 'generatepress-child' ),
		'section' => 'bp_header_section',
		'type'    => 'checkbox',
	) );

	// — Top bar: text —
	$wp_customize->add_setting( 'bp_topbar_text', array(
		'default'           => $d['bp_topbar_text'],
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bp_topbar_text', array(
		'label'   => __( 'Top bar text', 'generatepress-child' ),
		'section' => 'bp_header_section',
		'type'    => 'text',
	) );

	// — Top bar: background colour —
	$wp_customize->add_setting( 'bp_topbar_bg', array(
		'default'           => $d['bp_topbar_bg'],
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'bp_topbar_bg', array(
		'label'   => __( 'Top bar background', 'generatepress-child' ),
		'section' => 'bp_header_section',
	) ) );

	// — Top bar: text colour —
	$wp_customize->add_setting( 'bp_topbar_text_color', array(
		'default'           => $d['bp_topbar_text_color'],
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'bp_topbar_text_color', array(
		'label'   => __( 'Top bar text colour', 'generatepress-child' ),
		'section' => 'bp_header_section',
	) ) );

	// — Nav bar: background colour —
	$wp_customize->add_setting( 'bp_navbar_bg', array(
		'default'           => $d['bp_navbar_bg'],
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'bp_navbar_bg', array(
		'label'       => __( 'Navigation bar background', 'generatepress-child' ),
		'description' => __( 'The main pink header bar.', 'generatepress-child' ),
		'section'     => 'bp_header_section',
	) ) );

	// — Nav bar: link colour —
	$wp_customize->add_setting( 'bp_navbar_link_color', array(
		'default'           => $d['bp_navbar_link_color'],
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'bp_navbar_link_color', array(
		'label'   => __( 'Navigation link colour', 'generatepress-child' ),
		'section' => 'bp_header_section',
	) ) );

	// — Nav bar: link hover colour (text/icon colour on hover) —
	$wp_customize->add_setting( 'bp_navbar_link_hover_color', array(
		'default'           => $d['bp_navbar_link_hover_color'],
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'bp_navbar_link_hover_color', array(
		'label'       => __( 'Navigation link hover colour', 'generatepress-child' ),
		'description' => __( 'Text/icon colour on hover. Leave empty to keep the normal link colour.', 'generatepress-child' ),
		'section'     => 'bp_header_section',
	) ) );

	// — Nav bar: link hover background —
	$wp_customize->add_setting( 'bp_navbar_link_hover_bg', array(
		'default'           => $d['bp_navbar_link_hover_bg'],
		'sanitize_callback' => 'sanitize_hex_color',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'bp_navbar_link_hover_bg', array(
		'label'       => __( 'Navigation link hover background', 'generatepress-child' ),
		'description' => __( 'Leave empty to keep the default subtle dark hover.', 'generatepress-child' ),
		'section'     => 'bp_header_section',
	) ) );

	// — Logo width —
	// NOTE: GeneratePress's own "Logo Width" control does not affect this theme's
	// custom header (the logo lives in .bp-logo, sized by our own CSS). Use this one.
	$wp_customize->add_setting( 'bp_logo_width', array(
		'default'           => $d['bp_logo_width'],
		'sanitize_callback' => 'bp_sanitize_logo_width',
		'transport'         => 'postMessage',
	) );
	$wp_customize->add_control( 'bp_logo_width', array(
		'label'       => __( 'Logo width (px)', 'generatepress-child' ),
		'description' => __( 'Maximum logo width. The logo fills the header bar height; this caps how wide it can get. Leave empty for automatic.', 'generatepress-child' ),
		'section'     => 'bp_header_section',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 20, 'max' => 600, 'step' => 1 ),
	) );
}
add_action( 'customize_register', 'bp_header_customize_register' );

/* ── Dynamic CSS: emit only the values that differ from the defaults ───── */

function bp_header_dynamic_css() {
	$d         = bp_header_defaults();
	$color_map = bp_header_color_map();
	$rules     = array();

	// Colour variables.
	foreach ( $color_map as $setting => $css_var ) {
		$value = get_theme_mod( $setting, $d[ $setting ] );
		if ( '' === $value || $value === $d[ $setting ] ) {
			continue; // Empty or unchanged => fall back to header-style.css default.
		}
		$value = sanitize_hex_color( $value );
		if ( $value ) {
			$rules[] = $css_var . ':' . $value . ';';
		}
	}

	// Logo width — acts as a max-width cap; the logo fills the bar height and the
	// CSS keeps the aspect ratio. Empty => automatic (natural width at full height).
	$logo_width = bp_sanitize_logo_width( get_theme_mod( 'bp_logo_width', $d['bp_logo_width'] ) );
	if ( '' !== $logo_width ) {
		$rules[] = '--bp-logo-width:' . (int) $logo_width . 'px;';
	}

	if ( empty( $rules ) ) {
		return;
	}

	$css = ':root{' . implode( '', $rules ) . '}';
	wp_add_inline_style( 'bp-header-style', $css );
}
// Runs after bp_enqueue_custom_header_assets (priority 20) so the handle exists.
add_action( 'wp_enqueue_scripts', 'bp_header_dynamic_css', 30 );

/* ── Live preview script ───────────────────────────────────────────────── */

function bp_header_customize_preview_js() {
	wp_enqueue_script(
		'bp-customizer-preview',
		get_stylesheet_directory_uri() . '/inc/customizer-preview.js',
		array( 'customize-preview', 'jquery' ),
		filemtime( get_stylesheet_directory() . '/inc/customizer-preview.js' ),
		true
	);
}
add_action( 'customize_preview_init', 'bp_header_customize_preview_js' );

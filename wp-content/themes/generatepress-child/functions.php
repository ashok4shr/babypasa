<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_separate', trailingslashit( get_stylesheet_directory_uri() ) . 'ctc-style.css', array( 'generate-style','generate-child' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

defined( 'CHLD_THM_CFG_IGNORE_PARENT' ) or define( 'CHLD_THM_CFG_IGNORE_PARENT', TRUE );

// END ENQUEUE PARENT ACTION

// ── Product search index (pre-loaded for client-side instant search) ─────────
function bp_get_product_search_index() {
    $cache_key = 'bp_product_search_index';
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) return $cached;

    if ( ! class_exists( 'WooCommerce' ) ) return array();

    $products = array();
    $query    = new WP_Query( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ) );

    foreach ( $query->posts as $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) continue;

        $img_id   = get_post_thumbnail_id( $product_id );
        $img_data = $img_id ? wp_get_attachment_image_src( $img_id, 'thumbnail' ) : false;
        $img_url  = $img_data ? $img_data[0]
                              : ( function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src() : '' );

        $terms    = get_the_terms( $product_id, 'product_cat' );
        $category = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';

        $products[] = array(
            'id'       => $product_id,
            'name'     => $product->get_name(),
            'sku'      => $product->get_sku(),
            'url'      => get_permalink( $product_id ),
            'image'    => $img_url,
            'price'    => $product->get_price_html(),
            'category' => $category,
            'on_sale'  => (bool) $product->is_on_sale(),
            'in_stock' => (bool) $product->is_in_stock(),
        );
    }

    set_transient( $cache_key, $products, HOUR_IN_SECONDS );
    return $products;
}

// Bust search index cache + slider caches whenever a product is saved
add_action( 'save_post_product', function () {
    delete_transient( 'bp_product_search_index' );
    update_option( 'bp_search_index_version', (string) time(), false );
    // Bust all homepage slider transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_bp_slider_%'
            OR option_name LIKE '_transient_timeout_bp_slider_%'"
    );
} );

// Enqueue custom header styles and scripts
function bp_enqueue_custom_header_assets() {
    wp_enqueue_style( 'bp-header-style', get_stylesheet_directory_uri() . '/header-style.css', array(), filemtime( get_stylesheet_directory() . '/header-style.css' ) );
    wp_enqueue_style( 'bp-footer-style', get_stylesheet_directory_uri() . '/footer-style.css', array(), filemtime( get_stylesheet_directory() . '/footer-style.css' ) );

    wp_enqueue_script( 'bp-ajax-search', get_stylesheet_directory_uri() . '/ajax-search.js', array(), filemtime( get_stylesheet_directory() . '/ajax-search.js' ), true );

    // Search drawer — product index is lazy-loaded on first drawer open (not inline JSON)
    wp_enqueue_script( 'bp-search-drawer', get_stylesheet_directory_uri() . '/header-search-drawer.js', array(), filemtime( get_stylesheet_directory() . '/header-search-drawer.js' ), true );
    if ( class_exists( 'WooCommerce' ) ) {
        wp_localize_script( 'bp-search-drawer', 'bpSearchIndex', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'bp_search_index' ),
            'indexVersion' => (string) get_option( 'bp_search_index_version', '0' ),
            'viewAllUrl'   => add_query_arg( 'post_type', 'product', home_url( '/' ) ),
            'i18n'         => array(
                'noResults'  => __( 'No products found.', 'generatepress-child' ),
                'viewAll'    => __( 'See all results for "%s"', 'generatepress-child' ),
                'sale'       => __( 'Sale', 'generatepress-child' ),
                'outOfStock' => __( 'Out of Stock', 'generatepress-child' ),
                'loading'    => __( 'Loading…', 'generatepress-child' ),
            ),
        ) );
    }

    // Enqueue common WooCommerce components (Cards/Sliders + My Account styles)
    if ( is_woocommerce() || is_front_page() || is_account_page() ) {
        wp_enqueue_style( 'bp-common-woocommerce', get_stylesheet_directory_uri() . '/common-woocommerce.css', array(), filemtime( get_stylesheet_directory() . '/common-woocommerce.css' ) );
        wp_enqueue_script( 'bp-slider-scripts', get_stylesheet_directory_uri() . '/homepage.js', array(), filemtime( get_stylesheet_directory() . '/homepage.js' ), true );

        // Quick-add modal for variable products
        wp_enqueue_style(  'bp-quick-add', get_stylesheet_directory_uri() . '/assets/css/quick-add.css', array(), filemtime( get_stylesheet_directory() . '/assets/css/quick-add.css' ) );
        wp_enqueue_script( 'bp-quick-add', get_stylesheet_directory_uri() . '/assets/js/quick-add.js', array(), filemtime( get_stylesheet_directory() . '/assets/js/quick-add.js' ), true );
        wp_localize_script( 'bp-quick-add', 'bpQuickAdd', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bp_quick_add_nonce' ),
        ) );
    }

    // Enqueue homepage assets conditionally
    if ( is_front_page() || is_home() || is_page_template( 'template-homepage.php' ) ) {
        wp_enqueue_style( 'bp-homepage-style', get_stylesheet_directory_uri() . '/homepage-style.css', array(), filemtime( get_stylesheet_directory() . '/homepage-style.css' ) );
    }

    // Enqueue single product assets conditionally
    if ( is_product() ) {
        wp_enqueue_style( 'bp-product-style', get_stylesheet_directory_uri() . '/product-style.css', array(), filemtime( get_stylesheet_directory() . '/product-style.css' ) );
        wp_enqueue_script( 'bp-single-product', get_stylesheet_directory_uri() . '/single-product.js', array(), filemtime( get_stylesheet_directory() . '/single-product.js' ), true );
        // Required for variable product variation picker; registered by WooCommerce, safe to call on all product pages.
        wp_enqueue_script( 'wc-add-to-cart-variation' );

        // Custom product image slider (replaces WooCommerce flexslider/photoswipe gallery).
        wp_enqueue_style( 'bp-product-gallery', get_stylesheet_directory_uri() . '/assets/css/product-gallery.css', array( 'bp-product-style' ), filemtime( get_stylesheet_directory() . '/assets/css/product-gallery.css' ) );
        wp_enqueue_script( 'bp-product-gallery', get_stylesheet_directory_uri() . '/assets/js/product-gallery.js', array(), filemtime( get_stylesheet_directory() . '/assets/js/product-gallery.js' ), true );

        // Sticky Add-to-Cart bar — pins div.bp-single-add-to-cart to the top of the
        // viewport on any scroll (logic in assets/js/sticky-add-to-cart.js).
        wp_enqueue_style( 'bp-sticky-add-to-cart', get_stylesheet_directory_uri() . '/assets/css/sticky-add-to-cart.css', array( 'bp-product-style' ), filemtime( get_stylesheet_directory() . '/assets/css/sticky-add-to-cart.css' ) );
        wp_enqueue_script( 'bp-sticky-add-to-cart', get_stylesheet_directory_uri() . '/assets/js/sticky-add-to-cart.js', array(), filemtime( get_stylesheet_directory() . '/assets/js/sticky-add-to-cart.js' ), true );
    }

    // Enqueue My Account custom styles (logged-in dashboard + auth card)
    if ( is_account_page() ) {
        wp_enqueue_style(
            'bp-my-account',
            get_stylesheet_directory_uri() . '/assets/css/my-account.css',
            array(),
            filemtime( get_stylesheet_directory() . '/assets/css/my-account.css' )
        );

        // Opens the orders-table "Track Order" action in a new tab.
        wp_enqueue_script(
            'bp-account-orders',
            get_stylesheet_directory_uri() . '/assets/js/account-orders.js',
            array(),
            filemtime( get_stylesheet_directory() . '/assets/js/account-orders.js' ),
            true
        );
    }

    // Enqueue checkout two-column layout + enhancements
    if ( is_checkout() ) {
        wp_enqueue_style( 'bp-checkout-style', get_stylesheet_directory_uri() . '/checkout-style.css', array(), filemtime( get_stylesheet_directory() . '/checkout-style.css' ) );
        wp_enqueue_script( 'bp-checkout', get_stylesheet_directory_uri() . '/assets/js/checkout.js', array( 'jquery' ), filemtime( get_stylesheet_directory() . '/assets/js/checkout.js' ), true );
    }

    // Hide "View Cart" button from add-to-cart toast without touching the plugin files.
    // The plugin's showBpNotification() always wraps action links in .bp-notification-actions;
    // :has() collapses the whole container so no empty gap is left in the notification.
    wp_add_inline_style( 'bp-wishlist-compare',
        '.bp-notification-actions:has(a.bp-notification-btn[href*="cart"]) { display: none !important; }'
    );
}
add_action( 'wp_enqueue_scripts', 'bp_enqueue_custom_header_assets', 20 );

// Dequeue WooCommerce's default gallery scripts on single product pages — the
// custom slider (assets/js/product-gallery.js) fully replaces them. Runs late
// (priority 99) so it fires after WooCommerce has registered/enqueued them.
// Note: wc-add-to-cart-variation is intentionally NOT removed — variable
// products still need it for the variation picker.
add_action( 'wp_enqueue_scripts', 'bp_dequeue_default_gallery_assets', 99 );
function bp_dequeue_default_gallery_assets() {
    if ( ! is_product() ) {
        return;
    }
    // WooCommerce 8.6+ prefixes these handles with "wc-".
    wp_dequeue_script( 'wc-single-product' );
    wp_dequeue_script( 'wc-zoom' );
    wp_dequeue_script( 'wc-flexslider' );
    wp_dequeue_script( 'wc-photoswipe' );
    wp_dequeue_script( 'wc-photoswipe-ui-default' );
    wp_dequeue_style( 'photoswipe' );
    wp_dequeue_style( 'photoswipe-default-skin' );

    // The lightbox support also hooks the PhotoSwipe markup into the footer.
    // With the script gone it's inert, but remove it so no orphan template ships.
    remove_action( 'wp_footer', 'woocommerce_photoswipe' );
}

// Load custom WooCommerce helper functions
require_once get_stylesheet_directory() . '/inc/woocommerce-functions.php';

// Load custom admin settings for Homepage
require_once get_stylesheet_directory() . '/inc/admin-homepage.php';

// AJAX Product Search Handler
require_once get_stylesheet_directory() . '/inc/ajax-search-handler.php';

// Header Customizer (Appearance > Customize > BabyPasa Header)
require_once get_stylesheet_directory() . '/inc/header-customizer.php';

// === BABYPASA ASSET CACHE-BUST FIX: START ===
/**
 * The Child Theme Configurator plugin filters style_loader_src/script_loader_src
 * (priority 10) and rewrites EVERY child-theme asset's ?ver= to the theme's
 * static Version header. That overrides our filemtime() cache-busters, so edited
 * CSS/JS keeps the same URL and browsers serve stale cached copies until the
 * theme Version is manually bumped.
 *
 * Re-apply real filemtime versioning for our own theme files (runs after CTC at
 * priority 20) so edits are picked up immediately. Scoped strictly to child-theme
 * asset URLs; everything else is left untouched.
 */
function bp_filemtime_asset_version( $src ) {
	$dir_uri = get_stylesheet_directory_uri();
	if ( 0 !== strpos( $src, $dir_uri ) ) {
		return $src; // Not a child-theme asset — leave it alone.
	}
	$dir_path  = wp_parse_url( $dir_uri, PHP_URL_PATH );
	$src_path  = wp_parse_url( $src, PHP_URL_PATH );
	$rel       = ltrim( str_replace( $dir_path, '', $src_path ), '/' );
	$file      = get_stylesheet_directory() . '/' . $rel;
	if ( $rel && is_readable( $file ) ) {
		$src = add_query_arg( 'ver', filemtime( $file ), $src );
	}
	return $src;
}
add_filter( 'style_loader_src',  'bp_filemtime_asset_version', 20 );
add_filter( 'script_loader_src', 'bp_filemtime_asset_version', 20 );
// === BABYPASA ASSET CACHE-BUST FIX: END ===

// AJAX endpoint: serves the product search index to the search drawer JS
add_action( 'wp_ajax_bp_get_search_index',        'bp_ajax_serve_search_index' );
add_action( 'wp_ajax_nopriv_bp_get_search_index', 'bp_ajax_serve_search_index' );
function bp_ajax_serve_search_index() {
    check_ajax_referer( 'bp_search_index', 'nonce' );
    wp_send_json_success( bp_get_product_search_index() );
}

// Enqueue Archive Assets
function bp_enqueue_archive_assets() {
    if ( is_shop() || is_product_category() || is_product_tag() || is_tax() || is_page_template( 'template-price-drop.php' ) ) {
        wp_enqueue_style( 'bp-archive-style', get_stylesheet_directory_uri() . '/archive-style.css', array(), filemtime( get_stylesheet_directory() . '/archive-style.css' ) );
        wp_enqueue_script( 'bp-archive-scripts', get_stylesheet_directory_uri() . '/archive-scripts.js', array(), filemtime( get_stylesheet_directory() . '/archive-scripts.js' ), true );
    }
}
add_action( 'wp_enqueue_scripts', 'bp_enqueue_archive_assets', 30 );

// Cart icon counts UNIQUE products (line items), not the summed quantity.
// e.g. 1 product × qty 3 → "1"; 2 different products → "2".
// This filter is consumed by WC()->cart->get_cart_contents_count(), which feeds
// the header badge (header.php), the add-to-cart fragment below, and the mini-cart —
// so every surface stays consistent from this single point.
add_filter( 'woocommerce_cart_contents_count', function( $count ) {
    return ( WC()->cart ) ? count( WC()->cart->get_cart() ) : $count;
} );

// Keep cart count badge in sync after AJAX add-to-cart
add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
    $count   = WC()->cart->get_cart_contents_count();
    $display = $count > 0 ? '' : ' style="display:none"';
    $content = $count > 0 ? esc_html( $count ) : '';
    $fragments['.bp-cart-count'] = '<span class="bp-cart-count"' . $display . '>' . $content . '</span>';
    return $fragments;
} );

// Register WooCommerce Sidebar Native Widget Area
function bp_register_wc_sidebar() {
    register_sidebar( array(
        'name'          => __( 'WooCommerce Sidebar', 'generatepress-child' ),
        'id'            => 'woocommerce-sidebar',
        'description'   => __( 'Add widgets here to appear in your sidebar on WooCommerce archive pages.', 'generatepress-child' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );
}
add_action( 'widgets_init', 'bp_register_wc_sidebar' );

// Align WooCommerce's columns-N HTML class with our 4-column CSS Grid max
add_filter( 'loop_columns', function() { return 4; } );

// Custom WooCommerce Toolbar
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

add_action( 'woocommerce_before_shop_loop', 'bp_custom_woocommerce_toolbar', 15 );
function bp_custom_woocommerce_toolbar() {
	echo '<div class="bp-shop-toolbar">';
	
	// Left side: Grid/List Toggle + Result Count
	echo '<div class="bp-toolbar-left">';
	?>
	<div class="bp-view-modes">
		<button id="bp-grid-view" class="bp-view-btn active" aria-label="Grid View"></button>
		<button id="bp-list-view" class="bp-view-btn" aria-label="List View"></button>
	</div>
	<?php
	woocommerce_result_count();
	echo '</div>'; // close left side

	// Right side: SORT BY + Dropdown
	echo '<div class="bp-toolbar-right">';
	echo '<span class="bp-sort-label">SORT BY</span>';
	woocommerce_catalog_ordering();
	echo '</div>'; // close right side

	echo '</div>'; // close toolbar
}

// Allow 'pa_age-group' to be available in Appearance > Menus
function bp_enable_age_group_in_nav_menus( $args ) {
    $args['show_in_nav_menus'] = true;
    return $args;
}
add_filter( 'woocommerce_taxonomy_args_pa_age-group', 'bp_enable_age_group_in_nav_menus' );


// Cart shipping-cost display tweaks (hide price → "calculated at checkout")
// moved to the BabyPasa Delivery Overrides plugin
// (includes/class-cart-shipping-display.php) on 2026-06-05.
// The dead no-op hide_shipping_from_cart_total() was dropped during the move.

// Google OAuth is now rendered directly in the custom form-login.php template
// (positioned above the credential fields, inside .bp-auth-social).
// The previous hook-based approach has been removed to prevent double-rendering.

// 1. Replace the logo image
add_action( 'login_enqueue_scripts', 'custom_login_logo' );
function custom_login_logo() {
    $logo = get_custom_logo();
    preg_match( '/src="([^"]+)"/', $logo, $matches );
    $logo_url = $matches[1] ?? '';

    if ( ! $logo_url ) return;
    ?>
    <style>
        .wp-login-logo a {
            background-image: url('<?php echo esc_url( $logo_url ); ?>') !important;
            background-size: contain !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            width: 200px !important;
            height: 80px !important;
        }
    </style>
    <?php
}

// 2. Change the logo link to your site instead of wordpress.org
add_filter( 'login_headerurl', 'custom_login_logo_url' );
function custom_login_logo_url() {
    return home_url();
}

// 3. Change the logo hover title
add_filter( 'login_headertext', 'custom_login_logo_title' );
function custom_login_logo_title() {
    return get_bloginfo( 'name' );
}

// 1. Redirect non-admin users away from wp-admin
add_action( 'admin_init', 'redirect_non_admin_users' );
function redirect_non_admin_users() {
    if ( is_admin() && ! current_user_can( 'manage_options' ) && ! wp_doing_ajax() ) {
        wp_redirect( home_url( '/my-account/' ) );
        exit;
    }
}

// Redirect wp-login.php to /my-account/ for everyone except admins doing admin tasks
add_action( 'init', 'redirect_login_page' );
function redirect_login_page() {
    if ( ! isset( $_SERVER['REQUEST_URI'] ) ) return;

    $request_uri = $_SERVER['REQUEST_URI'];
    $is_login_page = strpos( $request_uri, 'wp-login.php' ) !== false;
    $is_admin_page = strpos( $request_uri, '/wp-admin' ) !== false;

    if ( ! $is_login_page && ! $is_admin_page ) return;

    // Never block AJAX calls
    if ( wp_doing_ajax() ) return;

    // Allow POST requests (login form submissions)
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) return;

    // Allow password reset flows
    $allowed_actions = [ 'lostpassword', 'rp', 'resetpass', 'confirmaction', 'logout' ];
    $action = isset( $_GET['action'] ) ? $_GET['action'] : '';
    if ( in_array( $action, $allowed_actions ) ) return;

    // Allow Nextend Social Login OAuth callback
    if ( isset( $_GET['loginSocial'] ) ) return;
    if ( strpos( $request_uri, 'social-login' ) !== false ) return;
    if ( strpos( $request_uri, 'nsl_' ) !== false ) return;

    // Allow logged-in admins
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) return;

    // Everyone else → /my-account/
    wp_redirect( home_url( '/my-account/' ) );
    exit;
}

// 3. After login, redirect customers to /my-account/, admins to wp-admin.
//    Honour an on-site redirect_to (e.g. returning to a product page after reviewing).
add_filter( 'login_redirect', 'custom_login_redirect', 10, 3 );
function custom_login_redirect( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        if ( in_array( 'administrator', $user->roles ) ) {
            return admin_url();
        }
        // $request holds the requested redirect URL; only honour it when it is on-site.
        if ( ! empty( $request ) ) {
            $parsed   = wp_parse_url( $request );
            $our_host = wp_parse_url( home_url(), PHP_URL_HOST );
            $is_offsite = ! empty( $parsed['host'] ) && $parsed['host'] !== $our_host;
            if ( ! $is_offsite && $request !== home_url( '/my-account/' ) ) {
                return $request;
            }
        }
        return home_url( '/my-account/' );
    }
    return $redirect_to;
}

// 4. After logout, redirect to /my-account/ instead of wp-login.php
add_action( 'wp_logout', 'redirect_after_logout' );
function redirect_after_logout() {
    wp_redirect( home_url( '/my-account/' ) );
    exit;
}

// -------------------------------------------------------------------------
// Reviews: customise the "awaiting approval" notice text
// -------------------------------------------------------------------------

add_filter( 'gettext', 'bp_review_awaiting_approval_text', 10, 3 );
function bp_review_awaiting_approval_text( $translated, $text, $domain ) {
    if ( 'woocommerce' === $domain && 'Your review is awaiting approval' === $text ) {
        return '✅ Thanks for helping our community! Your review is in the queue and will be live within 24 hours.';
    }
    return $translated;
}

// -------------------------------------------------------------------------
// Reviews: restrict submission form to logged-in users
// -------------------------------------------------------------------------

// comment_form_before fires before <div id="respond"> is output.
// Output the notice here (real output), then buffer+discard everything
// that comment_form() renders so guests never see the form shell.
add_action( 'comment_form_before', 'bp_start_form_suppression' );
function bp_start_form_suppression() {
    if ( ! is_product() || is_user_logged_in() ) return;
    $login_url = add_query_arg( 'redirect_to', rawurlencode( get_permalink() ), wc_get_page_permalink( 'myaccount' ) );
    echo '<div class="wc-login-notice">';
    printf(
        wp_kses( __( 'Please <a href="%s">log in</a> to post a review.', 'generatepress-child' ), array( 'a' => array( 'href' => array() ) ) ),
        esc_url( $login_url )
    );
    echo '</div>';
    ob_start();
}

add_action( 'comment_form_after', 'bp_end_form_suppression' );
function bp_end_form_suppression() {
    if ( ! is_product() || is_user_logged_in() ) return;
    ob_end_clean();
}

// -------------------------------------------------------------------------
// Product Videos — admin meta box + save
// -------------------------------------------------------------------------

add_action( 'add_meta_boxes', 'bp_add_product_videos_meta_box' );
function bp_add_product_videos_meta_box() {
    add_meta_box( 'bp_product_videos', 'Product Videos', 'bp_product_videos_meta_box_html', 'product', 'normal', 'default' );
}

function bp_product_videos_meta_box_html( $post ) {
    $videos = json_decode( get_post_meta( $post->ID, '_bp_product_videos', true ), true ) ?: [];
    wp_nonce_field( 'bp_save_product_videos', 'bp_product_videos_nonce' );
    ?>
    <div id="bp-video-rows">
        <?php foreach ( $videos as $url ) : ?>
        <div class="bp-video-row" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <input type="text" name="bp_product_videos[]" value="<?php echo esc_attr( $url ); ?>"
                   style="flex:1;" placeholder="Video URL" readonly />
            <button type="button" class="button bp-upload-video-row-btn">Upload</button>
            <button type="button" class="button bp-remove-video-row-btn">Remove</button>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="button button-secondary" id="bp-add-video-row" style="margin-top:6px;">+ Add Video</button>
    <?php
}

add_action( 'save_post_product', 'bp_save_product_videos' );
function bp_save_product_videos( $post_id ) {
    if ( ! isset( $_POST['bp_product_videos_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['bp_product_videos_nonce'], 'bp_save_product_videos' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $raw    = isset( $_POST['bp_product_videos'] ) ? (array) $_POST['bp_product_videos'] : [];
    $videos = array_values( array_filter( array_map( 'esc_url_raw', array_map( 'wp_unslash', $raw ) ) ) );

    if ( $videos ) {
        update_post_meta( $post_id, '_bp_product_videos', wp_json_encode( $videos ) );
    } else {
        delete_post_meta( $post_id, '_bp_product_videos' );
    }
}

add_action( 'admin_enqueue_scripts', 'bp_enqueue_product_videos_admin_scripts' );
function bp_enqueue_product_videos_admin_scripts( $hook ) {
    global $post_type;
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || $post_type !== 'product' ) return;
    wp_enqueue_media();
    wp_add_inline_script( 'media-editor', "
        jQuery(function($){
            function makeRow() {
                return $('<div class=\"bp-video-row\" style=\"display:flex;align-items:center;gap:8px;margin-bottom:8px;\">' +
                    '<input type=\"text\" name=\"bp_product_videos[]\" value=\"\" style=\"flex:1;\" placeholder=\"Video URL\" readonly />' +
                    '<button type=\"button\" class=\"button bp-upload-video-row-btn\">Upload</button>' +
                    '<button type=\"button\" class=\"button bp-remove-video-row-btn\">Remove</button>' +
                '</div>');
            }

            $('#bp-add-video-row').on('click', function(){
                $('#bp-video-rows').append(makeRow());
            });

            $('#bp-video-rows').on('click', '.bp-remove-video-row-btn', function(){
                $(this).closest('.bp-video-row').remove();
            });

            var activeInput = null;
            var frame = wp.media({
                title: 'Select Product Video',
                button: { text: 'Use this video' },
                multiple: false,
                library: { type: 'video' }
            });
            frame.on('select', function(){
                if (!activeInput) return;
                activeInput.val(frame.state().get('selection').first().toJSON().url);
            });

            $('#bp-video-rows').on('click', '.bp-upload-video-row-btn', function(){
                activeInput = $(this).siblings('input[type=text]');
                frame.open();
            });
        });
    " );
}

// ── Mini-cart drawer ──────────────────────────────────────────────────────────

// Remove wc-cart-fragments: it fires an AJAX call on every page load to sync cart state.
// Our mini-cart.js manages its own fragment refreshes (only on actual cart events),
// so this polling script is pure overhead.
add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_script( 'wc-cart-fragments' );
}, 100 );

add_action( 'wp_enqueue_scripts', 'bp_enqueue_mini_cart_assets' );
function bp_enqueue_mini_cart_assets() {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    wp_enqueue_style(
        'bp-mini-cart',
        get_stylesheet_directory_uri() . '/assets/css/mini-cart.css',
        array(),
        filemtime( get_stylesheet_directory() . '/assets/css/mini-cart.css' )
    );
    wp_enqueue_script(
        'bp-mini-cart',
        get_stylesheet_directory_uri() . '/assets/js/mini-cart.js',
        array(),  // wc-cart-fragments removed: mini-cart.js manages its own refreshFragments() calls
        filemtime( get_stylesheet_directory() . '/assets/js/mini-cart.js' ),
        true
    );
    wp_localize_script( 'bp-mini-cart', 'bpMiniCart', array(
        'wcAjaxUrl'   => add_query_arg( 'wc-ajax', '%%endpoint%%', home_url( '/' ) ),
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'woocommerce-cart' ),
        'updateNonce' => wp_create_nonce( 'bp_update_cart_qty' ),
        'cartUrl'     => wc_get_cart_url(),
        'checkoutUrl' => wc_get_checkout_url(),
        'i18n'        => array(
            'empty'   => __( 'Your cart is empty.', 'woocommerce' ),
            'loading' => __( 'Updating&hellip;', 'woocommerce' ),
        ),
    ) );
}

// Remove "View Cart" — only the Checkout button is needed in the drawer.
add_action( 'woocommerce_widget_shopping_cart_buttons', function () {
    remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
}, 0 );

// Inject drawer + overlay HTML just before </body>
add_action( 'wp_footer', 'bp_render_mini_cart_drawer' );
function bp_render_mini_cart_drawer() {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    ?>
    <div id="mini-cart-overlay" class="mini-cart-overlay" aria-hidden="true"></div>
    <div id="mini-cart-drawer"
         class="mini-cart-drawer"
         role="dialog"
         aria-modal="true"
         aria-label="<?php esc_attr_e( 'Shopping cart', 'woocommerce' ); ?>"
         aria-hidden="true">
        <div class="mini-cart-drawer__header">
            <span class="mini-cart-drawer__title"><?php esc_html_e( 'Your Cart', 'woocommerce' ); ?></span>
            <button type="button" id="mini-cart-close" class="mini-cart-drawer__close" aria-label="<?php esc_attr_e( 'Close cart', 'woocommerce' ); ?>">&times;</button>
        </div>
        <div class="mini-cart-drawer__inner">
            <div class="mini-cart-drawer__loading" aria-hidden="true">
                <span class="bp-mc-spinner"></span>
            </div>
            <div class="widget_shopping_cart_content">
                <?php woocommerce_mini_cart(); ?>
            </div>
        </div>
    </div>
    <?php
}

// Inject search drawer + overlay HTML before </body>
add_action( 'wp_footer', 'bp_render_search_drawer' );
function bp_render_search_drawer() {
    ?>
    <div id="bp-search-overlay" class="bp-search-overlay" aria-hidden="true"></div>
    <div id="bp-search-drawer"
         class="bp-search-drawer"
         role="dialog"
         aria-modal="true"
         aria-label="<?php esc_attr_e( 'Search products', 'generatepress-child' ); ?>"
         aria-hidden="true">
        <div class="bp-search-drawer__header">
            <span class="bp-search-drawer__title">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <?php esc_html_e( 'Search', 'generatepress-child' ); ?>
            </span>
            <button type="button" id="bp-search-drawer-close"
                    class="bp-search-drawer__close"
                    aria-label="<?php esc_attr_e( 'Close search', 'generatepress-child' ); ?>">&times;</button>
        </div>
        <div class="bp-search-drawer__body">
            <input type="search"
                   id="bp-search-drawer-input"
                   class="bp-search-drawer__input"
                   placeholder="<?php esc_attr_e( 'Search products...', 'generatepress-child' ); ?>"
                   autocomplete="off"
                   aria-label="<?php esc_attr_e( 'Search products', 'generatepress-child' ); ?>" />
            <div id="bp-search-drawer-results"
                 class="bp-search-drawer__results"
                 role="listbox"
                 aria-live="polite"></div>
        </div>
    </div>
    <?php
}

// Inject quick-add modal shell before </body> — JS fills the body dynamically.
add_action( 'wp_footer', 'bp_render_quick_add_modal' );
function bp_render_quick_add_modal() {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    if ( ! is_woocommerce() && ! is_front_page() && ! is_account_page() ) return;
    ?>
    <div id="bp-quick-add-overlay" aria-hidden="true"></div>
    <?php // Fix: quick-add modal stayed hidden — the inline style="display:none" overrode
          // the stylesheet's #bp-quick-add-modal.bp-qa-open { display:block } rule (inline
          // styles win), so the overlay showed but the modal never appeared. Hidden state is
          // already handled by quick-add.css (#bp-quick-add-modal { display:none }), matching
          // the overlay above, so the inline style is removed. ?>
    <div id="bp-quick-add-modal"
         role="dialog"
         aria-modal="true"
         aria-hidden="true"
         aria-label="<?php esc_attr_e( 'Select product options', 'generatepress-child' ); ?>">
        <button class="bp-quick-add-close" aria-label="<?php esc_attr_e( 'Close', 'generatepress-child' ); ?>">&times;</button>
        <div class="bp-quick-add-body"></div>
    </div>
    <?php
}

// AJAX: return variation data (attributes + all variation combinations) for a variable product.
add_action( 'wp_ajax_bp_get_product_variations',        'bp_ajax_get_product_variations' );
add_action( 'wp_ajax_nopriv_bp_get_product_variations', 'bp_ajax_get_product_variations' );
function bp_ajax_get_product_variations() {
    check_ajax_referer( 'bp_quick_add_nonce', 'nonce' );

    $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
    $product    = wc_get_product( $product_id );

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        wp_send_json_error( array( 'message' => 'Invalid product.' ) );
    }

    wp_send_json_success( array(
        'name'       => $product->get_name(),
        'image'      => get_the_post_thumbnail_url( $product_id, 'woocommerce_thumbnail' )
                        ?: wc_placeholder_img_src(),
        'price_html' => $product->get_price_html(),
        'attributes' => $product->get_variation_attributes(), // slug => [ values ]
        'variations' => $product->get_available_variations(), // full variation data for JS matching
    ) );
}

// AJAX: add a specific variation to the cart.
add_action( 'wp_ajax_bp_quick_add_to_cart',        'bp_ajax_quick_add_to_cart' );
add_action( 'wp_ajax_nopriv_bp_quick_add_to_cart', 'bp_ajax_quick_add_to_cart' );
function bp_ajax_quick_add_to_cart() {
    check_ajax_referer( 'bp_quick_add_nonce', 'nonce' );

    $product_id   = isset( $_POST['product_id'] )   ? intval( $_POST['product_id'] )   : 0;
    $variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
    $attributes   = isset( $_POST['attributes'] )
        ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['attributes'] ) )
        : array();

    if ( ! $product_id || ! $variation_id ) {
        wp_send_json_error( array( 'message' => __( 'Invalid product or variation.', 'woocommerce' ) ) );
    }

    $result = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $attributes );

    if ( $result ) {
        $fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );
        wp_send_json_success( array(
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'fragments'  => $fragments,
        ) );
    } else {
        wp_send_json_error( array( 'message' => __( 'Could not add to cart. Please try again.', 'woocommerce' ) ) );
    }
}

// Keep drawer contents in sync via WooCommerce fragment system.
// The existing closure above already refreshes .bp-cart-count; this adds
// div.widget_shopping_cart_content so the drawer HTML also updates.
add_filter( 'woocommerce_add_to_cart_fragments', 'bp_mini_cart_fragment' );
function bp_mini_cart_fragment( $fragments ) {
    ob_start();
    echo '<div class="widget_shopping_cart_content">';
    woocommerce_mini_cart();
    echo '</div>';
    $fragments['div.widget_shopping_cart_content'] = ob_get_clean();
    return $fragments;
}

// Custom AJAX handler: update a single cart item's quantity.
// WooCommerce exposes no native wc-ajax endpoint for this, so we provide one.
add_action( 'wp_ajax_bp_update_cart_qty',        'bp_ajax_update_cart_qty' );
add_action( 'wp_ajax_nopriv_bp_update_cart_qty', 'bp_ajax_update_cart_qty' );
function bp_ajax_update_cart_qty() {
    check_ajax_referer( 'bp_update_cart_qty', 'nonce' );

    $cart_item_key = sanitize_text_field( wp_unslash( isset( $_POST['cart_item_key'] ) ? $_POST['cart_item_key'] : '' ) );
    $quantity      = absint( isset( $_POST['quantity'] ) ? $_POST['quantity'] : 0 );

    if ( ! $cart_item_key ) {
        wp_send_json_error( array( 'message' => __( 'Missing cart item key.', 'woocommerce' ) ), 400 );
    }

    WC()->cart->set_quantity( $cart_item_key, $quantity, true );

    $fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );

    wp_send_json_success( array(
        'fragments'  => $fragments,
        'cart_hash'  => WC()->cart->get_cart_hash(),
        'cart_count' => WC()->cart->get_cart_contents_count(),
    ) );
}

/* ------------------------------------------------------------------
 * My Account Orders — Re-order button
 * Adds a "Re-order" action next to "View" in the orders table.
 * Clicking it clears the current cart, loads items from the chosen
 * order, and redirects straight to checkout.
 * ------------------------------------------------------------------ */

add_filter( 'woocommerce_my_account_my_orders_actions', 'bp_add_reorder_action', 10, 2 );
function bp_add_reorder_action( array $actions, WC_Order $order ): array {
    // Re-order is offered for completed orders (buy the same items again) and
    // cancelled orders (recover an order that fell through). No other status.
    if ( ! in_array( $order->get_status(), array( 'completed', 'cancelled' ), true ) ) {
        return $actions;
    }
    // wp_nonce_url appends _wpnonce automatically.
    $actions['reorder'] = array(
        'url'        => wp_nonce_url(
            add_query_arg( 'bp_reorder', $order->get_id(), home_url( '/' ) ),
            'bp_reorder_' . $order->get_id()
        ),
        'name'       => __( 'Re-order', 'babypasa' ),
        'aria-label' => sprintf( __( 'Re-order #%s', 'babypasa' ), $order->get_order_number() ),
    );
    return $actions;
}

/* ------------------------------------------------------------------
 * My Account Orders — Track Order button
 * Moved to the BabyPasa Delivery Overrides plugin
 * (BP_Order_Tracking_Account::add_track_order_action) on 2026-06-05,
 * alongside the track-orders endpoint it links to. The 'bp_upaya_tracking_url'
 * filter is preserved there.
 * ------------------------------------------------------------------ */

add_action( 'init', 'bp_handle_reorder' );
function bp_handle_reorder(): void {
    if ( empty( $_GET['bp_reorder'] ) ) {
        return;
    }

    $order_id = absint( $_GET['bp_reorder'] );

    // CSRF check.
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bp_reorder_' . $order_id ) ) {
        wc_add_notice( __( 'Invalid request.', 'babypasa' ), 'error' );
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    // Must be logged in — guest customer_id = 0 would match any guest order.
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order || (int) $order->get_customer_id() !== get_current_user_id() ) {
        wc_add_notice( __( 'Order not found.', 'babypasa' ), 'error' );
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    // Initialise session/cart (needed when this fires before WC fully boots the cart).
    WC()->cart->maybe_set_cart_cookies();

    // Clear whatever is already in the cart so the re-order starts fresh.
    WC()->cart->empty_cart();

    $added   = 0;
    $skipped = 0;

    foreach ( $order->get_items() as $item ) {
        /** @var WC_Order_Item_Product $item */
        $product_id   = (int) $item->get_product_id();
        $variation_id = (int) $item->get_variation_id();
        $quantity     = (int) $item->get_quantity();

        $product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
        if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            $skipped++;
            continue;
        }

        $result = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
        $result ? $added++ : $skipped++;
    }

    if ( $added > 0 && 0 === $skipped ) {
        // No notice — redirect silently to checkout.
    } elseif ( $added > 0 ) {
        wc_add_notice( sprintf(
            /* translators: 1: number added  2: number skipped */
            __( '%1$d item(s) added to cart. %2$d item(s) could not be added (out of stock or unavailable).', 'babypasa' ),
            $added,
            $skipped
        ), 'notice' );
    } else {
        wc_add_notice( __( 'No items could be added to cart — they may be out of stock or unavailable.', 'babypasa' ), 'error' );
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }

    // Skip the cart page; go straight to checkout.
    wp_safe_redirect( wc_get_checkout_url() );
    exit;
}

// ── Checkout — mobile keyboard hints ─────────────────────────────────────────
// Add inputmode/autocomplete so phones surface the right keyboard. font-size:16px
// (set in checkout-style.css) is what actually prevents iOS zoom-on-focus.
add_filter( 'woocommerce_checkout_fields', 'bp_checkout_input_attributes' );
function bp_checkout_input_attributes( array $fields ): array {
    $map = array(
        'email'           => array( 'inputmode' => 'email',   'autocomplete' => 'email' ),
        'phone'           => array( 'inputmode' => 'tel',     'autocomplete' => 'tel' ),
        'alternate_phone' => array( 'inputmode' => 'tel' ),
        'postcode'        => array( 'inputmode' => 'numeric', 'autocomplete' => 'postal-code' ),
    );
    foreach ( $fields as $group => &$set ) {
        foreach ( $set as $key => &$field ) {
            foreach ( $map as $needle => $attrs ) {
                // Match billing_email, shipping_phone, billing_postcode, etc.
                if ( substr( $key, -strlen( $needle ) ) === $needle ) {
                    $field['custom_attributes'] = array_merge(
                        isset( $field['custom_attributes'] ) ? $field['custom_attributes'] : array(),
                        $attrs
                    );
                }
            }
        }
    }
    unset( $set, $field );
    return $fields;
}

add_post_type_support( 'page', 'excerpt' );

// ── My Account — remove Downloads from nav menu ──────────────────────────────
add_filter( 'woocommerce_account_menu_items', 'bp_remove_account_menu_items' );
/**
 * Remove tabs from the My Account navigation that are not needed.
 *
 * @param  array $items Endpoint slug => label.
 * @return array
 */
function bp_remove_account_menu_items( array $items ): array {
	unset( $items['downloads'] );
	unset( $items['track-orders'] ); // Removed from nav; "Track Order" button lives in the orders table instead.
	return $items;
}

// ── Email header: expose the WC_Email object to emails/email-header.php ───────
// WooCommerce's WC_Emails::email_header() accepts only $email_heading and does
// NOT forward the $email object to the email-header.php template — even though
// every email body template fires do_action( 'woocommerce_email_header',
// $email_heading, $email ) with the object as the second argument. Our client
// header keys its hero icon/subline (and the E01 feature-strip placement) off
// $email->id, so we capture the object here at priority 1 (before WC's own
// priority-10 callback renders the template) and expose it via a global that
// email-header.php reads as a fallback.
add_action( 'woocommerce_email_header', 'bp_capture_email_header_object', 1, 2 );
/**
 * Stash the current WC_Email so email-header.php can read it.
 *
 * @param string         $email_heading The heading (unused here).
 * @param WC_Email|null  $email         The email object being sent.
 * @return void
 */
function bp_capture_email_header_object( $email_heading, $email = null ) {
	$GLOBALS['bp_email_header_object'] = $email;
}

// PWA functionality is handled by the BabyPasa PWA plugin (babypasa-pwa).
//
// The Nextend Social Login PWA standalone "Continue…" auto-follow fallback was
// moved to the BabyPasa PWA plugin (bp_nsl_pwa_continue_autofollow in
// babypasa-pwa.php) on 2026-06-05, where it lives alongside BP_PWA_Auth_Redirect.
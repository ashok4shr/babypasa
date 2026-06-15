<?php
/**
 * Plugin Name: Baby Pasa Wishlist & Compare
 * Description: Custom implementation for WooCommerce Wishlist (My Account) and Compare (max 3 items).
 * Version: 1.0.0
 * Author: Ashok Shrestha
 * Text Domain: babypasa-wishlist-compare
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Enqueue Scripts
add_action( 'wp_enqueue_scripts', 'bp_wishlist_compare_scripts' );
function bp_wishlist_compare_scripts() {
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_style( 'bp-wishlist-compare', plugin_dir_url( __FILE__ ) . 'assets/css/wishlist-compare.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/wishlist-compare.css' ) );
        wp_enqueue_script( 'bp-wishlist-compare', plugin_dir_url( __FILE__ ) . 'assets/js/wishlist-compare.js', array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/wishlist-compare.js' ), true );

        // Localize script
        wp_localize_script( 'bp-wishlist-compare', 'bpWishlistCompare', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'bp_wishlist_compare_nonce' ),
            'is_user_logged_in' => is_user_logged_in(),
            'login_url' => wc_get_page_permalink( 'myaccount' ),
            'wishlist_url' => wc_get_endpoint_url( 'wishlist', '', wc_get_page_permalink( 'myaccount' ) ),
            'wishlist' => is_user_logged_in() ? ( get_user_meta( get_current_user_id(), '_bp_wishlist', true ) ?: array() ) : array(),
        ) );
    }
}

// 2. Wishlist AJAX Handlers
add_action( 'wp_ajax_bp_toggle_wishlist', 'bp_ajax_toggle_wishlist' );
function bp_ajax_toggle_wishlist() {
    check_ajax_referer( 'bp_wishlist_compare_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Please log in to use the wishlist.' ) );
    }

    $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
    if ( ! $product_id ) {
        wp_send_json_error( array( 'message' => 'Invalid product.' ) );
    }

    $user_id = get_current_user_id();
    $wishlist = get_user_meta( $user_id, '_bp_wishlist', true );
    if ( ! is_array( $wishlist ) ) {
        $wishlist = array();
    }

    // Ensure wishlist array is just values (product IDs)
    $wishlist = array_values( array_map( 'intval', $wishlist ) );
    
    $is_in_wishlist = in_array( $product_id, $wishlist, true );

    if ( $is_in_wishlist ) {
        // Remove item
        $wishlist = array_values( array_diff( $wishlist, array( $product_id ) ) );
        $action = 'removed';
    } else {
        // Add item
        $wishlist[] = $product_id;
        $action = 'added';
    }

    update_user_meta( $user_id, '_bp_wishlist', $wishlist );

    wp_send_json_success( array( 'action' => $action, 'wishlist' => $wishlist ) );
}

// 3. WooCommerce My Account Wishlist Tab
add_action( 'init', 'bp_add_wishlist_endpoint' );
function bp_add_wishlist_endpoint() {
    add_rewrite_endpoint( 'wishlist', EP_ROOT | EP_PAGES );
}

register_activation_hook( __FILE__, function() {
    bp_add_wishlist_endpoint();
    flush_rewrite_rules();
} );

add_filter( 'woocommerce_account_menu_items', 'bp_wishlist_account_menu_item' );
function bp_wishlist_account_menu_item( $items ) {
    $new_items = array();
    foreach ( $items as $key => $item ) {
        $new_items[ $key ] = $item;
        if ( 'orders' === $key ) {
            $new_items['wishlist'] = 'My Wishlist';
        }
    }
    return $new_items ?: $items;
}

add_action( 'woocommerce_account_wishlist_endpoint', 'bp_wishlist_endpoint_content' );
function bp_wishlist_endpoint_content() {
    $wishlist = get_user_meta( get_current_user_id(), '_bp_wishlist', true );
    if ( empty( $wishlist ) || ! is_array( $wishlist ) ) {
        echo '<p>Your wishlist is empty.</p>';
        return;
    }

    echo '<h2>My Wishlist</h2>';
    
    // Display wishlist in row format similar to the cart page
    echo '<div class="bp-wishlist-table-wrapper">';
    echo '<table class="bp-wishlist-table shop_table shop_table_responsive cart">';
    echo '<thead><tr><th class="product-name">Product</th><th class="product-price">Price</th></tr></thead>';
    echo '<tbody>';
    foreach ( $wishlist as $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) continue;
        
        $thumbnail = $product->get_image('woocommerce_thumbnail');
        $title = $product->get_name();
        $price = $product->get_price_html();
        $desc = apply_filters( 'woocommerce_short_description', $product->get_short_description() );
        $permalink = $product->get_permalink();

        echo '<tr class="wishlist-item bp-wishlist-row" data-product_id="' . esc_attr($product_id) . '">';
        
        // Product Column
        echo '<td class="product-name" data-title="Product">';
        echo '<div class="bp-wishlist-item-flex">';
        echo '<div class="bp-wishlist-thumbnail"><a href="'.esc_url($permalink).'">'.$thumbnail.'</a></div>';
        echo '<div class="bp-wishlist-info">';
        echo '<a href="'.esc_url($permalink).'" class="bp-wishlist-title">'.esc_html($title).'</a>';
        echo '<div class="bp-wishlist-mobile-price" style="display:none;">'.$price.'</div>';
        if ($desc) { 
            // Stripping tags to make the description plain text like screenshot
            echo '<div class="bp-wishlist-desc">'.wp_trim_words(wp_strip_all_tags($desc), 15).'</div>'; 
        }
        
        echo '<div class="bp-wishlist-item-actions">';
        // Render add to cart button natively
        woocommerce_template_loop_add_to_cart( array( 'product' => $product ) );
        
        // Render trash icon (functions as wishlist toggle to remove)
        echo '<button class="bp-wishlist-btn bp-wishlist-remove in-wishlist" data-product_id="'.esc_attr($product_id).'" title="Remove from Wishlist"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>';
        echo '</div>'; // close actions
        
        echo '</div>'; // close info
        echo '</div>'; // close flex
        echo '</td>';
        
        // Price Column
        echo '<td class="product-price" data-title="Price">';
        echo $price;
        echo '</td>';
        
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// 4. Compare Shortcode and AJAX Render
add_shortcode( 'bp_compare', 'bp_compare_shortcode' );
function bp_compare_shortcode() {
    return '<div id="bp-compare-table-container"><div class="bp-loading">Loading Compare Data...</div></div>';
}

add_action( 'wp_ajax_nopriv_bp_get_compare_data', 'bp_get_compare_data' );
add_action( 'wp_ajax_bp_get_compare_data', 'bp_get_compare_data' );
function bp_get_compare_data() {
    check_ajax_referer( 'bp_wishlist_compare_nonce', 'nonce' );

    $product_ids = isset( $_POST['product_ids'] ) ? array_map( 'intval', (array) $_POST['product_ids'] ) : array();

    if ( empty( $product_ids ) ) {
        wp_send_json_error( '<p>No products to compare. Return to the <a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">shop</a> and add some.</p>' );
    }

    // Limit to 3 items
    $product_ids = array_slice( $product_ids, 0, 3 );

    ob_start();
    ?>
    <div class="bp-compare-table-wrapper">
        <table class="bp-compare-table">
            <thead>
                <tr>
                    <th class="bp-compare-feature-column">Product</th>
                    <?php foreach ( $product_ids as $id ) : 
                        $product = wc_get_product( $id );
                        if ( ! $product ) continue;
                    ?>
                        <td class="bp-compare-product-column" data-product_id="<?php echo esc_attr($id); ?>">
                            <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" class="bp-compare-product-link">
                                <?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
                                <h4 class="bp-compare-title"><?php echo esc_html( $product->get_name() ); ?></h4>
                            </a>
                            <div class="bp-compare-price"><?php echo $product->get_price_html(); ?></div>
                            <?php woocommerce_template_loop_add_to_cart( array( 'product' => $product ) ); ?>
                            <button class="bp-action-btn bp-compare-remove-btn" data-product_id="<?php echo esc_attr( $id ); ?>">&times; Remove</button>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th class="bp-compare-feature-column">Description</th>
                    <?php foreach ( $product_ids as $id ) : 
                        $product = wc_get_product( $id );
                        if ( ! $product ) continue;
                    ?>
                        <td class="bp-compare-product-column"><?php echo apply_filters( 'woocommerce_short_description', $product->get_short_description() ) ?: '-'; ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th class="bp-compare-feature-column">Stock Status</th>
                    <?php foreach ( $product_ids as $id ) : 
                        $product = wc_get_product( $id );
                        if ( ! $product ) continue;
                    ?>
                        <td class="bp-compare-product-column"><?php echo wc_get_stock_html( $product ) ?: ( $product->is_in_stock() ? '<p class="stock in-stock">In stock</p>' : '<p class="stock out-of-stock">Out of stock</p>' ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php
                // Get all unique attributes among the compared products
                $all_attributes = array();
                $product_objects = array();
                foreach ( $product_ids as $id ) {
                    $product = wc_get_product( $id );
                    if ( ! $product ) continue;
                    $product_objects[$id] = $product;
                    
                    if ( $product instanceof WC_Product_Variable ) {
                        $attributes = $product->get_variation_attributes();
                        // For variations, it's slightly different, but we'll get parent attributes
                        // as a fallback if get_attributes doesn't cover them.
                        // get_attributes() covers visible ones natively.
                    }
                    
                    foreach ( $product->get_attributes() as $attr_name => $attr_obj ) {
                        $all_attributes[ $attr_name ] = $attr_obj;
                    }
                }

                foreach ( $all_attributes as $attr_name => $attr_obj ) {
                    $taxonomy = get_taxonomy( $attr_name );
                    $label = $taxonomy ? $taxonomy->labels->singular_name : wc_attribute_label( $attr_name );
                    echo '<tr>';
                    echo '<th class="bp-compare-feature-column">' . esc_html( $label ) . '</th>';
                    foreach ( $product_ids as $id ) {
                        echo '<td class="bp-compare-product-column">';
                        if ( isset( $product_objects[$id] ) ) {
                            $product = $product_objects[$id];
                            $attr = $product->get_attribute( $attr_name );
                            if ( $attr ) {
                                echo wp_kses_post( wpautop( wptexturize( $attr ) ) );
                            } else {
                                echo '-';
                            }
                        } else {
                            echo '-';
                        }
                        echo '</td>';
                    }
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success( $html );
}

// 5. Sidebar Widget Synchronization
add_action( 'wp_ajax_nopriv_bp_get_sidebar_widget_data', 'bp_get_sidebar_widget_data' );
add_action( 'wp_ajax_bp_get_sidebar_widget_data', 'bp_get_sidebar_widget_data' );
function bp_get_sidebar_widget_data() {
    check_ajax_referer( 'bp_wishlist_compare_nonce', 'nonce' );

    $wishlist_ids = isset( $_POST['wishlist_ids'] ) ? array_map( 'intval', (array) $_POST['wishlist_ids'] ) : array();
    $compare_ids = isset( $_POST['compare_ids'] ) ? array_map( 'intval', (array) $_POST['compare_ids'] ) : array();

    $response = array(
        'wishlist' => bp_render_sidebar_list_html( $wishlist_ids, 'wishlist', wc_get_account_endpoint_url('wishlist'), 'WISHLIST' ),
        'compare' => bp_render_sidebar_list_html( $compare_ids, 'compare', home_url('/compare'), 'COMPARE' ),
        'wishlist_count' => count( $wishlist_ids ),
        'compare_count' => count( $compare_ids )
    );

    wp_send_json_success( $response );
}

function bp_render_sidebar_list_html( $ids, $type, $action_url, $action_label ) {
    if ( empty( $ids ) ) {
        return '<p class="bp-sidebar-empty">You have no items in your ' . ( $type === 'wishlist' ? 'wish list' : 'compare list' ) . '.</p>';
    }

    ob_start();
    echo '<ul class="bp-sidebar-dynamic-list">';
    foreach ( $ids as $id ) {
        $product = wc_get_product( $id );
        if ( ! $product ) continue;
        
        echo '<li>';
        echo '<button class="bp-sidebar-remove-item" data-type="' . esc_attr($type) . '" data-product_id="' . esc_attr($id) . '" aria-label="Remove ' . esc_attr( $product->get_name() ) . '"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>';
        echo '<a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( wp_trim_words($product->get_name(), 8) ) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
    
    echo '<div class="bp-sidebar-list-actions">';
    echo '<a href="' . esc_url( $action_url ) . '" class="bp-sidebar-view-btn">' . esc_html( $action_label ) . '</a>';
    echo '<button class="bp-sidebar-clear-all" data-type="' . esc_attr($type) . '">Clear All</button>';
    echo '</div>';
    
    return ob_get_clean();
}

add_action( 'wp_ajax_bp_clear_wishlist', 'bp_ajax_clear_wishlist' );
function bp_ajax_clear_wishlist() {
    check_ajax_referer( 'bp_wishlist_compare_nonce', 'nonce' );

    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        update_user_meta( $user_id, '_bp_wishlist', array() );
        wp_send_json_success();
    } else {
        wp_send_json_error();
    }
}


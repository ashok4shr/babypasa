<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BP_Price_Drop_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_bp_subscribe_price_drop', array( $this, 'ajax_subscribe' ) );
        add_action( 'wp_ajax_nopriv_bp_subscribe_price_drop', array( $this, 'ajax_nopriv_subscribe' ) );
    }

    public function enqueue_scripts() {
        if ( is_product() || is_account_page() ) {
            wp_enqueue_style( 'bp-price-drop-css', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/price-drop.css', array(), filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/css/price-drop.css' ) );
            wp_enqueue_script( 'bp-price-drop', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/price-drop.js', array( 'jquery' ), filemtime( plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/price-drop.js' ), true );
            wp_localize_script( 'bp-price-drop', 'bp_price_drop_params', array(
                'ajax_url'     => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'bp_price_drop_nonce' ),
                'is_logged_in' => is_user_logged_in(),
                'account_url'  => wc_get_account_endpoint_url( 'price-drop-alerts' )
            ) );
        }
    }

    public function ajax_nopriv_subscribe() {
        wp_send_json_error( array( 'message' => 'Please log in to use this feature.' ) );
    }

    public function ajax_subscribe() {
        check_ajax_referer( 'bp_price_drop_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $user_id = get_current_user_id();

        if ( ! $product_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => 'Product not found.' ) );
        }

        $current_price = $product->get_price();
        $meta_key = '_bp_price_alert_' . $product_id;

        update_user_meta( $user_id, $meta_key, $current_price );

        wp_send_json_success( array( 'message' => 'Subscribed successfully.' ) );
    }
}

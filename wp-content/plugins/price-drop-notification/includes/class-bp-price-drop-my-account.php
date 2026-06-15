<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BP_Price_Drop_My_Account {

    public function __construct() {
        add_action( 'init', array( $this, 'add_my_account_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_my_account_menu_item' ) );
        add_action( 'woocommerce_account_price-drop-alerts_endpoint', array( $this, 'my_account_endpoint_content' ) );
        
        add_action( 'wp_ajax_bp_remove_price_drop_alert', array( $this, 'ajax_remove' ) );
    }

    public function add_my_account_endpoint() {
        add_rewrite_endpoint( 'price-drop-alerts', EP_ROOT | EP_PAGES );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'price-drop-alerts';
        return $vars;
    }

    public function add_my_account_menu_item( $items ) {
        $new_items = array();
        foreach ( $items as $key => $value ) {
            $new_items[ $key ] = $value;
            if ( $key === 'edit-account' ) {
                $new_items['price-drop-alerts'] = 'Price Drop Alerts';
            }
        }
        if ( ! isset( $new_items['price-drop-alerts'] ) ) {
            $new_items['price-drop-alerts'] = 'Price Drop Alerts';
        }
        return $new_items;
    }

    public function ajax_remove() {
        check_ajax_referer( 'bp_price_drop_nonce', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $user_id = get_current_user_id();

        if ( ! $product_id || ! $user_id ) {
            wp_send_json_error( array( 'message' => 'Invalid request.' ) );
        }

        $meta_key = '_bp_price_alert_' . $product_id;
        delete_user_meta( $user_id, $meta_key );

        wp_send_json_success( array( 'message' => 'Removed successfully.' ) );
    }

    public function my_account_endpoint_content() {
        $user_id = get_current_user_id();
        $all_meta = get_user_meta( $user_id );
        $alerts = array();

        foreach ( $all_meta as $key => $value ) {
            if ( strpos( $key, '_bp_price_alert_' ) === 0 ) {
                $product_id = str_replace( '_bp_price_alert_', '', $key );
                $alerts[ $product_id ] = $value[0]; 
            }
        }

        echo '<h3>Price Drop Alerts</h3>';

        if ( empty( $alerts ) ) {
            echo '<p>You are not monitoring any products for price drops.</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
        echo '<thead><tr>';
        echo '<th class="woocommerce-orders-table__header"><span class="nobr">Product</span></th>';
        echo '<th class="woocommerce-orders-table__header"><span class="nobr">Subscribed Price</span></th>';
        echo '<th class="woocommerce-orders-table__header"><span class="nobr">Current Price</span></th>';
        echo '<th class="woocommerce-orders-table__header"><span class="nobr">Actions</span></th>';
        echo '</tr></thead><tbody>';

        foreach ( $alerts as $product_id => $subscribed_price ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) continue;

            $current_price = $product->get_price();

            echo '<tr class="woocommerce-orders-table__row bp-alert-row-' . esc_attr( $product_id ) . '">';
            echo '<td class="woocommerce-orders-table__cell" data-title="Product"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></td>';
            echo '<td class="woocommerce-orders-table__cell" data-title="Subscribed Price">' . wc_price( $subscribed_price ) . '</td>';
            echo '<td class="woocommerce-orders-table__cell" data-title="Current Price">' . wc_price( $current_price ) . '</td>';
            echo '<td class="woocommerce-orders-table__cell" data-title="Actions">';
            echo '<button class="button bp-remove-alert-btn" data-product_id="' . esc_attr( $product_id ) . '">Remove</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}

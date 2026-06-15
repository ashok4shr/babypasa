<?php
/**
 * Plugin Name: Price Drop Notification
 * Description: Allows logged-in users to subscribe to price drop alerts for specific products.
 * Version: 1.0.0
 * Author: Ashok Shrestha
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BP_Price_Drop_Notification {

    public function __construct() {
        $this->includes();
        $this->init_classes();

        // Automatic rewrite flush to fix 404s
        add_action( 'init', array( $this, 'flush_rewrites' ), 99 );
    }

    private function includes() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-bp-price-drop-frontend.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-bp-price-drop-my-account.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-bp-price-drop-backend.php';
    }

    private function init_classes() {
        new BP_Price_Drop_Frontend();
        new BP_Price_Drop_My_Account();
        new BP_Price_Drop_Backend();
    }

    public function flush_rewrites() {
        if ( ! get_option( 'bp_price_drop_flushed' ) ) {
            flush_rewrite_rules();
            update_option( 'bp_price_drop_flushed', 1 );
        }
    }
}

new BP_Price_Drop_Notification();


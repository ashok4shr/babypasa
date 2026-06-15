<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BP_Price_Drop_Backend {

    public function __construct() {
        add_action( 'woocommerce_update_product', array( $this, 'check_for_price_drop' ), 10, 2 );
    }

    public function check_for_price_drop( $product_id, $product ) {
        $new_price = $product->get_price();

        $meta_key = '_bp_price_alert_' . $product_id;
        $users = get_users( array(
            'meta_key' => $meta_key
        ) );

        if ( empty( $users ) ) {
            return;
        }

        foreach ( $users as $user ) {
            $subscribed_price = get_user_meta( $user->ID, $meta_key, true );

            if ( $new_price !== '' && $subscribed_price !== '' && (float) $new_price < (float) $subscribed_price ) {
                $this->send_notification_email( $user, $product, $new_price, $subscribed_price );
                delete_user_meta( $user->ID, $meta_key );
            }
        }
    }

    private function send_notification_email( $user, $product, $new_price, $old_price ) {
        $to = $user->user_email;
        $subject = 'Price Drop Alert: ' . $product->get_name();
        
        $message = sprintf( 
            "Hello %s,\n\nGreat news! The price of %s has dropped from %s to %s.\n\nGrab it now before it's gone: %s\n\nBest,\nBabyPasa Team",
            $user->display_name,
            $product->get_name(),
            wc_price( $old_price ),
            wc_price( $new_price ),
            $product->get_permalink()
        );

        wp_mail( $to, $subject, $message );
    }
}

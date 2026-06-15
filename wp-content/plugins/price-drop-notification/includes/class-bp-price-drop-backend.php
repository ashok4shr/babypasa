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
        // Send through the registered WC_Email so the alert uses the shared
        // BabyPasa HTML design (header/footer + CSS inliner) instead of the old
        // plain-text wp_mail() that leaked raw wc_price() markup.
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();

        if ( isset( $emails['BP_Price_Drop_Email'] ) ) {
            $emails['BP_Price_Drop_Email']->trigger( $user, $product, $new_price, $old_price );
        }
    }
}

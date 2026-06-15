<?php
/**
 * BabyPasa PWA — WooCommerce triggers.
 * Fires automatic push notifications on WooCommerce order status events.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BP_Push_Triggers {

    public function __construct() {
        add_action( 'woocommerce_order_status_processing', [ $this, 'order_confirmed' ], 20, 1 );
        add_action( 'woocommerce_order_status_completed',  [ $this, 'order_completed' ], 20, 1 );
        add_action( 'woocommerce_order_status_on-hold',    [ $this, 'order_on_hold' ],   20, 1 );
        add_action( 'woocommerce_order_status_cancelled',  [ $this, 'order_cancelled' ], 20, 1 );
    }

    public function order_confirmed( int $order_id ): void {
        $order   = wc_get_order( $order_id );
        $user_id = $this->customer_id( $order );
        if ( ! $user_id ) return;
        $title = '🎉 Order Confirmed!';
        $body  = "Your order #{$order->get_order_number()} is confirmed and being prepared.";
        $url   = '/my-account/orders/';
        $result = BP_Push_Sender::send_to_user( $user_id, $title, $body, $url );
        $this->log( $title, $body, $url, $result );
    }

    public function order_completed( int $order_id ): void {
        $order   = wc_get_order( $order_id );
        $user_id = $this->customer_id( $order );
        if ( ! $user_id ) return;
        $title = '📦 Your Order is On Its Way!';
        $body  = "Order #{$order->get_order_number()} has shipped. You'll receive it soon!";
        $url   = '/my-account/orders/';
        $result = BP_Push_Sender::send_to_user( $user_id, $title, $body, $url );
        $this->log( $title, $body, $url, $result );
    }

    public function order_on_hold( int $order_id ): void {
        $order   = wc_get_order( $order_id );
        $user_id = $this->customer_id( $order );
        if ( ! $user_id ) return;
        $title = '⏳ Order On Hold';
        $body  = "Order #{$order->get_order_number()} is on hold. Check your account for details.";
        $url   = '/my-account/orders/';
        $result = BP_Push_Sender::send_to_user( $user_id, $title, $body, $url );
        $this->log( $title, $body, $url, $result );
    }

    public function order_cancelled( int $order_id ): void {
        $order   = wc_get_order( $order_id );
        $user_id = $this->customer_id( $order );
        if ( ! $user_id ) return;
        $title = '❌ Order Cancelled';
        $body  = "Order #{$order->get_order_number()} has been cancelled. Visit your account for details.";
        $url   = '/my-account/orders/';
        $result = BP_Push_Sender::send_to_user( $user_id, $title, $body, $url );
        $this->log( $title, $body, $url, $result );
    }

    private function customer_id( $order ): int {
        if ( ! $order instanceof WC_Order ) return 0;
        return (int) $order->get_customer_id();
    }

    private function log( string $title, string $body, string $url, array $result ): void {
        $log = get_option( 'bp_push_send_log', [] );
        array_unshift( $log, [
            'date'    => current_time( 'mysql' ),
            'type'    => 'order',
            'title'   => $title,
            'body'    => $body,
            'url'     => $url,
            'sent'    => $result['sent']    ?? 0,
            'failed'  => $result['failed']  ?? 0,
            'removed' => $result['removed'] ?? 0,
        ] );
        update_option( 'bp_push_send_log', array_slice( $log, 0, 50 ) );
    }
}

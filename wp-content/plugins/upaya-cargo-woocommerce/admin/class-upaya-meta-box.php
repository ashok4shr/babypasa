<?php
/**
 * "Upaya Cargo" meta box on the WooCommerce order edit screen.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds an "Upaya Cargo" sidebar meta box to the WC order edit screen showing
 * submission status, live tracking data, and action buttons.
 */
class UPAYA_Meta_Box {

	/** @var UPAYA_Order_Manager */
	private UPAYA_Order_Manager $order_manager;

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		$this->order_manager = new UPAYA_Order_Manager();

		// Legacy order screen.
		add_action( 'add_meta_boxes_shop_order', [ $this, 'register_meta_box' ] );

		// HPOS order screen.
		add_action( 'add_meta_boxes_woocommerce_page_wc-orders', [ $this, 'register_meta_box' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		add_action( 'wp_ajax_upaya_resubmit_order',   [ $this, 'ajax_resubmit_order' ] );
		add_action( 'wp_ajax_upaya_refresh_tracking', [ $this, 'ajax_refresh_tracking' ] );
	}

	/* ------------------------------------------------------------------
	 * Meta box registration & render
	 * ------------------------------------------------------------------ */

	/**
	 * Registers the meta box on order edit screens.
	 *
	 * @return void
	 */
	public function register_meta_box(): void {
		foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
			add_meta_box(
				'upaya_cargo_meta_box',
				__( 'Upaya Cargo', 'upaya-cargo-woocommerce' ),
				[ $this, 'render_meta_box' ],
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Renders the meta box HTML by delegating to the view template.
	 *
	 * @param  \WP_Post|\WC_Order $post_or_order Post or order object.
	 * @return void
	 */
	public function render_meta_box( $post_or_order ): void {
		$order         = $post_or_order instanceof \WC_Order
			? $post_or_order
			: wc_get_order( (int) $post_or_order->ID );
		$order_id      = $order ? $order->get_id() : 0;
		$upaya_id      = $order ? $order->get_meta( '_upaya_order_id' )  : '';
		$submitted     = $order ? (bool) $order->get_meta( '_upaya_submitted' ) : false;
		$db_status     = $this->get_db_status( $order_id );
		$display_status = $db_status ?: ( $submitted ? 'submitted' : 'pending' );
		$nonce         = wp_create_nonce( 'upaya_meta_box_' . $order_id );
		$tracking      = $this->order_manager->get_tracking_info( $order_id );
		$order_manager = $this->order_manager;

		include UPAYA_PLUGIN_DIR . 'admin/views/order-tracking-box.php';
	}

	/* ------------------------------------------------------------------
	 * AJAX handlers
	 * ------------------------------------------------------------------ */

	/**
	 * AJAX: re-submits an order to Upaya after clearing the submission flag.
	 *
	 * @return void
	 */
	public function ajax_resubmit_order(): void {
		$order_id = absint( $_POST['order_id'] ?? 0 );
		check_ajax_referer( 'upaya_meta_box_' . $order_id, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'upaya-cargo-woocommerce' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Order not found.', 'upaya-cargo-woocommerce' ) );
		}

		$order->delete_meta_data( '_upaya_submitted' );
		$order->delete_meta_data( '_upaya_order_id' );
		$order->delete_meta_data( '_upaya_reference_id' );
		$order->save();

		$this->order_manager->submit_order_to_upaya( $order_id );

		$order     = wc_get_order( $order_id ); // Reload to read freshly-saved meta.
		$upaya_id  = $order ? $order->get_meta( '_upaya_order_id' ) : '';
		$submitted = $order ? (bool) $order->get_meta( '_upaya_submitted' ) : false;

		if ( $submitted && $upaya_id ) {
			wp_send_json_success( sprintf(
				/* translators: %s: tracking ID */
				__( 'Submitted. Tracking ID: %s', 'upaya-cargo-woocommerce' ),
				$upaya_id
			) );
		} else {
			wp_send_json_error( __( 'Submission failed. Check order notes for details.', 'upaya-cargo-woocommerce' ) );
		}
	}

	/**
	 * AJAX: refreshes tracking data (clears the 15-min transient first).
	 *
	 * @return void
	 */
	public function ajax_refresh_tracking(): void {
		$order_id = absint( $_POST['order_id'] ?? 0 );
		check_ajax_referer( 'upaya_meta_box_' . $order_id, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'upaya-cargo-woocommerce' ) );
		}

		delete_transient( 'upaya_track_' . $order_id );

		$tracking = $this->order_manager->get_tracking_info( $order_id );

		if ( is_wp_error( $tracking ) ) {
			wp_send_json_error( $tracking->get_error_message() );
		}

		wp_send_json_success( [
			'status'             => esc_html( $tracking['status']             ?? '' ),
			'estimated_delivery' => esc_html( $tracking['estimated_delivery'] ?? '' ),
			'items'              => array_map( static function ( $item ) {
				return [
					'name'     => esc_html( $item['name']     ?? '' ),
					'quantity' => esc_html( $item['quantity'] ?? '' ),
					'price'    => esc_html( $item['price']    ?? '' ),
				];
			}, $tracking['items'] ?? [] ),
		] );
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueues the meta box script and stylesheet on order edit screens.
	 *
	 * @param  string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$order_screens = [ 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ];

		if ( ! in_array( $hook, $order_screens, true ) ) {
			return;
		}

		global $post;
		if ( isset( $post ) && 'shop_order' !== $post->post_type ) {
			return;
		}

		wp_enqueue_script(
			'upaya-admin',
			UPAYA_PLUGIN_URL . 'assets/js/upaya-admin.js',
			[ 'jquery' ],
			filemtime( UPAYA_PLUGIN_DIR . 'assets/js/upaya-admin.js' ),
			true
		);

		wp_localize_script( 'upaya-admin', 'upayaAdmin', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'context'  => 'meta_box',
			'i18n'     => [
				'submitting' => __( 'Submitting…', 'upaya-cargo-woocommerce' ),
				'refreshing' => __( 'Refreshing…', 'upaya-cargo-woocommerce' ),
			],
		] );

		wp_enqueue_style(
			'upaya-admin',
			UPAYA_PLUGIN_URL . 'assets/css/upaya-admin.css',
			[],
			filemtime( UPAYA_PLUGIN_DIR . 'assets/css/upaya-admin.css' )
		);
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Reads the submission status from the upaya_orders custom table.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return string|null Status string, or null if no record exists.
	 */
	private function get_db_status( int $order_id ): ?string {
		global $wpdb;

		$table = $wpdb->prefix . 'upaya_orders';

		$status = $wpdb->get_var(
			$wpdb->prepare( "SELECT status FROM {$table} WHERE wc_order_id = %d LIMIT 1", $order_id )
		);

		return $status ?: null;
	}
}

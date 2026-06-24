<?php
/**
 * Admin settings page and AJAX handlers for Upaya Cargo.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers a "Upaya Cargo" tab inside WooCommerce > Settings and handles
 * all related AJAX endpoints.
 */
class UPAYA_Admin {

	/** @var UPAYA_Logger */
	private UPAYA_Logger $logger;

	/** @var UPAYA_API */
	private UPAYA_API $api;

	/** @var UPAYA_Location_Cache */
	private UPAYA_Location_Cache $location_cache;

	/**
	 * Constructor — wires up all admin hooks.
	 */
	public function __construct() {
		$this->logger         = new UPAYA_Logger();
		$this->api            = new UPAYA_API( get_option( 'upaya_api_key', '' ), $this->logger );
		$this->location_cache = new UPAYA_Location_Cache( $this->api, $this->logger );

		add_filter( 'woocommerce_settings_tabs_array',        [ $this, 'add_settings_tab' ], 60 );
		add_action( 'woocommerce_settings_tabs_upaya_cargo',  [ $this, 'output_settings' ] );
		add_action( 'woocommerce_update_options_upaya_cargo', [ $this, 'save_settings' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		add_action( 'wp_ajax_upaya_test_connection',     [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_upaya_sync_pending_orders', [ $this, 'ajax_sync_pending_orders' ] );
		add_action( 'wp_ajax_upaya_flush_location_cache', [ $this, 'ajax_flush_location_cache' ] );

		add_filter( 'plugin_action_links_' . UPAYA_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );

		// Warn on the settings tab when the daily refresh cron can't run.
		add_action( 'admin_notices', [ $this, 'maybe_show_cron_notice' ] );
	}

	/* ------------------------------------------------------------------
	 * Settings tab
	 * ------------------------------------------------------------------ */

	/**
	 * Adds the "Upaya Cargo" tab to the WooCommerce settings tabs array.
	 *
	 * @param  array<string,string> $tabs Existing tabs.
	 * @return array<string,string>
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['upaya_cargo'] = __( 'Upaya Cargo', 'upaya-cargo-woocommerce' );
		return $tabs;
	}

	/**
	 * Outputs the settings fields and custom action UI.
	 *
	 * @return void
	 */
	public function output_settings(): void {
		woocommerce_admin_fields( $this->get_settings() );

		$nonce          = wp_create_nonce( 'upaya_admin_action' );
		$location_cache = $this->location_cache;
		$pending_count  = $this->get_pending_order_count();

		include UPAYA_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Saves settings posted from our tab and flushes the location cache.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		woocommerce_update_options( $this->get_settings() );

		// Rebuild (flush + refill) so the area list is never left empty after a save —
		// the admin order-screen picker reads the cache directly with no lazy refill.
		$result = $this->location_cache->rebuild();
		if ( is_wp_error( $result ) ) {
			WC_Admin_Settings::add_error( sprintf(
				/* translators: %s: error message */
				__( 'Settings saved, but the Upaya location cache refresh failed: %s. The previous area list was kept.', 'upaya-cargo-woocommerce' ),
				$result->get_error_message()
			) );
		} elseif ( 0 === $result ) {
			WC_Admin_Settings::add_error(
				__( 'Settings saved, but Upaya returned no delivery areas. Verify your API key and account.', 'upaya-cargo-woocommerce' )
			);
		} else {
			WC_Admin_Settings::add_message( sprintf(
				/* translators: %d: number of areas */
				__( 'Settings saved. Upaya location cache refreshed — %d delivery area(s) loaded.', 'upaya-cargo-woocommerce' ),
				$result
			) );
		}
	}

	/**
	 * Returns the WooCommerce settings fields array for this tab.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_settings(): array {
		$location_options = [ '' => __( '— Select location —', 'upaya-cargo-woocommerce' ) ]
			+ $this->location_cache->get_locations_for_select();

		return [
			[
				'title' => __( 'Upaya Cargo Settings', 'upaya-cargo-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Configure your Upaya Cargo API credentials and default behaviour.', 'upaya-cargo-woocommerce' ),
				'id'    => 'upaya_settings_section_start',
			],
			[
				'title'    => __( 'API Key', 'upaya-cargo-woocommerce' ),
				'type'     => 'password',
				'desc'     => __( 'Your Upaya Cargo X-API-Key.', 'upaya-cargo-woocommerce' ),
				'id'       => 'upaya_api_key',
				'default'  => '',
				'desc_tip' => true,
			],
			[
				'title'   => __( 'Auto-Submit Orders', 'upaya-cargo-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Automatically submit orders to Upaya when status changes to "Processing".', 'upaya-cargo-woocommerce' ),
				'id'      => 'upaya_auto_submit',
				'default' => 'yes',
			],
			[
				'title'   => __( 'Debug Logging', 'upaya-cargo-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Enable verbose debug logging (WooCommerce > Status > Logs, source: upaya-cargo).', 'upaya-cargo-woocommerce' ),
				'id'      => 'upaya_debug_mode',
				'default' => 'no',
			],
			[
				'title'    => __( 'Default Pickup Location', 'upaya-cargo-woocommerce' ),
				'type'     => 'select',
				'desc'     => __( 'Default Upaya pickup location used when no zone override is configured.', 'upaya-cargo-woocommerce' ),
				'id'       => 'upaya_default_pickup_location',
				'default'  => '',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width: 300px;',
				'options'  => $location_options,
				'desc_tip' => true,
			],
			[
				'title'   => __( 'Retry Failed Submissions', 'upaya-cargo-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Schedule an automatic retry 1 hour after a failed Upaya submission.', 'upaya-cargo-woocommerce' ),
				'id'      => 'upaya_retry_failed_orders',
				'default' => 'yes',
			],
			[
				'type' => 'sectionend',
				'id'   => 'upaya_settings_section_end',
			],

			// ── Webhook ────────────────────────────────────────────────────
			[
				'title' => __( 'Webhook Settings', 'upaya-cargo-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Configure the status-update webhook that Upaya Cargo calls when a shipment status changes.', 'upaya-cargo-woocommerce' ),
				'id'    => 'upaya_webhook_section_start',
			],
			[
				'title'    => __( 'Webhook Secret', 'upaya-cargo-woocommerce' ),
				'type'     => 'password',
				'desc'     => __( 'Optional. If set, every incoming webhook request must supply this value in the X-Upaya-Webhook-Secret header.', 'upaya-cargo-woocommerce' ),
				'id'       => 'upaya_webhook_secret',
				'default'  => '',
				'desc_tip' => true,
			],
			[
				'title'    => __( 'Allowed Domains', 'upaya-cargo-woocommerce' ),
				'type'     => 'text',
				'desc'     => __( 'Optional comma-separated list of domains allowed to call the webhook (e.g. portal-api.upaya.com.np). Leave blank to allow any source.', 'upaya-cargo-woocommerce' ),
				'id'       => 'upaya_webhook_allowed_domains',
				'default'  => '',
				'css'      => 'min-width: 400px;',
				'desc_tip' => true,
			],
			[
				'title'   => __( 'Notify Customer', 'upaya-cargo-woocommerce' ),
				'type'    => 'checkbox',
				'desc'    => __( 'Send customers an email notification when Upaya pushes a delivery status update.', 'upaya-cargo-woocommerce' ),
				'id'      => 'upaya_webhook_notify_customer',
				'default' => 'yes',
			],
			[
				'type' => 'sectionend',
				'id'   => 'upaya_webhook_section_end',
			],
		];
	}

	/* ------------------------------------------------------------------
	 * AJAX handlers
	 * ------------------------------------------------------------------ */

	/**
	 * AJAX: tests the API connection by calling GET /locations.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'upaya_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'upaya-cargo-woocommerce' ) );
		}

		$this->location_cache->flush();
		$locations = $this->location_cache->get_locations();

		if ( empty( $locations ) ) {
			wp_send_json_error( __( 'API connection failed or returned no locations. Check your API key.', 'upaya-cargo-woocommerce' ) );
		}

		wp_send_json_success( sprintf(
			/* translators: %d: number of locations */
			__( 'Connection successful! %d location(s) available.', 'upaya-cargo-woocommerce' ),
			count( $locations )
		) );
	}

	/**
	 * AJAX: processes up to 20 pending-submission orders.
	 *
	 * @return void
	 */
	public function ajax_sync_pending_orders(): void {
		check_ajax_referer( 'upaya_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'upaya-cargo-woocommerce' ) );
		}

		$order_ids = $this->get_pending_order_ids( 20 );

		if ( empty( $order_ids ) ) {
			wp_send_json_success( __( 'No pending orders found.', 'upaya-cargo-woocommerce' ) );
		}

		$manager   = new UPAYA_Order_Manager();
		$submitted = 0;
		$failed    = 0;

		foreach ( $order_ids as $order_id ) {
			$manager->submit_order_to_upaya( (int) $order_id );
			$synced_order = wc_get_order( (int) $order_id );
			( $synced_order && $synced_order->get_meta( '_upaya_submitted' ) ) ? $submitted++ : $failed++;
		}

		wp_send_json_success( sprintf(
			/* translators: 1: submitted count  2: failed count */
			__( 'Sync complete. %1$d submitted, %2$d failed.', 'upaya-cargo-woocommerce' ),
			$submitted,
			$failed
		) );
	}

	/**
	 * AJAX: flushes the location cache.
	 *
	 * @return void
	 */
	public function ajax_flush_location_cache(): void {
		check_ajax_referer( 'upaya_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'upaya-cargo-woocommerce' ) );
		}

		// Flush AND refill in one synchronous step so the admin order-screen area
		// picker works immediately, with no frontend checkout visit required.
		$result = $this->location_cache->rebuild();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( sprintf(
				/* translators: %s: error message */
				__( 'Cache refresh failed: %s. Your previous area list was kept.', 'upaya-cargo-woocommerce' ),
				$result->get_error_message()
			) );
		}

		if ( 0 === $result ) {
			wp_send_json_error(
				__( 'Cache refreshed, but Upaya returned no delivery areas. Verify your API key and account.', 'upaya-cargo-woocommerce' )
			);
		}

		wp_send_json_success( sprintf(
			/* translators: %d: number of areas */
			__( 'Location cache refreshed — %d delivery area(s) loaded.', 'upaya-cargo-woocommerce' ),
			$result
		) );
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueues admin JS/CSS only on the Upaya Cargo settings tab.
	 *
	 * @param  string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( $_GET['tab'] ?? '' ) !== 'upaya_cargo' ) {
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
			'context'  => 'settings',
			'i18n'     => [
				'testing'  => __( 'Testing…',  'upaya-cargo-woocommerce' ),
				'syncing'  => __( 'Syncing…',  'upaya-cargo-woocommerce' ),
				'flushing' => __( 'Flushing…', 'upaya-cargo-woocommerce' ),
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
	 * Adds a "Settings" link to the plugins list table row.
	 *
	 * @param  array<int,string> $links Existing action links.
	 * @return array<int,string>
	 */
	public function plugin_action_links( array $links ): array {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=upaya_cargo' ) ) . '">'
				. esc_html__( 'Settings', 'upaya-cargo-woocommerce' )
				. '</a>'
		);
		return $links;
	}

	/**
	 * Warns, only on the Upaya Cargo settings tab, when the daily location-refresh
	 * cron cannot run: WP-Cron disabled, or the event is not scheduled.
	 *
	 * @return void
	 */
	public function maybe_show_cron_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( $_GET['page'] ?? '' ) !== 'wc-settings' || ( $_GET['tab'] ?? '' ) !== 'upaya_cargo' ) {
			return;
		}

		$messages = [];
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$messages[] = __( 'WP-Cron is disabled on this server (DISABLE_WP_CRON), so the daily Upaya location refresh will not run automatically. Use the “Flush Location Cache” button when areas change, or ensure a real system cron triggers wp-cron.php.', 'upaya-cargo-woocommerce' );
		}
		if ( false === wp_next_scheduled( 'upaya_refresh_location_cache' ) ) {
			$messages[] = __( 'The daily Upaya location refresh is not scheduled. Deactivate and reactivate the plugin to re-register it.', 'upaya-cargo-woocommerce' );
		}

		foreach ( $messages as $msg ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	/**
	 * Returns the count of "processing" WC orders not yet submitted to Upaya.
	 *
	 * @return int
	 */
	private function get_pending_order_count(): int {
		return count( $this->get_pending_order_ids( 9999 ) );
	}

	/**
	 * Returns WC order IDs in "processing" status that lack _upaya_submitted meta.
	 *
	 * @param  int $limit Max number of IDs to return.
	 * @return int[]
	 */
	private function get_pending_order_ids( int $limit ): array {
		$orders = wc_get_orders( [
			'status'     => 'processing',
			'limit'      => $limit,
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key'     => '_upaya_submitted',
					'compare' => 'NOT EXISTS',
				],
			],
			'return'     => 'ids',
		] );

		return array_map( 'intval', $orders );
	}
}

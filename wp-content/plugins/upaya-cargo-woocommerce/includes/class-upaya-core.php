<?php
/**
 * Core plugin loader — bootstraps every sub-system.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton loader for the Upaya Cargo plugin.
 *
 * Responsible for:
 *  - Loading all dependency files.
 *  - Registering the WooCommerce shipping method.
 *  - Instantiating all sub-components (admin, order manager, checkout, …).
 *  - Declaring HPOS compatibility.
 *  - Wiring the cron retry hook.
 */
final class UPAYA_Core {

	/** @var self|null */
	private static ?self $instance = null;

	/** Private constructor — use UPAYA_Core::instance(). */
	private function __construct() {}

	/**
	 * Returns the single plugin instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Runs after WooCommerce is confirmed loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_textdomain();
		$this->load_dependencies();
		$this->setup_hooks();
		$this->boot_components();
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'upaya-cargo-woocommerce',
			false,
			dirname( UPAYA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Requires all class files in dependency order.
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		$inc = UPAYA_PLUGIN_DIR . 'includes/';
		$adm = UPAYA_PLUGIN_DIR . 'admin/';

		require_once $inc . 'class-upaya-logger.php';
		require_once $inc . 'class-upaya-api.php';
		require_once $inc . 'class-upaya-location-cache.php';
		require_once $inc . 'class-upaya-shipping-method.php';
		require_once $inc . 'class-upaya-order-manager.php';
		require_once $inc . 'class-upaya-checkout.php';
		require_once $inc . 'class-upaya-webhook-processor.php';
		require_once $inc . 'class-upaya-webhook.php';
		require_once $adm . 'class-upaya-admin.php';
		require_once $adm . 'class-upaya-meta-box.php';
	}

	/**
	 * Registers plugin-level hooks that do not belong to a sub-component.
	 *
	 * @return void
	 */
	private function setup_hooks(): void {
		// Register the shipping method with WooCommerce.
		add_filter( 'woocommerce_shipping_methods', [ $this, 'register_shipping_method' ] );

		// Cron retry for failed order submissions.
		add_action( 'upaya_retry_order', [ $this, 'handle_retry_order' ] );

		// Declare HPOS (High-Performance Order Storage) compatibility.
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
	}

	/**
	 * Instantiates all sub-components so their hooks are registered.
	 *
	 * @return void
	 */
	private function boot_components(): void {
		new UPAYA_Order_Manager();
		new UPAYA_Checkout();
		new UPAYA_Meta_Box();
		new UPAYA_Webhook();

		if ( is_admin() ) {
			new UPAYA_Admin();
		}

		// Register our custom email with WooCommerce.
		add_filter( 'woocommerce_email_classes', [ $this, 'register_email_class' ] );
	}

	/**
	 * Adds UPAYA_Status_Email to the WooCommerce email class map.
	 *
	 * @param  array<string,WC_Email> $emails Existing WC email class instances.
	 * @return array<string,WC_Email>
	 */
	public function register_email_class( array $emails ): array {
		// Load here so WC_Email parent is guaranteed to exist.
		require_once UPAYA_PLUGIN_DIR . 'includes/emails/class-upaya-status-email.php';
		$emails['UPAYA_Status_Email'] = new UPAYA_Status_Email();
		return $emails;
	}

	/* ------------------------------------------------------------------
	 * Hook callbacks
	 * ------------------------------------------------------------------ */

	/**
	 * Adds UPAYA_Shipping_Method to the WooCommerce shipping methods list.
	 *
	 * @param  array<string,string> $methods Existing shipping method class names.
	 * @return array<string,string>
	 */
	public function register_shipping_method( array $methods ): array {
		$methods['upaya_cargo'] = 'UPAYA_Shipping_Method';
		return $methods;
	}

	/**
	 * Handles a scheduled single-order retry event.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_retry_order( int $order_id ): void {
		( new UPAYA_Order_Manager() )->submit_order_to_upaya( $order_id );
	}

	/**
	 * Declares compatibility with WooCommerce High-Performance Order Storage.
	 *
	 * @return void
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				UPAYA_PLUGIN_FILE,
				true
			);
		}
	}
}

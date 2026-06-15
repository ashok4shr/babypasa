<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Core bootstrapper for BP Ads Manager.
 *
 * Singleton. Initialises sub-components and registers activation/deactivation hooks.
 */
class BP_Ads_Manager {

	/** @var BP_Ads_Manager|null */
	private static $instance = null;

	/** @var BP_Ads_Admin|null */
	private $admin = null;

	/** @var BP_Ads_Ajax|null */
	private $ajax = null;

	/** @var BP_Ads_Renderer */
	private $renderer;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @return BP_Ads_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor — use get_instance(). */
	private function __construct() {
		$this->init();
	}

	/** Boots sub-components and registers hooks. */
	private function init() {
		// Run dbDelta if the stored DB schema version is behind the current one.
		if ( get_option( BP_Ads_DB::DB_OPTION ) !== BP_Ads_DB::DB_VERSION ) {
			BP_Ads_DB::create_table();
		}

		if ( is_admin() ) {
			$this->admin = new BP_Ads_Admin();
			$this->ajax  = new BP_Ads_Ajax();
		}

		$this->renderer = new BP_Ads_Renderer();
		$this->renderer->register_hooks();
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Creates the DB table and sets default options.
	 */
	public static function activate() {
		BP_Ads_DB::create_table();

		if ( false === get_option( 'bp_ads_settings' ) ) {
			add_option( 'bp_ads_settings', array( 'ads_enabled' => 1 ) );
		}
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Table and options are preserved; see uninstall.php for full cleanup.
	 */
	public static function deactivate() {
		// Intentionally empty — table persists across deactivation/reactivation.
	}
}

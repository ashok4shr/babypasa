<?php
/**
 * Core loader — boots the account-linking subsystems.
 *
 * @package BabyPasa_Account_Linking
 */

defined( 'ABSPATH' ) || exit;

final class BP_AL_Core {

	/** @var BP_AL_Core|null */
	private static $instance = null;

	public static function instance(): BP_AL_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();

		// Forward-looking: link a guest order to an existing account at checkout.
		new BP_AL_Checkout();

		// Redacts full address/phone in My Account order-details while a link is provisional.
		new BP_AL_Gating();

		// Order-edit "Confirm ownership" action, provisional notice, Orders-list column.
		if ( is_admin() ) {
			new BP_AL_Admin();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'babypasa guest-orders', 'BP_AL_CLI_Command' );
		}
	}

	private function includes(): void {
		require_once BP_AL_DIR . 'includes/class-bp-al-linker.php';
		require_once BP_AL_DIR . 'includes/class-bp-al-checkout.php';
		require_once BP_AL_DIR . 'includes/class-bp-al-gating.php';

		if ( is_admin() ) {
			require_once BP_AL_DIR . 'includes/class-bp-al-admin.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once BP_AL_DIR . 'includes/cli/class-bp-al-cli-command.php';
		}
	}
}

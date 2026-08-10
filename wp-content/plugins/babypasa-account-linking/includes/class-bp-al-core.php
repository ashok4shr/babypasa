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

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'babypasa guest-orders', 'BP_AL_CLI_Command' );
		}
	}

	private function includes(): void {
		require_once BP_AL_DIR . 'includes/class-bp-al-linker.php';
		require_once BP_AL_DIR . 'includes/class-bp-al-checkout.php';

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once BP_AL_DIR . 'includes/cli/class-bp-al-cli-command.php';
		}
	}
}

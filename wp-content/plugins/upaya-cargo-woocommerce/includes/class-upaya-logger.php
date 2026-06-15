<?php
/**
 * Wraps WC_Logger for Upaya Cargo log entries.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around WC_Logger that prepends a consistent source handle
 * and respects the upaya_debug_mode option for debug-level messages.
 */
class UPAYA_Logger {

	/** WooCommerce logger source handle. */
	const SOURCE = 'upaya-cargo';

	/** @var \WC_Logger_Interface */
	private \WC_Logger_Interface $wc_logger;

	/**
	 * Constructor — obtains the WC logger instance.
	 */
	public function __construct() {
		$this->wc_logger = wc_get_logger();
	}

	/**
	 * Logs a message at the given level.
	 *
	 * @param  string $message Log message.
	 * @param  string $level   WC_Log_Levels constant string (info, warning, error, debug…).
	 * @return void
	 */
	public function log( string $message, string $level = 'info' ): void {
		$this->wc_logger->log(
			$level,
			$message,
			[ 'source' => self::SOURCE ]
		);
	}

	/**
	 * Logs a debug message — only written when debug mode is enabled.
	 *
	 * @param  string $message Debug message.
	 * @return void
	 */
	public function debug( string $message ): void {
		if ( 'yes' !== get_option( 'upaya_debug_mode', 'no' ) ) {
			return;
		}
		$this->log( $message, 'debug' );
	}

	/**
	 * Logs an error message.
	 *
	 * @param  string $message Error message.
	 * @return void
	 */
	public function error( string $message ): void {
		$this->log( $message, 'error' );
	}

	/**
	 * Logs a warning message.
	 *
	 * @param  string $message Warning message.
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->log( $message, 'warning' );
	}
}

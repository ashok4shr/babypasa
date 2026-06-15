<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin WC_Logger wrapper. Writes to WooCommerce → Status → Logs (source: babypasa-connectips).
 * All logging is gated on the 'debug_mode' gateway setting.
 */
class BC_Logger {

	private static ?WC_Logger $wc = null;
	private static bool $debug    = false;

	public static function init( bool $debug ): void {
		self::$debug = $debug;
	}

	private static function logger(): WC_Logger {
		if ( null === self::$wc ) {
			self::$wc = wc_get_logger();
		}
		return self::$wc;
	}

	public static function info( string $message, array $context = [] ): void {
		self::logger()->info( $message, array_merge( [ 'source' => 'babypasa-connectips' ], $context ) );
	}

	public static function debug( string $message, array $context = [] ): void {
		if ( ! self::$debug ) {
			return;
		}
		self::logger()->debug( $message, array_merge( [ 'source' => 'babypasa-connectips' ], $context ) );
	}

	public static function error( string $message, array $context = [] ): void {
		self::logger()->error( $message, array_merge( [ 'source' => 'babypasa-connectips' ], $context ) );
	}
}

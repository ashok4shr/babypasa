<?php
/**
 * Protected storage for generated invoice PDFs + the Dompdf writable cache.
 *
 * PDFs live in wp-content/uploads/bp-invoices/ (never a guessable public URL);
 * the directory is created with an .htaccess deny + index.php guard, reusing the
 * pattern from the babypasa-seo MU-plugin. All paths derive from wp_upload_dir()
 * + trailingslashit() so they are Windows/Linux safe with no hardcoded separators.
 *
 * @package BabyPasa_Order_Emails
 */

defined( 'ABSPATH' ) || exit;

class BP_Invoice_PDF_Storage {

	const SUBDIR = 'bp-invoices';

	/**
	 * Absolute path to the protected invoices directory (created on demand).
	 *
	 * @return string|null Trailing-slashed path, or null if uploads is unavailable.
	 */
	public static function base_dir(): ?string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		$dir = trailingslashit( trailingslashit( $uploads['basedir'] ) . self::SUBDIR );
		if ( ! self::ensure_protected( $dir ) ) {
			return null;
		}
		return $dir;
	}

	/**
	 * Writable cache/temp directory for Dompdf (font cache + temp files).
	 * Kept off the committed lib/ tree so it works on read-only prod deploys.
	 *
	 * @return string|null
	 */
	public static function cache_dir(): ?string {
		$base = self::base_dir();
		if ( null === $base ) {
			return null;
		}
		$dir = trailingslashit( $base . 'cache' );
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}
		return $dir;
	}

	/**
	 * Create a directory (if needed) and drop an .htaccess deny + index.php guard.
	 *
	 * @param string $dir Trailing-slashed absolute path.
	 * @return bool Whether the directory exists and is protected.
	 */
	public static function ensure_protected( string $dir ): bool {
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		if ( ! file_exists( $dir . '.htaccess' ) ) {
			// Block direct web access + directory listing (Apache 2.4 and 2.2).
			@file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
				$dir . '.htaccess',
				"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n"
			);
		}
		if ( ! file_exists( $dir . 'index.php' ) ) {
			@file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		}

		return true;
	}

	/** Deterministic filename for an order: invoice-{order_number}.pdf. */
	public static function filename( WC_Order $order ): string {
		return 'invoice-' . sanitize_file_name( $order->get_order_number() ) . '.pdf';
	}

	/**
	 * Absolute path where an order's PDF is (or would be) stored.
	 *
	 * @return string|null
	 */
	public static function path_for_order( WC_Order $order ): ?string {
		$base = self::base_dir();
		return $base ? $base . self::filename( $order ) : null;
	}

	/**
	 * Persist PDF bytes for an order (overwrite in place).
	 *
	 * @param WC_Order $order Order.
	 * @param string   $bytes PDF binary.
	 * @return string|null Absolute file path on success, null on failure.
	 */
	public static function save( WC_Order $order, string $bytes ): ?string {
		$path = self::path_for_order( $order );
		if ( null === $path ) {
			return null;
		}
		$written = @file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
		return ( false === $written ) ? null : $path;
	}

	/** Pre-create the protected directories (called on activation + admin_init). */
	public static function bootstrap(): void {
		self::base_dir();
		self::cache_dir();
	}
}

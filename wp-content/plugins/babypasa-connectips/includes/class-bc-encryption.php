<?php
defined( 'ABSPATH' ) || exit;

/**
 * AES-256-CBC encryption for sensitive gateway settings (PEM key, passphrase, auth password).
 * Encryption key is derived from WordPress AUTH_KEY so it is site-specific.
 */
class BC_Encryption {

	private static function key(): string {
		return substr( hash( 'sha256', AUTH_KEY . 'bc_enc_salt' ), 0, 32 );
	}

	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}
		$iv         = openssl_random_pseudo_bytes( 16 );
		$ciphertext = openssl_encrypt( $plaintext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $ciphertext );
	}

	public static function decrypt( string $encoded ): string {
		if ( '' === $encoded ) {
			return '';
		}
		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}
		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );
		$plain      = openssl_decrypt( $ciphertext, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}
}

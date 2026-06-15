<?php
/**
 * Data layer for the address book. All CRUD goes through this class.
 *
 * @package BabyPasa_Address_Book
 */

defined( 'ABSPATH' ) || exit;

class BP_Address_Book {

	const META_KEY      = '_bp_address_book';
	const MAX_ADDRESSES = 10;

	/**
	 * Returns all saved addresses for a user, newest first.
	 *
	 * @param int $user_id
	 * @return array[]
	 */
	public static function get_addresses( int $user_id ): array {
		$addresses = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $addresses ) ? $addresses : [];
	}

	/**
	 * Returns a single address by ID, or null if not found.
	 *
	 * @param int    $user_id
	 * @param string $address_id
	 * @return array|null
	 */
	public static function get_address( int $user_id, string $address_id ): ?array {
		foreach ( self::get_addresses( $user_id ) as $addr ) {
			if ( isset( $addr['id'] ) && $addr['id'] === $address_id ) {
				return $addr;
			}
		}
		return null;
	}

	/**
	 * Returns the default address, or null.
	 *
	 * @param int $user_id
	 * @return array|null
	 */
	public static function get_default_address( int $user_id ): ?array {
		foreach ( self::get_addresses( $user_id ) as $addr ) {
			if ( ! empty( $addr['is_default'] ) ) {
				return $addr;
			}
		}
		return null;
	}

	/**
	 * Save (create or update) an address.
	 *
	 * @param int   $user_id
	 * @param array $data Raw POST data; sanitized internally.
	 * @return string|WP_Error New/updated address ID on success, WP_Error on failure.
	 */
	public static function save_address( int $user_id, array $data ): string|WP_Error {
		$clean = self::sanitize_address_data( $data );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		$addresses  = self::get_addresses( $user_id );
		$address_id = sanitize_text_field( $data['address_id'] ?? '' );
		$now        = gmdate( 'c' );

		if ( $address_id ) {
			// Update existing.
			$found = false;
			foreach ( $addresses as &$addr ) {
				if ( $addr['id'] === $address_id ) {
					$clean['id']         = $address_id;
					$clean['created_at'] = $addr['created_at'];
					$clean['updated_at'] = $now;
					$addr                = $clean;
					$found               = true;
					break;
				}
			}
			unset( $addr );
			if ( ! $found ) {
				return new WP_Error( 'not_found', __( 'Address not found.', 'babypasa-address-book' ) );
			}
		} else {
			// Create new.
			if ( count( $addresses ) >= self::MAX_ADDRESSES ) {
				return new WP_Error(
					'limit_reached',
					sprintf(
						/* translators: %d: max address count */
						__( 'You can save up to %d addresses.', 'babypasa-address-book' ),
						self::MAX_ADDRESSES
					)
				);
			}
			$address_id          = uniqid( 'addr_', true );
			$clean['id']         = $address_id;
			$clean['created_at'] = $now;
			$clean['updated_at'] = $now;
			$addresses[]         = $clean;
		}

		// If this address is being set as default, clear the flag on all others.
		if ( $clean['is_default'] ) {
			foreach ( $addresses as &$addr ) {
				if ( $addr['id'] !== $address_id ) {
					$addr['is_default'] = false;
				}
			}
			unset( $addr );
		}

		update_user_meta( $user_id, self::META_KEY, $addresses );
		return $address_id;
	}

	/**
	 * Delete an address by ID. Cannot delete the default address.
	 *
	 * @param int    $user_id
	 * @param string $address_id
	 * @return bool
	 */
	public static function delete_address( int $user_id, string $address_id ): bool {
		$addresses = self::get_addresses( $user_id );
		$updated   = array_values(
			array_filter( $addresses, fn( $a ) => $a['id'] !== $address_id )
		);

		if ( count( $updated ) === count( $addresses ) ) {
			return false; // Nothing was removed.
		}

		update_user_meta( $user_id, self::META_KEY, $updated );
		return true;
	}

	/**
	 * Mark one address as default, clearing the flag on all others.
	 *
	 * @param int    $user_id
	 * @param string $address_id
	 * @return bool
	 */
	public static function set_default( int $user_id, string $address_id ): bool {
		$addresses = self::get_addresses( $user_id );
		$found     = false;

		foreach ( $addresses as &$addr ) {
			$addr['is_default'] = ( $addr['id'] === $address_id );
			if ( $addr['is_default'] ) {
				$found = true;
			}
		}
		unset( $addr );

		if ( ! $found ) {
			return false;
		}

		update_user_meta( $user_id, self::META_KEY, $addresses );
		return true;
	}

	/**
	 * Finds an existing saved address that matches the given address fields,
	 * used to avoid storing duplicates (e.g. when a customer re-uses an address
	 * at checkout or one is already on file). Comparison is case-insensitive and
	 * whitespace-trimmed across the identifying fields.
	 *
	 * @param int                  $user_id
	 * @param array<string,string> $fields Expects address_1, city, postcode, state.
	 * @return array|null The matching stored address, or null if none match.
	 */
	public static function find_matching_address( int $user_id, array $fields ): ?array {
		$norm = static fn( $v ): string => strtolower( trim( (string) $v ) );

		foreach ( self::get_addresses( $user_id ) as $addr ) {
			if (
				$norm( $addr['address_1'] ?? '' ) === $norm( $fields['address_1'] ?? '' )
				&& $norm( $addr['city'] ?? '' )     === $norm( $fields['city'] ?? '' )
				&& $norm( $addr['postcode'] ?? '' ) === $norm( $fields['postcode'] ?? '' )
				&& $norm( $addr['state'] ?? '' )    === $norm( $fields['state'] ?? '' )
			) {
				return $addr;
			}
		}

		return null;
	}

	/**
	 * Sanitize and validate raw address data from POST.
	 *
	 * @param array $raw
	 * @return array|WP_Error
	 */
	private static function sanitize_address_data( array $raw ): array|WP_Error {
		$nickname   = sanitize_text_field( $raw['nickname'] ?? '' );
		$first_name = sanitize_text_field( $raw['first_name'] ?? '' );
		$last_name  = sanitize_text_field( $raw['last_name'] ?? '' );
		$hub_area   = sanitize_text_field( $raw['hub_area'] ?? '' );
		$phone      = sanitize_text_field( $raw['phone'] ?? '' );
		$alt_phone  = sanitize_text_field( $raw['alternate_phone'] ?? '' );

		if ( ! $nickname ) {
			return new WP_Error( 'missing_nickname', __( 'Address label is required.', 'babypasa-address-book' ) );
		}
		if ( mb_strlen( $nickname ) > 50 ) {
			return new WP_Error( 'nickname_too_long', __( 'Address label must be 50 characters or less.', 'babypasa-address-book' ) );
		}
		if ( ! $first_name ) {
			return new WP_Error( 'missing_first_name', __( 'First name is required.', 'babypasa-address-book' ) );
		}
		if ( ! $last_name ) {
			return new WP_Error( 'missing_last_name', __( 'Last name is required.', 'babypasa-address-book' ) );
		}
		if ( ! $hub_area || strpos( $hub_area, '||' ) === false ) {
			return new WP_Error( 'missing_hub_area', __( 'Please select a delivery area.', 'babypasa-address-book' ) );
		}

		$parts = explode( '||', $hub_area, 2 );
		if ( ! trim( $parts[0] ) || ! trim( $parts[1] ) ) {
			return new WP_Error( 'invalid_hub_area', __( 'Invalid delivery area selected.', 'babypasa-address-book' ) );
		}

		if ( ! $phone ) {
			return new WP_Error( 'missing_phone', __( 'Mobile number is required.', 'babypasa-address-book' ) );
		}
		if ( ! preg_match( '/^[0-9]{10}$/', $phone ) ) {
			return new WP_Error( 'invalid_phone', __( 'Mobile number must be exactly 10 digits (no country code).', 'babypasa-address-book' ) );
		}
		if ( $alt_phone && ! preg_match( '/^[0-9]{10}$/', $alt_phone ) ) {
			return new WP_Error( 'invalid_alt_phone', __( 'Alternate mobile number must be exactly 10 digits.', 'babypasa-address-book' ) );
		}

		$email = sanitize_email( $raw['email'] ?? '' );
		if ( $email && ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'babypasa-address-book' ) );
		}

		return [
			'nickname'        => $nickname,
			'is_default'      => ! empty( $raw['is_default'] ),
			'first_name'      => $first_name,
			'last_name'       => $last_name,
			'hub_area'        => $hub_area,
			'state'           => trim( $parts[0] ),
			'city'            => trim( $parts[1] ),
			'address_1'       => sanitize_text_field( $raw['address_1'] ?? '' ),
			'address_2'       => sanitize_text_field( $raw['address_2'] ?? '' ),
			'postcode'        => sanitize_text_field( $raw['postcode'] ?? '' ),
			'phone'           => $phone,
			'alternate_phone' => $alt_phone,
			'email'           => $email,
			'landmark'        => sanitize_text_field( $raw['landmark'] ?? '' ),
			// Optional provenance fields. Manual saves leave these empty/false;
			// the checkout auto-save sets a note and flags the address.
			'note'            => sanitize_text_field( $raw['note'] ?? '' ),
			'auto_saved'      => ! empty( $raw['auto_saved'] ),
		];
	}
}

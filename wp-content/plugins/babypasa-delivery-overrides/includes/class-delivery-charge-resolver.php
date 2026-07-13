<?php
/**
 * Canonical delivery-charge resolver.
 *
 * Single source of truth for the delivery charge, shared by the frontend
 * cart/checkout (woocommerce_package_rates) and the admin manual-order screen
 * (babypasa-admin-order-enhancements). Both contexts MUST call this so their
 * results can never diverge again.
 *
 * Precedence (first match wins):
 *   1. Any cart/order item has "Offer Free Delivery" (_bp_free_delivery=yes) → 0
 *   2. Any item's "Free Delivery Areas" (_bp_free_delivery_areas) includes the
 *      resolved destination district → 0
 *   3. Resolved area matches an enabled Area-Based override rule → that price
 *   4. Default Delivery Charge Override is set → that value
 *   5. Otherwise → null (leave the Upaya-calculated rate untouched)
 *
 * The aggregation rule is ANY: one qualifying item makes the whole
 * single-shipment package free (see BP_Cart_Shipping_Display for why the cart
 * is a single Upaya shipment).
 *
 * @package BabyPasa_Delivery_Overrides
 * @author  Ashok Shrestha / The Hive Craft
 */

defined( 'ABSPATH' ) || exit;

class BP_Delivery_Charge_Resolver {

	/**
	 * Resolves the delivery charge for a set of items and a destination area.
	 *
	 * @param array  $items Array of items, each ['product_id'=>int, 'variation_id'=>int].
	 * @param string $area  Full Upaya area name (billing_city, e.g. "Kathmandu-Naya Baneshwor-Kathmandu").
	 * @return array{charge:float,label:?string,reason:string}|null
	 *         Null = no override applies (leave the Upaya rate). Otherwise the
	 *         resolved charge; 'label' is null when the label should be left as-is.
	 */
	public static function resolve( array $items, string $area ): ?array {
		// 1 + 2) Product free-everywhere flag, or product free-in-area match.
		if ( self::any_item_qualifies_free( $items, $area ) ) {
			return [
				'charge' => 0.0,
				'label'  => __( 'Free Delivery', 'babypasa-delivery-overrides' ),
				'reason' => 'free_product',
			];
		}

		// 3) Area-based override rule.
		$rule = self::area_override_rule( $area );
		if ( null !== $rule ) {
			return [
				'charge' => (float) ( $rule['override_price'] ?? 0 ),
				'label'  => ( '' !== ( $rule['label'] ?? '' ) ) ? $rule['label'] : null,
				'reason' => 'area_override',
			];
		}

		// 4) Default override (only when explicitly set; '0' is a valid free value).
		$default = self::default_override();
		if ( null !== $default ) {
			return [
				'charge' => $default,
				'label'  => null,
				'reason' => 'default',
			];
		}

		// 5) Nothing applies — leave the Upaya rate.
		return null;
	}

	/* ------------------------------------------------------------------
	 * Free-delivery detection (product flag + product area list)
	 * ------------------------------------------------------------------ */

	/**
	 * True when AT LEAST ONE item qualifies for free delivery, either via the
	 * global "_bp_free_delivery" flag or via a "_bp_free_delivery_areas" match
	 * against the destination district. Checks the variation/simple ID first,
	 * then falls back to the parent product ID (meta lives on the parent).
	 *
	 * @param array  $items Items as ['product_id'=>int, 'variation_id'=>int].
	 * @param string $area  Full Upaya area name.
	 * @return bool
	 */
	public static function any_item_qualifies_free( array $items, string $area ): bool {
		$areas_enabled = self::areas_feature_enabled();
		$district      = $areas_enabled ? self::district_from_area( $area ) : '';

		foreach ( $items as $item ) {
			$product_id = (int) ( $item['product_id'] ?? 0 );
			$variation  = (int) ( $item['variation_id'] ?? 0 );
			$check_id   = $variation ?: $product_id;

			// Free everywhere — always wins.
			if ( 'yes' === get_post_meta( $check_id, '_bp_free_delivery', true )
				|| 'yes' === get_post_meta( $product_id, '_bp_free_delivery', true ) ) {
				return true;
			}

			// Free in selected districts.
			if ( $areas_enabled && '' !== $district ) {
				$product_areas = self::get_product_free_areas( $check_id );
				if ( empty( $product_areas ) ) {
					$product_areas = self::get_product_free_areas( $product_id );
				}
				if ( self::district_in_list( $district, $product_areas ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/* ------------------------------------------------------------------
	 * Area-based override + default lookups
	 * ------------------------------------------------------------------ */

	/**
	 * Returns the first enabled Area-Based rule matching $area, or null.
	 *
	 * @param string $area Full Upaya area name (billing_city).
	 * @return array{enabled:string,area_name:string,match_type:string,override_price:string,label:string}|null
	 */
	public static function area_override_rule( string $area ): ?array {
		if ( '' === $area ) {
			return null;
		}

		$rules = get_option( BP_Area_Override::OPTION_KEY, [] );
		if ( ! is_array( $rules ) ) {
			return null;
		}

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$area_name = $rule['area_name'] ?? '';
			if ( '' === $area_name ) {
				continue;
			}

			$matched = ( 'exact' === ( $rule['match_type'] ?? 'contains' ) )
				? ( 0 === strcasecmp( $area, $area_name ) )
				: ( false !== stripos( $area, $area_name ) );

			if ( $matched ) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * The Default Delivery Charge Override as a float, or null when unset.
	 * '' = unset (leave the Upaya rate); '0' is a valid free-delivery value.
	 *
	 * @return float|null
	 */
	public static function default_override(): ?float {
		$default = get_option( BP_Area_Override::DEFAULT_OPTION_KEY, '' );
		if ( '' !== $default && is_numeric( $default ) ) {
			return (float) $default;
		}
		return null;
	}

	/* ------------------------------------------------------------------
	 * Area normalisation helpers — the single canonical implementation.
	 * BP_Free_Delivery_Product delegates to these so both features (and both
	 * contexts) normalise area identically.
	 * ------------------------------------------------------------------ */

	/**
	 * Whether area-based (district) free delivery is active. Rollback switch:
	 * define BP_FREE_DELIVERY_AREAS_DISABLED truthy, or return false from the
	 * 'bp_free_delivery_areas_enabled' filter.
	 */
	public static function areas_feature_enabled(): bool {
		if ( defined( 'BP_FREE_DELIVERY_AREAS_DISABLED' ) && BP_FREE_DELIVERY_AREAS_DISABLED ) {
			return false;
		}
		return (bool) apply_filters( 'bp_free_delivery_areas_enabled', true );
	}

	/**
	 * Product's selected districts (_bp_free_delivery_areas) as a clean string[].
	 *
	 * @param int $product_id Product (or parent) ID.
	 * @return string[]
	 */
	public static function get_product_free_areas( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return [];
		}
		$areas = get_post_meta( $product_id, '_bp_free_delivery_areas', true );
		if ( ! is_array( $areas ) ) {
			return [];
		}
		$clean = [];
		foreach ( $areas as $token ) {
			$token = trim( (string) $token );
			if ( '' !== $token ) {
				$clean[] = $token;
			}
		}
		return $clean;
	}

	/**
	 * Extracts the district token from an Upaya area name — the last "-" segment
	 * (e.g. "Kathmandu-Naya Baneshwor-Kathmandu" → "Kathmandu"). Names without a
	 * hyphen are returned trimmed as-is.
	 *
	 * @param string $area_name Full Upaya area name.
	 * @return string
	 */
	public static function district_from_area( string $area_name ): string {
		$area_name = trim( $area_name );
		if ( '' === $area_name ) {
			return '';
		}
		$parts    = explode( '-', $area_name );
		$district = trim( (string) end( $parts ) );

		/** Filter the district token parsed from an Upaya area name. */
		return (string) apply_filters( 'bp_free_delivery_district_from_area', $district, $area_name );
	}

	/**
	 * Case-insensitive membership test of a district against a product's tokens.
	 *
	 * @param string   $district Destination district.
	 * @param string[] $areas    Product's selected district tokens.
	 * @return bool
	 */
	public static function district_in_list( string $district, array $areas ): bool {
		if ( '' === $district || empty( $areas ) ) {
			return false;
		}
		foreach ( $areas as $token ) {
			if ( 0 === strcasecmp( trim( (string) $token ), $district ) ) {
				return true;
			}
		}
		return false;
	}
}

/**
 * Cross-plugin wrapper so sibling BabyPasa plugins (e.g. the admin order
 * enhancements) can resolve the delivery charge without a hard class
 * dependency. Callers must guard with function_exists() and fall back
 * gracefully when this plugin is inactive.
 *
 * @param array  $items Items as ['product_id'=>int, 'variation_id'=>int].
 * @param string $area  Full Upaya area name (billing_city).
 * @return array{charge:float,label:?string,reason:string}|null
 */
if ( ! function_exists( 'babypasa_resolve_delivery_charge' ) ) {
	function babypasa_resolve_delivery_charge( array $items, string $area ): ?array {
		return BP_Delivery_Charge_Resolver::resolve( $items, $area );
	}
}

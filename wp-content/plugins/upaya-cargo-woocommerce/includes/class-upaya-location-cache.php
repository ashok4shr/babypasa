<?php
/**
 * Caches Upaya location data to minimise redundant API calls.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches and caches Upaya Cargo locations using WordPress transients.
 *
 * Two layers of cache:
 *  - upaya_raw_cities_cache  — full city/hub/area tree from /locations
 *  - upaya_locations_cache   — flattened area list (backward-compat)
 */
class UPAYA_Location_Cache {

	/** Transient key for raw city+area tree (matches API /locations data[]). */
	const TRANSIENT_RAW = 'upaya_raw_cities_cache';

	/** Transient key for the flattened area list (backward compat). */
	const TRANSIENT_ALL = 'upaya_locations_cache';

	/** Prefix for per-location transient keys. */
	const TRANSIENT_SINGLE_PREFIX = 'upaya_location_';

	/** Cache TTL for raw and flattened lists: 12 hours. */
	const TTL_ALL = 12 * HOUR_IN_SECONDS;

	/** Cache TTL for a single location: 24 hours. */
	const TTL_SINGLE = 24 * HOUR_IN_SECONDS;

	/** Transient key for the short concurrency lock held during a rebuild. */
	const TRANSIENT_LOCK = 'upaya_location_refill_lock';

	/** Lock TTL (seconds) — long enough to cover one /locations fetch. */
	const LOCK_TTL = 60;

	/** @var UPAYA_API */
	private UPAYA_API $api;

	/** @var UPAYA_Logger */
	private UPAYA_Logger $logger;

	public function __construct( UPAYA_API $api, UPAYA_Logger $logger ) {
		$this->api    = $api;
		$this->logger = $logger;
	}

	/* ------------------------------------------------------------------
	 * Raw city tree (hub → city → areas)
	 * ------------------------------------------------------------------ */

	/**
	 * Returns the full city tree as returned by the API, with caching.
	 *
	 * Shape: [ { id, name, hubName, areas: [ { id, name, locationId, … } ] } ]
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_raw_cities(): array {
		$cached = get_transient( self::TRANSIENT_RAW );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->api->get_raw_locations();

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Location cache: failed to fetch raw cities — ' . $result->get_error_message() );
			return [];
		}

		set_transient( self::TRANSIENT_RAW, $result, self::TTL_ALL );
		return $result;
	}

	/**
	 * Returns unique hub names as [hubName => hubName] for the zone dropdown.
	 *
	 * @return array<string,string>
	 */
	public function get_hubs_for_select(): array {
		$hubs = [];
		foreach ( $this->get_raw_cities() as $city ) {
			$hub = $city['hubName'] ?? '';
			if ( $hub !== '' ) {
				$hubs[ $hub ] = $hub;
			}
		}
		ksort( $hubs );
		return $hubs;
	}

	/**
	 * Returns all active areas for a given hub as [area_name => area_name].
	 *
	 * @param  string $hub_name Exact hub name (e.g. "Kathmandu Hub").
	 * @return array<string,string>
	 */
	public function get_areas_for_hub( string $hub_name ): array {
		$areas = [];
		foreach ( $this->get_raw_cities() as $city ) {
			if ( ( $city['hubName'] ?? '' ) !== $hub_name ) {
				continue;
			}
			foreach ( $city['areas'] ?? [] as $area ) {
				if ( ! ( $area['isActive'] ?? true ) ) {
					continue;
				}
				$name = $area['name'] ?? '';
				if ( $name !== '' ) {
					$areas[ $name ] = $name;
				}
			}
		}
		ksort( $areas );
		return $areas;
	}

	/* ------------------------------------------------------------------
	 * Flattened area list (backward compat)
	 * ------------------------------------------------------------------ */

	/**
	 * Returns all areas as a flat list, fetching and caching if needed.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_locations(): array {
		$cached = get_transient( self::TRANSIENT_ALL );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$flattened = $this->flatten_areas( $this->get_raw_cities() );

		if ( ! empty( $flattened ) ) {
			set_transient( self::TRANSIENT_ALL, $flattened, self::TTL_ALL );
		}

		return $flattened;
	}

	/**
	 * Returns a single location by ID, checking the flattened list first.
	 *
	 * @param  int $id Location ID.
	 * @return array<string,mixed>|null
	 */
	public function get_location( int $id ): ?array {
		$transient_key = self::TRANSIENT_SINGLE_PREFIX . $id;
		$cached        = get_transient( $transient_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		foreach ( $this->get_locations() as $location ) {
			if ( isset( $location['locationId'] ) && (int) $location['locationId'] === $id ) {
				set_transient( $transient_key, $location, self::TTL_SINGLE );
				return $location;
			}
		}

		$this->logger->debug( "Location cache: location ID {$id} not found." );
		return null;
	}

	/**
	 * Flushes all cached location transients.
	 *
	 * @return void
	 */
	public function flush(): void {
		$locations = get_transient( self::TRANSIENT_ALL );

		delete_transient( self::TRANSIENT_RAW );
		delete_transient( self::TRANSIENT_ALL );

		if ( is_array( $locations ) ) {
			foreach ( $locations as $location ) {
				if ( isset( $location['locationId'] ) ) {
					delete_transient( self::TRANSIENT_SINGLE_PREFIX . (int) $location['locationId'] );
				}
			}
		}

		$this->logger->debug( 'Location cache: flushed.' );
	}

	/**
	 * Rebuilds the location cache from a fresh /locations API call.
	 *
	 * Fetch-then-swap: the API is called FIRST and the cached transients are only
	 * replaced when it succeeds, so a transient API failure never empties a working
	 * cache. Shared by the manual "Flush Location Cache" action and the daily refresh
	 * cron. A short transient lock prevents concurrent rebuilds (manual click racing
	 * the cron).
	 *
	 * @return int|\WP_Error Number of areas loaded on success (0 when Upaya returns a
	 *                       valid-but-empty list); WP_Error on lock contention or on an
	 *                       API/transport failure.
	 */
	public function rebuild() {
		// Concurrency guard — bail if another rebuild is already in flight.
		if ( get_transient( self::TRANSIENT_LOCK ) ) {
			return new \WP_Error(
				'upaya_refill_locked',
				__( 'A location cache refresh is already running. Please try again in a moment.', 'upaya-cargo-woocommerce' )
			);
		}
		set_transient( self::TRANSIENT_LOCK, 1, self::LOCK_TTL );

		// Fetch fresh data BEFORE touching the existing cache.
		$raw = $this->api->get_raw_locations();

		if ( is_wp_error( $raw ) ) {
			$this->logger->error( 'Location cache rebuild failed — ' . $raw->get_error_message() );
			delete_transient( self::TRANSIENT_LOCK );
			return $raw; // Existing cache left intact.
		}

		if ( ! is_array( $raw ) ) {
			$raw = [];
		}

		// Swap in the fresh data: drop the flattened + per-location caches so they are
		// recomputed from the new raw tree, then store the new raw tree.
		$old = get_transient( self::TRANSIENT_ALL );
		delete_transient( self::TRANSIENT_ALL );
		if ( is_array( $old ) ) {
			foreach ( $old as $location ) {
				if ( isset( $location['locationId'] ) ) {
					delete_transient( self::TRANSIENT_SINGLE_PREFIX . (int) $location['locationId'] );
				}
			}
		}
		set_transient( self::TRANSIENT_RAW, $raw, self::TTL_ALL );

		// Warm the flattened list (recomputed from the fresh raw tree) and count areas.
		$flattened = $this->get_locations();
		$count     = is_array( $flattened ) ? count( $flattened ) : 0;

		delete_transient( self::TRANSIENT_LOCK );

		$this->logger->debug( "Location cache rebuilt — {$count} area(s) loaded." );
		return $count;
	}

	/**
	 * Returns pickup-location options as [ areaName => "Hub › Area" ] for the admin
	 * Default Pickup Location dropdown.
	 *
	 * Mirrors the checkout's combined Hub/Area selector (get_hub_area_options) so the
	 * same area-level locations are selectable and labelled identically. The KEY is the
	 * exact area `name`, which is what resolve_location_id()/get_location_id_by_name()
	 * match on — so the stored value resolves to the same locationId a real checkout
	 * for that area would produce.
	 *
	 * (The older flattened-area approach keyed by city-level `locationId`, collapsing
	 * many areas onto a few rows and dropping area names entirely.)
	 *
	 * @return array<string,string>
	 */
	public function get_locations_for_select(): array {
		$select = [];
		foreach ( $this->get_raw_cities() as $city ) {
			$hub = sanitize_text_field( $city['hubName'] ?? '' );
			foreach ( $city['areas'] ?? [] as $area ) {
				if ( ! ( $area['isActive'] ?? true ) ) {
					continue;
				}
				$name = sanitize_text_field( $area['name'] ?? '' );
				if ( $name === '' ) {
					continue;
				}
				$select[ $name ] = ( '' !== $hub ) ? $hub . ' › ' . $name : $name;
			}
		}
		asort( $select ); // Alphabetical by label (field is a searchable enhanced-select).
		return $select;
	}

	/**
	 * Returns locations as [name => name] for WooCommerce city dropdowns.
	 *
	 * @return array<string,string>
	 */
	public function get_locations_for_city_select(): array {
		$select = [];
		foreach ( $this->get_locations() as $location ) {
			if ( isset( $location['name'] ) ) {
				$name            = sanitize_text_field( $location['name'] );
				$select[ $name ] = $name;
			}
		}
		return $select;
	}

	/**
	 * Resolves a location name to its locationId (city-level ID).
	 *
	 * @param  string $name Location name.
	 * @return int Location ID or 0.
	 */
	public function get_location_id_by_name( string $name ): int {
		foreach ( $this->get_locations() as $location ) {
			if ( isset( $location['name'] ) && strcasecmp( $location['name'], $name ) === 0 ) {
				return (int) $location['locationId'];
			}
		}
		return 0;
	}

	/**
	 * Resolves an area name to its area `id` (used as area_id in add-order).
	 *
	 * @param  string $name Area name.
	 * @return int Area ID or 0.
	 */
	public function get_area_id_by_name( string $name ): int {
		foreach ( $this->get_locations() as $location ) {
			if ( isset( $location['name'] ) && strcasecmp( $location['name'], $name ) === 0 ) {
				return (int) $location['id'];
			}
		}
		return 0;
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Flattens the raw city tree into a list of area objects.
	 *
	 * Each area gets a `name` key (from `area.name`) and keeps its own
	 * `id` and `locationId` so downstream lookups work unchanged.
	 *
	 * @param  array<int,array<string,mixed>> $cities Raw city tree.
	 * @return array<int,array<string,mixed>> Flat area list.
	 */
	private function flatten_areas( array $cities ): array {
		$flattened = [];

		foreach ( $cities as $city ) {
			if ( ! isset( $city['areas'] ) || ! is_array( $city['areas'] ) ) {
				continue;
			}
			foreach ( $city['areas'] as $area ) {
				if ( ! isset( $area['id'] ) ) {
					continue;
				}
				$area['name']         = $area['name'] ?? $area['locationName'] ?? '';
				$area['locationId']   = $area['locationId'] ?? $city['id'] ?? 0;
				$area['locationName'] = $area['locationName'] ?? $area['name'];
				$area['hubName']      = $city['hubName'] ?? '';
				$flattened[]          = $area;
			}
		}

		return $flattened;
	}
}

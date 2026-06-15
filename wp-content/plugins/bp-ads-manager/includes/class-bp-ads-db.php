<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * All database interactions for BP Ads Manager.
 *
 * Uses a custom table {prefix}bp_ads instead of CPT/postmeta.
 */
class BP_Ads_DB {

	const DB_VERSION = '1.4.0';
	const DB_OPTION  = 'bp_ads_db_version';

	/**
	 * Banner placement section slug shown after the Trending Products section.
	 * Also the backwards-compatible default for ads with no stored placement.
	 */
	const PLACEMENT_TRENDING = 'trending';

	/** Banner placement section slug shown after the Daily Essentials section. */
	const PLACEMENT_DAILY_ESSENTIALS = 'daily_essentials';

	/** Banner placement section slug shown after the New / Latest Products section. */
	const PLACEMENT_NEW_PRODUCTS = 'new_products';

	/**
	 * Returns the fully-qualified table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'bp_ads';
	}

	/**
	 * Returns the map of valid banner placement slugs to their human labels.
	 *
	 * Used by both the admin edit screen (to render the checkboxes) and the
	 * front-end renderer (to validate stored slugs).
	 *
	 * @return array slug => translated label.
	 */
	public static function get_placement_choices() {
		return array(
			self::PLACEMENT_TRENDING         => __( 'After Trending Products', 'bp-ads-manager' ),
			self::PLACEMENT_DAILY_ESSENTIALS => __( 'After Daily Essentials', 'bp-ads-manager' ),
			self::PLACEMENT_NEW_PRODUCTS     => __( 'After New Products', 'bp-ads-manager' ),
		);
	}

	/**
	 * Normalises a stored placement value into an array of valid slugs.
	 *
	 * The placement column stores a comma-separated list of section slugs.
	 * Backwards compatibility: an empty/NULL placement falls back to showing the
	 * ad only in the "After Trending Products" section.
	 *
	 * @param string|null $raw Raw placement column value (comma-separated slugs).
	 * @return array Array of valid placement slugs (never empty).
	 */
	public static function parse_placement( $raw ) {
		$valid = array_keys( self::get_placement_choices() );

		if ( null === $raw || '' === trim( (string) $raw ) ) {
			// Legacy rows with no placement default to Trending only.
			return array( self::PLACEMENT_TRENDING );
		}

		$slugs = array_map( 'trim', explode( ',', (string) $raw ) );
		$slugs = array_values( array_intersect( $slugs, $valid ) );

		// If nothing valid survived, fall back to the legacy default.
		if ( empty( $slugs ) ) {
			return array( self::PLACEMENT_TRENDING );
		}

		return $slugs;
	}

	/**
	 * Creates or upgrades the custom ads table using dbDelta().
	 * Stores the schema version in wp_options.
	 */
	public static function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = self::table_name();

		$sql = "CREATE TABLE {$table} (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(255)    NOT NULL DEFAULT '',
  type         ENUM('popup','banner') NOT NULL,
  content_mode ENUM('html','image') NOT NULL DEFAULT 'html',
  content      LONGTEXT        NOT NULL,
  image_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  active       TINYINT(1)      NOT NULL DEFAULT 1,
  popup_delay  INT             NOT NULL DEFAULT 0,
  frequency    ENUM('once','always') NOT NULL DEFAULT 'once',
  device       ENUM('all','mobile','desktop') NOT NULL DEFAULT 'all',
  link_url     VARCHAR(500)    NOT NULL DEFAULT '',
  placement    VARCHAR(255)    NOT NULL DEFAULT '',
  sort_order   INT             NOT NULL DEFAULT 0,
  created_at   DATETIME        DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY          type (type),
  KEY          active (active)
) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// dbDelta does not reliably add ENUM columns to existing tables, so we
		// add content_mode and image_id manually if they are still missing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing_cols = $wpdb->get_col( "DESC {$table}", 0 );

		if ( ! in_array( 'content_mode', $existing_cols, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN content_mode ENUM('html','image') NOT NULL DEFAULT 'html' AFTER `type`" );
		}

		if ( ! in_array( 'image_id', $existing_cols, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN image_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `content`" );
		}

		if ( ! in_array( 'placement', $existing_cols, true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN placement VARCHAR(255) NOT NULL DEFAULT '' AFTER `link_url`" );
		}

		update_option( self::DB_OPTION, self::DB_VERSION );
	}

	/**
	 * Returns all ads, newest first.
	 *
	 * @return array Array of stdClass rows.
	 */
	public static function get_all() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC" );
	}

	/**
	 * Returns active ads filtered by type.
	 *
	 * @param string $type 'popup' or 'banner'.
	 * @return array
	 */
	public static function get_active( $type ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE active = 1 AND type = %s ORDER BY sort_order ASC, created_at DESC",
				$type
			)
		);
	}

	/**
	 * Returns active banner ads assigned to a given placement section.
	 *
	 * Fetches all active banners, then filters in PHP by parsed placement so the
	 * backwards-compatible default (empty placement → Trending only) is applied
	 * consistently. Ordering matches get_active().
	 *
	 * @param string $section Placement slug (see PLACEMENT_* constants).
	 * @return array Array of stdClass rows assigned to that section.
	 */
	public static function get_active_banners_for_placement( $section ) {
		$valid = array_keys( self::get_placement_choices() );
		if ( ! in_array( $section, $valid, true ) ) {
			return array();
		}

		$banners = self::get_active( 'banner' );
		$matched = array();

		foreach ( $banners as $ad ) {
			$placement = self::parse_placement( isset( $ad->placement ) ? $ad->placement : '' );
			if ( in_array( $section, $placement, true ) ) {
				$matched[] = $ad;
			}
		}

		return $matched;
	}

	/**
	 * Returns a single ad row by ID.
	 *
	 * @param int $id Ad ID.
	 * @return object|null stdClass row or null if not found.
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
		);
	}

	/**
	 * Inserts a new ad row.
	 *
	 * @param array $data Associative array of column => value pairs.
	 * @return int|WP_Error Inserted row ID on success, WP_Error on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$clean = self::sanitize_data( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			self::table_name(),
			$clean,
			self::get_formats( $clean )
		);

		if ( false === $result ) {
			return new WP_Error( 'bp_ads_insert_error', $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates an existing ad row.
	 *
	 * @param int   $id   Ad ID.
	 * @param array $data Column => value pairs to update.
	 * @return int|false Rows affected, or false on DB failure.
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$clean = self::sanitize_data( $data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->update(
			self::table_name(),
			$clean,
			array( 'id' => absint( $id ) ),
			self::get_formats( $clean ),
			array( '%d' )
		);
	}

	/**
	 * Flips the active flag for an ad between 0 and 1.
	 *
	 * @param int $id Ad ID.
	 * @return int|false New active state (0 or 1), or false on error.
	 */
	public static function toggle_active( $id ) {
		global $wpdb;
		$table = self::table_name();
		$id    = absint( $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$current = $wpdb->get_var(
			$wpdb->prepare( "SELECT active FROM {$table} WHERE id = %d", $id )
		);

		if ( null === $current ) {
			return false;
		}

		$new_state = ( (int) $current === 1 ) ? 0 : 1;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update(
			$table,
			array( 'active' => $new_state ),
			array( 'id'     => $id ),
			array( '%d' ),
			array( '%d' )
		);

		return ( false === $updated ) ? false : $new_state;
	}

	/**
	 * Deletes an ad row.
	 *
	 * @param int $id Ad ID.
	 * @return int|false Rows affected, or false on failure.
	 */
	public static function delete( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->delete(
			self::table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);
	}

	/**
	 * Performs a bulk action on multiple ads.
	 *
	 * @param array  $ids    Array of ad IDs (will be cast to absint).
	 * @param string $action 'enable', 'disable', or 'delete'.
	 * @return int Number of rows affected.
	 */
	public static function bulk_action( $ids, $action ) {
		global $wpdb;

		$table = self::table_name();
		$ids   = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		if ( 'delete' === $action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders
			$affected = $wpdb->query(
				$wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids )
			);
		} elseif ( 'enable' === $action || 'disable' === $action ) {
			$new_state = ( 'enable' === $action ) ? 1 : 0;
			$args      = array_merge( array( $new_state ), $ids );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders
			$affected = $wpdb->query(
				$wpdb->prepare( "UPDATE {$table} SET active = %d WHERE id IN ({$placeholders})", ...$args )
			);
		} else {
			$affected = 0;
		}

		return (int) $affected;
	}

	/**
	 * Returns the wp_kses allowed-tags map used for HTML-mode ad content.
	 *
	 * Covers common ad markup: images, links, wrappers, iframes.
	 * Intentionally excludes <script> — admins who need JS tracking should
	 * use a dedicated script-injection plugin.
	 *
	 * @return array
	 */
	public static function get_html_kses_allowed() {
		return array(
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'class' => true, 'style' => true, 'title' => true ),
			'img'    => array( 'src' => true, 'srcset' => true, 'sizes' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true, 'style' => true, 'loading' => true ),
			'div'    => array( 'class' => true, 'style' => true, 'id' => true ),
			'span'   => array( 'class' => true, 'style' => true ),
			'p'      => array( 'class' => true, 'style' => true ),
			'strong' => array( 'class' => true, 'style' => true ),
			'em'     => array( 'class' => true, 'style' => true ),
			'h1'     => array( 'class' => true, 'style' => true ),
			'h2'     => array( 'class' => true, 'style' => true ),
			'h3'     => array( 'class' => true, 'style' => true ),
			'ul'     => array( 'class' => true, 'style' => true ),
			'ol'     => array( 'class' => true, 'style' => true ),
			'li'     => array( 'class' => true, 'style' => true ),
			'br'     => array(),
			'hr'     => array( 'class' => true, 'style' => true ),
			'iframe' => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allowfullscreen' => true,
				'allow'           => true,
				'class'           => true,
				'style'           => true,
				'title'           => true,
				'loading'         => true,
			),
		);
	}

	/**
	 * Sanitizes input data before DB operations.
	 *
	 * @param array $data Raw data array.
	 * @return array Sanitized data.
	 */
	private static function sanitize_data( $data ) {
		$clean = array();

		if ( isset( $data['title'] ) ) {
			$clean['title'] = sanitize_text_field( $data['title'] );
		}
		if ( isset( $data['type'] ) ) {
			$clean['type'] = in_array( $data['type'], array( 'popup', 'banner' ), true )
				? $data['type'] : 'popup';
		}
		if ( isset( $data['content_mode'] ) ) {
			$clean['content_mode'] = in_array( $data['content_mode'], array( 'html', 'image' ), true )
				? $data['content_mode'] : 'html';
		}
		if ( isset( $data['content'] ) ) {
			$clean['content'] = wp_kses( $data['content'], self::get_html_kses_allowed() );
		}
		if ( isset( $data['image_id'] ) ) {
			$clean['image_id'] = absint( $data['image_id'] );
		}
		if ( isset( $data['active'] ) ) {
			$clean['active'] = (int) (bool) $data['active'];
		}
		if ( isset( $data['popup_delay'] ) ) {
			$clean['popup_delay'] = absint( $data['popup_delay'] );
		}
		if ( isset( $data['frequency'] ) ) {
			$clean['frequency'] = in_array( $data['frequency'], array( 'once', 'always' ), true )
				? $data['frequency'] : 'once';
		}
		if ( isset( $data['device'] ) ) {
			$clean['device'] = in_array( $data['device'], array( 'all', 'mobile', 'desktop' ), true )
				? $data['device'] : 'all';
		}
		if ( isset( $data['link_url'] ) ) {
			$clean['link_url'] = esc_url_raw( $data['link_url'] );
		}
		if ( isset( $data['placement'] ) ) {
			$valid     = array_keys( self::get_placement_choices() );
			$requested = is_array( $data['placement'] )
				? $data['placement']
				: array_map( 'trim', explode( ',', (string) $data['placement'] ) );
			$requested = array_map( 'sanitize_key', $requested );
			$requested = array_values( array_unique( array_intersect( $requested, $valid ) ) );
			// Empty string is stored when no placement is chosen; the renderer's
			// parse_placement() then applies the Trending-only legacy default.
			$clean['placement'] = implode( ',', $requested );
		}
		if ( isset( $data['sort_order'] ) ) {
			$clean['sort_order'] = absint( $data['sort_order'] );
		}

		return $clean;
	}

	/**
	 * Returns wpdb format strings matching the columns present in $data.
	 *
	 * @param array $data Sanitized data (after sanitize_data).
	 * @return array Format strings ('%s' or '%d').
	 */
	private static function get_formats( $data ) {
		$int_cols = array( 'active', 'popup_delay', 'sort_order', 'image_id' );
		$formats  = array();

		foreach ( array_keys( $data ) as $col ) {
			$formats[] = in_array( $col, $int_cols, true ) ? '%d' : '%s';
		}

		return $formats;
	}
}

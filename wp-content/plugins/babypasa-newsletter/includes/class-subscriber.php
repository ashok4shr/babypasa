<?php
/**
 * Database model for newsletter subscribers.
 */

namespace BabypasaNewsletter\Includes;

defined( 'ABSPATH' ) || exit;

class Subscriber {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'bpnl_subscribers';
	}

	/**
	 * @return object|null Row object or null if not found.
	 */
	public static function get_by_email( string $email ): ?object {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE email = %s LIMIT 1", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return $row ?: null;
	}

	/**
	 * @return object|null Row object or null if not found.
	 */
	public static function get_by_token( string $token ): ?object {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE token = %s LIMIT 1", $token ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return $row ?: null;
	}

	/**
	 * Inserts a new subscriber row and returns the new ID, or false on failure.
	 *
	 * @return int|false
	 */
	public static function insert( string $email ): int|false {
		global $wpdb;
		$token  = wp_generate_password( 64, false );
		$result = $wpdb->insert(
			self::table(),
			array(
				'email'  => $email,
				'token'  => $token,
				'status' => 'active',
			),
			array( '%s', '%s', '%s' )
		);
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Re-activates a previously unsubscribed row.
	 */
	public static function reactivate( int $id ): void {
		global $wpdb;
		$table = self::table();
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = 'active', unsubscribed_at = NULL WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * Marks a subscriber as unsubscribed.
	 */
	public static function unsubscribe( int $id ): void {
		global $wpdb;
		$table = self::table();
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = 'unsubscribed', unsubscribed_at = %s WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql' ),
				$id
			)
		);
	}

	/**
	 * Permanently deletes a single subscriber row.
	 */
	public static function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Permanently deletes multiple subscriber rows by ID.
	 *
	 * @param int[] $ids
	 */
	public static function delete_by_ids( array $ids ): void {
		global $wpdb;
		if ( empty( $ids ) ) {
			return;
		}
		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table        = self::table();
		$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( "DELETE FROM `{$table}` WHERE id IN ({$placeholders})", $ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Returns a page of subscriber rows matching the given filters.
	 *
	 * @param array{status?:string,search?:string,limit?:int,offset?:int,orderby?:string,order?:string} $args
	 * @return object[]
	 */
	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$table    = self::table();
		$defaults = array(
			'status'  => '',
			'search'  => '',
			'limit'   => 20,
			'offset'  => 0,
			'orderby' => 'id',
			'order'   => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = '1=1';
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND email LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$allowed_orderby = array( 'id', 'email', 'status', 'subscribed_at', 'unsubscribed_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'id';
		$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$limit           = max( 1, (int) $args['limit'] );
		$offset          = max( 0, (int) $args['offset'] );

		$sql      = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values[] = $limit;
		$values[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Counts rows matching the given filters.
	 *
	 * @param array{status?:string,search?:string} $args
	 */
	public static function count( array $args = array() ): int {
		global $wpdb;
		$table  = self::table();
		$where  = '1=1';
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where   .= ' AND email LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns active subscriber rows, optionally limited to specific IDs.
	 *
	 * @param int[] $ids  Empty array means all active subscribers.
	 * @return object[]
	 */
	public static function get_active_subscribers( array $ids = array() ): array {
		global $wpdb;
		$table = self::table();

		if ( ! empty( $ids ) ) {
			$ids          = array_map( 'intval', $ids );
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			return $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare( "SELECT * FROM `{$table}` WHERE status = 'active' AND id IN ({$placeholders})", $ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		}

		return $wpdb->get_results( "SELECT * FROM `{$table}` WHERE status = 'active'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

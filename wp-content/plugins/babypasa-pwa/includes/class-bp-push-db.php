<?php
/**
 * BabyPasa PWA — DB layer.
 * Manages the wp_bp_push_subscriptions table.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BP_Push_DB {

    const TABLE = 'bp_push_subscriptions';

    // ── Schema ────────────────────────────────────────────────────────────────

    public static function create_table(): void {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        // endpoint(191) = max index length for utf8mb4 on older MySQL
        $sql = "CREATE TABLE {$table} (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
            endpoint   TEXT NOT NULL,
            p256dh     TEXT NOT NULL,
            auth       VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY endpoint_hash (endpoint(191))
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Insert or update a subscription.
     * If the endpoint already exists, update the keys and user_id.
     */
    public static function save_subscription( int $user_id, string $endpoint, string $p256dh, string $auth ): bool {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $existing_id = $wpdb->get_var(
            $wpdb->prepare( "SELECT id FROM {$table} WHERE endpoint = %s LIMIT 1", $endpoint )
        );

        if ( $existing_id ) {
            $result = $wpdb->update(
                $table,
                [ 'user_id' => $user_id, 'p256dh' => $p256dh, 'auth' => $auth ],
                [ 'id'      => $existing_id ],
                [ '%d', '%s', '%s' ],
                [ '%d' ]
            );
            return $result !== false;
        }

        $result = $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'endpoint'   => $endpoint,
                'p256dh'     => $p256dh,
                'auth'       => $auth,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        return $result !== false;
    }

    public static function delete_subscription( string $endpoint ): void {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . self::TABLE, [ 'endpoint' => $endpoint ], [ '%s' ] );
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    public static function get_all(): array {
        global $wpdb;
        return $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . self::TABLE ) ?: [];
    }

    public static function get_by_user( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . self::TABLE . ' WHERE user_id = %d', $user_id )
        ) ?: [];
    }

    public static function count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . self::TABLE );
    }
}

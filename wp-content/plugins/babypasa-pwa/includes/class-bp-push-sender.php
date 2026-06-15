<?php
/**
 * BabyPasa PWA — Push Sender.
 * Handles VAPID signing and delivery via web-push-php.
 * Reads VAPID keys from wp_options (via BP_PWA_Settings) — no wp-config.php required.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class BP_Push_Sender {

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Send to all subscribers (broadcast).
     */
    public static function send_to_all( string $title, string $body, string $url = '/', string $icon = '' ): array {
        return self::dispatch( BP_Push_DB::get_all(), $title, $body, $url, $icon );
    }

    /**
     * Send to all devices belonging to a specific WP user.
     */
    public static function send_to_user( int $user_id, string $title, string $body, string $url = '/', string $icon = '' ): array {
        if ( ! $user_id ) return [ 'sent' => 0, 'failed' => 0, 'removed' => 0 ];
        return self::dispatch( BP_Push_DB::get_by_user( $user_id ), $title, $body, $url, $icon );
    }

    // ── Core dispatch ─────────────────────────────────────────────────────────

    private static function dispatch( array $rows, string $title, string $body, string $url, string $icon ): array {
        if ( empty( $rows ) ) {
            return [ 'sent' => 0, 'failed' => 0, 'removed' => 0 ];
        }

        if ( ! self::vapid_configured() ) {
            return [ 'error' => 'VAPID keys not configured. Go to Settings → BabyPasa PWA to enter your keys.' ];
        }

        $webpush = new WebPush( [
            'VAPID' => [
                'subject'    => BP_PWA_Settings::get_vapid_subject(),
                'publicKey'  => BP_PWA_Settings::get_vapid_public(),
                'privateKey' => BP_PWA_Settings::get_vapid_private(),
            ],
        ] );

        $default_icon = get_stylesheet_directory_uri() . '/assets/images/pwa-icons/icon-192x192.png';

        $payload = wp_json_encode( [
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'icon'  => $icon ?: $default_icon,
        ] );

        foreach ( $rows as $row ) {
            $subscription = Subscription::create( [
                'endpoint' => $row->endpoint,
                'keys'     => [
                    'p256dh' => $row->p256dh,
                    'auth'   => $row->auth,
                ],
            ] );
            $webpush->queueNotification( $subscription, $payload );
        }

        $sent = $failed = $removed = 0;

        foreach ( $webpush->flush() as $report ) {
            if ( $report->isSuccess() ) {
                $sent++;
            } else {
                $failed++;
                // HTTP 410 Gone: subscription is permanently expired — clean up.
                $response = $report->getResponse();
                if ( $response && $response->getStatusCode() === 410 ) {
                    BP_Push_DB::delete_subscription( $report->getEndpoint() );
                    $removed++;
                }
            }
        }

        return [ 'sent' => $sent, 'failed' => $failed, 'removed' => $removed ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function vapid_configured(): bool {
        return BP_PWA_Settings::is_vapid_configured();
    }
}

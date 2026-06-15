<?php
/**
 * BabyPasa PWA — Push Notification Admin.
 *
 * WooCommerce → Push Notifications submenu.
 * Three tabs:
 *   1. Compose  — write + send now, or schedule for later
 *   2. Scheduled — upcoming sends with cancel
 *   3. History  — log of past sends (last 20)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BP_Push_Admin {

    const OPT_SCHEDULED = 'bp_push_scheduled';
    const OPT_LOG       = 'bp_push_send_log';
    const CRON_HOOK     = 'bp_push_send_scheduled';

    public function __construct() {
        add_action( 'admin_menu',                            [ $this, 'register_menu' ] );
        add_action( 'admin_post_bp_push_send_broadcast',     [ $this, 'handle_broadcast' ] );
        add_action( 'admin_post_bp_push_schedule',           [ $this, 'handle_schedule' ] );
        add_action( 'admin_post_bp_push_cancel_scheduled',   [ $this, 'handle_cancel' ] );
        add_action( self::CRON_HOOK,                         [ $this, 'run_scheduled' ] );
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public function register_menu(): void {
        add_submenu_page(
            'woocommerce',
            'Push Notifications — BabyPasa',
            'Push Notifications',
            'manage_options',
            'bp-push-notifications',
            [ $this, 'render_page' ]
        );
    }

    // ── Handle: Send now ─────────────────────────────────────────────────────

    public function handle_broadcast(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        check_admin_referer( 'bp_push_broadcast' );

        $title = sanitize_text_field( wp_unslash( $_POST['bp_push_title'] ?? '' ) );
        $body  = sanitize_text_field( wp_unslash( $_POST['bp_push_body']  ?? '' ) );
        $url   = esc_url_raw(         wp_unslash( $_POST['bp_push_url']   ?? '/' ) );

        if ( ! $title || ! $body ) {
            wp_redirect( $this->page_url( [ 'tab' => 'compose', 'bp_error' => 'missing_fields' ] ) );
            exit;
        }

        $result = BP_Push_Sender::send_to_all( $title, $body, $url );
        $this->append_log( $title, $body, $url, $result, 'broadcast' );

        wp_redirect( $this->page_url( [ 'tab' => 'history', 'bp_sent' => $result['sent'] ?? 0 ] ) );
        exit;
    }

    // ── Handle: Schedule ─────────────────────────────────────────────────────

    public function handle_schedule(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        check_admin_referer( 'bp_push_schedule' );

        $title    = sanitize_text_field( wp_unslash( $_POST['bp_push_title']        ?? '' ) );
        $body     = sanitize_text_field( wp_unslash( $_POST['bp_push_body']         ?? '' ) );
        $url      = esc_url_raw(         wp_unslash( $_POST['bp_push_url']          ?? '/' ) );
        $sched_at = sanitize_text_field( wp_unslash( $_POST['bp_push_scheduled_at'] ?? '' ) );

        if ( ! $title || ! $body || ! $sched_at ) {
            wp_redirect( $this->page_url( [ 'tab' => 'compose', 'bp_error' => 'missing_fields' ] ) );
            exit;
        }

        // Convert from site timezone to UTC timestamp for wp_schedule_single_event
        try {
            $tz = new DateTimeZone( wp_timezone_string() );
            $dt = new DateTime( $sched_at, $tz );
        } catch ( Exception $e ) {
            wp_redirect( $this->page_url( [ 'tab' => 'compose', 'bp_error' => 'bad_date' ] ) );
            exit;
        }

        $timestamp = $dt->getTimestamp();
        if ( $timestamp <= time() ) {
            wp_redirect( $this->page_url( [ 'tab' => 'compose', 'bp_error' => 'past_date' ] ) );
            exit;
        }

        $id = 'bpsched_' . uniqid();
        $item = [
            'id'         => $id,
            'title'      => $title,
            'body'       => $body,
            'url'        => $url,
            'scheduled'  => $sched_at,           // local time string (for display)
            'timestamp'  => $timestamp,           // UTC for cron
            'created'    => current_time( 'mysql' ),
            'status'     => 'pending',
        ];

        $all   = get_option( self::OPT_SCHEDULED, [] );
        $all[] = $item;
        update_option( self::OPT_SCHEDULED, $all );

        wp_schedule_single_event( $timestamp, self::CRON_HOOK, [ $id ] );

        wp_redirect( $this->page_url( [ 'tab' => 'scheduled', 'bp_scheduled' => '1' ] ) );
        exit;
    }

    // ── Handle: Cancel scheduled ─────────────────────────────────────────────

    public function handle_cancel(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        check_admin_referer( 'bp_push_cancel' );

        $id  = sanitize_text_field( wp_unslash( $_POST['bp_sched_id'] ?? '' ) );
        $all = get_option( self::OPT_SCHEDULED, [] );

        foreach ( $all as &$item ) {
            if ( $item['id'] === $id && $item['status'] === 'pending' ) {
                $item['status'] = 'cancelled';
                wp_unschedule_event( $item['timestamp'], self::CRON_HOOK, [ $id ] );
                break;
            }
        }
        unset( $item );

        update_option( self::OPT_SCHEDULED, $all );
        wp_redirect( $this->page_url( [ 'tab' => 'scheduled', 'bp_cancelled' => '1' ] ) );
        exit;
    }

    // ── WP-Cron: fire scheduled notification ─────────────────────────────────

    public function run_scheduled( string $id ): void {
        $all = get_option( self::OPT_SCHEDULED, [] );

        foreach ( $all as &$item ) {
            if ( $item['id'] !== $id || $item['status'] !== 'pending' ) continue;

            $result        = BP_Push_Sender::send_to_all( $item['title'], $item['body'], $item['url'] );
            $item['status'] = 'sent';
            $item['result'] = $result;
            $this->append_log( $item['title'], $item['body'], $item['url'], $result, 'scheduled' );
            break;
        }
        unset( $item );

        update_option( self::OPT_SCHEDULED, $all );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render_page(): void {
        $tab      = sanitize_key( $_GET['tab'] ?? 'compose' );
        $count    = BP_Push_DB::count();
        $vapid_ok = BP_Push_Sender::vapid_configured();
        $settings = admin_url( 'options-general.php?page=bp-pwa-settings' );
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">🔔 Push Notifications
                <span style="font-size:13px;font-weight:400;color:#888;margin-left:4px;">
                    <?php echo number_format( $count ); ?> subscriber<?php echo 1 === $count ? '' : 's'; ?>
                </span>
            </h1>

            <?php $this->render_notices( $vapid_ok, $settings ); ?>

            <!-- Tabs -->
            <nav class="nav-tab-wrapper" style="margin-bottom:0;">
                <?php
                foreach ( [ 'compose' => '✏️ Compose', 'scheduled' => '🕐 Scheduled', 'history' => '📋 History' ] as $slug => $label ) {
                    $active = $tab === $slug ? ' nav-tab-active' : '';
                    echo '<a href="' . esc_url( $this->page_url( [ 'tab' => $slug ] ) ) . '" class="nav-tab' . $active . '">' . $label . '</a>';
                }
                ?>
            </nav>

            <div style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:28px 24px;max-width:960px;">
                <?php
                if ( $tab === 'compose' )   $this->render_compose( $count, $vapid_ok );
                if ( $tab === 'scheduled' ) $this->render_scheduled();
                if ( $tab === 'history' )   $this->render_history();
                ?>
            </div>
        </div>
        <?php
    }

    // ── Tab: Compose ─────────────────────────────────────────────────────────

    private function render_compose( int $count, bool $vapid_ok ): void {
        // Minimum datetime-local value = now in site timezone (can't schedule in the past)
        $tz  = wp_timezone();
        $now = new DateTime( 'now', $tz );
        $min = $now->format( 'Y-m-d\TH:i' );

        // Default schedule = 1 hour from now, rounded to next 5 min
        $later = clone $now;
        $later->modify( '+1 hour' );
        $min5 = (int) ceil( (int) $later->format( 'i' ) / 5 ) * 5;
        if ( $min5 === 60 ) { $later->modify( '+1 hour' ); $min5 = 0; }
        $later->setTime( (int) $later->format( 'H' ), $min5 );
        $default_sched = $later->format( 'Y-m-d\TH:i' );
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;">

            <!-- ── Send Now ──────────────────────────────────────────────── -->
            <div>
                <h2 style="margin-top:0;font-size:1rem;border-bottom:2px solid #FF2A61;padding-bottom:8px;display:inline-block;">
                    Send Now
                </h2>
                <p style="color:#555;font-size:13px;margin-bottom:20px;">
                    Delivers immediately to all
                    <strong style="color:#FF2A61;"><?php echo number_format( $count ); ?></strong>
                    subscriber<?php echo 1 === $count ? '' : 's'; ?>.
                </p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'bp_push_broadcast' ); ?>
                    <input type="hidden" name="action" value="bp_push_send_broadcast">
                    <?php $this->render_message_fields(); ?>
                    <p style="margin-top:20px;">
                        <button type="submit" class="button button-primary button-large"
                                <?php disabled( ! $vapid_ok || ! $count ); ?>>
                            🚀 Send Now
                        </button>
                    </p>
                </form>
            </div>

            <!-- ── Schedule ──────────────────────────────────────────────── -->
            <div>
                <h2 style="margin-top:0;font-size:1rem;border-bottom:2px solid #6b7280;padding-bottom:8px;display:inline-block;">
                    Schedule for Later
                </h2>
                <p style="color:#555;font-size:13px;margin-bottom:20px;">
                    Pick a date &amp; time — the notification fires automatically.
                    Times are in <strong><?php echo esc_html( wp_timezone_string() ); ?></strong>.
                </p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'bp_push_schedule' ); ?>
                    <input type="hidden" name="action" value="bp_push_schedule">
                    <?php $this->render_message_fields( 'sched_' ); ?>
                    <p>
                        <label for="bp_push_scheduled_at"><strong>Send at</strong></label><br>
                        <input type="datetime-local"
                               id="bp_push_scheduled_at" name="bp_push_scheduled_at"
                               min="<?php echo esc_attr( $min ); ?>"
                               value="<?php echo esc_attr( $default_sched ); ?>"
                               style="margin-top:4px;font-size:14px;padding:6px 8px;border:1px solid #ccc;border-radius:4px;"
                               required>
                    </p>
                    <p style="margin-top:20px;">
                        <button type="submit" class="button button-secondary button-large"
                                <?php disabled( ! $vapid_ok || ! $count ); ?>>
                            🕐 Schedule Notification
                        </button>
                    </p>
                </form>

                <p style="margin-top:16px;font-size:12px;color:#aaa;line-height:1.5;">
                    ℹ️ Scheduled sends use WP-Cron. For precise timing on low-traffic sites,
                    set up a real server cron: <code>* * * * * curl <?php echo esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ); ?></code>
                </p>
            </div>

        </div>
        <?php
    }

    // ── Tab: Scheduled ───────────────────────────────────────────────────────

    private function render_scheduled(): void {
        $all     = get_option( self::OPT_SCHEDULED, [] );
        $pending = array_filter( $all, fn( $i ) => $i['status'] === 'pending' );
        usort( $pending, fn( $a, $b ) => $a['timestamp'] <=> $b['timestamp'] );

        if ( empty( $pending ) ) {
            echo '<p style="color:#aaa;">No notifications scheduled. Go to <strong>Compose</strong> to schedule one.</p>';
            return;
        }
        ?>
        <table class="widefat striped" style="border:none;">
            <thead>
                <tr>
                    <th>Send at (<?php echo esc_html( wp_timezone_string() ); ?>)</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Link</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $pending as $item ) : ?>
                    <tr>
                        <td style="white-space:nowrap;font-weight:600;">
                            <?php
                            try {
                                $tz = wp_timezone();
                                $dt = new DateTime( $item['scheduled'], $tz );
                                echo esc_html( $dt->format( 'M j, Y · g:i a' ) );
                            } catch ( Exception $e ) {
                                echo esc_html( $item['scheduled'] );
                            }
                            ?>
                        </td>
                        <td><strong><?php echo esc_html( $item['title'] ); ?></strong></td>
                        <td style="color:#555;max-width:220px;"><?php echo esc_html( wp_trim_words( $item['body'], 10 ) ); ?></td>
                        <td style="color:#888;font-size:12px;"><?php echo esc_html( $item['url'] ); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                  onsubmit="return confirm('Cancel this scheduled notification?');">
                                <?php wp_nonce_field( 'bp_push_cancel' ); ?>
                                <input type="hidden" name="action"     value="bp_push_cancel_scheduled">
                                <input type="hidden" name="bp_sched_id" value="<?php echo esc_attr( $item['id'] ); ?>">
                                <button type="submit" class="button button-small" style="color:#dc3232;border-color:#dc3232;">
                                    Cancel
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // ── Tab: History ─────────────────────────────────────────────────────────

    private function render_history(): void {
        $log = get_option( self::OPT_LOG, [] );

        if ( empty( $log ) ) {
            echo '<p style="color:#aaa;">No notifications sent yet.</p>';
            return;
        }
        ?>
        <table class="widefat striped" style="border:none;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Message</th>
                    <th>Link</th>
                    <th style="text-align:right;">Sent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $log as $entry ) : ?>
                    <tr>
                        <td style="white-space:nowrap;color:#888;font-size:12px;">
                            <?php echo esc_html( date( 'M j, Y · g:i a', strtotime( $entry['date'] ) ) ); ?>
                        </td>
                        <td>
                            <?php if ( ( $entry['type'] ?? 'broadcast' ) === 'scheduled' ) : ?>
                                <span style="background:#e8f0fe;color:#1a73e8;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">scheduled</span>
                            <?php elseif ( ( $entry['type'] ?? '' ) === 'order' ) : ?>
                                <span style="background:#fce8ff;color:#9c27b0;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">order</span>
                            <?php else : ?>
                                <span style="background:#e8fce8;color:#1e8e1e;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">broadcast</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html( $entry['title'] ); ?></strong></td>
                        <td style="color:#555;max-width:200px;"><?php echo esc_html( wp_trim_words( $entry['body'], 10 ) ); ?></td>
                        <td style="color:#888;font-size:12px;"><?php echo esc_html( $entry['url'] ?? '' ); ?></td>
                        <td style="text-align:right;">
                            <span style="color:#46b450;font-weight:700;"><?php echo intval( $entry['sent'] ); ?></span>
                            <?php if ( ! empty( $entry['failed'] ) ) : ?>
                                <br><span style="color:#dc3232;font-size:11px;"><?php echo intval( $entry['failed'] ); ?> failed</span>
                            <?php endif; ?>
                            <?php if ( ! empty( $entry['removed'] ) ) : ?>
                                <br><span style="color:#aaa;font-size:11px;"><?php echo intval( $entry['removed'] ); ?> removed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // ── Shared: message fields ────────────────────────────────────────────────

    private function render_message_fields( string $id_prefix = '' ): void {
        ?>
        <p>
            <label for="<?php echo esc_attr( $id_prefix ); ?>bp_push_title"><strong>Title</strong></label><br>
            <input type="text" id="<?php echo esc_attr( $id_prefix ); ?>bp_push_title"
                   name="bp_push_title" class="large-text" style="margin-top:4px;"
                   placeholder="🎉 Flash Sale — 30% off today!" maxlength="80" required>
            <span class="description">Keep it short and punchy — under 50 characters shows best.</span>
        </p>
        <p>
            <label for="<?php echo esc_attr( $id_prefix ); ?>bp_push_body"><strong>Message</strong></label><br>
            <textarea id="<?php echo esc_attr( $id_prefix ); ?>bp_push_body"
                      name="bp_push_body" rows="2" class="large-text"
                      style="margin-top:4px;resize:vertical;" maxlength="150"
                      placeholder="Limited time. Shop now before it's gone." required></textarea>
            <span class="description">Max ~100 characters — longer text gets truncated on some devices.</span>
        </p>
        <p>
            <label for="<?php echo esc_attr( $id_prefix ); ?>bp_push_url"><strong>Link</strong></label><br>
            <input type="text" id="<?php echo esc_attr( $id_prefix ); ?>bp_push_url"
                   name="bp_push_url" class="regular-text" style="margin-top:4px;"
                   value="/shop/" placeholder="/shop/">
            <span class="description">Relative URL — where tapping the notification takes the user.</span>
        </p>
        <?php
    }

    // ── Notices ───────────────────────────────────────────────────────────────

    private function render_notices( bool $vapid_ok, string $settings_url ): void {
        if ( ! $vapid_ok ) : ?>
            <div class="notice notice-error">
                <p>
                    <strong>VAPID keys not configured.</strong>
                    Go to <a href="<?php echo esc_url( $settings_url ); ?>">Settings → BabyPasa PWA</a>
                    to enter your keys before sending.
                </p>
            </div>
        <?php endif;

        if ( isset( $_GET['bp_sent'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>✅ Notification sent to <strong><?php echo intval( $_GET['bp_sent'] ); ?></strong> subscriber(s).</p>
            </div>
        <?php endif;

        if ( isset( $_GET['bp_scheduled'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>🕐 Notification scheduled successfully.</p>
            </div>
        <?php endif;

        if ( isset( $_GET['bp_cancelled'] ) ) : ?>
            <div class="notice notice-info is-dismissible">
                <p>Scheduled notification cancelled.</p>
            </div>
        <?php endif;

        if ( isset( $_GET['bp_error'] ) ) {
            $messages = [
                'missing_fields' => 'Title and message are required.',
                'past_date'      => 'Scheduled time must be in the future.',
                'bad_date'       => 'Invalid date/time — please try again.',
            ];
            $msg = $messages[ $_GET['bp_error'] ] ?? 'An error occurred.';
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function append_log( string $title, string $body, string $url, array $result, string $type ): void {
        $log = get_option( self::OPT_LOG, [] );
        array_unshift( $log, [
            'date'    => current_time( 'mysql' ),
            'type'    => $type,
            'title'   => $title,
            'body'    => $body,
            'url'     => $url,
            'sent'    => $result['sent']    ?? 0,
            'failed'  => $result['failed']  ?? 0,
            'removed' => $result['removed'] ?? 0,
        ] );
        update_option( self::OPT_LOG, array_slice( $log, 0, 50 ) );
    }

    private function page_url( array $args = [] ): string {
        return add_query_arg(
            array_merge( [ 'page' => 'bp-push-notifications' ], $args ),
            admin_url( 'admin.php' )
        );
    }
}

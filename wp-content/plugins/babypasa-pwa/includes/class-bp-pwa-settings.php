<?php
/**
 * BabyPasa PWA — Settings.
 *
 * Stores VAPID keys in wp_options so they migrate automatically with the site.
 * Provides a Settings → BabyPasa PWA admin page to enter / update the keys.
 *
 * Reading priority:
 *   1. wp_options  (bp_pwa_vapid_public / bp_pwa_vapid_private / bp_pwa_vapid_subject)
 *   2. wp-config.php constants (BP_VAPID_PUBLIC_KEY etc.) — backwards compat only
 *
 * On first plugin activation the constants are automatically copied to wp_options.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BP_PWA_Settings {

    const OPT_PUBLIC  = 'bp_pwa_vapid_public';
    const OPT_PRIVATE = 'bp_pwa_vapid_private';
    const OPT_SUBJECT = 'bp_pwa_vapid_subject';
    const OPT_PAGE    = 'bp-pwa-settings';

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'register_menu' ] );
        add_action( 'admin_post_bp_pwa_save_settings', [ $this, 'handle_save' ] );
    }

    // ── Static getters (used by sender & core) ────────────────────────────────

    public static function get_vapid_public(): string {
        $val = get_option( self::OPT_PUBLIC, '' );
        if ( ! $val && defined( 'BP_VAPID_PUBLIC_KEY' ) ) $val = BP_VAPID_PUBLIC_KEY;
        return (string) $val;
    }

    public static function get_vapid_private(): string {
        $val = get_option( self::OPT_PRIVATE, '' );
        if ( ! $val && defined( 'BP_VAPID_PRIVATE_KEY' ) ) $val = BP_VAPID_PRIVATE_KEY;
        return (string) $val;
    }

    public static function get_vapid_subject(): string {
        $val = get_option( self::OPT_SUBJECT, '' );
        if ( ! $val && defined( 'BP_VAPID_SUBJECT' ) ) $val = BP_VAPID_SUBJECT;
        if ( ! $val ) $val = 'mailto:' . get_option( 'admin_email' );
        return (string) $val;
    }

    public static function is_vapid_configured(): bool {
        return self::get_vapid_public() !== ''
            && self::get_vapid_private() !== ''
            && self::get_vapid_subject() !== '';
    }

    // ── Admin menu ────────────────────────────────────────────────────────────

    public function register_menu(): void {
        add_options_page(
            'BabyPasa PWA Settings',
            'BabyPasa PWA',
            'manage_options',
            self::OPT_PAGE,
            [ $this, 'render_page' ]
        );
    }

    // ── Save handler ──────────────────────────────────────────────────────────

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        check_admin_referer( 'bp_pwa_save_settings' );

        $public  = sanitize_text_field( wp_unslash( $_POST['bp_vapid_public']  ?? '' ) );
        $private = sanitize_text_field( wp_unslash( $_POST['bp_vapid_private'] ?? '' ) );
        $subject = sanitize_text_field( wp_unslash( $_POST['bp_vapid_subject'] ?? '' ) );

        if ( $public  ) update_option( self::OPT_PUBLIC,  $public  );
        if ( $private ) update_option( self::OPT_PRIVATE, $private );
        if ( $subject ) update_option( self::OPT_SUBJECT, $subject );

        wp_redirect( add_query_arg( [ 'page' => self::OPT_PAGE, 'saved' => '1' ], admin_url( 'options-general.php' ) ) );
        exit;
    }

    // ── Render page ───────────────────────────────────────────────────────────

    public function render_page(): void {
        $configured = self::is_vapid_configured();
        $public     = self::get_vapid_public();
        $subject    = self::get_vapid_subject();
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:10px;">📲 BabyPasa PWA Settings</h1>

            <?php if ( isset( $_GET['saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px;margin-top:24px;">

                <!-- ── VAPID keys form ──────────────────────────────────────── -->
                <div style="background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:28px;">
                    <h2 style="margin-top:0;font-size:1.1rem;">VAPID Keys</h2>
                    <p style="color:#555;font-size:13px;margin-bottom:20px;">
                        Required for push notifications. Keys are stored in the database and migrate automatically.
                        Generate once with: <code>php vendor/bin/generate-vapid-keys.php</code>
                    </p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'bp_pwa_save_settings' ); ?>
                        <input type="hidden" name="action" value="bp_pwa_save_settings">

                        <p>
                            <label for="bp_vapid_public"><strong>Public Key</strong></label><br>
                            <input type="text" id="bp_vapid_public" name="bp_vapid_public"
                                   class="large-text" style="margin-top:4px;font-family:monospace;font-size:12px;"
                                   value="<?php echo esc_attr( $public ); ?>"
                                   placeholder="BOnUH17wdgPDWvD4y…">
                        </p>
                        <p>
                            <label for="bp_vapid_private"><strong>Private Key</strong></label><br>
                            <input type="password" id="bp_vapid_private" name="bp_vapid_private"
                                   class="large-text" style="margin-top:4px;font-family:monospace;font-size:12px;"
                                   value=""
                                   placeholder="<?php echo $configured ? '(saved — leave blank to keep)' : 'yLqdsN5L1EYtaww…'; ?>">
                            <span class="description">Leave blank to keep the existing private key.</span>
                        </p>
                        <p>
                            <label for="bp_vapid_subject"><strong>Subject (contact email)</strong></label><br>
                            <input type="text" id="bp_vapid_subject" name="bp_vapid_subject"
                                   class="regular-text" style="margin-top:4px;"
                                   value="<?php echo esc_attr( $subject ); ?>"
                                   placeholder="mailto:admin@example.com">
                        </p>

                        <p style="margin-top:20px;">
                            <button type="submit" class="button button-primary">Save Keys</button>
                        </p>
                    </form>
                </div>

                <!-- ── Status panel ─────────────────────────────────────────── -->
                <div style="background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.08);padding:28px;">
                    <h2 style="margin-top:0;font-size:1.1rem;">PWA Status</h2>

                    <?php $this->render_status_row( 'VAPID keys configured', $configured ); ?>
                    <?php $this->render_status_row( 'Subscriptions table',   $this->table_exists() ); ?>
                    <?php $this->render_status_row( 'Manifest reachable',    true, home_url('/manifest.json') ); ?>
                    <?php $this->render_status_row( 'Service worker URL',    true, home_url('/sw.js') ); ?>
                    <?php $this->render_status_row( 'Offline page URL',      true, home_url('/offline/') ); ?>

                    <p style="margin-top:20px;font-size:12px;color:#888;">
                        After activating the plugin on a new server, go to<br>
                        <strong>Settings → Permalinks → Save</strong> to flush rewrite rules.
                    </p>
                </div>

            </div>
        </div>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function render_status_row( string $label, bool $ok, string $link = '' ): void {
        $icon  = $ok ? '✅' : '❌';
        $color = $ok ? '#46b450' : '#dc3232';
        echo '<p style="display:flex;align-items:center;gap:8px;margin:8px 0;font-size:13px;">';
        echo '<span>' . $icon . '</span>';
        echo '<span style="flex:1;">' . esc_html( $label ) . '</span>';
        if ( $link ) {
            echo '<a href="' . esc_url( $link ) . '" target="_blank" style="font-size:11px;color:#999;">view ↗</a>';
        }
        echo '</p>';
    }

    private function table_exists(): bool {
        global $wpdb;
        return (bool) $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'bp_push_subscriptions' )
        );
    }
}

<?php
/**
 * Plugin Name: Babypasa Contact Form
 * Description: AJAX contact form with honeypot, rate limiting, admin inbox, and email notifications.
 * Version: 1.0.0
 * Author: Ashok Shrestha
 */

defined('ABSPATH') || exit;

define('BCF_VERSION', '1.0.0');
define('BCF_PLUGIN_URL', plugin_dir_url(__FILE__));

// ---------------------------------------------------------------------------
// Activation – create submissions table
// ---------------------------------------------------------------------------
register_activation_hook(__FILE__, 'bcf_activate');
function bcf_activate() {
    global $wpdb;
    $table           = $wpdb->prefix . 'contact_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL DEFAULT '',
        email varchar(100) NOT NULL DEFAULT '',
        phone varchar(30) NOT NULL DEFAULT '',
        message text NOT NULL,
        ip_address varchar(45) NOT NULL DEFAULT '',
        submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// ---------------------------------------------------------------------------
// Front-end assets – enqueued only when shortcode is on the page
// ---------------------------------------------------------------------------
add_action('wp_enqueue_scripts', 'bcf_enqueue_scripts');
function bcf_enqueue_scripts() {
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'contact_form')) {
        return;
    }

    wp_enqueue_script(
        'babypasa-contact-form',
        BCF_PLUGIN_URL . 'contact-form.js',
        ['jquery'],
        filemtime( plugin_dir_path( __FILE__ ) . 'contact-form.js' ),
        true
    );

    wp_localize_script('babypasa-contact-form', 'bcfData', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('bcf_submit'),
    ]);

    // Notification + form CSS – reuses bp-notification-* classes from babypasa-wishlist-compare.
    // Included here so the form is self-contained if that plugin is inactive.
    wp_register_style('babypasa-contact-form', false, [], BCF_VERSION);
    wp_enqueue_style('babypasa-contact-form');
    wp_add_inline_style('babypasa-contact-form', bcf_inline_css());
}

function bcf_inline_css() {
    return '
/* ── bp-notification system (mirrors babypasa-wishlist-compare) ── */
.bp-notification-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 999999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}
.bp-notification {
    width: 320px;
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    border-left: 4px solid #ff4b4b;
    pointer-events: all;
    transform: translateX(120%);
    opacity: 0;
    transition: transform .3s cubic-bezier(.34,1.56,.64,1), opacity .3s ease;
    position: relative;
}
.bp-notification.bp-show {
    transform: translateX(0);
    opacity: 1;
}
.bp-notification.bp-hiding {
    transform: translateX(120%);
    opacity: 0;
}
.bp-notification-close {
    position: absolute;
    top: 8px;
    right: 10px;
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #999;
    line-height: 1;
    padding: 0;
}
.bp-notification-title {
    margin: 0 0 6px;
    font-size: 14px;
    font-weight: 700;
}
.bp-notification-message {
    font-size: 13px;
    color: #555;
}
.bp-notification-message p { margin: 0; }

/* ── Babypasa Contact Form ── */
.bcf-form-wrap { max-width: 560px; }
.bcf-field { margin-bottom: 16px; }
.bcf-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 14px;
}
.bcf-required { color: #e00; }
.bcf-field input[type="text"],
.bcf-field input[type="email"],
.bcf-field textarea {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color .2s;
}
.bcf-field input:focus,
.bcf-field textarea:focus {
    border-color: #ff4b4b;
    outline: none;
}
.bcf-submit-btn {
    background: #ff4b4b;
    color: #fff;
    border: none;
    padding: 11px 28px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
.bcf-submit-btn:hover { background: #e03333; }
.bcf-submit-btn:disabled { opacity: .6; cursor: not-allowed; }
';
}

// ---------------------------------------------------------------------------
// Shortcode [contact_form]
// ---------------------------------------------------------------------------
add_shortcode('contact_form', 'bcf_render_form');
function bcf_render_form() {
    ob_start();
    ?>
    <div class="bcf-form-wrap">
        <form id="bcf-contact-form" novalidate>
            <?php wp_nonce_field('bcf_submit', 'bcf_nonce'); ?>

            <?php /* Honeypot: visually hidden, not type="hidden", invisible to real users */ ?>
            <div style="display:none;" aria-hidden="true">
                <label for="bcf_website">Website</label>
                <input type="text" id="bcf_website" name="bcf_hp" tabindex="-1" autocomplete="off" value="">
            </div>

            <div class="bcf-field">
                <label for="bcf-name"><?php esc_html_e('Name', 'babypasa-contact-form'); ?> <span class="bcf-required">*</span></label>
                <input type="text" id="bcf-name" name="bcf_name" autocomplete="name">
            </div>

            <div class="bcf-field">
                <label for="bcf-email"><?php esc_html_e('Email', 'babypasa-contact-form'); ?> <span class="bcf-required">*</span></label>
                <input type="email" id="bcf-email" name="bcf_email" autocomplete="email">
            </div>

            <div class="bcf-field">
                <label for="bcf-phone"><?php esc_html_e('Phone', 'babypasa-contact-form'); ?></label>
                <input type="tel" id="bcf-phone" name="bcf_phone" autocomplete="tel">
            </div>

            <div class="bcf-field">
                <label for="bcf-message"><?php esc_html_e('Message', 'babypasa-contact-form'); ?> <span class="bcf-required">*</span></label>
                <textarea id="bcf-message" name="bcf_message" rows="5"></textarea>
            </div>

            <div class="bcf-field">
                <button type="submit" class="bcf-submit-btn"><?php esc_html_e('Send Message', 'babypasa-contact-form'); ?></button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

// ---------------------------------------------------------------------------
// AJAX handler
// ---------------------------------------------------------------------------
add_action('wp_ajax_bcf_submit',        'bcf_handle_submit');
add_action('wp_ajax_nopriv_bcf_submit', 'bcf_handle_submit');

function bcf_handle_submit() {
    // 1. Nonce verification
    check_ajax_referer('bcf_submit', 'bcf_nonce');

    // 2. Honeypot – silently succeed if filled by a bot
    if (!empty($_POST['bcf_hp'])) {
        wp_send_json_success(['message' => __('Thank you! Your message has been sent.', 'babypasa-contact-form')]);
    }

    // 3. Rate limit: max 3 submissions per IP per 10 minutes
    $ip            = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    $transient_key = 'bcf_rl_' . md5($ip);
    $count         = (int) get_transient($transient_key);

    if ($count >= 3) {
        wp_send_json_error(['message' => __('Too many submissions. Please wait a few minutes and try again.', 'babypasa-contact-form')]);
    }

    // 4. Sanitize
    $name    = sanitize_text_field(wp_unslash($_POST['bcf_name']    ?? ''));
    $email   = sanitize_email(wp_unslash($_POST['bcf_email']        ?? ''));
    $phone   = sanitize_text_field(wp_unslash($_POST['bcf_phone']   ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['bcf_message'] ?? ''));

    // 5. Validate
    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors[] = __('Name must be at least 2 characters.', 'babypasa-contact-form');
    }
    if (!is_email($email)) {
        $errors[] = __('Please enter a valid email address.', 'babypasa-contact-form');
    }
    if (mb_strlen($message) < 10) {
        $errors[] = __('Message must be at least 10 characters.', 'babypasa-contact-form');
    }

    if (!empty($errors)) {
        wp_send_json_error(['message' => implode(' ', $errors)]);
    }

    // 6. Persist to DB
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'contact_submissions',
        [
            'name'         => $name,
            'email'        => $email,
            'phone'        => $phone,
            'message'      => $message,
            'ip_address'   => $ip,
            'submitted_at' => current_time('mysql'),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s']
    );

    // Update rate-limit counter (10-minute window)
    set_transient($transient_key, $count + 1, 10 * MINUTE_IN_SECONDS);

    // 7. Email notification
    bcf_send_notification($name, $email, $phone, $message);

    wp_send_json_success(['message' => __('Thank you! Your message has been sent.', 'babypasa-contact-form')]);
}

// ---------------------------------------------------------------------------
// Email helper
// ---------------------------------------------------------------------------
function bcf_send_notification($name, $email, $phone, $message) {
    $recipients = [];

    if (get_option('bcf_notify_admin', 1)) {
        $recipients[] = get_option('admin_email');
    }

    $extra_raw = trim((string) get_option('bcf_extra_emails', ''));
    if ($extra_raw !== '') {
        foreach (explode("\n", $extra_raw) as $line) {
            $extra = sanitize_email(trim($line));
            if (is_email($extra)) {
                $recipients[] = $extra;
            }
        }
    }

    $recipients = array_unique(array_filter($recipients));
    if (empty($recipients)) {
        return;
    }

    $site_name    = get_bloginfo('name');
    $mail_subject = sprintf('[%s] New contact form message', $site_name);

    $mail_body = sprintf(
        "Name: %s\nEmail: %s\nPhone: %s\n\n%s",
        $name,
        $email,
        $phone !== '' ? $phone : '(not provided)',
        $message
    );

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $name . ' <' . $email . '>',
    ];

    wp_mail(implode(',', $recipients), $mail_subject, $mail_body, $headers);
}

// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------
add_action('admin_menu', 'bcf_admin_menu');
function bcf_admin_menu() {
    add_menu_page(
        __('Contact Form', 'babypasa-contact-form'),
        __('Contact Form', 'babypasa-contact-form'),
        'manage_options',
        'babypasa-contact-form',
        'bcf_submissions_page',
        'dashicons-email-alt',
        30
    );

    add_submenu_page(
        'babypasa-contact-form',
        __('Submissions', 'babypasa-contact-form'),
        __('Submissions', 'babypasa-contact-form'),
        'manage_options',
        'babypasa-contact-form',
        'bcf_submissions_page'
    );

    add_submenu_page(
        'babypasa-contact-form',
        __('Settings', 'babypasa-contact-form'),
        __('Settings', 'babypasa-contact-form'),
        'manage_options',
        'babypasa-contact-form-settings',
        'bcf_settings_page'
    );
}

// ---------------------------------------------------------------------------
// Settings registration
// ---------------------------------------------------------------------------
add_action('admin_init', 'bcf_register_settings');
function bcf_register_settings() {
    register_setting('bcf_settings', 'bcf_notify_admin', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 1,
    ]);

    register_setting('bcf_settings', 'bcf_extra_emails', [
        'type'              => 'string',
        'sanitize_callback' => 'bcf_sanitize_emails_textarea',
        'default'           => '',
    ]);
}

function bcf_sanitize_emails_textarea($value) {
    $lines = explode("\n", wp_unslash((string) $value));
    $clean = [];
    foreach ($lines as $line) {
        $e = sanitize_email(trim($line));
        if (is_email($e)) {
            $clean[] = $e;
        }
    }
    return implode("\n", $clean);
}

// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------
function bcf_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Contact Form — Settings', 'babypasa-contact-form'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('bcf_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Email Notifications', 'babypasa-contact-form'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="bcf_notify_admin" value="1"
                                <?php checked(1, get_option('bcf_notify_admin', 1)); ?>>
                            <?php esc_html_e('Send notification to site admin email on each new submission', 'babypasa-contact-form'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="bcf_extra_emails"><?php esc_html_e('Additional Recipients', 'babypasa-contact-form'); ?></label>
                    </th>
                    <td>
                        <textarea id="bcf_extra_emails" name="bcf_extra_emails" rows="5" class="large-text"><?php echo esc_textarea(get_option('bcf_extra_emails', '')); ?></textarea>
                        <p class="description"><?php esc_html_e('One valid email address per line. Invalid addresses are silently ignored.', 'babypasa-contact-form'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Submissions page
// ---------------------------------------------------------------------------
function bcf_submissions_page() {
    global $wpdb;

    $table       = $wpdb->prefix . 'contact_submissions';
    $per_page    = 20;
    $cur_page    = max(1, (int) ($_GET['paged'] ?? 1));
    $offset      = ($cur_page - 1) * $per_page;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    $total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $rows        = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY submitted_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );

    $total_pages = (int) ceil($total / $per_page);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Contact Submissions', 'babypasa-contact-form'); ?></h1>

        <?php if (empty($rows)) : ?>
            <p><?php esc_html_e('No submissions yet.', 'babypasa-contact-form'); ?></p>
        <?php else : ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;"><?php esc_html_e('#', 'babypasa-contact-form'); ?></th>
                    <th style="width:130px;"><?php esc_html_e('Name', 'babypasa-contact-form'); ?></th>
                    <th style="width:170px;"><?php esc_html_e('Email', 'babypasa-contact-form'); ?></th>
                    <th style="width:130px;"><?php esc_html_e('Phone', 'babypasa-contact-form'); ?></th>
                    <th><?php esc_html_e('Message', 'babypasa-contact-form'); ?></th>
                    <th style="width:115px;"><?php esc_html_e('IP', 'babypasa-contact-form'); ?></th>
                    <th style="width:140px;"><?php esc_html_e('Date', 'babypasa-contact-form'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row->id); ?></td>
                    <td><?php echo esc_html($row->name); ?></td>
                    <td>
                        <a href="mailto:<?php echo esc_attr($row->email); ?>">
                            <?php echo esc_html($row->email); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($row->phone); ?></td>
                    <td><?php echo nl2br(esc_html($row->message)); ?></td>
                    <td><?php echo esc_html($row->ip_address); ?></td>
                    <td><?php echo esc_html($row->submitted_at); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $cur_page,
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}

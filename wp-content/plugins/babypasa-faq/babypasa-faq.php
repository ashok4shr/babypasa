<?php
/**
 * Plugin Name: BabyPasa FAQ
 * Description: FAQ management for BabyPasa with custom admin UI, accordion display, and category grouping.
 * Version: 1.1.0
 * Author: Ashok Shrestha
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ------------------------------------------------------------------
   1. CUSTOM POST TYPE & TAXONOMY
   (show_ui/show_in_menu disabled — managed via custom admin page)
------------------------------------------------------------------ */

add_action( 'init', 'bp_faq_register_cpt' );
function bp_faq_register_cpt() {
    register_post_type( 'bp_faq', [
        'labels'   => [ 'name' => 'FAQs', 'singular_name' => 'FAQ' ],
        'public'   => false,
        'show_ui'  => false,
        'supports' => [ 'title', 'editor' ],
        'rewrite'  => false,
    ] );

    register_taxonomy( 'faq_category', 'bp_faq', [
        'labels'       => [ 'name' => 'FAQ Categories', 'singular_name' => 'FAQ Category' ],
        'hierarchical' => true,
        'public'       => false,
        'show_ui'      => false,
        'rewrite'      => false,
    ] );
}

/* ------------------------------------------------------------------
   2. ADMIN MENU & PAGE
------------------------------------------------------------------ */

add_action( 'admin_menu', 'bp_faq_admin_menu' );
function bp_faq_admin_menu() {
    add_menu_page(
        'BabyPasa FAQs',
        'FAQs',
        'manage_options',
        'babypasa-faq',
        'bp_faq_render_admin_page',
        'dashicons-editor-help',
        30
    );
}

add_action( 'admin_enqueue_scripts', 'bp_faq_admin_assets' );
function bp_faq_admin_assets( $hook ) {
    if ( $hook !== 'toplevel_page_babypasa-faq' ) return;
    wp_enqueue_script( 'jquery-ui-sortable' );
}

function bp_faq_render_admin_page() {
    include plugin_dir_path( __FILE__ ) . 'admin-page.php';
}

/* ------------------------------------------------------------------
   3. AJAX HANDLERS
------------------------------------------------------------------ */

add_action( 'wp_ajax_bp_faq_save_faq',        'bp_faq_ajax_save_faq' );
add_action( 'wp_ajax_bp_faq_delete_faq',      'bp_faq_ajax_delete_faq' );
add_action( 'wp_ajax_bp_faq_save_category',   'bp_faq_ajax_save_category' );
add_action( 'wp_ajax_bp_faq_delete_category', 'bp_faq_ajax_delete_category' );
add_action( 'wp_ajax_bp_faq_reorder',         'bp_faq_ajax_reorder' );

function bp_faq_ajax_save_faq() {
    check_ajax_referer( 'bp_faq_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $faq_id   = intval( $_POST['faq_id']   ?? 0 );
    $question = sanitize_text_field( wp_unslash( $_POST['question'] ?? '' ) );
    $answer   = wp_kses_post( wp_unslash( $_POST['answer']   ?? '' ) );
    $cat_id   = intval( $_POST['cat_id']   ?? 0 );

    if ( ! $question ) wp_send_json_error( [ 'message' => 'Question is required.' ] );

    $post_data = [
        'post_type'    => 'bp_faq',
        'post_title'   => $question,
        'post_content' => $answer,
        'post_status'  => 'publish',
    ];

    if ( $faq_id ) {
        $post_data['ID'] = $faq_id;
        $post_id = wp_update_post( $post_data, true );
    } else {
        $post_id = wp_insert_post( $post_data, true );
    }

    if ( is_wp_error( $post_id ) ) wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );

    if ( $cat_id ) {
        wp_set_post_terms( $post_id, [ $cat_id ], 'faq_category' );
    }

    wp_send_json_success( [ 'id' => $post_id, 'question' => $question, 'answer' => $answer ] );
}

function bp_faq_ajax_delete_faq() {
    check_ajax_referer( 'bp_faq_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $faq_id = intval( $_POST['faq_id'] ?? 0 );
    if ( ! $faq_id ) wp_send_json_error();

    wp_delete_post( $faq_id, true );
    wp_send_json_success();
}

function bp_faq_ajax_save_category() {
    check_ajax_referer( 'bp_faq_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $cat_id = intval( $_POST['cat_id'] ?? 0 );
    $name   = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );

    if ( ! $name ) wp_send_json_error( [ 'message' => 'Category name is required.' ] );

    if ( $cat_id ) {
        $result = wp_update_term( $cat_id, 'faq_category', [ 'name' => $name ] );
    } else {
        $result = wp_insert_term( $name, 'faq_category', [ 'slug' => sanitize_title( $name ) ] );
    }

    if ( is_wp_error( $result ) ) wp_send_json_error( [ 'message' => $result->get_error_message() ] );

    $id = is_array( $result ) ? $result['term_id'] : $cat_id;
    wp_send_json_success( [ 'id' => $id, 'name' => $name ] );
}

function bp_faq_ajax_delete_category() {
    check_ajax_referer( 'bp_faq_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $cat_id = intval( $_POST['cat_id'] ?? 0 );
    if ( ! $cat_id ) wp_send_json_error();

    $faqs = get_posts( [
        'post_type'      => 'bp_faq',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [ [ 'taxonomy' => 'faq_category', 'field' => 'term_id', 'terms' => $cat_id ] ],
    ] );
    foreach ( $faqs as $id ) wp_delete_post( $id, true );

    wp_delete_term( $cat_id, 'faq_category' );
    wp_send_json_success();
}

function bp_faq_ajax_reorder() {
    check_ajax_referer( 'bp_faq_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();

    $order = $_POST['order'] ?? [];
    foreach ( $order as $index => $faq_id ) {
        wp_update_post( [ 'ID' => intval( $faq_id ), 'menu_order' => intval( $index ) ] );
    }
    wp_send_json_success();
}

/* ------------------------------------------------------------------
   4. SHORTCODE  [babypasa_faq]  /  [babypasa_faq category="slug"]
------------------------------------------------------------------ */

add_shortcode( 'babypasa_faq', 'bp_faq_shortcode' );
function bp_faq_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'category' => '' ], $atts );

    $categories = get_terms( [
        'taxonomy'   => 'faq_category',
        'hide_empty' => true,
        'orderby'    => 'name',
    ] );

    if ( is_wp_error( $categories ) || empty( $categories ) ) {
        return '<p>No FAQs found.</p>';
    }

    ob_start();

    $schema_entities = [];

    echo '<div class="bp-faq-wrap">';

    foreach ( $categories as $cat ) {
        if ( $atts['category'] && ! in_array( $cat->slug, array_map( 'trim', explode( ',', $atts['category'] ) ) ) ) {
            continue;
        }

        $faqs = get_posts( [
            'post_type'      => 'bp_faq',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'tax_query'      => [ [ 'taxonomy' => 'faq_category', 'field' => 'term_id', 'terms' => $cat->term_id ] ],
        ] );

        if ( empty( $faqs ) ) continue;

        echo '<div class="bp-faq-section">';
        echo '<button class="bp-faq-cat-toggle" aria-expanded="true">';
        echo esc_html( $cat->name );
        echo '<span class="bp-faq-cat-icon" aria-hidden="true"></span>';
        echo '</button>';
        echo '<div class="bp-faq-items">';

        foreach ( $faqs as $faq ) {
            $answer = apply_filters( 'the_content', $faq->post_content );
            echo '<div class="bp-faq-item">';
            echo '<button class="bp-faq-question" aria-expanded="false">';
            echo esc_html( $faq->post_title );
            echo '<span class="bp-faq-icon" aria-hidden="true"></span>';
            echo '</button>';
            echo '<div class="bp-faq-answer" hidden>' . wp_kses_post( $answer ) . '</div>';
            echo '</div>';

            $schema_entities[] = [
                '@type' => 'Question',
                'name'  => $faq->post_title,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $faq->post_content ),
                ],
            ];
        }

        echo '</div></div>';
    }

    echo '</div>';

    if ( ! empty( $schema_entities ) ) {
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $schema_entities,
        ];
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
    }

    return ob_get_clean();
}

/* ------------------------------------------------------------------
   5. FRONTEND ASSETS
------------------------------------------------------------------ */

add_action( 'wp_enqueue_scripts', 'bp_faq_assets' );
function bp_faq_assets() {
    if ( ! is_singular() && ! has_shortcode( get_post()->post_content ?? '', 'babypasa_faq' ) ) return;
    wp_add_inline_style( 'wp-block-library', bp_faq_css() );
    wp_add_inline_script( 'jquery', bp_faq_js() );
}

function bp_faq_css() {
    return '
.bp-faq-wrap { max-width: 860px; margin: 0 auto; font-family: inherit; }
.bp-faq-section { margin-bottom: 12px; border: 1px solid #e0e0e0; border-radius: 6px; overflow: hidden; }
.bp-faq-cat-toggle {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    background: #FF2A61; color: #fff; font-size: 13px; font-weight: 700;
    letter-spacing: 0.8px; text-transform: uppercase; padding: 14px 20px;
    border: none; cursor: pointer; text-align: left;
}
.bp-faq-cat-icon { width: 18px; height: 18px; flex-shrink: 0; position: relative; }
.bp-faq-cat-icon::before,.bp-faq-cat-icon::after {
    content: ""; position: absolute; background: #fff;
    left: 50%; top: 50%; transform: translate(-50%,-50%); transition: transform .25s;
}
.bp-faq-cat-icon::before { width: 12px; height: 2px; }
.bp-faq-cat-icon::after  { width: 2px; height: 12px; }
.bp-faq-cat-toggle[aria-expanded="true"] .bp-faq-cat-icon::after { transform: translate(-50%,-50%) rotate(90deg); }
.bp-faq-items { background: #fff; }
.bp-faq-item { border-top: 1px solid #f0f0f0; }
.bp-faq-item:first-child { border-top: none; }
.bp-faq-question {
    width: 100%; display: flex; align-items: center; justify-content: space-between;
    background: none; border: none; padding: 16px 20px; font-size: 14px; font-weight: 600;
    color: #1a1a1a !important; cursor: pointer; text-align: left; gap: 12px;
}
.bp-faq-question:hover,
.bp-faq-question:focus { background: none; outline: none; box-shadow: none; color: #1a1a1a !important; }
.bp-faq-cat-toggle:hover,
.bp-faq-cat-toggle:focus { background-color: #ff2a61; outline: none; }
.bp-faq-icon { width: 20px; height: 20px; flex-shrink: 0; border-radius: 50%;
    background: #f0f0f0; position: relative; }
.bp-faq-icon::before,.bp-faq-icon::after {
    content: ""; position: absolute; background: #555;
    left: 50%; top: 50%; transform: translate(-50%,-50%); transition: transform .25s;
}
.bp-faq-icon::before { width: 8px; height: 1.5px; }
.bp-faq-icon::after  { width: 1.5px; height: 8px; }
.bp-faq-question[aria-expanded="true"] .bp-faq-icon::after { transform: translate(-50%,-50%) rotate(90deg); }
.bp-faq-answer { padding: 0 20px 18px 20px; font-size: 14px; line-height: 1.7; color: #555; }
.bp-faq-answer p { margin: 0 0 8px; }
.bp-faq-answer p:last-child { margin: 0; }
.bp-faq-answer ul { margin: 8px 0 8px 20px; padding: 0; }
.bp-faq-answer li { margin-bottom: 4px; }
@media (max-width: 576px) {
    .bp-faq-question { font-size: 13px; padding: 14px 16px; }
    .bp-faq-answer { padding: 0 16px 14px; font-size: 13px; }
}
';
}

function bp_faq_js() {
    return '
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".bp-faq-cat-toggle").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var expanded = this.getAttribute("aria-expanded") === "true";
            this.setAttribute("aria-expanded", String(!expanded));
            var items = this.nextElementSibling;
            if (expanded) { items.style.display = "none"; } else { items.style.display = ""; }
        });
    });
    document.querySelectorAll(".bp-faq-question").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var expanded = this.getAttribute("aria-expanded") === "true";
            var answer = this.nextElementSibling;
            if (expanded) {
                this.setAttribute("aria-expanded", "false");
                answer.hidden = true;
            } else {
                this.setAttribute("aria-expanded", "true");
                answer.hidden = false;
            }
        });
    });
});
';
}

/* ------------------------------------------------------------------
   6. ONE-TIME DATA IMPORT  (runs on plugin activation)
------------------------------------------------------------------ */

register_activation_hook( __FILE__, 'bp_faq_import_data' );
function bp_faq_import_data() {
    bp_faq_register_cpt();

    $data = [
        'Orders & Payments' => [
            [ 'q' => 'What payment methods do you accept?',
              'a' => 'We currently accept payments via ConnectIPS, Cash on Delivery (COD), and QR code — QR can be directly scanned from your mobile banking app for payment.' ],
            [ 'q' => 'Can I modify or cancel my order after placing it?',
              'a' => 'Orders cannot be modified once placed. However, if you need to update details such as the delivery address, please contact our customer support within 12 hours. If your order has not been processed for shipping, you can cancel it via the Orders section of your account or by reaching out to our support team.' ],
            [ 'q' => 'How do I track my order?',
              'a' => 'You can track your order through the Order section of your account once it has been shipped.' ],
        ],
        'Shipping & Delivery' => [
            [ 'q' => 'Where do you deliver?',
              'a' => 'We currently deliver within Nepal only. International shipping is not available at the moment.' ],
            [ 'q' => 'How long does delivery take?',
              'a' => "<p>Orders are typically processed within 1–3 business days. Delivery time depends on the destination:</p><ul><li>Kathmandu Valley: Estimated delivery within 1–3 days</li><li>Outside Kathmandu Valley: Estimated delivery within 3–7 days, depending on the region and logistics partner.</li></ul><p>Delays may occur due to weather, high demand, or strikes.</p>" ],
            [ 'q' => 'Are there any shipping charges?',
              'a' => 'Yes, shipping charges are determined by our logistic partners and are displayed at checkout.' ],
            [ 'q' => 'What happens if my package is lost or stolen after delivery?',
              'a' => 'Once marked as "delivered" by the courier, we are not liable for lost or stolen packages. If you experience an issue, please contact the courier directly.' ],
        ],
        'Returns & Refunds' => [
            [ 'q' => 'What is your return policy?',
              'a' => 'Products can be returned within 7 days if they are unused, unopened, and in their original packaging. Some items like pacifiers, baby bottles, and personal care products are non-returnable for hygiene reasons.' ],
            [ 'q' => 'How do I request a return?',
              'a' => 'You can initiate a return request through your account under the Orders section.' ],
            [ 'q' => 'Who covers return shipping for defective or damaged items?',
              'a' => 'We cover return shipping costs if the product is defective or damaged.' ],
            [ 'q' => 'How will I receive my refund?',
              'a' => 'Refunds are processed via bank transfer and may take 7–10 business days after approval. Customers may need to provide their bank details for processing.' ],
            [ 'q' => 'Will I get a full refund if I used a discount coupon?',
              'a' => 'No, refunds only cover the actual amount paid. The discount amount will not be included.' ],
            [ 'q' => 'Can I exchange an item instead of getting a refund?',
              'a' => 'Yes, exchanges are available within 3 days, provided the item is unused and in its original packaging.' ],
        ],
        'Discount Coupons & Promotions' => [
            [ 'q' => 'Can I use multiple discount coupons on one order?',
              'a' => 'No, only one discount coupon can be used per order.' ],
            [ 'q' => 'Do discount coupons expire?',
              'a' => 'Yes, all discount coupons are time-bound and must be used within the validity period.' ],
            [ 'q' => 'If I return an item bought with a discount, will I get the discount amount refunded?',
              'a' => 'No, only the actual amount paid will be refunded.' ],
        ],
        'Customer Support' => [
            [ 'q' => 'How can I contact customer support?',
              'a' => 'You can reach our customer support team via email, phone, or our website\'s contact form. For faster responses, you can also contact us on WhatsApp or Messenger (if available).' ],
            [ 'q' => 'What are your customer support hours?',
              'a' => 'Our support team is available Monday to Friday from 9 AM – 6 PM. We aim to respond to all queries within 24 hours.' ],
        ],
        'Products' => [
            [ 'q' => 'Are the product images on the website accurate?',
              'a' => 'We strive to display accurate images, but slight variations in color and appearance may occur due to screen differences and lighting.' ],
            [ 'q' => 'Do you offer warranties on your products?',
              'a' => 'Warranty availability depends on the product and manufacturer. Please check the product description for warranty details.' ],
            [ 'q' => 'How can I check if a product is in stock?',
              'a' => 'The product page will indicate whether the item is in stock, out of stock, or available for pre-order.' ],
            [ 'q' => 'Can I request a custom or personalized product?',
              'a' => 'At the moment, we do not offer customization services.' ],
            [ 'q' => 'Are your products safe for babies?',
              'a' => 'Yes, all products are sourced from trusted manufacturers and meet applicable safety standards. Where applicable, products are BPA-free, organic, or eco-friendly.' ],
        ],
    ];

    $order = 0;
    foreach ( $data as $category_name => $faqs ) {
        $slug    = sanitize_title( $category_name );
        $term    = term_exists( $slug, 'faq_category' );
        if ( ! $term ) {
            $term = wp_insert_term( $category_name, 'faq_category', [ 'slug' => $slug ] );
        }
        $term_id = is_array( $term ) ? $term['term_id'] : $term;

        foreach ( $faqs as $faq ) {
            $existing = get_posts( [
                'post_type'      => 'bp_faq',
                'title'          => $faq['q'],
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ] );
            if ( ! empty( $existing ) ) continue;

            $post_id = wp_insert_post( [
                'post_type'    => 'bp_faq',
                'post_title'   => $faq['q'],
                'post_content' => $faq['a'],
                'post_status'  => 'publish',
                'menu_order'   => $order++,
            ] );

            if ( $post_id && ! is_wp_error( $post_id ) ) {
                wp_set_post_terms( $post_id, [ (int) $term_id ], 'faq_category' );
            }
        }
    }
}

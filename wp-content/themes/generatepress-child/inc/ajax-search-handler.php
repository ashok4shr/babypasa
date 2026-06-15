<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// AJAX Product Search Handler
function bp_ajax_product_search() {
    check_ajax_referer( 'bp_product_search_nonce', 'nonce' );

    $search_term = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';

    if ( empty( $search_term ) ) {
        wp_send_json_error( 'Empty search term' );
    }

    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        's'              => $search_term,
    );

    $query = new WP_Query( $args );

    ob_start();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            
            // Setup global product data so template parts can use it easily
            global $product;
            
            // Load the template part for a single search result
            get_template_part( 'template-parts/search-result' );
        }
        
        ?>
        <a href="<?php echo esc_url( add_query_arg( array( 's' => $search_term, 'post_type' => 'product' ), home_url( '/' ) ) ); ?>" class="bp-view-all">
            See all results for "<?php echo esc_html( $search_term ); ?>"
        </a>
        <?php
    } else {
        echo '<div class="bp-search-empty">No products found.</div>';
    }

    wp_reset_postdata();

    $html = ob_get_clean();
    wp_send_json_success( $html );
}

add_action( 'wp_ajax_bp_product_search', 'bp_ajax_product_search' );
add_action( 'wp_ajax_nopriv_bp_product_search', 'bp_ajax_product_search' );

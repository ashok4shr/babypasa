<?php
/**
 * Shared WooCommerce Helper Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper function for product cards
 */
if ( ! function_exists( 'bp_render_product_card' ) ) {
    function bp_render_product_card( $product_id, $context = 'grid' ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) return;

        $thumbnail_id = get_post_thumbnail_id( $product_id );
        $title        = get_the_title( $product_id );
        $price_html   = $product->get_price_html();
        $product_url  = get_permalink( $product_id );
        
        // Check if new (simulated logic: if created in last 30 days)
        $created = strtotime( $product->get_date_created() );
        $is_new = ( time() - $created ) < ( 30 * DAY_IN_SECONDS );

        ?>
        <div class="bp-product-card">
            <div class="bp-product-image-wrapper">
                <a href="<?php echo esc_url( $product_url ); ?>" class="bp-product-img-link">
                    <?php
                    if ( $thumbnail_id ) {
                        echo wp_get_attachment_image(
                            $thumbnail_id,
                            'woocommerce_thumbnail',
                            false,
                            array( 'alt' => esc_attr( $title ) )
                        );
                    } else {
                        echo wc_placeholder_img( 'woocommerce_thumbnail' );
                    }
                    ?>
                    <div class="bp-product-overlay"></div>
                </a>
                <?php if ( $is_new ) : ?>
                    <span class="bp-badge-new">NEW</span>
                <?php endif; ?>
                
                <!-- Hover Actions (Wishlist/Compare Placeholders) -->
                <div class="bp-product-actions bp-grid-actions">
                    <button class="bp-action-btn bp-wishlist-btn" title="Add to Wishlist" data-product_id="<?php echo esc_attr( $product_id ); ?>"></button>
                    <button class="bp-action-btn bp-compare-btn" title="Compare" data-product_id="<?php echo esc_attr( $product_id ); ?>"></button>
                </div>
            </div>
            <div class="bp-product-details">
                <h3 class="bp-product-title"><a href="<?php echo esc_url( $product_url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
                
                <?php
                if ( $context === 'archive' ) {
                    // Truncated short description for List View
                    $short_desc = $product->get_short_description();
                    $truncated_desc = wp_trim_words( $short_desc, 25, '...' );
                    if ( ! empty( $truncated_desc ) ) : ?>
                        <div class="bp-product-description">
                            <?php echo wp_kses_post( $truncated_desc ); ?>
                        </div>
                    <?php endif;
                }
                ?>

                <div class="bp-product-price"><?php echo wp_kses_post( $price_html ); ?></div>
                
                <?php if ( $context === 'archive' ) : ?>
                <div class="bp-product-footer">
                    <?php woocommerce_template_loop_add_to_cart(); ?>
                    
                    <!-- Actions visible next to cart button in List View -->
                    <div class="bp-product-actions bp-list-actions">
                        <button class="bp-action-btn bp-wishlist-btn" title="Add to Wishlist" data-product_id="<?php echo esc_attr( $product_id ); ?>"></button>
                        <button class="bp-action-btn bp-compare-btn" title="Compare" data-product_id="<?php echo esc_attr( $product_id ); ?>"></button>
                    </div>
                </div>
                <?php else : ?>
                    <?php if ( $product->is_type( 'variable' ) ) : ?>
                        <button class="bp-quick-add-btn add_to_cart_button button product_type_variable"
                                data-product_id="<?php echo esc_attr( $product_id ); ?>"
                                data-bp-cart-btn="true">
                            <?php esc_html_e( 'Select options', 'woocommerce' ); ?>
                        </button>
                    <?php else : ?>
                        <?php woocommerce_template_loop_add_to_cart(); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

/**
 * Helper function for product slider section
 */
if ( ! function_exists( 'bp_render_product_slider_section' ) ) {
    function bp_render_product_slider_section( $title, $tag_slug = '', $latest = false, $post_ids = array() ) {
        // Serve cached HTML when available (busted on save_post_product)
        $cache_key = 'bp_slider_' . md5( $tag_slug . ( $latest ? '1' : '0' ) . serialize( $post_ids ) );
        $html      = get_transient( $cache_key );

        if ( false !== $html ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => 10,
            'post_status'    => 'publish',
        );

        if ( ! empty( $post_ids ) ) {
            $args['post__in'] = $post_ids;
            $args['orderby'] = 'post__in';
            // === BABYPASA PRODUCT PICKER: START ===
            // Show every explicitly-selected product (not capped at the default 10).
            $args['posts_per_page'] = count( $post_ids );
            // === BABYPASA PRODUCT PICKER: END ===
        } elseif ( ! $latest && ! empty( $tag_slug ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => $tag_slug,
                ),
            );
        }

        $query = new WP_Query( $args );

        ob_start();
        if ( $query->have_posts() ) : ?>
            <section class="bp-products-section">
                <div class="bp-section-header">
                    <h2 class="bp-section-title"><?php echo esc_html( $title ); ?></h2>
                    <div class="bp-slider-controls">
                        <button class="bp-control-btn bp-prev-btn" aria-label="Previous">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z"/></svg>
                        </button>
                        <button class="bp-control-btn bp-next-btn" aria-label="Next">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>
                        </button>
                    </div>
                </div>
                <div class="bp-slider-container bp-product-slider">
                    <div class="bp-slider-track">
                        <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                            <div class="bp-slide">
                                <?php bp_render_product_card( get_the_ID() ); ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </section>
        <?php
        endif;
        wp_reset_postdata();

        $html = ob_get_clean();
        if ( $html ) {
            set_transient( $cache_key, $html, 2 * HOUR_IN_SECONDS );
        }
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

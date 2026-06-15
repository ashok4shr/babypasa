<?php
/**
 * The template for displaying the footer.
 *
 * @package GeneratePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

	</div>
</div>

<?php
/**
 * generate_before_footer hook.
 *
 * @since 0.1
 */
do_action( 'generate_before_footer' );
?>

<div <?php generate_do_attr( 'footer' ); ?>>
	<?php
	/**
	 * generate_before_footer_content hook.
	 *
	 * @since 0.1
	 */
	do_action( 'generate_before_footer_content' );
?>
	<footer class="bp-footer">
		<div class="bp-footer-container">

			<!-- WhatsApp Community Banner -->
			<div class="bp-wa-banner" role="complementary" aria-label="<?php esc_attr_e( 'WhatsApp Community', 'generatepress-child' ); ?>">
				<span class="bp-wa-banner__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" fill="#25D366" width="24" height="24">
						<path d="M16.004 2.667C8.64 2.667 2.667 8.64 2.667 16c0 2.363.627 4.581 1.72 6.506L2.667 29.333l7.04-1.693A13.28 13.28 0 0 0 16.004 29.333C23.36 29.333 29.333 23.36 29.333 16S23.36 2.667 16.004 2.667zm0 24.267a11.04 11.04 0 0 1-5.627-1.547l-.4-.24-4.173 1.093 1.107-4.053-.267-.413A11.03 11.03 0 0 1 4.96 16c0-6.08 4.96-11.04 11.04-11.04S27.04 9.92 27.04 16s-4.957 10.934-11.036 10.934zm6.08-8.24c-.333-.16-1.973-.973-2.28-1.08-.307-.107-.533-.16-.76.16-.227.32-.88 1.08-1.08 1.307-.2.227-.4.253-.733.08-.333-.16-1.413-.52-2.693-1.653-.997-.893-1.667-1.987-1.867-2.32-.2-.333-.02-.52.147-.68.15-.147.333-.373.5-.56.163-.187.217-.32.327-.533.107-.213.053-.4-.027-.56-.08-.16-.76-1.827-1.04-2.507-.28-.653-.56-.56-.76-.573H10.8c-.213 0-.56.08-.853.4-.293.32-1.12 1.093-1.12 2.667s1.147 3.093 1.307 3.307c.16.213 2.267 3.453 5.493 4.84.773.333 1.373.533 1.84.68.773.24 1.48.213 2.04.133.62-.093 1.973-.8 2.253-1.573.28-.773.28-1.44.2-1.573-.08-.133-.293-.213-.627-.373z"/>
					</svg>
				</span>
				<span class="bp-wa-banner__text">
					<?php esc_html_e( 'Join our WhatsApp Community for deals &amp; updates', 'generatepress-child' ); ?>
				</span>
				<a href="https://whatsapp.com/channel/0029Vb7dIAn3WHTTnuVclZ0X" class="bp-wa-banner__link" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Join Now →', 'generatepress-child' ); ?>
				</a>
			</div>

			<div class="bp-footer-grid">
				<!-- Shop Info -->
				<div class="bp-footer-col bp-shop-info">
					<h4 class="bp-footer-title">SHOP INFO</h4>
					<p>Welcome to BabyPasa.com, your trusted online store for premium baby products in Nepal. We offer a wide range of baby essentials, including toys, and care items, ensuring quality and comfort for your little ones.</p>
				</div>

				<!-- Information -->
				<div class="bp-footer-col">
					<h4 class="bp-footer-title">INFORMATION</h4>
					<ul class="bp-footer-links">
						<li><a href="<?php echo esc_url( home_url( '/privacy-and-cookie-policy' ) ); ?>">Privacy and Cookie Policy</a></li>
					    <li><a href="<?php echo esc_url( home_url( '/faq' ) ); ?>">FAQ</a></li>
						<li><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>">Contact Us</a></li>
					</ul>
				</div>
    
				<!-- Newsletter -->
				<div class="bp-footer-col bp-newsletter">
					<h4 class="bp-footer-title">NEWSLETTER</h4>
					<p>Subscribe our newsletter.</p>
					<?php echo do_shortcode( '[bpnl_form]' ); ?>
				</div>
			</div>

			<div class="bp-footer-bottom">
				<p>Copyright © <?php echo date('Y'); ?> Baby Pasa. All rights reserved.</p>
			</div>
		</div>
	</footer>

	<?php
	/**
	 * generate_after_footer_content hook.
	 *
	 * @since 0.1
	 */
	do_action( 'generate_after_footer_content' );
	?>
</div>

<?php
/**
 * generate_after_footer hook.
 *
 * @since 2.1
 */
do_action( 'generate_after_footer' );

wp_footer();
?>

<!-- WhatsApp Float Button -->
<style>
@keyframes wa-float-up {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes wa-pulse {
    0%   { box-shadow: 0 4px 16px rgba(37,211,102,0.45), 0 0 0 0 rgba(37,211,102,0.55); }
    70%  { box-shadow: 0 4px 16px rgba(37,211,102,0.45), 0 0 0 18px rgba(37,211,102,0); }
    100% { box-shadow: 0 4px 16px rgba(37,211,102,0.45), 0 0 0 0 rgba(37,211,102,0); }
}
.wa-float {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: #25D366;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(37,211,102,0.45);
    text-decoration: none;
    animation: wa-float-up 0.5s ease both, wa-pulse 2s ease-out 0.8s infinite;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wa-float:hover {
    transform: scale(1.12);
    box-shadow: 0 8px 28px rgba(37,211,102,0.65);
    animation: none;
}
.wa-float svg { width: 32px; height: 32px; fill: #fff; display: block; }
.wa-float::before {
    content: "Chat with us on WhatsApp";
    position: absolute;
    right: calc(100% + 14px);
    top: 50%;
    transform: translateY(-50%);
    background: #1a1a1a;
    color: #fff;
    font-size: 13px;
    font-family: -apple-system, sans-serif;
    white-space: nowrap;
    padding: 6px 12px;
    border-radius: 6px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}
.wa-float::after {
    content: "";
    position: absolute;
    right: calc(100% + 8px);
    top: 50%;
    transform: translateY(-50%);
    border: 6px solid transparent;
    border-left-color: #1a1a1a;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}
.wa-float:hover::before,
.wa-float:hover::after { opacity: 1; }
@media (max-width: 768px) {
    /* Sit just above the mobile bottom nav bar so the two never overlap.
       Nav bar: 68px tall, 20px from viewport bottom → clear it by ~16px. */
    .wa-float {
        width: 50px;
        height: 50px;
        bottom: calc(68px + 20px + 16px);
        right: 16px;
        animation: wa-float-up 0.5s ease both;
    }
    .wa-float svg { width: 27px; height: 27px; }
    /* Tooltip is pointless on touch devices */
    .wa-float::before,
    .wa-float::after { display: none; }
}
</style>

<a href="https://wa.me/+9779705511177"
   class="wa-float"
   target="_blank"
   rel="noopener noreferrer"
   aria-label="Chat on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true">
        <path d="M16.004 2.667C8.64 2.667 2.667 8.64 2.667 16c0 2.363.627 4.581 1.72 6.506L2.667 29.333l7.04-1.693A13.28 13.28 0 0 0 16.004 29.333C23.36 29.333 29.333 23.36 29.333 16S23.36 2.667 16.004 2.667zm0 24.267a11.04 11.04 0 0 1-5.627-1.547l-.4-.24-4.173 1.093 1.107-4.053-.267-.413A11.03 11.03 0 0 1 4.96 16c0-6.08 4.96-11.04 11.04-11.04S27.04 9.92 27.04 16s-4.957 10.934-11.036 10.934zm6.08-8.24c-.333-.16-1.973-.973-2.28-1.08-.307-.107-.533-.16-.76.16-.227.32-.88 1.08-1.08 1.307-.2.227-.4.253-.733.08-.333-.16-1.413-.52-2.693-1.653-.997-.893-1.667-1.987-1.867-2.32-.2-.333-.02-.52.147-.68.15-.147.333-.373.5-.56.163-.187.217-.32.327-.533.107-.213.053-.4-.027-.56-.08-.16-.76-1.827-1.04-2.507-.28-.653-.56-.56-.76-.573H10.8c-.213 0-.56.08-.853.4-.293.32-1.12 1.093-1.12 2.667s1.147 3.093 1.307 3.307c.16.213 2.267 3.453 5.493 4.84.773.333 1.373.533 1.84.68.773.24 1.48.213 2.04.133.62-.093 1.973-.8 2.253-1.573.28-.773.28-1.44.2-1.573-.08-.133-.293-.213-.627-.373z"/>
    </svg>
</a>
<!-- /WhatsApp Float Button -->

<!-- Mobile Bottom Nav -->
<style>
@keyframes mbn-slide-up {
    from { opacity: 0; transform: translateX(-50%) translateY(20px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}
.mbn-bar { display: none; }
@media (max-width: 768px) {
    .mbn-bar {
        display: flex;
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 40px);
        max-width: 480px;
        height: 68px;
        padding: 0 8px;
        background: #F5F5F0;
        border-radius: 40px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.15), 0 2px 8px rgba(0,0,0,0.08);
        align-items: center;
        justify-content: space-around;
        z-index: 9999;
        animation: mbn-slide-up 0.4s ease both;
    }
    .mbn-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        text-decoration: none;
        color: #2D3A4A;
        border-radius: 20px;
        padding: 6px 10px;
        transition: background 0.2s ease;
        -webkit-tap-highlight-color: transparent;
    }
    /* Reset browser/theme default button styles for the Shop trigger */
    button.mbn-item {
        background: none;
        border: none;
        cursor: pointer;
        font-family: inherit;
        font-size: inherit;
        outline: none;
        -webkit-appearance: none;
        appearance: none;
    }
    .mbn-item:hover:not(.mbn-active) {
        background: rgba(45,58,74,0.06);
    }
    .mbn-item svg {
        width: 24px;
        height: 24px;
        display: block;
        flex-shrink: 0;
    }
    .mbn-label {
        font-size: 10px;
        font-weight: 500;
        font-family: inherit;
        line-height: 1;
        color: #2D3A4A;
        white-space: nowrap;
    }
    .mbn-item.mbn-active {
        background: #FFFFFF;
        border-radius: 30px;
        padding: 6px 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    }
    .mbn-item.mbn-active .mbn-label {
        font-weight: 700;
        color: #1A1A2E;
    }
    .mbn-item.mbn-active svg { stroke: #1A1A2E; }
    /* Cart icon + count badge inside the bottom nav */
    .mbn-cart-icon-wrap {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .mbn-cart-item .bp-cart-count {
        position: absolute;
        top: -7px;
        right: -9px;
        background: #FF2A61;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        min-width: 16px;
        height: 16px;
        padding: 0 3px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 0 2px #F5F5F0;
    }
    .mbn-cart-item.mbn-active .bp-cart-count {
        box-shadow: 0 0 0 2px #FFFFFF;
    }
}
</style>

<nav class="mbn-bar" role="navigation" aria-label="Mobile navigation">

    <a href="<?php echo home_url('/'); ?>" class="mbn-item" title="Home">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 9.5L12 3l9 6.5V20a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V9.5z"/>
            <path d="M9 21V12h6v9"/>
        </svg>
        <span class="mbn-label">Home</span>
    </a>

    <button type="button" id="mbn-shop-btn" class="mbn-item"
            title="<?php esc_attr_e( 'Shop', 'generatepress-child' ); ?>"
            aria-expanded="false" aria-controls="mbn-cats-sheet">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        <span class="mbn-label"><?php esc_html_e( 'Shop', 'generatepress-child' ); ?></span>
    </button>

    <a href="<?php echo home_url('/my-account'); ?>" class="mbn-item" title="Account">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
        </svg>
        <span class="mbn-label">Account</span>
    </a>

    <a href="<?php echo home_url('/my-account/wishlist/'); ?>" class="mbn-item" title="Wishlist">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        <span class="mbn-label">Wishlist</span>
    </a>

    <?php
    $bp_mbn_cart_count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
    ?>
    <button type="button"
            id="mbn-cart-btn"
            class="mbn-item mbn-cart-item"
            aria-controls="mini-cart-drawer"
            aria-expanded="false"
            title="<?php esc_attr_e( 'Cart', 'generatepress-child' ); ?>"
            data-cart-url="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>">
        <span class="mbn-cart-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6h-2a4 4 0 0 0-8 0H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2z"/>
                <path d="M8 6a4 4 0 0 1 8 0"/>
            </svg>
            <span class="bp-cart-count"<?php echo (int) $bp_mbn_cart_count === 0 ? ' style="display:none"' : ''; ?>><?php echo (int) $bp_mbn_cart_count > 0 ? esc_html( $bp_mbn_cart_count ) : ''; ?></span>
        </span>
        <span class="mbn-label"><?php esc_html_e( 'Cart', 'generatepress-child' ); ?></span>
    </button>

</nav>
<script>
(function() {
    var loc = window.location.href.split('?')[0].split('#')[0].replace(/\/$/, '');
    document.querySelectorAll('.mbn-item').forEach(function(a) {
        if (a.tagName !== 'A') return;
        var href = a.href.split('?')[0].split('#')[0].replace(/\/$/, '');
        if (!href || href.indexOf('wa.me') !== -1) return;
        var isActive = href === loc;
        if (isActive) {
            a.classList.add('mbn-active');
            a.setAttribute('aria-current', 'page');
        }
    });

    // Cart item opens the mini-cart drawer — same behaviour as the header cart
    // icon — by forwarding the click to #mini-cart-trigger (handled by
    // mini-cart.js). Falls back to the cart page only if the drawer/trigger is
    // unavailable (e.g. WooCommerce inactive).
    var cartBtn = document.getElementById('mbn-cart-btn');
    if (cartBtn) {
        cartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var trigger = document.getElementById('mini-cart-trigger');
            if (trigger) {
                trigger.click();
            } else {
                var url = cartBtn.getAttribute('data-cart-url');
                if (url) window.location.href = url;
            }
        });
    }
})();
</script>
<!-- /Mobile Bottom Nav -->

<?php
// ── Categories sheet — shown when the "Shop" mobile nav button is tapped ──
if ( class_exists( 'WooCommerce' ) ) :
    $bp_shop_cats = get_terms( array(
        'taxonomy'   => 'product_cat',
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
        'parent'     => 0,
        'hide_empty' => true,
        'exclude'    => array( (int) get_option( 'default_product_cat' ) ), // exclude Uncategorized
    ) );
    if ( ! is_wp_error( $bp_shop_cats ) && ! empty( $bp_shop_cats ) ) :
?>
<div id="mbn-cats-overlay" class="mbn-cats-overlay" aria-hidden="true"></div>
<div id="mbn-cats-sheet"
     class="mbn-cats-sheet"
     role="dialog"
     aria-modal="true"
     aria-label="<?php esc_attr_e( 'Browse Categories', 'generatepress-child' ); ?>"
     aria-hidden="true">
    <div class="mbn-cats-sheet__header">
        <span class="mbn-cats-sheet__title"><?php esc_html_e( 'Browse Categories', 'generatepress-child' ); ?></span>
        <button type="button" class="mbn-cats-sheet__close"
                aria-label="<?php esc_attr_e( 'Close', 'generatepress-child' ); ?>">&times;</button>
    </div>
    <ul class="mbn-cats-sheet__list">
        <?php foreach ( $bp_shop_cats as $bp_cat ) : ?>
        <li>
            <a href="<?php echo esc_url( get_term_link( $bp_cat ) ); ?>">
                <?php echo esc_html( $bp_cat->name ); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<script>
(function() {
    var btn     = document.getElementById('mbn-shop-btn');
    var sheet   = document.getElementById('mbn-cats-sheet');
    var overlay = document.getElementById('mbn-cats-overlay');
    if (!btn || !sheet || !overlay) return;

    function openSheet() {
        sheet.classList.add('is-open');
        sheet.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
        btn.setAttribute('aria-expanded', 'true');
        btn.classList.add('mbn-active');
        document.body.style.overflow = 'hidden';
    }
    function closeSheet() {
        sheet.classList.remove('is-open');
        sheet.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        btn.setAttribute('aria-expanded', 'false');
        btn.classList.remove('mbn-active');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', function() {
        sheet.classList.contains('is-open') ? closeSheet() : openSheet();
    });
    overlay.addEventListener('click', closeSheet);
    sheet.querySelector('.mbn-cats-sheet__close').addEventListener('click', closeSheet);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSheet();
    });
})();
</script>
<?php endif; endif; ?>

</body>
</html>

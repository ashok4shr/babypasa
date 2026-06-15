<?php
/**
 * My Account Page — BabyPasa Custom Template
 *
 * Overrides the default WooCommerce My Account template.
 *
 * Logged-out : renders the auth card (login / register) via form-login.php
 *              through the woocommerce_account_content action.
 * Logged-in  : renders a personalised welcome bar, a tab-based navigation
 *              (via navigation.php), and the content panel.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package BabyPasa\MyAccount
 * @version 3.5.0 (WooCommerce base)
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) :

	$current_user = wp_get_current_user();
	$first_name   = ! empty( $current_user->first_name )
		? $current_user->first_name
		: $current_user->display_name;

	?>
	<div class="bp-myaccount-wrapper">

		<!-- ── Welcome / greeting bar ───────────────────────────────── -->
		<div class="bp-myaccount-welcome">
			<div class="bp-myaccount-welcome__text">
				<h2 class="bp-myaccount-welcome__greeting">
					<?php
					printf(
						/* translators: %s: user first name */
						esc_html__( 'Hello, %s 👋', 'generatepress-child' ),
						esc_html( $first_name )
					);
					?>
				</h2>
			</div>
			<div class="bp-myaccount-welcome__avatar">
				<?php
				echo get_avatar(
					$current_user->ID,
					48,
					'',
					esc_attr( $first_name ),
					array( 'class' => 'bp-myaccount-avatar' )
				);
				?>
			</div>
		</div><!-- .bp-myaccount-welcome -->

		<!-- ── Tab navigation (navigation.php) ─────────────────────── -->
		<?php
		/**
		 * My Account navigation.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_account_navigation' );
		?>

		<!-- ── Content panel ────────────────────────────────────────── -->
		<div class="bp-myaccount-content-panel">
			<div class="woocommerce-MyAccount-content">
				<?php
				/**
				 * My Account content.
				 *
				 * @since 2.6.0
				 */
				do_action( 'woocommerce_account_content' );
				?>
			</div>
		</div><!-- .bp-myaccount-content-panel -->

	</div><!-- .bp-myaccount-wrapper -->

<?php else : ?>

	<!-- Logged-out: woocommerce_account_content outputs the login form -->
	<div class="bp-myaccount-auth-wrapper">
		<div class="woocommerce-MyAccount-content">
			<?php do_action( 'woocommerce_account_content' ); ?>
		</div>
	</div><!-- .bp-myaccount-auth-wrapper -->

<?php endif; ?>

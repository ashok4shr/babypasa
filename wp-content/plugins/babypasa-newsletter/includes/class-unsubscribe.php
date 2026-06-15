<?php
/**
 * Handles one-click unsubscribe via the ?bpnl_unsubscribe=TOKEN query var.
 */

namespace BabypasaNewsletter\Includes;

defined( 'ABSPATH' ) || exit;

class Unsubscribe {

	public function __construct() {
		add_action( 'init',      array( $this, 'handle' ) );
		add_action( 'wp_footer', array( $this, 'maybe_show_notice' ) );
	}

	/**
	 * Intercepts the unsubscribe token on init, updates the DB, then redirects.
	 */
	public function handle(): void {
		if ( ! isset( $_GET['bpnl_unsubscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['bpnl_unsubscribe'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $token ) ) {
			return;
		}

		$subscriber = Subscriber::get_by_token( $token );
		if ( $subscriber && 'active' === $subscriber->status ) {
			Subscriber::unsubscribe( (int) $subscriber->id );
		}

		wp_safe_redirect( add_query_arg( 'bpnl', 'unsubscribed', home_url( '/' ) ) );
		exit;
	}

	/**
	 * Shows a dismissible front-end notice after a successful unsubscribe redirect.
	 * Uses the same .bp-notification toast structure as babypasa-wishlist-compare.
	 */
	public function maybe_show_notice(): void {
		if ( ! isset( $_GET['bpnl'] ) || 'unsubscribed' !== $_GET['bpnl'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		?>
		<script>
		( function () {
			var container = document.querySelector( '.bp-notification-container' );
			if ( ! container ) {
				container = document.createElement( 'div' );
				container.className = 'bp-notification-container';
				document.body.appendChild( container );
			}

			var id   = 'bp-toast-' + Date.now();
			var html = '<div class="bp-notification" id="' + id + '" data-notif-type="bpnl-unsubscribed" role="alert">'
				+ '<button class="bp-notification-close" aria-label="Close">&times;</button>'
				+ '<div class="bp-notification-title"><?php echo esc_js( __( 'Unsubscribed', 'babypasa-newsletter' ) ); ?></div>'
				+ '<div class="bp-notification-message"><p><?php echo esc_js( __( 'You have been unsubscribed.', 'babypasa-newsletter' ) ); ?></p></div>'
				+ '</div>';

			container.insertAdjacentHTML( 'beforeend', html );

			var $n = document.getElementById( id );

			// Trigger reflow so the transition fires.
			void $n.offsetWidth;
			$n.classList.add( 'bp-show' );

			var timer = setTimeout( function () {
				$n.classList.remove( 'bp-show' );
				$n.classList.add( 'bp-hiding' );
				setTimeout( function () { $n.parentNode && $n.parentNode.removeChild( $n ); }, 300 );
			}, 5000 );

			$n.querySelector( '.bp-notification-close' ).addEventListener( 'click', function () {
				clearTimeout( timer );
				$n.classList.remove( 'bp-show' );
				$n.classList.add( 'bp-hiding' );
				setTimeout( function () { $n.parentNode && $n.parentNode.removeChild( $n ); }, 300 );
			} );
		} )();
		</script>
		<?php
	}
}

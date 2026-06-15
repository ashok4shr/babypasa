<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Front-end popup and banner rendering for BP Ads Manager.
 *
 * Popup HTML is injected via wp_footer; banners via the homepage section
 * actions (added to template-homepage.php):
 *   - bp_after_trending_products   → placement slug 'trending'
 *   - bp_after_daily_essentials    → placement slug 'daily_essentials'
 *   - bp_after_new_products        → placement slug 'new_products'
 * Each banner only renders in the sections listed in its placement.
 * Assets are only enqueued when active ads exist.
 */
class BP_Ads_Renderer {

	/** @var array|null Cached popup ads. */
	private $popup_ads = null;

	/** @var array|null Cached banner ads. */
	private $banner_ads = null;

	/**
	 * Registers front-end hooks.
	 *
	 * Respects the ads_enabled scope:
	 *   'global'    → show on every page (default / legacy value of 1)
	 *   'frontpage' → show only on the homepage
	 *   'disabled'  → show nowhere
	 */
	public function register_hooks() {
		$settings = get_option( 'bp_ads_settings', array( 'ads_enabled' => 'global' ) );

		// Backward-compat: legacy stored value was integer 1.
		$scope = $settings['ads_enabled'] ?? 'global';
		if ( 1 === $scope || '1' === $scope ) {
			$scope = 'global';
		}

		if ( 'disabled' === $scope || empty( $scope ) ) {
			return;
		}

		if ( 'frontpage' === $scope ) {
			// Only hook when we are on the front page; bail out on all other pages.
			add_action( 'wp', function () {
				if ( ! is_front_page() && ! is_home() ) {
					return;
				}
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
				add_action( 'wp_footer',          array( $this, 'render_popups' ) );
				$this->register_banner_hooks();
			} );
			return;
		}

		// 'global' scope — register on every page.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer',          array( $this, 'render_popups' ) );
		$this->register_banner_hooks();
	}

	/**
	 * Wires each homepage section action to a placement-scoped banner renderer.
	 *
	 * The child theme's template-homepage.php fires these actions after the
	 * Daily Essentials, New Products, and Trending Products slider sections.
	 */
	private function register_banner_hooks() {
		add_action( 'bp_after_daily_essentials',  array( $this, 'render_banners_daily_essentials' ) );
		add_action( 'bp_after_new_products',      array( $this, 'render_banners_new_products' ) );
		add_action( 'bp_after_trending_products', array( $this, 'render_banners_trending' ) );
	}

	/**
	 * Enqueues front-end CSS/JS only when active ads exist.
	 */
	public function enqueue_assets() {
		$has_popups  = ! empty( $this->get_popup_ads() );
		$has_banners = ! empty( $this->get_banner_ads() );

		if ( ! $has_popups && ! $has_banners ) {
			return;
		}

		wp_enqueue_style(
			'bp-ads-front',
			BP_ADS_URL . 'assets/css/ads-front.css',
			array(),
			filemtime( BP_ADS_PATH . 'assets/css/ads-front.css' )
		);

		if ( $has_popups ) {
			wp_enqueue_script(
				'bp-ads-front',
				BP_ADS_URL . 'assets/js/ads-front.js',
				array(),
				filemtime( BP_ADS_PATH . 'assets/js/ads-front.js' ),
				true
			);

			$ads_config = array();
			foreach ( $this->get_popup_ads() as $ad ) {
				$ads_config[] = array(
					'id'          => absint( $ad->id ),
					'frequency'   => $ad->frequency,
					'popup_delay' => absint( $ad->popup_delay ),
					'device'      => $ad->device,
					'link_url'    => ! empty( $ad->link_url ) ? esc_url( $ad->link_url ) : '',
				);
			}

			wp_localize_script( 'bp-ads-front', 'bpAdsConfig', array(
				'ads'     => $ads_config,
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			) );
		}
	}

	/**
	 * Returns the rendered ad body — image or HTML — based on content_mode.
	 *
	 * For image mode: uses wp_get_attachment_image() so srcset, lazy-load, and
	 * alt text come from the media library automatically.
	 * For html mode (and legacy rows where content_mode is empty): outputs the
	 * stored HTML filtered through the plugin's wp_kses whitelist.
	 *
	 * @param object $ad Ad row.
	 * @return string HTML string (not yet echo'd).
	 */
	private function render_ad_body( $ad ) {
		$mode = isset( $ad->content_mode ) ? $ad->content_mode : 'html';

		if ( 'image' === $mode && ! empty( $ad->image_id ) ) {
			return wp_get_attachment_image(
				absint( $ad->image_id ),
				'full',
				false,
				array( 'style' => 'max-width:100%;height:auto;display:block;' )
			);
		}

		// html mode or legacy rows without a content_mode value.
		return wp_kses( $ad->content, BP_Ads_DB::get_html_kses_allowed() );
	}

	/**
	 * Outputs hidden popup overlay HTML into wp_footer.
	 * JS reads bpAdsConfig to decide which ones to show.
	 */
	public function render_popups() {
		$popups = $this->get_popup_ads();
		if ( empty( $popups ) ) {
			return;
		}

		foreach ( $popups as $ad ) {
			$overlay_id  = 'bp-popup-' . absint( $ad->id );
			$aria_label  = ! empty( $ad->title ) ? esc_attr( $ad->title ) : esc_attr__( 'Advertisement', 'bp-ads-manager' );

			echo '<div id="' . esc_attr( $overlay_id ) . '" class="bp-popup-overlay" aria-modal="true" role="dialog" aria-label="' . $aria_label . '" style="display:none">';
			echo '<button class="bp-ad-close" aria-label="' . esc_attr__( 'Close', 'bp-ads-manager' ) . '">&times;</button>';
			echo '<div class="bp-popup-modal">';
			echo '<div class="bp-ad-container">';
			echo $this->render_ad_body( $ad ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			echo '</div>';
			echo '</div>';
		}
	}

	/**
	 * Renders banners assigned to the "After Trending Products" section.
	 * Hooked to bp_after_trending_products.
	 */
	public function render_banners_trending() {
		$this->render_banners_for_section( BP_Ads_DB::PLACEMENT_TRENDING );
	}

	/**
	 * Renders banners assigned to the "After Daily Essentials" section.
	 * Hooked to bp_after_daily_essentials.
	 */
	public function render_banners_daily_essentials() {
		$this->render_banners_for_section( BP_Ads_DB::PLACEMENT_DAILY_ESSENTIALS );
	}

	/**
	 * Renders banners assigned to the "After New Products" section.
	 * Hooked to bp_after_new_products.
	 */
	public function render_banners_new_products() {
		$this->render_banners_for_section( BP_Ads_DB::PLACEMENT_NEW_PRODUCTS );
	}

	/**
	 * Outputs banner ads assigned to a given homepage section.
	 * Device targeting is applied server-side via CSS utility classes.
	 *
	 * @param string $section Placement slug (see BP_Ads_DB::PLACEMENT_* constants).
	 */
	private function render_banners_for_section( $section ) {
		$banners = BP_Ads_DB::get_active_banners_for_placement( $section );
		if ( empty( $banners ) ) {
			return;
		}

		foreach ( $banners as $ad ) {
			$wrapper_class = 'bp-banner-wrapper';
			if ( 'mobile' === $ad->device ) {
				$wrapper_class .= ' bp-hide-desktop';
			} elseif ( 'desktop' === $ad->device ) {
				$wrapper_class .= ' bp-hide-mobile';
			}

			echo '<div class="' . esc_attr( $wrapper_class ) . '">';
			// removed the advertisement label for now since some clients find it visually distracting, but can be re-added if needed for compliance (e.g. echoing a <span> with a visually subtle style).
			// echo '<span class="bp-banner-label">' . esc_html__( 'Advertisement', 'bp-ads-manager' ) . '</span>';

			$body = $this->render_ad_body( $ad ); // phpcs:ignore WordPress.Security.EscapeOutput

			if ( ! empty( $ad->link_url ) ) {
				echo '<a href="' . esc_url( $ad->link_url ) . '" class="bp-banner-link" target="_blank" rel="noopener noreferrer">';
				echo '<div class="bp-ad-container">' . $body . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '</a>';
			} else {
				echo '<div class="bp-ad-container">' . $body . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}

			echo '</div>';
		}
	}

	/**
	 * Returns cached active popup ads.
	 *
	 * @return array
	 */
	private function get_popup_ads() {
		if ( null === $this->popup_ads ) {
			$this->popup_ads = BP_Ads_DB::get_active( 'popup' );
		}
		return $this->popup_ads;
	}

	/**
	 * Returns cached active banner ads.
	 *
	 * @return array
	 */
	private function get_banner_ads() {
		if ( null === $this->banner_ads ) {
			$this->banner_ads = BP_Ads_DB::get_active( 'banner' );
		}
		return $this->banner_ads;
	}
}

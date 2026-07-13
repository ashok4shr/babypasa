<?php
/**
 * Feature 1: Free Delivery product flag.
 *
 * Adds a "Offer Free Delivery" checkbox and a "Free Delivery Areas" district
 * picker to the WooCommerce product edit page, and shows the matching "Free
 * Delivery" badge on the product page and in the cart. When ANY item in a cart
 * carries the flag (or matches a free area), the Upaya Cargo shipping cost is
 * zeroed for the whole single-shipment package — but the actual rate change is
 * performed by BP_Area_Override via the shared BP_Delivery_Charge_Resolver, not
 * here, so free delivery cannot be clobbered by the area/default override.
 *
 * @package BabyPasa_Delivery_Overrides
 * @author  Ashok Shrestha / The Hive Craft
 */

defined( 'ABSPATH' ) || exit;

class BP_Free_Delivery_Product {

	public function __construct() {
		// Product edit-page field — Shipping tab is visible for all product types.
		add_action( 'woocommerce_product_options_shipping', [ $this, 'add_product_field' ] );
		add_action( 'woocommerce_process_product_meta',     [ $this, 'save_product_field' ] );

		// NOTE: this class no longer hooks woocommerce_package_rates. The rate is
		// applied by BP_Area_Override::apply_delivery_charge() via the shared
		// BP_Delivery_Charge_Resolver, which evaluates the free-delivery flag and
		// free-delivery-areas as the highest-precedence rules. Keeping a second
		// filter here is what previously let the area/default override clobber the
		// free-delivery zero.

		// Frontend badges.
		add_action( 'woocommerce_single_product_summary', [ $this, 'show_product_badge' ], 29 );
		add_filter( 'woocommerce_cart_item_name',         [ $this, 'append_cart_badge'  ], 10, 3 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/* ------------------------------------------------------------------
	 * Product meta field
	 * ------------------------------------------------------------------ */

	public function add_product_field(): void {
		woocommerce_wp_checkbox( [
			'id'          => '_bp_free_delivery',
			'label'       => __( 'Offer Free Delivery', 'babypasa-delivery-overrides' ),
			'description' => __( 'When every item in the cart has this enabled, shipping is free.', 'babypasa-delivery-overrides' ),
		] );

		// BABYPASA: Scenario 3 — area-based free delivery. A district multi-select
		// lets the merchant pick the districts where THIS product ships free (e.g.
		// "Kathmandu" only). Districts come from the live Upaya location list, so the
		// merchant can only pick areas Upaya actually serves. Stored on the parent
		// product (variations inherit) in _bp_free_delivery_areas as an array of tokens.
		global $post;
		$product_id = $post ? (int) $post->ID : 0;
		$saved      = $this->get_product_free_areas( $product_id );
		$available  = $this->get_selectable_districts();

		// Merge any saved tokens into the option list so a selection is never lost when
		// the Upaya location cache is cold (the saved district may not be in $available).
		$options = $available;
		foreach ( $saved as $token ) {
			if ( '' !== $token && ! isset( $options[ $token ] ) ) {
				$options[ $token ] = $token;
			}
		}
		// Help text goes in a WooCommerce ? tooltip (the native pattern) so the long
		// guidance doesn't wrap awkwardly beside the floated label.
		$help = __( 'Free delivery applies only when the customer\'s delivery district is one of those selected (e.g. Kathmandu). Leave empty for no area-based free delivery. To ship free everywhere instead, use the “Offer Free Delivery” checkbox above.', 'babypasa-delivery-overrides' );
		?>
		<p class="form-field _bp_free_delivery_areas_field">
			<label for="_bp_free_delivery_areas"><?php esc_html_e( 'Free Delivery Areas', 'babypasa-delivery-overrides' ); ?></label>
			<select id="_bp_free_delivery_areas"
				name="_bp_free_delivery_areas[]"
				multiple="multiple"
				class="wc-enhanced-select"
				style="width:50%;"
				data-placeholder="<?php esc_attr_e( 'Select districts…', 'babypasa-delivery-overrides' ); ?>">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( in_array( (string) $value, $saved, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php echo wc_help_tip( $help ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() returns escaped markup. ?>
		</p>
		<?php if ( empty( $available ) ) : ?>
		<p class="form-field">
			<label>&nbsp;</label>
			<span class="description" style="color:#b32d2e;">
				<?php esc_html_e( 'Upaya area list unavailable — refresh via WooCommerce → Settings → Shipping → Upaya Cargo → Flush Location Cache.', 'babypasa-delivery-overrides' ); ?>
			</span>
		</p>
		<?php endif; ?>
		<?php
	}

	public function save_product_field( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC handles nonce on product save.
		update_post_meta( $post_id, '_bp_free_delivery', isset( $_POST['_bp_free_delivery'] ) ? 'yes' : '' );

		// BABYPASA: Save the Scenario-3 district multi-select. Sanitise each token and
		// store a de-duplicated array; an empty submission clears the override (fail-safe).
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC handles nonce on product save.
		$raw   = isset( $_POST['_bp_free_delivery_areas'] ) ? (array) wp_unslash( $_POST['_bp_free_delivery_areas'] ) : [];
		$clean = [];
		foreach ( $raw as $token ) {
			$token = sanitize_text_field( $token );
			if ( '' !== $token && ! in_array( $token, $clean, true ) ) {
				$clean[] = $token;
			}
		}
		update_post_meta( $post_id, '_bp_free_delivery_areas', $clean );
	}

	/* ------------------------------------------------------------------
	 * Badges
	 * ------------------------------------------------------------------ */

	/** Shows "Free Delivery" badge on the single-product page (before add-to-cart). */
	public function show_product_badge(): void {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// BABYPASA: No delivery badge on items that don't ship (virtual/downloadable).
		if ( ! $product->needs_shipping() ) {
			return;
		}

		$id        = $product->get_id();
		$parent_id = $product->get_parent_id();

		$flag = get_post_meta( $id, '_bp_free_delivery', true );
		// Also check parent for variations shown as single products.
		if ( 'yes' !== $flag && $parent_id ) {
			$flag = get_post_meta( $parent_id, '_bp_free_delivery', true );
		}

		// Free everywhere takes precedence — unconditional badge.
		if ( 'yes' === $flag ) {
			echo '<span class="bp-free-delivery-badge">' . esc_html__( 'Free Delivery', 'babypasa-delivery-overrides' ) . '</span>';
			return;
		}

		// BABYPASA: Scenario 3 — show which districts ship free so the promise isn't
		// over-stated to out-of-area shoppers. Skipped when the feature is disabled.
		if ( ! $this->areas_feature_enabled() ) {
			return;
		}
		$areas = $this->get_product_free_areas( $id );
		if ( empty( $areas ) && $parent_id ) {
			$areas = $this->get_product_free_areas( $parent_id );
		}
		if ( ! empty( $areas ) ) {
			echo '<span class="bp-free-delivery-badge">'
				. esc_html( sprintf(
					/* translators: %s: comma-separated district list */
					__( 'Free Delivery in %s', 'babypasa-delivery-overrides' ),
					implode( ', ', $areas )
				) )
				. '</span>';
		}
	}

	/**
	 * Appends a small "Free Delivery" chip after the product name in the cart
	 * and checkout order-review table.
	 *
	 * @param  string $name          Product name HTML.
	 * @param  array  $cart_item     Cart item data.
	 * @param  string $cart_item_key Cart item hash key.
	 * @return string
	 */
	public function append_cart_badge( string $name, array $cart_item, string $cart_item_key ): string {
		$product_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];

		// Free everywhere.
		$flag = get_post_meta( $product_id, '_bp_free_delivery', true );
		if ( 'yes' !== $flag ) {
			$flag = get_post_meta( $cart_item['product_id'], '_bp_free_delivery', true );
		}
		$qualifies = ( 'yes' === $flag );

		// BABYPASA: Scenario 3 — only badge the item when the currently chosen
		// destination district matches one of the product's selected districts, so the
		// cart never shows "Free Delivery" for an order that will actually be charged.
		if ( ! $qualifies && $this->areas_feature_enabled() ) {
			$dest_district = $this->current_destination_district();
			if ( '' !== $dest_district ) {
				$areas = $this->get_product_free_areas( $product_id );
				if ( empty( $areas ) ) {
					$areas = $this->get_product_free_areas( $cart_item['product_id'] );
				}
				$qualifies = $this->district_in_list( $dest_district, $areas );
			}
		}

		if ( $qualifies ) {
			$name .= ' <span class="bp-free-delivery-badge bp-free-delivery-badge--inline">'
				. esc_html__( 'Free Delivery', 'babypasa-delivery-overrides' )
				. '</span>';
		}

		return $name;
	}

	/* ------------------------------------------------------------------
	 * Scenario 3 — area-based free delivery helpers
	 * ------------------------------------------------------------------ */

	/**
	 * BABYPASA: The following are thin delegators to BP_Delivery_Charge_Resolver,
	 * the single canonical implementation of the area-normalisation + free-delivery
	 * logic. The badge display below and the shared resolver therefore never
	 * disagree on what "free" means. See class-delivery-charge-resolver.php.
	 */
	private function areas_feature_enabled(): bool {
		return BP_Delivery_Charge_Resolver::areas_feature_enabled();
	}

	/**
	 * @param int $product_id Product (or parent) ID.
	 * @return string[]
	 */
	private function get_product_free_areas( int $product_id ): array {
		return BP_Delivery_Charge_Resolver::get_product_free_areas( $product_id );
	}

	/**
	 * @param string $area_name Full Upaya area name.
	 * @return string District token (may be empty).
	 */
	private function district_from_area( string $area_name ): string {
		return BP_Delivery_Charge_Resolver::district_from_area( $area_name );
	}

	/**
	 * BABYPASA: District of the customer's current session destination, used for the
	 * cart/checkout badge (which has no $package). Prefers shipping city, falls back to
	 * billing city — Upaya writes the area name into both.
	 */
	private function current_destination_district(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
			return '';
		}
		$city = (string) WC()->customer->get_shipping_city();
		if ( '' === $city ) {
			$city = (string) WC()->customer->get_billing_city();
		}
		return $this->district_from_area( $city );
	}

	/**
	 * @param string   $district Destination district.
	 * @param string[] $areas    Product's selected district tokens.
	 * @return bool
	 */
	private function district_in_list( string $district, array $areas ): bool {
		return BP_Delivery_Charge_Resolver::district_in_list( $district, $areas );
	}

	/**
	 * BABYPASA: Distinct, sorted district list for the admin picker, derived from the
	 * live Upaya location cache so merchants only pick districts Upaya serves. Returns
	 * [token => token]; empty when the cache is cold or Upaya classes are unavailable
	 * (the field then falls back to showing only previously-saved values — see
	 * add_product_field()). Admin-only; never called on the front end.
	 *
	 * AREA-LEVEL (future): replace district_from_area( $name ) with the full $name to
	 * offer area-level selection (pair with the matching change in district_from_area()).
	 *
	 * @return array<string,string>
	 */
	private function get_selectable_districts(): array {
		if ( ! class_exists( 'UPAYA_Location_Cache' ) || ! class_exists( 'UPAYA_API' ) || ! class_exists( 'UPAYA_Logger' ) ) {
			return [];
		}

		// Same instantiation pattern Upaya uses in its own admin/shipping classes.
		$logger = new UPAYA_Logger();
		$api    = new UPAYA_API( get_option( 'upaya_api_key', '' ), $logger );
		$cache  = new UPAYA_Location_Cache( $api, $logger );

		$districts = [];
		foreach ( $cache->get_locations() as $location ) {
			$name     = isset( $location['name'] ) ? (string) $location['name'] : '';
			$district = $this->district_from_area( $name );
			if ( '' !== $district ) {
				$districts[ $district ] = $district;
			}
		}
		ksort( $districts, SORT_NATURAL | SORT_FLAG_CASE );
		return $districts;
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public function enqueue_styles(): void {
		wp_enqueue_style(
			'bp-delivery-overrides',
			BP_DELIVERY_OVERRIDES_URL . 'assets/css/delivery-overrides.css',
			[],
			filemtime( BP_DELIVERY_OVERRIDES_DIR . 'assets/css/delivery-overrides.css' )
		);
	}
}

<?php
/**
 * Feature 1: Free Delivery product flag.
 *
 * Adds a "Offer Free Delivery" checkbox to the WooCommerce product edit page.
 * When ANY item in a cart carries the flag, the Upaya Cargo shipping cost is
 * zeroed out for the whole (single-shipment) package via the
 * woocommerce_package_rates filter (no core files touched).
 * A "Free Delivery" badge is shown on the product page and in the cart.
 *
 * @package BabyPasa_Delivery_Overrides
 */

defined( 'ABSPATH' ) || exit;

class BP_Free_Delivery_Product {

	public function __construct() {
		// Product edit-page field — Shipping tab is visible for all product types.
		add_action( 'woocommerce_product_options_shipping', [ $this, 'add_product_field' ] );
		add_action( 'woocommerce_process_product_meta',     [ $this, 'save_product_field' ] );

		// Rate override — priority 10 so area overrides (priority 20) can still win.
		add_filter( 'woocommerce_package_rates', [ $this, 'override_rate_if_any_free' ], 10, 2 );

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
	 * Shipping rate override
	 * ------------------------------------------------------------------ */

	/**
	 * Zeros the Upaya shipping cost when AT LEAST ONE item in the package is
	 * flagged as free delivery. Because woocommerce_package_rates fires per
	 * package and a package is a single shipment to one destination, one free
	 * item makes the whole shipment free. Returns rates unchanged only when no
	 * item is flagged.
	 *
	 * @param  WC_Shipping_Rate[] $rates   Rates keyed by rate ID.
	 * @param  array              $package WooCommerce shipping package.
	 * @return WC_Shipping_Rate[]
	 */
	public function override_rate_if_any_free( array $rates, array $package ): array {
		if ( empty( $package['contents'] ) ) {
			return $rates;
		}

		// BABYPASA: Scenario 3 can be globally disabled via constant or filter (rollback
		// switch). When off, only the original free-everywhere flag is evaluated below.
		$areas_enabled = $this->areas_feature_enabled();

		// BABYPASA: Destination district (last "-" segment of billing_city), computed
		// once. Empty when no area is selected yet — then the area-based branch can't match.
		$dest_district = $areas_enabled ? $this->get_destination_district( $package ) : '';

		$has_free_item = false;
		foreach ( $package['contents'] as $item ) {
			$product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
			// Check the variation/simple ID first, then fall back to the parent
			// product ID — the meta is stored on the parent for variable products.
			if ( 'yes' === get_post_meta( $product_id, '_bp_free_delivery', true )
				|| 'yes' === get_post_meta( $item['product_id'], '_bp_free_delivery', true ) ) {
				$has_free_item = true;
				break;
			}

			// BABYPASA: Scenario 3 — the item ships free only when the destination
			// district is one of the districts the merchant selected on the product.
			// Same variation→parent fallback as the flag above. Free-everywhere (handled
			// just above) always wins, so a product with both set is free everywhere.
			if ( $areas_enabled && '' !== $dest_district ) {
				$areas = $this->get_product_free_areas( $product_id );
				if ( empty( $areas ) ) {
					$areas = $this->get_product_free_areas( $item['product_id'] );
				}
				if ( $this->district_in_list( $dest_district, $areas ) ) {
					$has_free_item = true;
					break;
				}
			}
		}

		if ( ! $has_free_item ) {
			return $rates; // No qualifying free-delivery item in this package — charge normally.
		}

		// At least one item qualifies — zero out the Upaya Cargo rate.
		foreach ( $rates as $rate_id => $rate ) {
			if ( false !== strpos( $rate_id, 'upaya_cargo' ) ) {
				$rate->cost  = 0;
				$rate->label = __( 'Free Delivery', 'babypasa-delivery-overrides' );
				// Zero any per-item taxes that WC may have added for shipping.
				$rate->taxes = [];
				$rates[ $rate_id ] = $rate;
			}
		}

		return $rates;
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
	 * BABYPASA: Whether area-based (district) free delivery is active. Off-switch for
	 * rollback — define BP_FREE_DELIVERY_AREAS_DISABLED truthy, or return false from the
	 * 'bp_free_delivery_areas_enabled' filter, to revert to free-everywhere-only behaviour.
	 */
	private function areas_feature_enabled(): bool {
		if ( defined( 'BP_FREE_DELIVERY_AREAS_DISABLED' ) && BP_FREE_DELIVERY_AREAS_DISABLED ) {
			return false;
		}
		return (bool) apply_filters( 'bp_free_delivery_areas_enabled', true );
	}

	/**
	 * BABYPASA: The districts selected on a product (_bp_free_delivery_areas), as a
	 * clean array of string tokens. Returns [] when unset/empty.
	 *
	 * @param int $product_id Product (or parent) ID.
	 * @return string[]
	 */
	private function get_product_free_areas( int $product_id ): array {
		if ( $product_id <= 0 ) {
			return [];
		}
		$areas = get_post_meta( $product_id, '_bp_free_delivery_areas', true );
		if ( ! is_array( $areas ) ) {
			return [];
		}
		$clean = [];
		foreach ( $areas as $token ) {
			$token = trim( (string) $token );
			if ( '' !== $token ) {
				$clean[] = $token;
			}
		}
		return $clean;
	}

	/**
	 * BABYPASA: Extracts the district token from an Upaya area name. Upaya names follow
	 * "<City>-<Sub-area>-<District>", so the district is the last "-" segment
	 * (e.g. "Kathmandu-Naya Baneshwor-Kathmandu" → "Kathmandu"). Names without a hyphen
	 * are returned trimmed as-is.
	 *
	 * AREA-LEVEL (future): to match at area granularity instead of district, return the
	 * full $area_name here (and feed full area names into get_selectable_districts()).
	 * Everything else is token-based and needs no change. The 'bp_free_delivery_district_from_area'
	 * filter can also remap individual values without code edits.
	 *
	 * @param string $area_name Full Upaya area name.
	 * @return string District token (may be empty).
	 */
	private function district_from_area( string $area_name ): string {
		$area_name = trim( $area_name );
		if ( '' === $area_name ) {
			return '';
		}
		$parts    = explode( '-', $area_name );
		$district = trim( (string) end( $parts ) );

		/**
		 * Filter the district token parsed from an Upaya area name.
		 *
		 * @param string $district  Parsed district token.
		 * @param string $area_name Original area name.
		 */
		return (string) apply_filters( 'bp_free_delivery_district_from_area', $district, $area_name );
	}

	/**
	 * BABYPASA: District of the shipping package destination, read from
	 * $package['destination']['city'] (= billing_city, the Upaya area name).
	 *
	 * @param array $package WooCommerce shipping package.
	 * @return string
	 */
	private function get_destination_district( array $package ): string {
		$city = $package['destination']['city'] ?? '';
		return $this->district_from_area( (string) $city );
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
	 * BABYPASA: Case-insensitive membership test of a destination district against a
	 * product's selected district tokens.
	 *
	 * @param string   $district Destination district.
	 * @param string[] $areas    Product's selected district tokens.
	 * @return bool
	 */
	private function district_in_list( string $district, array $areas ): bool {
		if ( '' === $district || empty( $areas ) ) {
			return false;
		}
		foreach ( $areas as $token ) {
			if ( 0 === strcasecmp( trim( (string) $token ), $district ) ) {
				return true;
			}
		}
		return false;
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

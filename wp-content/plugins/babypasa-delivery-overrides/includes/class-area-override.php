<?php
/**
 * Feature 2: Area-based delivery charge override.
 *
 * Admins define rules in WooCommerce → Settings → Delivery Overrides.
 * Each rule matches an area name (exact or substring) against the destination
 * city that Upaya's checkout JS writes into $package['destination']['city']
 * (= billing_city) and replaces the Upaya shipping cost + label.
 *
 * The filter runs at priority 20, after the free-delivery product override
 * (priority 10), so product-level free-delivery always wins.
 *
 * @package BabyPasa_Delivery_Overrides
 */

defined( 'ABSPATH' ) || exit;

class BP_Area_Override {

	/** WP option key that stores the array of override rules. */
	const OPTION_KEY = 'bp_area_delivery_overrides';

	/**
	 * WP option key for the single default charge applied to areas that match no
	 * area-based rule. Stored as a string: '' = unset (fall through to Upaya),
	 * '0' = free delivery, any other numeric string = that flat charge.
	 */
	const DEFAULT_OPTION_KEY = 'babypasa_delivery_default_override';

	/** WooCommerce settings tab slug. */
	const TAB_SLUG = 'bp_delivery_overrides';

	public function __construct() {
		// WooCommerce settings tab.
		add_filter( 'woocommerce_settings_tabs_array',                  [ $this, 'add_settings_tab'   ], 50 );
		add_action( 'woocommerce_settings_tabs_' . self::TAB_SLUG,      [ $this, 'render_settings'    ] );
		// woocommerce_update_options_{tab} is the hook WC fires after verifying its own nonce.
		add_action( 'woocommerce_update_options_' . self::TAB_SLUG,    [ $this, 'save_settings'      ] );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// Rate override — priority 20, after free-delivery product override (priority 10).
		add_filter( 'woocommerce_package_rates', [ $this, 'apply_area_override' ], 20, 2 );
	}

	/* ------------------------------------------------------------------
	 * Settings tab
	 * ------------------------------------------------------------------ */

	public function add_settings_tab( array $tabs ): array {
		$tabs[ self::TAB_SLUG ] = __( 'Delivery Overrides', 'babypasa-delivery-overrides' );
		return $tabs;
	}

	public function render_settings(): void {
		$rules = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $rules ) ) {
			$rules = [];
		}
		?>
		<h2><?php esc_html_e( 'Area-Based Delivery Charge Overrides', 'babypasa-delivery-overrides' ); ?></h2>
		<p><?php esc_html_e( 'Define rules to override the Upaya shipping cost for specific delivery areas. Rules are matched against the area name the customer selects at checkout (billing_city). The first matching enabled rule is applied.', 'babypasa-delivery-overrides' ); ?></p>

		<table class="widefat bp-area-overrides-table" id="bp-area-overrides-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Enabled', 'babypasa-delivery-overrides' ); ?></th>
					<th><?php esc_html_e( 'Area Name / Keyword', 'babypasa-delivery-overrides' ); ?></th>
					<th><?php esc_html_e( 'Match Type', 'babypasa-delivery-overrides' ); ?></th>
					<th><?php esc_html_e( 'Override Price (Rs.)', 'babypasa-delivery-overrides' ); ?></th>
					<th><?php esc_html_e( 'Label Shown at Checkout', 'babypasa-delivery-overrides' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="bp-area-overrides-rows">
				<?php if ( empty( $rules ) ) : ?>
					<tr class="bp-no-rules-row">
						<td colspan="6"><?php esc_html_e( 'No rules yet. Click "Add Rule" to create one.', 'babypasa-delivery-overrides' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rules as $i => $rule ) : ?>
						<tr>
							<?php $this->render_rule_row( $i, $rule ); ?>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="6">
						<button type="button" id="bp-add-override-rule" class="button">
							<?php esc_html_e( '+ Add Rule', 'babypasa-delivery-overrides' ); ?>
						</button>
					</td>
				</tr>
			</tfoot>
		</table>

		<!-- Hidden template row cloned by JS when adding new rules -->
		<table style="display:none">
			<tbody>
				<tr id="bp-rule-row-template">
					<?php $this->render_rule_row( '__INDEX__', [] ); ?>
				</tr>
			</tbody>
		</table>

		<h2 style="margin-top:2em;"><?php esc_html_e( 'Default Delivery Charge Override', 'babypasa-delivery-overrides' ); ?></h2>
		<p><?php esc_html_e( 'Applies to every delivery area that does NOT match an Area-Based rule above. Leave blank to use the Upaya-calculated charge. Enter 0 for free delivery.', 'babypasa-delivery-overrides' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="babypasa_delivery_default_override"><?php esc_html_e( 'Default charge (NPR)', 'babypasa-delivery-overrides' ); ?></label>
				</th>
				<td>
					<input type="number"
						name="babypasa_delivery_default_override"
						id="babypasa_delivery_default_override"
						value="<?php echo esc_attr( get_option( self::DEFAULT_OPTION_KEY, '' ) ); ?>"
						min="0"
						step="0.01"
						class="regular-text" />
				</td>
			</tr>
		</table>

		<?php
	}

	/**
	 * Outputs a single rule row for the settings table.
	 *
	 * @param int|string $index Row index (or '__INDEX__' for the JS template row).
	 * @param array      $rule  Saved rule data.
	 */
	private function render_rule_row( $index, array $rule ): void {
		$enabled        = ! empty( $rule['enabled'] ) ? '1' : '';
		$area_name      = isset( $rule['area_name'] )     ? esc_attr( $rule['area_name'] )     : '';
		$match_type     = isset( $rule['match_type'] )    ? esc_attr( $rule['match_type'] )    : 'contains';
		$override_price = isset( $rule['override_price'] ) ? esc_attr( $rule['override_price'] ) : '0';
		$label          = isset( $rule['label'] )          ? esc_attr( $rule['label'] )          : '';
		$field_name     = "bp_area_override_rules[{$index}]";
		?>
		<td>
			<input type="checkbox"
				name="<?php echo esc_attr( $field_name ); ?>[enabled]"
				value="1"
				<?php checked( $enabled, '1' ); ?> />
		</td>
		<td>
			<input type="text"
				name="<?php echo esc_attr( $field_name ); ?>[area_name]"
				value="<?php echo $area_name; ?>"
				placeholder="<?php esc_attr_e( 'e.g. Kathmandu', 'babypasa-delivery-overrides' ); ?>"
				class="regular-text" />
		</td>
		<td>
			<select name="<?php echo esc_attr( $field_name ); ?>[match_type]">
				<option value="contains" <?php selected( $match_type, 'contains' ); ?>>
					<?php esc_html_e( 'Contains', 'babypasa-delivery-overrides' ); ?>
				</option>
				<option value="exact" <?php selected( $match_type, 'exact' ); ?>>
					<?php esc_html_e( 'Exact', 'babypasa-delivery-overrides' ); ?>
				</option>
			</select>
		</td>
		<td>
			<input type="number"
				name="<?php echo esc_attr( $field_name ); ?>[override_price]"
				value="<?php echo $override_price; ?>"
				min="0"
				step="1"
				class="small-text" />
		</td>
		<td>
			<input type="text"
				name="<?php echo esc_attr( $field_name ); ?>[label]"
				value="<?php echo $label; ?>"
				placeholder="<?php esc_attr_e( 'e.g. Free delivery inside Kathmandu Valley', 'babypasa-delivery-overrides' ); ?>"
				class="regular-text" />
		</td>
		<td>
			<button type="button" class="button bp-remove-rule">
				<?php esc_html_e( 'Remove', 'babypasa-delivery-overrides' ); ?>
			</button>
		</td>
		<?php
	}

	public function save_settings(): void {
		// WooCommerce verifies its own nonce (woocommerce-settings) before firing
		// woocommerce_update_options_{tab}, so no additional nonce check is needed here.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_rules = isset( $_POST['bp_area_override_rules'] ) ? (array) $_POST['bp_area_override_rules'] : [];
		$clean     = [];

		foreach ( $raw_rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$area_name = sanitize_text_field( $rule['area_name'] ?? '' );
			if ( '' === $area_name ) {
				continue; // Skip rows with no area name.
			}
			$match_type = in_array( $rule['match_type'] ?? '', [ 'exact', 'contains' ], true )
				? $rule['match_type']
				: 'contains';

			$clean[] = [
				'enabled'        => ! empty( $rule['enabled'] ) ? '1' : '',
				'area_name'      => $area_name,
				'match_type'     => $match_type,
				'override_price' => (string) absint( $rule['override_price'] ?? 0 ),
				'label'          => sanitize_text_field( $rule['label'] ?? '' ),
			];
		}

		update_option( self::OPTION_KEY, $clean );

		// Default override — sanitise as a non-negative float; '' keeps the field
		// unset so non-matched areas fall through to the Upaya charge. '0' is a
		// valid value (free delivery) and is preserved, not treated as empty.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_default = isset( $_POST['babypasa_delivery_default_override'] )
			? trim( (string) wp_unslash( $_POST['babypasa_delivery_default_override'] ) )
			: '';
		update_option(
			self::DEFAULT_OPTION_KEY,
			'' === $raw_default ? '' : (string) max( 0, (float) $raw_default )
		);
	}

	/* ------------------------------------------------------------------
	 * Shipping rate override
	 * ------------------------------------------------------------------ */

	/**
	 * Applies the first matching enabled area rule to the Upaya Cargo rate.
	 *
	 * Area is read from $package['destination']['city'], which holds the
	 * billing_city value written by Upaya's checkout JS when the customer
	 * selects a Hub+Area in the combined dropdown. This is the same value
	 * UPAYA_Shipping_Method uses to resolve the location_id for /order-rates.
	 *
	 * @param  WC_Shipping_Rate[] $rates   Rates keyed by rate ID.
	 * @param  array              $package WooCommerce shipping package.
	 * @return WC_Shipping_Rate[]
	 */
	public function apply_area_override( array $rates, array $package ): array {
		// billing_city = area name (e.g. "Kathmandu-Naya Baneshwor-Kathmandu").
		$city  = $package['destination']['city'] ?? '';
		$rules = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $rules ) ) {
			$rules = [];
		}

		// 1) Area-based rules — first enabled match wins (only when we have a city).
		if ( '' !== $city ) {
			foreach ( $rules as $rule ) {
				if ( empty( $rule['enabled'] ) ) {
					continue;
				}

				$area_name = $rule['area_name'] ?? '';
				if ( '' === $area_name ) {
					continue;
				}

				$matched = false;
				if ( 'exact' === $rule['match_type'] ) {
					$matched = 0 === strcasecmp( $city, $area_name );
				} else {
					// 'contains' — case-insensitive substring match.
					$matched = false !== stripos( $city, $area_name );
				}

				if ( ! $matched ) {
					continue;
				}

				// Apply this rule to every Upaya Cargo rate in the package.
				foreach ( $rates as $rate_id => $rate ) {
					if ( false === strpos( $rate_id, 'upaya_cargo' ) ) {
						continue;
					}
					$rate->cost  = (float) ( $rule['override_price'] ?? 0 );
					$rate->taxes = []; // Clear any shipping tax calculated on the old cost.
					if ( ! empty( $rule['label'] ) ) {
						$rate->label = $rule['label'];
					}
					$rates[ $rate_id ] = $rate;
				}

				// First matching rule wins; the default override does not apply.
				return $rates;
			}
		}

		// 2) No area rule matched — apply the Default Delivery Charge Override when
		// one is set. '' = unset (fall through to the Upaya charge); '0' is a valid
		// free-delivery value and must be honoured, hence the explicit '' check.
		$default = get_option( self::DEFAULT_OPTION_KEY, '' );
		if ( '' !== $default && is_numeric( $default ) ) {
			foreach ( $rates as $rate_id => $rate ) {
				if ( false === strpos( $rate_id, 'upaya_cargo' ) ) {
					continue;
				}
				$rate->cost  = (float) $default;
				$rate->taxes = []; // Clear any shipping tax calculated on the old cost.
				$rates[ $rate_id ] = $rate;
			}
		}

		return $rates;
	}

	/* ------------------------------------------------------------------
	 * Admin assets
	 * ------------------------------------------------------------------ */

	public function enqueue_admin_scripts( string $hook ): void {
		// Only load on the WooCommerce settings page showing our tab.
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ( $_GET['tab'] ?? '' ) !== self::TAB_SLUG ) {
			return;
		}

		wp_enqueue_script(
			'bp-area-overrides-admin',
			BP_DELIVERY_OVERRIDES_URL . 'assets/js/area-overrides-admin.js',
			[ 'jquery' ],
			filemtime( BP_DELIVERY_OVERRIDES_DIR . 'assets/js/area-overrides-admin.js' ),
			true
		);
	}
}

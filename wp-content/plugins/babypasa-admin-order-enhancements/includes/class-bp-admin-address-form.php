<?php
/**
 * Replaces the default WC admin billing/shipping address UI with a unified
 * delivery area form. Injects inline into the billing column via
 * woocommerce_admin_order_data_after_billing_address, and hides the default
 * pencil-edit UI via JS.
 *
 * @package BabyPasa_Admin_Order_Enhancements
 */

defined( 'ABSPATH' ) || exit;

class BP_Admin_Address_Form {

	public function __construct() {
		add_action( 'woocommerce_admin_order_data_after_billing_address',  [ $this, 'render_address_form' ], 5 );
		add_action( 'woocommerce_process_shop_order_meta',                 [ $this, 'save_address_form'   ], 10, 2 );
		add_action( 'admin_enqueue_scripts',                               [ $this, 'enqueue_assets'      ] );

		// Enrich the admin "select customer" AJAX response with the combined
		// delivery-area value + alt phone + landmark, so the custom delivery form
		// can pre-fill on customer selection (the JS reads these keys).
		add_filter( 'woocommerce_ajax_get_customer_details', [ $this, 'add_delivery_fields_to_customer_details' ], 10, 3 );
	}

	/* ------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------ */

	public function enqueue_assets( string $hook ): void {
		if ( ! $this->is_order_screen( $hook ) ) {
			return;
		}
		if ( ! $this->is_new_order_screen() ) {
			return;
		}

		wp_enqueue_style(
			'bp-aoe',
			BP_AOE_URL . 'assets/css/bp-admin-order.css',
			[],
			filemtime( BP_AOE_DIR . 'assets/css/bp-admin-order.css' )
		);

		wp_enqueue_script(
			'bp-aoe',
			BP_AOE_URL . 'assets/js/bp-admin-order.js',
			[ 'jquery' ],
			filemtime( BP_AOE_DIR . 'assets/js/bp-admin-order.js' ),
			true
		);

		wp_localize_script( 'bp-aoe', 'bpAoe', [
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'calc_nonce' => wp_create_nonce( 'bp_aoe_calc_shipping' ),
			'apply_nonce'=> wp_create_nonce( 'bp_aoe_apply_shipping' ),
			'i18n'       => [
				'calculating'   => __( 'Calculating…', 'babypasa-aoe' ),
				'unavailable'   => __( 'Rate unavailable', 'babypasa-aoe' ),
				'apply_label'   => __( 'Applying…', 'babypasa-aoe' ),
				'applied'       => __( 'Applied', 'babypasa-aoe' ),
			],
		] );
	}

	/* ------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------ */

	public function render_address_form( $post_or_order ): void {
		if ( ! $this->is_new_order_screen() ) {
			return;
		}

		$order = $this->get_order( $post_or_order );
		if ( ! $order ) {
			return;
		}

		$order_id = $order->get_id();
		$options  = $this->get_hub_area_options();

		// Reconstruct the combined hub||area value from saved billing state + city.
		$hub  = $order->get_billing_state();
		$city = $order->get_billing_city();
		$selected_hub_area = ( $hub && $city ) ? ( $hub . '||' . $city ) : '';

		$shipping_different = (bool) $order->get_meta( '_bp_ship_different' );

		$s_hub  = $order->get_shipping_state();
		$s_city = $order->get_shipping_city();
		$selected_ship_hub_area = ( $s_hub && $s_city ) ? ( $s_hub . '||' . $s_city ) : '';

		wp_nonce_field( 'bp_aoe_address_' . $order_id, '_bp_aoe_address_nonce' );
		?>
		<div id="bp-aoe-address-wrap">

			<h4 style="margin-bottom:8px;"><?php esc_html_e( 'Delivery Address', 'babypasa-aoe' ); ?></h4>

			<div class="bp-aoe-field-row">
				<label><?php esc_html_e( 'Delivery Area', 'babypasa-aoe' ); ?> <span class="required">*</span></label>
				<select id="bp_billing_hub_area" name="bp_address[hub_area]" class="wc-enhanced-select bp-hub-area-select">
					<option value=""><?php esc_html_e( '— Select Delivery Area —', 'babypasa-aoe' ); ?></option>
					<?php foreach ( $options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"
							<?php selected( $selected_hub_area, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( empty( $options ) ) : ?>
					<p class="description" style="color:#c00;">
						<?php esc_html_e( 'Location data unavailable. Flush the Upaya location cache in WooCommerce → Settings → Shipping → Upaya Cargo.', 'babypasa-aoe' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div id="bp-aoe-shipping-rate-row" style="display:none;">
				<span id="bp-aoe-rate-label"></span>
			</div>

			<div class="bp-aoe-field-row bp-aoe-two-col">
				<div>
					<label><?php esc_html_e( 'First Name', 'babypasa-aoe' ); ?></label>
					<input type="text" name="bp_address[first_name]"
						value="<?php echo esc_attr( $order->get_billing_first_name() ); ?>" />
				</div>
				<div>
					<label><?php esc_html_e( 'Last Name', 'babypasa-aoe' ); ?></label>
					<input type="text" name="bp_address[last_name]"
						value="<?php echo esc_attr( $order->get_billing_last_name() ); ?>" />
				</div>
			</div>

			<div class="bp-aoe-field-row bp-aoe-two-col">
				<div>
					<label><?php esc_html_e( 'Phone', 'babypasa-aoe' ); ?></label>
					<input type="tel" name="bp_address[phone]"
						value="<?php echo esc_attr( $order->get_billing_phone() ); ?>"
						maxlength="10" />
				</div>
				<div>
					<label><?php esc_html_e( 'Alternate Phone', 'babypasa-aoe' ); ?></label>
					<input type="tel" name="bp_address[alt_phone]"
						value="<?php echo esc_attr( (string) $order->get_meta( '_billing_alternate_phone' ) ); ?>"
						maxlength="10" />
				</div>
			</div>

			<div class="bp-aoe-field-row">
				<label><?php esc_html_e( 'Address Line 1', 'babypasa-aoe' ); ?></label>
				<input type="text" name="bp_address[address_1]"
					value="<?php echo esc_attr( $order->get_billing_address_1() ); ?>" />
			</div>

			<div class="bp-aoe-field-row">
				<label><?php esc_html_e( 'Address Line 2', 'babypasa-aoe' ); ?></label>
				<input type="text" name="bp_address[address_2]"
					value="<?php echo esc_attr( $order->get_billing_address_2() ); ?>" />
			</div>

			<div class="bp-aoe-field-row">
				<label><?php esc_html_e( 'Nearest Landmark', 'babypasa-aoe' ); ?></label>
				<input type="text" name="bp_address[landmark]"
					value="<?php echo esc_attr( (string) $order->get_meta( '_upaya_landmark' ) ); ?>" />
			</div>

			<div class="bp-aoe-field-row">
				<label><?php esc_html_e( 'Email', 'babypasa-aoe' ); ?></label>
				<input type="email" name="bp_address[email]"
					value="<?php echo esc_attr( $order->get_billing_email() ); ?>" />
			</div>

			<div class="bp-aoe-field-row bp-aoe-toggle-row">
				<label>
					<input type="checkbox" id="bp_ship_different" name="bp_address[ship_different]"
						value="1" <?php checked( $shipping_different ); ?> />
					<?php esc_html_e( 'Ship to a different address?', 'babypasa-aoe' ); ?>
				</label>
			</div>

			<div id="bp-aoe-shipping-address" <?php echo $shipping_different ? '' : 'style="display:none;"'; ?>>
				<h4 style="margin-top:12px;margin-bottom:8px;"><?php esc_html_e( 'Shipping Address', 'babypasa-aoe' ); ?></h4>

				<div class="bp-aoe-field-row">
					<label><?php esc_html_e( 'Delivery Area', 'babypasa-aoe' ); ?></label>
					<select name="bp_shipping_address[hub_area]" class="wc-enhanced-select bp-hub-area-select">
						<option value=""><?php esc_html_e( '— Select Delivery Area —', 'babypasa-aoe' ); ?></option>
						<?php foreach ( $options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"
								<?php selected( $selected_ship_hub_area, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="bp-aoe-field-row bp-aoe-two-col">
					<div>
						<label><?php esc_html_e( 'First Name', 'babypasa-aoe' ); ?></label>
						<input type="text" name="bp_shipping_address[first_name]"
							value="<?php echo esc_attr( $order->get_shipping_first_name() ); ?>" />
					</div>
					<div>
						<label><?php esc_html_e( 'Last Name', 'babypasa-aoe' ); ?></label>
						<input type="text" name="bp_shipping_address[last_name]"
							value="<?php echo esc_attr( $order->get_shipping_last_name() ); ?>" />
					</div>
				</div>

				<div class="bp-aoe-field-row">
					<label><?php esc_html_e( 'Address Line 1', 'babypasa-aoe' ); ?></label>
					<input type="text" name="bp_shipping_address[address_1]"
						value="<?php echo esc_attr( $order->get_shipping_address_1() ); ?>" />
				</div>

				<div class="bp-aoe-field-row">
					<label><?php esc_html_e( 'Address Line 2', 'babypasa-aoe' ); ?></label>
					<input type="text" name="bp_shipping_address[address_2]"
						value="<?php echo esc_attr( $order->get_shipping_address_2() ); ?>" />
				</div>
			</div>

		</div><!-- #bp-aoe-address-wrap -->
		<?php
	}

	/* ------------------------------------------------------------------
	 * Save
	 * ------------------------------------------------------------------ */

	public function save_address_form( int $order_id, $post_or_order ): void {
		if ( ! isset( $_POST['_bp_aoe_address_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_bp_aoe_address_nonce'] ) ), 'bp_aoe_address_' . $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$addr = isset( $_POST['bp_address'] ) ? (array) $_POST['bp_address'] : [];

		$hub_area  = sanitize_text_field( $addr['hub_area'] ?? '' );
		$parts     = $hub_area ? explode( '||', $hub_area, 2 ) : [];
		$hub       = sanitize_text_field( $parts[0] ?? '' );
		$area      = sanitize_text_field( $parts[1] ?? '' );

		$first = sanitize_text_field( $addr['first_name'] ?? '' );
		$last  = sanitize_text_field( $addr['last_name']  ?? '' );
		$phone = sanitize_text_field( $addr['phone']      ?? '' );
		$email = sanitize_email(      $addr['email']      ?? '' );
		$addr1 = sanitize_text_field( $addr['address_1']  ?? '' );
		$addr2 = sanitize_text_field( $addr['address_2']  ?? '' );

		$order->set_billing_first_name( $first );
		$order->set_billing_last_name(  $last );
		$order->set_billing_phone(      $phone );
		$order->set_billing_email(      $email );
		$order->set_billing_address_1(  $addr1 );
		$order->set_billing_address_2(  $addr2 );
		$order->set_billing_state(  $hub );
		$order->set_billing_city(   $area );
		$order->set_billing_country( 'NP' );

		// Mirror into the standard WC billing $_POST fields so WooCommerce's own
		// order-data save (woocommerce_process_shop_order_meta @ 40, which runs
		// AFTER this @ 10) re-applies these values instead of overwriting them
		// with the hidden, empty default billing inputs. Without this the custom
		// Delivery Area / phone / address are silently clobbered on save.
		$_POST['_billing_first_name'] = $first;
		$_POST['_billing_last_name']  = $last;
		$_POST['_billing_phone']      = $phone;
		$_POST['_billing_email']      = $email;
		$_POST['_billing_address_1']  = $addr1;
		$_POST['_billing_address_2']  = $addr2;
		$_POST['_billing_city']       = $area;
		$_POST['_billing_state']      = $hub;
		$_POST['_billing_country']    = 'NP';

		$order->update_meta_data( '_billing_alternate_phone', sanitize_text_field( $addr['alt_phone'] ?? '' ) );
		$order->update_meta_data( '_upaya_landmark',          sanitize_text_field( $addr['landmark']  ?? '' ) );

		$ship_different = ! empty( $addr['ship_different'] );
		$order->update_meta_data( '_bp_ship_different', $ship_different ? '1' : '' );

		if ( $ship_different ) {
			$saddr = isset( $_POST['bp_shipping_address'] ) ? (array) $_POST['bp_shipping_address'] : [];

			$s_hub_area = sanitize_text_field( $saddr['hub_area'] ?? '' );
			$s_parts    = $s_hub_area ? explode( '||', $s_hub_area, 2 ) : [];

			$s_first = sanitize_text_field( $saddr['first_name'] ?? '' );
			$s_last  = sanitize_text_field( $saddr['last_name']  ?? '' );
			$s_addr1 = sanitize_text_field( $saddr['address_1']  ?? '' );
			$s_addr2 = sanitize_text_field( $saddr['address_2']  ?? '' );
			$s_hub   = sanitize_text_field( $s_parts[0] ?? '' );
			$s_city  = sanitize_text_field( $s_parts[1] ?? '' );
		} else {
			// Mirror billing → shipping.
			$s_first = $first;
			$s_last  = $last;
			$s_addr1 = $addr1;
			$s_addr2 = $addr2;
			$s_hub   = $hub;
			$s_city  = $area;
		}

		$order->set_shipping_first_name( $s_first );
		$order->set_shipping_last_name(  $s_last );
		$order->set_shipping_address_1(  $s_addr1 );
		$order->set_shipping_address_2(  $s_addr2 );
		$order->set_shipping_state(   $s_hub );
		$order->set_shipping_city(    $s_city );
		$order->set_shipping_country( 'NP' );

		// Same reasoning as billing: feed the standard shipping $_POST fields so
		// WC core's @40 save does not blank them out from the hidden defaults.
		$_POST['_shipping_first_name'] = $s_first;
		$_POST['_shipping_last_name']  = $s_last;
		$_POST['_shipping_address_1']  = $s_addr1;
		$_POST['_shipping_address_2']  = $s_addr2;
		$_POST['_shipping_city']       = $s_city;
		$_POST['_shipping_state']      = $s_hub;
		$_POST['_shipping_country']    = 'NP';
		// phpcs:enable

		$order->save();
	}

	/* ------------------------------------------------------------------
	 * Customer-select pre-fill
	 * ------------------------------------------------------------------ */

	/**
	 * Appends the custom delivery fields to the WC "get customer details" AJAX
	 * response so selecting a customer on the new-order screen can pre-fill the
	 * unified delivery form. Standard billing fields (phone, address, city,
	 * state, email) are already present in $data['billing']; here we add the
	 * combined hub||area value and the bespoke meta the default response omits.
	 *
	 * @param  array        $data     Customer details ('billing', 'shipping', …).
	 * @param  \WC_Customer $customer The selected customer.
	 * @param  int          $user_id  Customer user ID.
	 * @return array
	 */
	public function add_delivery_fields_to_customer_details( array $data, $customer, int $user_id ): array {
		if ( ! $customer instanceof \WC_Customer ) {
			return $data;
		}

		$hub  = $customer->get_billing_state();
		$city = $customer->get_billing_city();

		$data['bp_aoe'] = [
			'hub_area'  => ( $hub && $city ) ? $hub . '||' . $city : '',
			'alt_phone' => (string) $customer->get_meta( 'billing_alternate_phone' ),
			'landmark'  => (string) $customer->get_meta( 'billing_landmark' ),
		];

		return $data;
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Builds hub+area options from the Upaya location transient.
	 * Same approach as babypasa-address-book — no dependency on Upaya classes.
	 *
	 * @return array<string,string>
	 */
	private function get_hub_area_options(): array {
		$cities = get_transient( 'upaya_raw_cities_cache' );
		if ( ! is_array( $cities ) ) {
			return [];
		}

		$hubs = [];
		foreach ( $cities as $city ) {
			$hub = $city['hubName'] ?? '';
			if ( $hub === '' ) {
				continue;
			}
			foreach ( $city['areas'] ?? [] as $area ) {
				if ( ! ( $area['isActive'] ?? true ) ) {
					continue;
				}
				$name = $area['name'] ?? '';
				if ( $name !== '' ) {
					$hubs[ $hub ][] = $name;
				}
			}
		}
		ksort( $hubs );

		$options = [];
		foreach ( $hubs as $hub => $areas ) {
			sort( $areas );
			foreach ( $areas as $area ) {
				$options[ $hub . '||' . $area ] = $hub . ' › ' . $area;
			}
		}

		return $options;
	}

	private function get_order( $post_or_order ): ?\WC_Order {
		if ( $post_or_order instanceof \WC_Order ) {
			return $post_or_order;
		}
		if ( $post_or_order instanceof \WP_Post ) {
			return wc_get_order( $post_or_order->ID ) ?: null;
		}
		return null;
	}

	/**
	 * Returns true only on the new-order creation screen, not on existing order
	 * edit screens. Handles both legacy (post-new.php) and HPOS (wc-orders?action=new).
	 */
	private function is_new_order_screen(): bool {
		global $pagenow;

		// Legacy: post-new.php?post_type=shop_order
		if ( 'post-new.php' === $pagenow ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return ( $_GET['post_type'] ?? '' ) === 'shop_order';
		}

		// HPOS: admin.php?page=wc-orders&action=new
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ( $_GET['page'] ?? '' ) === 'wc-orders' && ( $_GET['action'] ?? '' ) === 'new' ) {
			return true;
		}
		// phpcs:enable

		return false;
	}

	private function is_order_screen( string $hook ): bool {
		if ( in_array( $hook, [ 'woocommerce_page_wc-orders' ], true ) ) {
			return true;
		}
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return false;
		}
		global $post;
		return ! isset( $post ) || 'shop_order' === $post->post_type;
	}
}

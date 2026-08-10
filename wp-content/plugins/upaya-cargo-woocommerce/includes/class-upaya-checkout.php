<?php
/**
 * Checkout customisation — Nepal-only country, zone/area dropdowns, and
 * Upaya-specific fields (landmark, alternate phone).
 *
 * Also owns the migrated WooCommerce checkout hooks from the child theme's
 * functions.php (phone requirements, billing alternate phone, shipping
 * section removal).
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * UPAYA_Checkout wires every checkout-page customisation for the Upaya
 * Cargo plugin:
 *
 *  1. Force Nepal as the only selectable country.
 *  2. Replace billing_state + billing_city with a single combined Hub+Area
 *     SelectWoo field, searched remotely via AJAX (the full dataset — several
 *     thousand hub+area combinations — is never rendered into the page; see
 *     get_current_hub_area_options() and ajax_search_areas()).
 *  3. Make mobile number required; add "Alternate Mobile Number" field.
 *  4. Disable the "Ship to different address" section (use billing).
 *  5. Add Upaya-specific landmark field to billing.
 */
class UPAYA_Checkout {

	/** WP AJAX action (and nonce action) for the combined Hub+Area remote search. */
	const AJAX_NONCE = 'upaya_search_areas';

	/**
	 * WP AJAX action (and nonce action) serving the full hub+area list once,
	 * for client-side local filtering (see upaya-checkout.js). AJAX_NONCE
	 * above stays live as the fallback while that local index is loading.
	 */
	const AJAX_INDEX_ACTION = 'upaya_get_area_index';

	/** @var UPAYA_Location_Cache */
	private UPAYA_Location_Cache $location_cache;

	/**
	 * Constructor — wires up all checkout hooks.
	 */
	public function __construct() {
		$logger               = new UPAYA_Logger();
		$api                  = new UPAYA_API( get_option( 'upaya_api_key', '' ), $logger );
		$this->location_cache = new UPAYA_Location_Cache( $api, $logger );

		// ── Country restriction ──────────────────────────────────────────
		add_filter( 'woocommerce_countries',          [ $this, 'restrict_to_nepal' ] );
		add_filter( 'woocommerce_default_country',    [ $this, 'default_nepal' ] );
		add_filter( 'woocommerce_allowed_countries',  [ $this, 'allowed_nepal' ] );

		// Clear NP's state list so WC renders billing_state as a plain text
		// input (not a select) and WC's address-i18n JS hides it entirely.
		// The hub name is written into the hidden billing_state input by JS
		// when the customer selects from the combined billing_hub_area field.
		add_filter( 'woocommerce_states', [ $this, 'clear_nepal_states' ] );

		// ── Phone validation ──────────────────────────────────────────────
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_phone_length' ] );

		// ── Checkout field modifications ─────────────────────────────────
		add_filter( 'woocommerce_checkout_fields',          [ $this, 'modify_checkout_fields' ] );
		add_filter( 'woocommerce_default_address_fields',   [ $this, 'override_default_address_fields' ] );
		// Falls back to the address book's default saved address for the hidden
		// billing/shipping state+city fields when WooCommerce's own native
		// customer profile has no hub/area on file yet (see get_current_hub_area_value()
		// and get_default_saved_address() for why that's the common case).
		add_filter( 'woocommerce_checkout_get_value',       [ $this, 'fallback_hidden_fields_to_default_address' ], 10, 2 );
		// Mobile Number (billing_phone) label/required/priority is set here, on the
		// filter WooCommerce documents as authoritative for address-field changes,
		// so it reliably sorts above the Alternate Mobile Number. See override_billing_phone_field().
		add_filter( 'woocommerce_billing_fields',           [ $this, 'override_billing_phone_field' ] );
		// Expose the Alternate Mobile Number on the billing fieldset so it is
		// editable in My Account → Addresses → Billing Address. Priority 20 runs
		// after override_billing_phone_field() so phone (80) is already in place.
		add_filter( 'woocommerce_billing_fields',           [ $this, 'add_alternate_phone_billing_field' ], 20 );

		// ── Shipping address — copy billing → shipping on order save ─────
		// The checkout has no separate shipping form (shipping section removed
		// from fields), but we persist billing data as shipping so that order
		// confirmation, admin view, and emails all show a shipping address.
		add_action( 'woocommerce_checkout_create_order', [ $this, 'copy_billing_to_shipping_on_save' ], 10, 2 );

		// ── Landmark in formatted address output ──────────────────────────
		add_filter( 'woocommerce_localisation_address_formats',       [ $this, 'add_landmark_to_address_format' ] );
		add_filter( 'woocommerce_formatted_address_replacements',     [ $this, 'replace_landmark_token' ], 10, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address',   [ $this, 'inject_landmark_into_address' ], 10, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address',    [ $this, 'inject_landmark_into_address' ], 10, 2 );

		// ── Save & display custom fields ─────────────────────────────────
		add_action( 'woocommerce_checkout_update_order_meta',              [ $this, 'save_checkout_fields' ] );
		add_action( 'woocommerce_admin_order_data_after_billing_address',  [ $this, 'display_fields_in_admin' ] );
		add_action( 'woocommerce_order_details_after_customer_address',    [ $this, 'display_fields_in_order_details' ], 10, 2 );
		add_filter( 'woocommerce_email_order_meta_fields',                 [ $this, 'add_fields_to_emails' ], 10, 3 );

		// ── Frontend assets ──────────────────────────────────────────────
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_checkout_assets' ] );

		// ── Combined Hub+Area remote search (guests + logged-in) ─────────
		add_action( 'wp_ajax_' . self::AJAX_NONCE,        [ $this, 'ajax_search_areas' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_NONCE, [ $this, 'ajax_search_areas' ] );

		// ── Full hub+area index, fetched once for client-side local filtering ──
		add_action( 'wp_ajax_' . self::AJAX_INDEX_ACTION,        [ $this, 'ajax_get_area_index' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_INDEX_ACTION, [ $this, 'ajax_get_area_index' ] );
	}

	/* ------------------------------------------------------------------
	 * Country restriction
	 * ------------------------------------------------------------------ */

	/**
	 * @param  array<string,string> $countries
	 * @return array<string,string>
	 */
	public function restrict_to_nepal( array $countries ): array {
		return [ 'NP' => $countries['NP'] ?? __( 'Nepal', 'upaya-cargo-woocommerce' ) ];
	}

	/** @return string */
	public function default_nepal(): string {
		return 'NP';
	}

	/** @return string[] */
	public function allowed_nepal(): array {
		return [ 'NP' ];
	}

	/**
	 * Returns an empty state list for Nepal so WooCommerce renders billing_state
	 * as a plain text input instead of a select, and wc-address-i18n JS hides
	 * the state field container automatically. The hub name is written into the
	 * hidden billing_state input by upaya-checkout.js from the combined
	 * billing_hub_area field.
	 *
	 * @param  array<string,array<string,string>> $states All WC country states.
	 * @return array<string,array<string,string>>
	 */
	public function clear_nepal_states( array $states ): array {
		$states['NP'] = [];
		return $states;
	}

	/* ------------------------------------------------------------------
	 * Checkout fields
	 * ------------------------------------------------------------------ */

	/**
	 * All modifications to WooCommerce checkout fields:
	 *  - Hub + Area merged into a single combined select (billing_hub_area).
	 *  - billing_state and billing_city become hidden fields, populated by JS
	 *    from the combined select so the rest of the system (rate calc, API
	 *    payload) continues to use them unchanged.
	 *  - Phone fields required & relabelled.
	 *  - Alternate Mobile Number placed directly after Mobile Number.
	 *  - Landmark field added (billing).
	 *  - Shipping section removed (billing address is used for delivery;
	 *    billing is copied to shipping on order save via copy_billing_to_shipping_on_save).
	 *
	 * @param  array<string,array> $fields
	 * @return array<string,array>
	 */
	public function modify_checkout_fields( array $fields ): array {

		// ── Combined Delivery Hub + Area single select ────────────────────
		// Replaces the separate billing_state (hub) and billing_city (area)
		// visible fields. Both are kept as hidden inputs for WC compatibility.
		$fields['billing']['billing_hub_area'] = [
			'type'     => 'select',
			'label'    => __( 'Delivery Area', 'upaya-cargo-woocommerce' ),
			'required' => true,
			// wc-enhanced-select makes WC's checkout JS treat this as a SelectWoo
			// field, ensuring search is re-applied after every DOM update.
			'class'    => [ 'form-row-wide', 'upaya-hub-area-select', 'wc-enhanced-select' ],
			// Only a placeholder (+ the current selection, if any) is rendered —
			// the full hub+area dataset is fetched on demand via ajax_search_areas().
			'options'  => $this->get_current_hub_area_options(),
			'priority' => 49,
			'default'  => $this->get_current_hub_area_value(),
		];

		// Hide the underlying WC state/city fields — JS populates them from
		// the combined select above, keeping rate calculation and API payload
		// logic completely unchanged.
		if ( isset( $fields['billing']['billing_state'] ) ) {
			$fields['billing']['billing_state']['type']     = 'hidden';
			$fields['billing']['billing_state']['label']    = '';
			$fields['billing']['billing_state']['class']    = [];
			$fields['billing']['billing_state']['required'] = false;
		}
		if ( isset( $fields['billing']['billing_city'] ) ) {
			$fields['billing']['billing_city']['type']     = 'hidden';
			$fields['billing']['billing_city']['label']    = '';
			$fields['billing']['billing_city']['class']    = [];
			$fields['billing']['billing_city']['required'] = false;
		}

		// ── Mobile Number (billing_phone) ────────────────────────────────
		// Label/required/priority/10-digit validation are applied earlier on the
		// woocommerce_billing_fields filter (override_billing_phone_field) — the
		// point WooCommerce documents as authoritative for address-field changes.
		// Setting them here on woocommerce_checkout_fields was unreliable (the
		// field was not always present at this stage), which left phone at WC's
		// default priority 100 — rendering it BELOW the Alternate Mobile Number.
		if ( isset( $fields['billing']['billing_email'] ) ) {
			$fields['billing']['billing_email']['priority'] = 85;
		}

		// ── Alternate Mobile Number — immediately after Mobile Number ─────
		// Priority 81 sits directly after billing_phone (80) and before
		// billing_email (85). WooCommerce re-sorts every checkout fieldset by
		// priority after the woocommerce_checkout_fields filter runs
		// (WC_Checkout::get_checkout_fields), so the DOM order follows these
		// numbers on ALL viewports. Both fields are full-width (form-row-wide),
		// meaning they stack in priority order on mobile — guaranteeing Mobile
		// Number always renders ABOVE Alternate Mobile Number, never beside or
		// below it.
		$fields['billing']['billing_alternate_phone'] = [
			'label'       => __( 'Alternate Mobile Number', 'upaya-cargo-woocommerce' ),
			'placeholder' => __( 'Enter alternate mobile number', 'upaya-cargo-woocommerce' ),
			'required'    => false,
			'type'        => 'tel',
			'class'       => [ 'form-row-wide' ],
			'priority'    => 81,
		];

		// ── Landmark (billing) — placed just after the address block ────
		// Priorities: address_1=60, address_2=65, landmark=66, postcode=70
		$fields['billing']['billing_landmark'] = [
			'type'        => 'text',
			'label'       => __( 'Nearest Landmark', 'upaya-cargo-woocommerce' ),
			'placeholder' => __( 'e.g. Near City Bank', 'upaya-cargo-woocommerce' ),
			'required'    => false,
			'class'       => [ 'form-row-wide' ],
			'priority'    => 66,
		];

		// ── Shipping section — same combined Hub+Area treatment as billing ─
		// Mirrors the billing changes so "Ship to a different address?" works.
		if ( isset( $fields['shipping'] ) ) {
			$fields['shipping']['shipping_hub_area'] = [
				'type'     => 'select',
				'label'    => __( 'Delivery Area', 'upaya-cargo-woocommerce' ),
				'required' => true,
				'class'    => [ 'form-row-wide', 'upaya-hub-area-select', 'wc-enhanced-select' ],
				'options'  => $this->get_current_hub_area_options( 'shipping' ),
				'priority' => 49,
				'default'  => $this->get_current_hub_area_value( 'shipping' ),
			];

			if ( isset( $fields['shipping']['shipping_state'] ) ) {
				$fields['shipping']['shipping_state']['type']     = 'hidden';
				$fields['shipping']['shipping_state']['label']    = '';
				$fields['shipping']['shipping_state']['class']    = [];
				$fields['shipping']['shipping_state']['required'] = false;
			}
			if ( isset( $fields['shipping']['shipping_city'] ) ) {
				$fields['shipping']['shipping_city']['type']     = 'hidden';
				$fields['shipping']['shipping_city']['label']    = '';
				$fields['shipping']['shipping_city']['class']    = [];
				$fields['shipping']['shipping_city']['required'] = false;
			}
		}

		return $fields;
	}

	/**
	 * Builds the minimal option list for the combined Hub+Area select: a
	 * placeholder, plus — when one is already selected (POST data, a
	 * logged-in customer's saved address, or a validation-failure re-render)
	 * — that single "Hub||Area" option, pre-selected.
	 *
	 * The full dataset (several thousand hub+area combinations) is never
	 * rendered into the page; SelectWoo fetches matches on demand from
	 * ajax_search_areas() as the customer types. The label is derived from
	 * the value itself (which already encodes both names), so this needs no
	 * cache lookup and works even if the location cache is cold.
	 *
	 * @param  string $prefix 'billing' or 'shipping'.
	 * @return array<string,string>  [ value => label ]
	 */
	private function get_current_hub_area_options( string $prefix = 'billing' ): array {
		$options = [ '' => __( '— Select Delivery Area —', 'upaya-cargo-woocommerce' ) ];

		$current = $this->get_current_hub_area_value( $prefix );
		if ( '' !== $current && false !== strpos( $current, '||' ) ) {
			[ $hub, $area ] = explode( '||', $current, 2 );
			$options[ $current ] = $hub . ' › ' . $area;
		}

		return $options;
	}

	/**
	 * AJAX: returns up to 20 "Hub › Area" matches for the combined Delivery
	 * Area SelectWoo field's remote search. Reads the existing, already-cached
	 * location tree — never calls the Upaya API directly from this request
	 * path (a cold cache self-heals via UPAYA_Location_Cache::get_raw_cities(),
	 * same as any other page that reads it).
	 *
	 * @return void
	 */
	public function ajax_search_areas(): void {
		check_ajax_referer( self::AJAX_NONCE, 'security' );

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( [] );
		}

		wp_send_json_success( $this->location_cache->search_hub_area_options( $term ) );
	}

	/**
	 * AJAX: returns the full hub+area list ([{id, text}, ...], several thousand
	 * entries) once so the checkout field can filter it locally afterwards —
	 * no server round trip per keystroke. Fetched eagerly in the background on
	 * page load (see upaya-checkout.js), cached client-side in sessionStorage
	 * keyed by the index version, so this normally runs once per browser
	 * session rather than once per checkout visit.
	 *
	 * @return void
	 */
	public function ajax_get_area_index(): void {
		check_ajax_referer( self::AJAX_INDEX_ACTION, 'security' );

		wp_send_json_success( $this->location_cache->get_all_hub_area_options() );
	}

	/**
	 * Returns the pre-selected combined value for a Hub+Area dropdown based on
	 * the current state and city for the given address type (billing|shipping)
	 * from POST data or the customer session. Called for both the billing and
	 * shipping combined selects so update_checkout re-renders preserve the value.
	 *
	 * @param  string $prefix  'billing' or 'shipping'.
	 * @return string  "HubName||AreaName" or empty string.
	 */
	private function get_current_hub_area_value( string $prefix = 'billing' ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$hub  = '';
		$area = '';

		$state_key = $prefix . '_state';
		$city_key  = $prefix . '_city';

		if ( ! empty( $_POST[ $state_key ] ) ) {
			$hub = sanitize_text_field( wp_unslash( $_POST[ $state_key ] ) );
		} elseif ( WC()->customer ) {
			$hub = 'billing' === $prefix
				? WC()->customer->get_billing_state()
				: WC()->customer->get_shipping_state();
		}

		if ( ! empty( $_POST[ $city_key ] ) ) {
			$area = sanitize_text_field( wp_unslash( $_POST[ $city_key ] ) );
		} elseif ( WC()->customer ) {
			$area = 'billing' === $prefix
				? WC()->customer->get_billing_city()
				: WC()->customer->get_shipping_city();
		}
		// phpcs:enable

		// Fall back to the address book's default saved address when the
		// customer's native WooCommerce profile has nothing on file yet — the
		// normal case for someone who saved an address via the address book but
		// has never actually completed an order (the only point at which
		// WooCommerce itself persists billing_state/billing_city to the account).
		if ( '' === $hub || '' === $area ) {
			$default = $this->get_default_saved_address();
			if ( null !== $default ) {
				$hub  = '' !== $hub  ? $hub  : $default['state'];
				$area = '' !== $area ? $area : $default['city'];
			}
		}

		return ( $hub !== '' && $area !== '' ) ? ( $hub . '||' . $area ) : '';
	}

	/**
	 * Looks up the logged-in customer's default babypasa-address-book entry, if
	 * any. Cross-plugin, soft dependency — guards against that plugin being
	 * inactive, mirroring the babypasa_resolve_delivery_charge() wrapper pattern
	 * used elsewhere in this codebase.
	 *
	 * @return array{state:string,city:string}|null
	 */
	private function get_default_saved_address(): ?array {
		if ( ! is_user_logged_in() || ! class_exists( 'BP_Address_Book' ) ) {
			return null;
		}

		$default = BP_Address_Book::get_default_address( get_current_user_id() );
		if ( ! $default || empty( $default['state'] ) || empty( $default['city'] ) ) {
			return null;
		}

		return [
			'state' => (string) $default['state'],
			'city'  => (string) $default['city'],
		];
	}

	/**
	 * Filters woocommerce_checkout_get_value so the hidden billing_state /
	 * billing_city (and shipping_ equivalents) fields also fall back to the
	 * address book's default saved address — without this, the visible
	 * combined Hub+Area select can show the correct pre-selected value (via
	 * get_current_hub_area_value()) while these hidden inputs it's meant to
	 * mirror stay empty, so submitting without touching the field would
	 * silently drop the delivery area.
	 *
	 * This filter fires BEFORE WooCommerce checks $_POST or the native customer
	 * object (see WC_Checkout::get_value()), so it must replicate that same
	 * "real data wins" precedence itself — delegating to
	 * get_current_hub_area_value(), which already checks $_POST and the native
	 * customer profile before this address-book fallback, achieves exactly that.
	 *
	 * @param  mixed  $value WooCommerce's value so far for this field (null here).
	 * @param  string $input Checkout field key, e.g. 'billing_state'.
	 * @return mixed
	 */
	public function fallback_hidden_fields_to_default_address( $value, string $input ) {
		$map = [
			'billing_state'  => [ 'billing',  'hub'  ],
			'billing_city'   => [ 'billing',  'area' ],
			'shipping_state' => [ 'shipping', 'hub'  ],
			'shipping_city'  => [ 'shipping', 'area' ],
		];

		if ( ! isset( $map[ $input ] ) ) {
			return $value;
		}

		[ $prefix, $part ] = $map[ $input ];
		$combined          = $this->get_current_hub_area_value( $prefix );

		if ( '' === $combined ) {
			return $value;
		}

		[ $hub, $area ] = explode( '||', $combined, 2 );
		return 'hub' === $part ? $hub : $area;
	}

	/**
	 * Sets field priorities. Hub (state) and Area (city) are now hidden and
	 * populated by JS from the combined billing_hub_area select; they are kept
	 * as plain text inputs at their original priorities so the rest of the WC
	 * pipeline (rate calculation, payload) still reads them normally.
	 *
	 * @param  array<string,array> $fields WooCommerce default address fields.
	 * @return array<string,array>
	 */
	public function override_default_address_fields( array $fields ): array {
		// Desired visible order: [billing_hub_area at 49] → Street → Apt → Postcode
		// state(50) and city(55) are hidden, so their priorities don't matter visually.
		$fields['state']['priority']     = 50;
		$fields['city']['priority']      = 55;
		$fields['address_1']['priority'] = 60;
		$fields['address_2']['priority'] = 65;
		$fields['postcode']['priority']  = 70;

		// Mirror the Mobile Number (phone) label/required/priority that
		// override_billing_phone_field() applies server-side into the locale data.
		// WooCommerce builds the country-locale JSON from these default fields
		// (WC_Countries::get_country_locale → woocommerce_get_country_locale_default),
		// and address-i18n.js re-applies each locale field's label/required/priority
		// on the client and then re-sorts the billing rows. Without this, phone's
		// locale priority stays at WC's default (100) and the JS snaps Mobile Number
		// back below the Alternate Mobile Number a moment after page load. Setting
		// `required` is mandatory: if the locale entry omits it, address-i18n.js
		// forcibly marks the field NOT required (see its `else` branch).
		if ( isset( $fields['phone'] ) ) {
			$fields['phone']['label']    = __( 'Mobile Number', 'upaya-cargo-woocommerce' );
			$fields['phone']['required'] = true;
			$fields['phone']['priority'] = 80;
		}

		return $fields;
	}

	/**
	 * Configures the Mobile Number (billing_phone) field: relabels it, makes it
	 * required, adds 10-digit validation, and pins its priority to 80 — directly
	 * above the Alternate Mobile Number (81).
	 *
	 * Applied on woocommerce_billing_fields rather than woocommerce_checkout_fields
	 * because WooCommerce documents this as the authoritative filter for address-
	 * field properties (WC_Countries::get_address_fields) and billing_phone is
	 * guaranteed present here. Setting it on woocommerce_checkout_fields proved
	 * unreliable: the field was not always present at that stage, so phone kept
	 * WC's default priority 100 and rendered below the Alternate Mobile Number.
	 *
	 * @param  array<string,array> $fields Billing fields (billing_-prefixed keys).
	 * @return array<string,array>
	 */
	public function override_billing_phone_field( array $fields ): array {
		if ( isset( $fields['billing_phone'] ) ) {
			$fields['billing_phone']['required']          = true;
			$fields['billing_phone']['label']             = __( 'Mobile Number', 'upaya-cargo-woocommerce' );
			$fields['billing_phone']['priority']          = 80;
			$fields['billing_phone']['custom_attributes'] = [
				'pattern'   => '[0-9]{10}',
				'minlength' => '10',
				'maxlength' => '10',
				'title'     => __( 'Please enter a 10-digit mobile number (e.g. 9812345678)', 'upaya-cargo-woocommerce' ),
			];
		}
		return $fields;
	}

	/**
	 * Registers the Alternate Mobile Number on the billing address fieldset so
	 * it is editable in My Account → Addresses → Billing Address, pre-filled
	 * from and saved to the user meta key `billing_alternate_phone`.
	 *
	 * No separate save hook is needed:
	 *  - Checkout: the field is also added in modify_checkout_fields()
	 *    (woocommerce_checkout_fields), and WooCommerce core persists billing_-
	 *    prefixed posted fields to user meta for logged-in customers
	 *    (WC_Checkout::process_customer).
	 *  - My Account: WC_Form_Handler::save_address() saves every
	 *    woocommerce_billing_fields-registered key to user meta automatically.
	 *
	 * On the checkout, modify_checkout_fields() re-defines this same key with an
	 * identical definition, so there is no duplicate row. Priority 81 keeps it
	 * directly after Mobile Number (billing_phone, 80), matching checkout order.
	 *
	 * @param  array<string,array> $fields Billing fields (billing_-prefixed keys).
	 * @return array<string,array>
	 */
	public function add_alternate_phone_billing_field( array $fields ): array {
		if ( isset( $fields['billing_alternate_phone'] ) ) {
			return $fields;
		}

		$fields['billing_alternate_phone'] = [
			'label'       => __( 'Alternate Mobile Number', 'upaya-cargo-woocommerce' ),
			'placeholder' => __( 'Enter alternate mobile number', 'upaya-cargo-woocommerce' ),
			'required'    => false,
			'type'        => 'tel',
			'class'       => [ 'form-row-wide' ],
			'priority'    => 81,
		];

		return $fields;
	}

	/**
	 * When the customer ships to the same address as billing, copies billing
	 * data to shipping before the order is saved so the shipping address block
	 * appears correctly in admin, confirmation page, and emails.
	 *
	 * Skipped when "Ship to a different address?" is checked — WooCommerce
	 * already populates the order's shipping address from the shipping form.
	 *
	 * @param  \WC_Order $order The order being created.
	 * @param  array     $data  POST data from checkout submission.
	 * @return void
	 */
	public function copy_billing_to_shipping_on_save( \WC_Order $order, array $data ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$ship_to_different = ! empty( $_POST['ship_to_different_address'] )
			&& ! wc_ship_to_billing_address_only();
		// phpcs:enable

		if ( $ship_to_different ) {
			return; // WC has already set shipping from the shipping form fields.
		}

		$order->set_shipping_first_name( $order->get_billing_first_name() );
		$order->set_shipping_last_name( $order->get_billing_last_name() );
		$order->set_shipping_company( $order->get_billing_company() );
		$order->set_shipping_address_1( $order->get_billing_address_1() );
		$order->set_shipping_address_2( $order->get_billing_address_2() );
		$order->set_shipping_city( $order->get_billing_city() );
		$order->set_shipping_state( $order->get_billing_state() );
		$order->set_shipping_postcode( $order->get_billing_postcode() );
		$order->set_shipping_country( $order->get_billing_country() );
	}

	/* ------------------------------------------------------------------
	 * Phone validation
	 * ------------------------------------------------------------------ */

	/**
	 * Server-side validation: billing_phone must be exactly 10 numeric digits.
	 * Nepal mobile numbers are 10 digits (e.g. 9812345678), no country code.
	 * HTML5 pattern/minlength/maxlength on the field provides client-side
	 * feedback; this hook is the authoritative server-side guard.
	 *
	 * @return void
	 */
	public function validate_phone_length(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$phone = isset( $_POST['billing_phone'] )
			? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) )
			: '';
		// phpcs:enable

		if ( $phone === '' ) {
			return; // WC's own required-field check handles the empty case.
		}

		$digits = preg_replace( '/[^0-9]/', '', $phone );

		if ( strlen( $digits ) !== 10 ) {
			wc_add_notice(
				__( 'Mobile Number must be exactly 10 digits (e.g. 9812345678). Do not include the country code (+977).', 'upaya-cargo-woocommerce' ),
				'error'
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Save / display custom fields
	 * ------------------------------------------------------------------ */

	/**
	 * Persists custom checkout fields to order meta.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function save_checkout_fields( int $order_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$dirty = false;

		if ( ! empty( $_POST['billing_alternate_phone'] ) ) {
			$order->update_meta_data(
				'_billing_alternate_phone',
				sanitize_text_field( wp_unslash( $_POST['billing_alternate_phone'] ) )
			);
			$dirty = true;
		}

		if ( ! empty( $_POST['billing_landmark'] ) ) {
			$order->update_meta_data(
				'_upaya_landmark',
				sanitize_text_field( wp_unslash( $_POST['billing_landmark'] ) )
			);
			$dirty = true;
		}

		if ( $dirty ) {
			$order->save();
		}

		// phpcs:enable
	}

	/**
	 * Shows custom fields inside the order billing section in WP Admin.
	 *
	 * @param  \WC_Order $order
	 * @return void
	 */
	public function display_fields_in_admin( \WC_Order $order ): void {
		$alt_phone = $order->get_meta( '_billing_alternate_phone' );
		$landmark  = $order->get_meta( '_upaya_landmark' );

		if ( $alt_phone ) {
			echo '<p><strong>' . esc_html__( 'Alternate Mobile Number:', 'upaya-cargo-woocommerce' ) . '</strong> '
				. esc_html( $alt_phone ) . '</p>';
		}
		if ( $landmark ) {
			echo '<p><strong>' . esc_html__( 'Nearest Landmark:', 'upaya-cargo-woocommerce' ) . '</strong> '
				. esc_html( $landmark ) . '</p>';
		}
	}

	/**
	 * Shows the alternate mobile number on the customer-facing order page
	 * (Order-received / Thank-You page and My Account → View Order), directly
	 * under the billing phone. The landmark already appears there via the
	 * formatted billing address (inject_landmark_into_address), so only the
	 * alternate phone needs adding.
	 *
	 * Hooked to woocommerce_order_details_after_customer_address, which fires
	 * inside the billing <address> block for each address type.
	 *
	 * @param  string    $address_type 'billing' or 'shipping'.
	 * @param  \WC_Order $order        The order being displayed.
	 * @return void
	 */
	public function display_fields_in_order_details( string $address_type, \WC_Order $order ): void {
		if ( 'billing' !== $address_type ) {
			return;
		}

		// Unconfirmed account-email match — hide until an admin confirms ownership.
		if ( class_exists( 'BP_AL_Linker' ) && \BP_AL_Linker::is_provisional( $order ) ) {
			return;
		}

		$alt_phone = $order->get_meta( '_billing_alternate_phone' );
		if ( $alt_phone ) {
			echo '<p class="woocommerce-customer-details--alt-phone">'
				. esc_html__( 'Alternate Mobile Number:', 'upaya-cargo-woocommerce' ) . ' '
				. esc_html( $alt_phone ) . '</p>';
		}
	}

	/**
	 * Adds custom fields to WooCommerce order confirmation emails.
	 *
	 * @param  array     $fields
	 * @param  bool      $sent_to_admin
	 * @param  \WC_Order $order
	 * @return array
	 */
	public function add_fields_to_emails( array $fields, bool $sent_to_admin, \WC_Order $order ): array {
		$alt_phone = $order->get_meta( '_billing_alternate_phone' );
		$landmark  = $order->get_meta( '_upaya_landmark' );

		if ( $alt_phone ) {
			$fields['billing_alternate_phone'] = [
				'label' => __( 'Alternate Mobile Number', 'upaya-cargo-woocommerce' ),
				'value' => $alt_phone,
			];
		}
		if ( $landmark ) {
			$fields['upaya_landmark'] = [
				'label' => __( 'Nearest Landmark', 'upaya-cargo-woocommerce' ),
				'value' => $landmark,
			];
		}

		return $fields;
	}

	/* ------------------------------------------------------------------
	 * Landmark in formatted address (Change 4)
	 * ------------------------------------------------------------------ */

	/**
	 * Appends a {landmark} token to the Nepal address format so it appears
	 * in admin order detail, confirmation page, and emails.
	 *
	 * @param  array<string,string> $formats Country → format string map.
	 * @return array<string,string>
	 */
	public function add_landmark_to_address_format( array $formats ): array {
		if ( isset( $formats['NP'] ) && strpos( $formats['NP'], '{landmark}' ) === false ) {
			$formats['NP'] .= "\n{landmark}";
		}
		// Fallback: WC uses a 'default' key when no country-specific format exists.
		if ( isset( $formats['default'] ) && strpos( $formats['default'], '{landmark}' ) === false ) {
			$formats['default'] .= "\n{landmark}";
		}
		return $formats;
	}

	/**
	 * Provides the replacement value for the {landmark} address token.
	 *
	 * @param  array<string,string> $replacements Token → value map.
	 * @param  array<string,mixed>  $args         Address fields passed to formatter.
	 * @return array<string,string>
	 */
	public function replace_landmark_token( array $replacements, array $args ): array {
		$landmark = $args['landmark'] ?? '';
		$replacements['{landmark}'] = $landmark
			? esc_html__( 'Landmark:', 'upaya-cargo-woocommerce' ) . ' ' . esc_html( $landmark )
			: '';
		return $replacements;
	}

	/**
	 * Injects the landmark value into the address array before WC formats it.
	 * Handles both billing and shipping address filters via the same callback.
	 *
	 * @param  array<string,string> $address Address field array.
	 * @param  \WC_Order            $order   The order.
	 * @return array<string,string>
	 */
	public function inject_landmark_into_address( array $address, \WC_Order $order ): array {
		$landmark = get_post_meta( $order->get_id(), '_upaya_landmark', true );
		if ( $landmark ) {
			$address['landmark'] = sanitize_text_field( $landmark );
		}
		return $address;
	}

	/* ------------------------------------------------------------------
	 * Enqueue checkout assets
	 * ------------------------------------------------------------------ */

	/**
	 * Enqueues the AJAX area-loader script on checkout and My Account address pages.
	 *
	 * @return void
	 */
	public function enqueue_checkout_assets(): void {
		if ( ! is_checkout() && ! is_account_page() ) {
			return;
		}

		// Prime the location cache so the remote area search and the shipping-rate
		// calculation both have warm data the moment the customer needs them.
		// Rendering the full options list used to do this as a side effect on every
		// checkout load; now that only a placeholder is rendered, that warm-up must
		// happen explicitly. No-op (cheap transient read) when already warm.
		$this->location_cache->get_raw_cities();

		wp_enqueue_script(
			'upaya-checkout',
			UPAYA_PLUGIN_URL . 'assets/js/upaya-checkout.js',
			[ 'jquery' ],
			filemtime( UPAYA_PLUGIN_DIR . 'assets/js/upaya-checkout.js' ),
			true
		);

		wp_localize_script(
			'upaya-checkout',
			'upaya_checkout_params',
			[
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'search_action' => self::AJAX_NONCE,
				'search_nonce'  => wp_create_nonce( self::AJAX_NONCE ),
				'index_action'  => self::AJAX_INDEX_ACTION,
				'index_nonce'   => wp_create_nonce( self::AJAX_INDEX_ACTION ),
				'index_version' => (string) get_option( UPAYA_Location_Cache::INDEX_VERSION_OPTION, '0' ),
			]
		);
	}
}

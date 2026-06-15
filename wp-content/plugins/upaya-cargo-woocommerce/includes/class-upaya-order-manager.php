<?php
/**
 * Manages the WooCommerce ↔ Upaya Cargo order lifecycle.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into WooCommerce order status changes to submit orders to Upaya Cargo
 * and surfaces tracking information back to the customer and admin.
 */
class UPAYA_Order_Manager {

	/** Tracking cache TTL: 15 minutes. */
	const TRACK_TTL = 15 * MINUTE_IN_SECONDS;

	/** @var UPAYA_API */
	private UPAYA_API $api;

	/** @var UPAYA_Logger */
	private UPAYA_Logger $logger;

	/** @var UPAYA_Location_Cache */
	private UPAYA_Location_Cache $location_cache;

	/**
	 * Constructor — wires up WooCommerce hooks.
	 */
	public function __construct() {
		$this->logger         = new UPAYA_Logger();
		$this->api            = new UPAYA_API( get_option( 'upaya_api_key', '' ), $this->logger );
		$this->location_cache = new UPAYA_Location_Cache( $this->api, $this->logger );

		add_action( 'woocommerce_order_status_processing', [ $this, 'maybe_submit_order' ] );
		add_action( 'woocommerce_order_status_cancelled',  [ $this, 'log_cancellation' ] );
		add_action( 'woocommerce_thankyou',                [ $this, 'display_tracking_on_thankyou' ] );
	}

	/* ------------------------------------------------------------------
	 * Hook callbacks
	 * ------------------------------------------------------------------ */

	/**
	 * Character-count threshold for the Upaya product_description field.
	 *
	 * When the assembled product description exceeds this many characters the
	 * description is considered too long to forward verbatim: instead we send
	 * the fixed placeholder "Please check Instructions." and the full item
	 * breakdown is carried in client_note. At or below the threshold the real
	 * description is sent untouched (never truncated, never chunked).
	 *
	 * NOTE: this is a *character* count (multibyte-safe via mb_strlen()).
	 */
	const DESC_CHAR_LIMIT = 200;

	/** Fixed placeholder sent as product_description when the character limit is exceeded. */
	const DESC_PLACEHOLDER = 'Please check Instructions.';

	/**
	 * Hard character cap Upaya enforces on the client_note field (verified in the
	 * Upaya dashboard). Multibyte-safe via mb_strlen(). Descriptions between 200
	 * and this limit are carried here; anything longer triggers an item split.
	 */
	const CLIENT_NOTE_CHAR_LIMIT = 255;

	/**
	 * Prefix for the Upaya order_reference_id (mirrors the Magento "BPA…" scheme,
	 * which Upaya accepts). The reference is PREFIX + WC order number + a 3-digit
	 * counter, e.g. BPA1140001 — see build_reference_id() / parse_reference_id().
	 */
	const REFERENCE_PREFIX = 'BPA';

	/**
	 * Submits an order to Upaya when it moves to "processing" status,
	 * provided auto-submit is enabled.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function maybe_submit_order( int $order_id ): void {
		if ( 'yes' !== get_option( 'upaya_auto_submit', 'yes' ) ) {
			return;
		}

		$this->submit_order_to_upaya( $order_id );
	}

	/**
	 * Logs order cancellation (Upaya has no cancel endpoint).
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function log_cancellation( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$upaya_id = $order->get_meta( '_upaya_order_id' );

		if ( $upaya_id ) {
			$this->logger->warning(
				"Order #{$order_id} cancelled in WooCommerce. Upaya order ID: {$upaya_id}. Manual cancellation required in Upaya dashboard."
			);
			$order->add_order_note(
				__( 'Upaya Cargo: order cancelled in WooCommerce. Manual cancellation in Upaya dashboard may be required.', 'upaya-cargo-woocommerce' )
			);
		}
	}

	/**
	 * Displays tracking info on the thank-you page if the order was
	 * successfully submitted to Upaya.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function display_tracking_on_thankyou( int $order_id ): void {
		$order    = wc_get_order( $order_id );
		$upaya_id = $order ? $order->get_meta( '_upaya_order_id' ) : '';

		if ( ! $upaya_id ) {
			return;
		}

		$tracking = $this->get_tracking_info( $order_id );

		if ( is_wp_error( $tracking ) || empty( $tracking ) ) {
			return;
		}

		$status   = esc_html( $tracking['status']            ?? '' );
		$est_date = esc_html( $tracking['estimated_delivery'] ?? '' );

		echo '<section class="woocommerce-upaya-tracking">';
		echo '<h2 class="woocommerce-column__title">' . esc_html__( 'Upaya Cargo Tracking', 'upaya-cargo-woocommerce' ) . '</h2>';
		echo '<p>' . esc_html__( 'Tracking ID:', 'upaya-cargo-woocommerce' ) . ' <strong>' . esc_html( $upaya_id ) . '</strong></p>';

		if ( $status ) {
			echo '<p>' . esc_html__( 'Status:', 'upaya-cargo-woocommerce' ) . ' <strong>' . $status . '</strong></p>';
		}

		if ( $est_date ) {
			echo '<p>' . esc_html__( 'Estimated Delivery:', 'upaya-cargo-woocommerce' ) . ' <strong>' . $est_date . '</strong></p>';
		}

		echo '</section>';
	}

	/* ------------------------------------------------------------------
	 * Core submission
	 * ------------------------------------------------------------------ */

	/**
	 * Builds the Upaya payload(s) and submits them via the API.
	 *
	 * Usually one payload per WooCommerce order, but
	 * {@see UPAYA_Order_Manager::build_payloads()} returns several when the
	 * product description is too long for a single shipment (>255 chars) and the
	 * items are split across multiple add-order requests. The loop below submits
	 * each, accumulating the returned tracking/reference IDs.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function submit_order_to_upaya( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->logger->error( "Order #{$order_id}: could not load WC_Order." );
			return;
		}

		if ( $order->get_meta( '_upaya_submitted' ) ) {
			$this->logger->debug( "Order #{$order_id}: already submitted to Upaya, skipping." );
			return;
		}

		$this->increment_attempts( $order_id );

		$payloads = $this->build_payloads( $order );

		if ( is_wp_error( $payloads ) ) {
			$message = $payloads->get_error_message();
			$order->add_order_note( sprintf(
				__( 'Upaya submission skipped: %s', 'upaya-cargo-woocommerce' ),
				$message
			) );
			$this->logger->error( "Order #{$order_id}: payload build failed — {$message}" );
			return;
		}

		$upaya_ids     = [];
		$upaya_ref_ids = [];

		foreach ( $payloads as $index => $payload ) {
			$this->logger->debug( "Order #{$order_id} chunk " . ( $index + 1 ) . ': submitting — ' . wp_json_encode( $payload ) );

			$result = $this->api->add_order( $payload );

			if ( is_wp_error( $result ) ) {
				$error_message = $result->get_error_message();
				$order->add_order_note( sprintf(
					__( 'Upaya submission failed (chunk %d): %s', 'upaya-cargo-woocommerce' ),
					$index + 1,
					$error_message
				) );
				$this->update_db_record( $order_id, 'failed', $payload, $result->get_error_data() ?? [] );

				if ( 'yes' === get_option( 'upaya_retry_failed_orders', 'yes' ) ) {
					wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'upaya_retry_order', [ $order_id ] );
					$this->logger->debug( "Order #{$order_id}: retry scheduled in 1 hour." );
				}
				return;
			}

			// Upaya wraps the result two levels deep:
			//   { "meta": {…}, "data": { "message": "…", "data": [ { "trackingCode", "orderReferenceId" } ] } }
			// Read that shape first, then fall back to the single-nested and
			// legacy/top-level shapes for safety.
			$order_row = $result['data']['data'][0]   // actual API shape
				?? $result['data'][0]                  // single-nested (Magento reference)
				?? [];

			$upaya_order_id = $order_row['trackingCode']
				?? $order_row['tracking_code']
				?? $result['id']
				?? $result['order_id']
				?? $result['tracking_number']
				?? '';

			if ( '' === $upaya_order_id ) {
				$this->logger->warning(
					"Order #{$order_id} chunk " . ( $index + 1 )
					. ': Upaya add-order succeeded but no tracking code found in response — '
					. wp_json_encode( $result )
				);
			}

			$upaya_ref_id = $order_row['orderReferenceId'] ?? '';
			if ( '' !== $upaya_ref_id ) {
				$upaya_ref_ids[] = sanitize_text_field( $upaya_ref_id );
			}

			$upaya_ids[] = sanitize_text_field( $upaya_order_id );
		}

		// A split order yields several tracking codes, but only the FIRST is stored
		// in _upaya_order_id: get_tracking_info() passes that value straight to
		// /track-order/{id}, so a comma-joined string would be an invalid lookup
		// (and tracking UIs would show a malformed code). Webhooks for the other
		// shipments still resolve via the reference parse (find_order strategy 1b),
		// so storing only the first loses nothing.
		$upaya_ids        = array_values( array_filter( $upaya_ids ) );
		$primary_tracking = $upaya_ids[0] ?? '';
		$all_tracking     = implode( ', ', $upaya_ids ); // audit/log only

		// Store via CRUD so the meta lands in WooCommerce's canonical order store
		// (HPOS table when enabled, post meta otherwise) — keeps reads consistent.
		$order->update_meta_data( '_upaya_submitted', '1' );
		$order->update_meta_data( '_upaya_order_id',  $primary_tracking );

		// Persist the Upaya-returned reference ID(s) for resilient webhook lookup.
		$upaya_ref_string = implode( ', ', array_filter( $upaya_ref_ids ) );
		if ( '' !== $upaya_ref_string ) {
			$order->update_meta_data( '_upaya_reference_id', $upaya_ref_string );
		}

		$order->save();

		// Note shows the primary tracking code; for a split order the full set is
		// still recorded for the audit trail (Upaya dashboard cross-reference).
		if ( count( $upaya_ids ) > 1 ) {
			$order->add_order_note( sprintf(
				/* translators: 1: shipment count, 2: primary tracking code, 3: all comma-separated codes */
				__( 'Order submitted to Upaya Cargo (split into %1$d shipments). Tracking ID: %2$s. All codes: %3$s', 'upaya-cargo-woocommerce' ),
				count( $upaya_ids ),
				$primary_tracking,
				$all_tracking
			) );
		} else {
			$order->add_order_note( sprintf(
				__( 'Order submitted to Upaya Cargo. Tracking ID: %s', 'upaya-cargo-woocommerce' ),
				$primary_tracking
			) );
		}

		$this->update_db_record( $order_id, 'submitted', $payloads[0], [], $primary_tracking );

		$this->logger->debug( "Order #{$order_id}: submitted successfully. Primary tracking: {$primary_tracking}; all: {$all_tracking}" );
	}

	/* ------------------------------------------------------------------
	 * Tracking
	 * ------------------------------------------------------------------ */

	/**
	 * Returns formatted tracking data for an order, with 15-minute caching.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return array<string,mixed>|\WP_Error Tracking data or error.
	 */
	public function get_tracking_info( int $order_id ) {
		$order          = wc_get_order( $order_id );
		$upaya_order_id = $order ? $order->get_meta( '_upaya_order_id' ) : '';

		if ( ! $upaya_order_id ) {
			return new \WP_Error( 'upaya_no_tracking', __( 'No Upaya order ID for this order.', 'upaya-cargo-woocommerce' ) );
		}

		$transient_key = 'upaya_track_' . $order_id;
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$result = $this->api->track_order( $upaya_order_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tracking = $this->format_tracking_response( $result );
		set_transient( $transient_key, $tracking, self::TRACK_TTL );

		return $tracking;
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Builds the Upaya /add-order payload(s) from a WC_Order, splitting into
	 * multiple shipments when the product description is too long for one.
	 *
	 * FIELD LIMITS (verified in the Upaya dashboard):
	 *   • product_description ≤ 200 chars
	 *   • client_note         ≤ 255 chars
	 *
	 * DECISION (based on the full "{name} ({sku}) x{qty} | …" description D):
	 *   • D ≤ 200            → one payload; D in product_description.
	 *   • 200 < D ≤ 255      → one payload; placeholder in product_description,
	 *                          D (plus the customer note if it still fits 255) in
	 *                          client_note.
	 *   • D > 255            → split the line items into groups whose OWN
	 *                          description each fits ≤ 200, one payload per group.
	 *                          COD is divided across groups (proportional to each
	 *                          group's item value); no text is ever truncated.
	 *
	 * The submission loop, retry scheduling and error-handling in
	 * {@see UPAYA_Order_Manager::submit_order_to_upaya()} already handle an
	 * array of any size, storing the tracking codes comma-separated.
	 *
	 * @param  \WC_Order $order WooCommerce order.
	 * @return array<int,array<string,mixed>>|\WP_Error  Payload array or error.
	 */
	private function build_payloads( \WC_Order $order ) {
		$instance_settings = $this->get_shipping_method_settings( $order );

		// ── Collect line items once (name, sku, qty, line_total, weight) ──
		$all_items = [];

		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$all_items[] = [
				'name'       => (string) $product->get_name(),
				'sku'        => (string) $product->get_sku(),
				'qty'        => (int) $item->get_quantity(),
				'line_total' => (float) $item->get_total(), // after discounts, excl. tax
				'weight'     => (float) $product->get_weight(),
			];
		}

		if ( empty( $all_items ) ) {
			return new \WP_Error(
				'upaya_no_items',
				__( 'Order contains no shippable products.', 'upaya-cargo-woocommerce' )
			);
		}

		// Single shipment when the whole description fits the client_note cap
		// (covers D ≤ 200 → product_description, and 200 < D ≤ 255 → client_note).
		$full_description = $this->build_description( $all_items );

		if ( mb_strlen( $full_description ) <= self::CLIENT_NOTE_CHAR_LIMIT ) {
			return [ $this->build_order_payload( $order, $all_items, $all_items, $instance_settings, 0 ) ];
		}

		// D > 255 — split items into groups each describable within 200 chars.
		$groups   = $this->split_items_by_description( $all_items );
		$payloads = [];

		foreach ( $groups as $i => $group ) {
			// Each split gets a unique reference (see build_reference_id); webhooks
			// resolve via tracking_code / _upaya_reference_id LIKE.
			$payloads[] = $this->build_order_payload( $order, $group, $all_items, $instance_settings, $i );
		}

		return $payloads;
	}

	/**
	 * Splits a flat item list into ordered groups whose per-group description
	 * (see {@see build_description()}) each stays within DESC_CHAR_LIMIT (200),
	 * so every group can carry its full description in product_description with
	 * no truncation. A single item whose own description already exceeds 200 is
	 * placed in its own group (it cannot be reduced further; build_order_payload
	 * then carries it in client_note).
	 *
	 * @param  array<int,array<string,mixed>> $items Line items.
	 * @return array<int,array<int,array<string,mixed>>> Groups of items.
	 */
	private function split_items_by_description( array $items ): array {
		$groups  = [];
		$current = [];

		foreach ( $items as $item ) {
			$candidate = array_merge( $current, [ $item ] );

			if ( mb_strlen( $this->build_description( $candidate ) ) <= self::DESC_CHAR_LIMIT ) {
				$current = $candidate;
				continue;
			}

			// Adding this item would overflow — close the current group first.
			if ( ! empty( $current ) ) {
				$groups[] = $current;
			}
			$current = [ $item ];
		}

		if ( ! empty( $current ) ) {
			$groups[] = $current;
		}

		return $groups;
	}

	/**
	 * Assembles one flat Upaya payload for a set of items (the whole order, or a
	 * single split group). Applies the 200/255 field rules and divides COD
	 * proportionally when the order is split across multiple payloads.
	 *
	 * @param  \WC_Order                      $order             WooCommerce order.
	 * @param  array<int,array<string,mixed>> $items             Items for THIS payload.
	 * @param  array<int,array<string,mixed>> $all_items         All order items (for COD proportions).
	 * @param  array<string,mixed>            $instance_settings Upaya shipping method settings.
	 * @param  int                            $index             0-based payload index (counter in the reference ID).
	 * @return array<string,mixed>  Flat Upaya payload.
	 */
	private function build_order_payload(
		\WC_Order $order,
		array $items,
		array $all_items,
		array $instance_settings,
		int $index
	): array {
		$receiver_name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$default_weight = (float) ( $instance_settings['default_weight'] ?? 0.5 );

		// ── Weight & item subtotal for THIS payload ─────────────────────
		$group_weight      = 0.0;
		$group_items_total = 0.0;
		foreach ( $items as $it ) {
			$w                  = (float) $it['weight'];
			$group_weight      += ( $w > 0 ? $w : $default_weight ) * (int) $it['qty'];
			$group_items_total += (float) $it['line_total'];
		}

		// Order-wide item subtotal (for proportional COD distribution on splits).
		$order_items_total = array_sum( array_column( $all_items, 'line_total' ) );

		// ── Description + 200/255 rules ─────────────────────────────────
		$description   = $this->build_description( $items );
		$customer_note = (string) $order->get_customer_note();

		if ( mb_strlen( $description ) <= self::DESC_CHAR_LIMIT ) {
			// Fits product_description verbatim; client_note carries only the note.
			$product_description = $description;
			$client_note         = $this->assemble_client_note( '', $customer_note );
		} else {
			// Too long for product_description — placeholder there, full description
			// (plus the customer note if it still fits 255) in client_note.
			$product_description = self::DESC_PLACEHOLDER;
			$client_note         = $this->assemble_client_note( $description, $customer_note );
		}

		// ── Pricing / COD ───────────────────────────────────────────────
		$is_cod         = 'cod' === $order->get_payment_method();
		$shipping_total = (float) $order->get_shipping_total();
		// Proportional shipping share for this group (COD only).
		$shipping_share = $order_items_total > 0
			? $shipping_total * ( $group_items_total / $order_items_total )
			: $shipping_total;
		$cod_amount     = $is_cod ? ( $group_items_total + $shipping_share ) : 0.0;

		$payload = [
			'receiver_name'             => sanitize_text_field( $receiver_name ),
			'receiver_contact'          => sanitize_text_field( $order->get_billing_phone() ),
			'receiver_alternate_number' => sanitize_text_field(
				(string) ( $order->get_meta( '_billing_alternate_phone' )
					?: $order->get_meta( '_upaya_alt_phone' ) )
			),
			// billing_city holds the area name (set from the combined hub_area select).
			'area_id'                   => $this->location_cache->get_area_id_by_name(
				$order->get_billing_city()
			),
			'remarks'                   => '',
			'receiver_address'          => sanitize_text_field( trim(
				$order->get_billing_address_1() . ' ' . $order->get_billing_address_2()
			) ),
			'receiver_landmark'         => sanitize_text_field(
				(string) $order->get_meta( '_upaya_landmark' )
			),
			'order_reference_id'        => sanitize_text_field( $this->build_reference_id( $order, $index ) ),
			'weight'                    => (float) ( $group_weight > 0 ? $group_weight : $default_weight ),
			'service_type_id'           => (int) ( $instance_settings['service_type_id'] ?? UPAYA_API::SERVICE_DOOR_TO_DOOR ),
			'length'                    => 0.1,
			'breadth'                   => 0.1,
			'height'                    => 0.1,
			'product_category_id'       => (int) ( $instance_settings['default_product_category_id'] ?? UPAYA_CAT_ELECTRONICS ),
			'order_type'                => sanitize_text_field( $instance_settings['order_type'] ?? UPAYA_API::ORDER_TYPE_DELIVERY ),
			'product_description'       => sanitize_text_field( $product_description ),
			'product_price'             => (int) round( $group_items_total ),
			'cod_amount'                => (int) apply_filters(
				'upaya_payload_cod_amount',
				round( $cod_amount ),
				$order,
				$group_items_total,
				$order_items_total
			),
			'client_note'               => $client_note,
		];

		return $payload;
	}

	/**
	 * Builds the Upaya order_reference_id.
	 *
	 * Upaya validates the reference format and rejects bare numeric values and
	 * hyphenated strings ("order_reference_id format is invalid"). We mirror the
	 * proven Magento format — "BPA" + WC order number + a zero-padded counter —
	 * e.g. BPA1140001 (single shipment) and BPA1140001 / BPA1140002 / … for split
	 * shipments. Each is unique so Upaya treats split orders as distinct, and the
	 * webhook resolves them via tracking_code / _upaya_reference_id LIKE matches.
	 *
	 * Filter `upaya_order_reference_id` to customise the scheme.
	 *
	 * @param  \WC_Order $order WooCommerce order.
	 * @param  int       $index 0-based payload index.
	 * @return string
	 */
	private function build_reference_id( \WC_Order $order, int $index ): string {
		// PREFIX + order number + zero-padded 3-digit counter (index+1).
		// Kept reversible by parse_reference_id() so webhooks can map back to the
		// WC order without relying on stored meta.
		$reference = sprintf( '%s%s%03d', self::REFERENCE_PREFIX, $order->get_order_number(), $index + 1 );

		/**
		 * Filter the Upaya order_reference_id.
		 *
		 * NOTE: if you change the format here, update parse_reference_id() too, or
		 * the webhook's reference→order mapping will fall back to meta LIKE matches.
		 *
		 * @param string    $reference Default "BPA{order_number}{counter}".
		 * @param \WC_Order  $order     The order.
		 * @param int        $index     0-based payload index.
		 */
		return (string) apply_filters( 'upaya_order_reference_id', $reference, $order, $index );
	}

	/**
	 * Reverses {@see build_reference_id()} — extracts the WC order number from an
	 * Upaya order_reference_id ("BPA{order_number}{3-digit counter}").
	 *
	 * Public + static so the webhook processor can resolve the order
	 * deterministically from the reference alone (no stored meta needed).
	 *
	 * @param  string $reference The order_reference_id received from Upaya.
	 * @return string  The WC order number, or '' if the reference is not ours.
	 */
	public static function parse_reference_id( string $reference ): string {
		$reference = trim( $reference );
		$prefix    = self::REFERENCE_PREFIX;

		// Must start with our prefix and end with the 3-digit counter, with a
		// non-empty order number in between.
		if ( ! preg_match( '/^' . preg_quote( $prefix, '/' ) . '(.+)\d{3}$/', $reference, $m ) ) {
			return '';
		}

		return $m[1];
	}

	/**
	 * Builds the single human-readable product description string for an order.
	 *
	 * One segment per line item in the format "{name} ({sku}) x{qty}" (the SKU
	 * parens are omitted when the SKU is empty), joined with " | ". No
	 * truncation is applied here — the 200-character rule in build_order_payload()
	 * decides whether this string is sent verbatim or replaced wholesale by the
	 * fixed placeholder.
	 *
	 * @param  array<int,array{name:string,sku:string,qty:int,line_total:float}> $items Line items.
	 * @return string
	 */
	private function build_description( array $items ): string {
		$segments = [];

		foreach ( $items as $item ) {
			$name     = $item['name'];
			$sku      = $item['sku'];
			$qty      = (int) $item['qty'];
			$sku_part = '' !== $sku ? " ({$sku})" : '';

			$segments[] = "{$name}{$sku_part} x{$qty}";
		}

		return implode( ' | ', $segments );
	}

	/**
	 * Builds the client_note value, guaranteed to stay within
	 * CLIENT_NOTE_CHAR_LIMIT (255).
	 *
	 * Composition:
	 *   • $description — the full item description, passed ONLY when it was too
	 *     long for product_description (200 < D ≤ 255). It is always preserved in
	 *     full and never trimmed.
	 *   • The customer's order note — appended as "Client Note: …" only when one
	 *     exists AND it still fits within the 255 budget alongside the
	 *     description. The "Client Note:" label is omitted entirely when there is
	 *     no customer note.
	 *
	 * @param  string $description   Description to carry here, or '' when it
	 *                               already fit in product_description.
	 * @param  string $customer_note Raw WC customer order note.
	 * @return string  Sanitised note ≤ 255 characters.
	 */
	private function assemble_client_note( string $description, string $customer_note ): string {
		$customer_note = trim( $customer_note );

		if ( '' === $description && '' === $customer_note ) {
			return '';
		}

		if ( '' === $customer_note ) {
			$note = $description;
		} elseif ( '' === $description ) {
			$note = 'Client Note: ' . $customer_note;
		} else {
			$with_note = $description . "\nClient Note: " . $customer_note;
			// Keep the note only if the combined value still fits; the item
			// description always takes priority and is never trimmed.
			$note = ( mb_strlen( $with_note ) <= self::CLIENT_NOTE_CHAR_LIMIT )
				? $with_note
				: $description;
		}

		$note = sanitize_textarea_field( $note );

		// Final safety net for a single item whose description alone exceeds the
		// cap (cannot be split further) — hard-trim with a trailing ellipsis.
		if ( mb_strlen( $note ) > self::CLIENT_NOTE_CHAR_LIMIT ) {
			$note = mb_substr( $note, 0, self::CLIENT_NOTE_CHAR_LIMIT - 1 ) . '…';
		}

		return $note;
	}

	/**
	 * Extracts Upaya shipping method instance settings from the order's
	 * shipping items.
	 *
	 * @param  \WC_Order $order WooCommerce order.
	 * @return array<string,mixed> Instance settings, or empty array on failure.
	 */
	private function get_shipping_method_settings( \WC_Order $order ): array {
		foreach ( $order->get_shipping_methods() as $shipping_item ) {
			if ( 'upaya_cargo' !== $shipping_item->get_method_id() ) {
				continue;
			}

			$instance_id = (int) $shipping_item->get_instance_id();
			$method      = new UPAYA_Shipping_Method( $instance_id );

			return [
				'service_type_id'             => $method->get_option( 'service_type_id',             UPAYA_API::SERVICE_DOOR_TO_DOOR ),
				'default_weight'              => $method->get_option( 'default_weight',              0.5 ),
				'default_product_category_id' => $method->get_option( 'default_product_category_id', UPAYA_CAT_ELECTRONICS ),
				'order_type'                  => $method->get_option( 'order_type',                  UPAYA_API::ORDER_TYPE_DELIVERY ),
			];
		}

		return [];
	}

	/**
	 * Normalises a raw tracking API response into a consistent array.
	 *
	 * @param  array<string,mixed> $raw Raw API response.
	 * @return array<string,mixed>
	 */
	private function format_tracking_response( array $raw ): array {
		// Upaya wraps payloads under `data` (and sometimes `data.data[0]`), exactly
		// like /add-order. Unwrap to the innermost record before reading fields.
		$data = $raw;
		if ( isset( $raw['data']['data'][0] ) && is_array( $raw['data']['data'][0] ) ) {
			$data = $raw['data']['data'][0];
		} elseif ( isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$data = $raw['data'];
		}

		return [
			'order_number'       => $data['orderNumber'] ?? $data['order_number'] ?? $data['trackingCode'] ?? '',
			'status'             => $data['status'] ?? $data['orderStatus'] ?? $data['currentStatus'] ?? '',
			'estimated_delivery' => $data['estimatedDeliveryDate'] ?? $data['estimated_delivery_date'] ?? $data['estimatedDelivery'] ?? '',
			'items'              => $data['items'] ?? [],
			'raw'                => $raw,
		];
	}

	/**
	 * Increments the attempt counter in the upaya_orders table.
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return void
	 */
	private function increment_attempts( int $order_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'upaya_orders';

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE wc_order_id = %d", $order_id )
		);

		if ( $existing ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET attempts = attempts + 1, last_attempt = %s WHERE wc_order_id = %d",
					current_time( 'mysql' ),
					$order_id
				)
			);
		} else {
			$wpdb->insert(
				$table,
				[
					'wc_order_id'  => $order_id,
					'status'       => 'pending',
					'attempts'     => 1,
					'last_attempt' => current_time( 'mysql' ),
				],
				[ '%d', '%s', '%d', '%s' ]
			);
		}
	}

	/**
	 * Creates or updates the upaya_orders table record after an API call.
	 *
	 * @param  int                 $order_id      WooCommerce order ID.
	 * @param  string              $status        pending|submitted|failed.
	 * @param  array               $payload       Request payload sent to API.
	 * @param  array               $response      API response body.
	 * @param  string              $upaya_order_id Upaya order/tracking ID on success.
	 * @return void
	 */
	private function update_db_record(
		int $order_id,
		string $status,
		array $payload,
		array $response,
		string $upaya_order_id = ''
	): void {
		global $wpdb;

		$table = $wpdb->prefix . 'upaya_orders';

		$wpdb->update(
			$table,
			[
				'upaya_order_id' => $upaya_order_id ? sanitize_text_field( $upaya_order_id ) : null,
				'status'         => $status,
				'payload'        => wp_json_encode( $payload ),
				'response'       => wp_json_encode( $response ),
				'last_attempt'   => current_time( 'mysql' ),
			],
			[ 'wc_order_id' => $order_id ],
			[ '%s', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}
}

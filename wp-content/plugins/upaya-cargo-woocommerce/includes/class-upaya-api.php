<?php
/**
 * HTTP client for the Upaya Cargo REST API.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps all HTTP calls to the Upaya Cargo API.
 *
 * Base URL:  UPAYA_API_BASE  (https://portal-api.upaya.com.np/api/v1/client)
 * Auth:      X-API-Key header
 */
class UPAYA_API {

	/** Default HTTP timeout in seconds. */
	const TIMEOUT = 15;

	/* ------------------------------------------------------------------
	 * Service type constants (Upaya API reference table)
	 * ------------------------------------------------------------------ */
	const SERVICE_DOOR_TO_DOOR     = 3;
	const SERVICE_DOOR_TO_BRANCH   = 4;
	const SERVICE_BRANCH_TO_BRANCH = 5;
	const SERVICE_ACTIVATION       = 6;
	const SERVICE_BULK             = 7;

	/* ------------------------------------------------------------------
	 * Order type constants (exact string values required by API)
	 * ------------------------------------------------------------------ */
	const ORDER_TYPE_DELIVERY = 'delivery_order';
	const ORDER_TYPE_RETURN   = 'return_order';
	const ORDER_TYPE_EXCHANGE = 'exchange_order';

	/** @var string */
	private string $api_key;

	/** @var UPAYA_Logger */
	private UPAYA_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param string       $api_key API key for X-API-Key header.
	 * @param UPAYA_Logger $logger  Logger instance.
	 */
	public function __construct( string $api_key, UPAYA_Logger $logger ) {
		$this->api_key = $api_key;
		$this->logger  = $logger;
	}

	/* ------------------------------------------------------------------
	 * Static reference helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Returns all service types as [id => label].
	 *
	 * @return array<int,string>
	 */
	public static function get_service_types(): array {
		return [
			self::SERVICE_DOOR_TO_DOOR     => __( 'Door To Door Delivery',     'upaya-cargo-woocommerce' ),
			self::SERVICE_DOOR_TO_BRANCH   => __( 'Door To Branch Delivery',   'upaya-cargo-woocommerce' ),
			self::SERVICE_BRANCH_TO_BRANCH => __( 'Branch To Branch Delivery', 'upaya-cargo-woocommerce' ),
			self::SERVICE_ACTIVATION       => __( 'Activation Delivery',       'upaya-cargo-woocommerce' ),
			self::SERVICE_BULK             => __( 'Bulk Delivery',             'upaya-cargo-woocommerce' ),
		];
	}

	/**
	 * Returns all 17 product categories as [id => label].
	 *
	 * @return array<int,string>
	 */
	public static function get_product_categories(): array {
		return [
			1  => __( 'Clothing and Apparels',        'upaya-cargo-woocommerce' ),
			2  => __( 'Bags and Accessories',          'upaya-cargo-woocommerce' ),
			3  => __( 'Beauty and Accessories',        'upaya-cargo-woocommerce' ),
			4  => __( 'Shoes and Slippers',            'upaya-cargo-woocommerce' ),
			5  => __( 'Electronic and Gadgets',        'upaya-cargo-woocommerce' ),
			6  => __( 'Kitchen and Household Items',   'upaya-cargo-woocommerce' ),
			7  => __( 'Jewellery and Accessories',     'upaya-cargo-woocommerce' ),
			8  => __( 'Customized Products',           'upaya-cargo-woocommerce' ),
			9  => __( 'Supplements',                   'upaya-cargo-woocommerce' ),
			10 => __( 'Herbal Products',               'upaya-cargo-woocommerce' ),
			11 => __( 'Sports',                        'upaya-cargo-woocommerce' ),
			12 => __( 'Stationaries',                  'upaya-cargo-woocommerce' ),
			13 => __( 'QR Standee',                    'upaya-cargo-woocommerce' ),
			14 => __( 'Credit/Debit Card',             'upaya-cargo-woocommerce' ),
			15 => __( 'Documents',                     'upaya-cargo-woocommerce' ),
			16 => __( 'Gadgets',                       'upaya-cargo-woocommerce' ),
			17 => __( 'Organic Agricultural Products', 'upaya-cargo-woocommerce' ),
		];
	}

	/* ------------------------------------------------------------------
	 * Public API methods
	 * ------------------------------------------------------------------ */

	/**
	 * GET /locations — returns the raw `data` array (cities with nested areas).
	 *
	 * Shape: [ { id, name, hubName, areas: [ { id, name, locationId, … } ] } ]
	 *
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function get_raw_locations() {
		$response = $this->request( 'GET', '/locations' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['data'] ?? $response;
	}

	/**
	 * GET /locations — retrieves all client delivery locations.
	 *
	 * Response shape: [ { locationId, locationName, address }, … ]
	 *
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public function get_locations() {
		$response = $this->request( 'GET', '/locations' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$raw_data = $response['locations'] ?? $response['data'] ?? $response;

		// The Upaya API nests locations inside an 'areas' array for each city.
		// We flatten them into a single array of locations.
		$flattened = [];
		if ( is_array( $raw_data ) ) {
			foreach ( $raw_data as $item ) {
				if ( isset( $item['areas'] ) && is_array( $item['areas'] ) ) {
					foreach ( $item['areas'] as $area ) {
						if ( isset( $area['locationId'] ) ) {
							$flattened[] = $area;
						}
					}
				} elseif ( isset( $item['locationId'] ) ) {
					// Fallback for older flat structure
					$flattened[] = $item;
				}
			}
		}

		return ! empty( $flattened ) ? $flattened : $raw_data;
	}

	/**
	 * GET /locations/{id} — retrieves a single delivery location.
	 *
	 * @param  int $location_id Upaya location ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_location( int $location_id ) {
		return $this->request( 'GET', '/locations/' . $location_id );
	}

	/**
	 * GET /track-order/{orderid} — live tracking data for an order.
	 *
	 * Response shape: { orderNumber, status, estimatedDeliveryDate, items[] }
	 *
	 * @param  string $order_id Upaya-assigned order ID (e.g. WRL2408001AZSN).
	 * @return array<string,mixed>|\WP_Error
	 */
	public function track_order( string $order_id ) {
		$response = $this->request( 'GET', '/track-order/' . rawurlencode( $order_id ) );

		if ( ! is_wp_error( $response ) ) {
			$this->logger->debug( 'track_order response: ' . wp_json_encode( $response ) );
		}

		return $response;
	}

	/**
	 * POST /order-rates — calculates shipping cost before placing an order.
	 *
	 * @param  array<string,mixed> $params {
	 *   @type float  initial_weight   Weight in kg (required).
	 *   @type string order_type       ORDER_TYPE_* constant (required).
	 *   @type int    service_type_id  SERVICE_* constant (required).
	 *   @type int    location_id      Pickup location ID (required).
	 *   @type float  length           Optional cm.
	 *   @type float  breadth          Optional cm.
	 *   @type float  height           Optional cm.
	 * }
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_order_rates( array $params ) {
		$location_id = (int) ( $params['location_id'] ?? 0 );

		// Upaya rejects any non-positive location_id with HTTP 422. Bail before the
		// request so checkout falls through to other shipping methods gracefully.
		if ( $location_id <= 0 ) {
			$this->logger->debug( 'Upaya: location_id is invalid or unresolved — skipping API call.' );
			return [];
		}

		$body = [
			'initial_weight'  => (float) ( $params['initial_weight'] ?? $params['weight'] ?? 0.5 ),
			'order_type'      => sanitize_text_field( $params['order_type']    ?? self::ORDER_TYPE_DELIVERY ),
			'service_type_id' => (int) ( $params['service_type_id']            ?? self::SERVICE_DOOR_TO_DOOR ),
			'location_id'     => $location_id,
			'length'          => null,
			'breadth'         => null,
			'height'          => null,
		];

		foreach ( [ 'length', 'breadth', 'height' ] as $dim ) {
			if ( isset( $params[ $dim ] ) && (float) $params[ $dim ] > 0 ) {
				$body[ $dim ] = (float) $params[ $dim ];
			}
		}

		$this->logger->debug( 'get_order_rates request: ' . wp_json_encode( $body ) );

		$response = $this->request( 'POST', '/order-rates', $body );

		if ( ! is_wp_error( $response ) ) {
			$this->logger->debug( 'get_order_rates response: ' . wp_json_encode( $response ) );
		}

		return $response;
	}

	/**
	 * POST /add-order — submits a single order inside the required batch envelope.
	 *
	 * Body: { "orders": [ { receiver_name, receiver_contact,
	 *   receiver_alternate_number, area_id, product_price, cod_amount,
	 *   remarks, receiver_address, receiver_landmark, order_reference_id,
	 *   initial_weight, service_type_id, product_description,
	 *   length, breadth, height, product_category_id,
	 *   order_type, client_note } ] }
	 *
	 * @param  array<string,mixed> $order_data Flat order payload (19 fields).
	 * @return array<string,mixed>|\WP_Error
	 */
	public function add_order( array $order_data ) {
		$body = [ 'orders' => [ $order_data ] ];

		$this->logger->debug( 'add_order request: ' . wp_json_encode( $body ) );

		$response = $this->request( 'POST', '/add-order', $body );

		if ( ! is_wp_error( $response ) ) {
			$this->logger->debug( 'add_order response: ' . wp_json_encode( $response ) );
		}

		return $response;
	}

	/* ------------------------------------------------------------------
	 * Private HTTP helper
	 * ------------------------------------------------------------------ */

	/**
	 * Sends an HTTP request and returns the decoded JSON body or a WP_Error.
	 *
	 * @param  string $method   HTTP verb (GET|POST).
	 * @param  string $endpoint Relative path starting with /.
	 * @param  array  $body     Optional request body (POST only).
	 * @return array<string,mixed>|\WP_Error
	 */
	private function request( string $method, string $endpoint, array $body = [] ) {
		$url  = UPAYA_API_BASE . $endpoint;
		$args = [
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => [
				'X-API-Key'    => $this->api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
		];

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$this->logger->debug( "API {$method} {$endpoint}" );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$message = "Upaya API [{$method} {$endpoint}] HTTP error: " . $response->get_error_message();
			$this->logger->error( $message );
			return new \WP_Error( 'upaya_http_error', $message );
		}

		$status   = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$message = "Upaya API [{$method} {$endpoint}] invalid JSON (HTTP {$status}).";
			$this->logger->error( $message );
			return new \WP_Error( 'upaya_invalid_json', $message );
		}

		if ( $status < 200 || $status >= 300 ) {
			$api_msg = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$status}";
			$message = "Upaya API [{$method} {$endpoint}] error: {$api_msg}";
			$this->logger->error( $message );

			// Log the FULL response body (status + entire decoded payload) so
			// field-level validation errors (e.g. the `errors` object on an
			// HTTP 422) are visible in wc-logs, not just the top-level message.
			$this->logger->error(
				"Upaya API [{$method} {$endpoint}] HTTP {$status} response body: "
				. wp_json_encode( $decoded )
			);

			return new \WP_Error( 'upaya_api_error', $message, [ 'status' => $status, 'body' => $decoded ] );
		}

		return is_array( $decoded ) ? $decoded : [];
	}
}

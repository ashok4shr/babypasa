<?php
/**
 * REST API webhook endpoint for Upaya Cargo status push notifications.
 *
 * Upaya calls POST /wp-json/upaya-cargo/v1/webhook whenever a shipment
 * status changes.  This class registers that route and validates requests
 * before handing off to UPAYA_Webhook_Processor.
 *
 * @package Upaya_Cargo_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

class UPAYA_Webhook {

	/** @var UPAYA_Logger */
	private UPAYA_Logger $logger;

	public function __construct() {
		$this->logger = new UPAYA_Logger();
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	/**
	 * Registers the /upaya-cargo/v1/webhook REST route.
	 */
	public function register_route(): void {
		register_rest_route(
			'upaya-cargo/v1',
			'/webhook',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_request' ],
				'permission_callback' => [ $this, 'authenticate_request' ],
			]
		);
	}

	/**
	 * Returns the publicly accessible webhook URL for display in admin.
	 */
	public static function get_url(): string {
		return rest_url( 'upaya-cargo/v1/webhook' );
	}

	/**
	 * Validates the incoming request before the callback runs.
	 *
	 * Two optional security layers (either or both may be configured):
	 *  1. Secret header: X-Upaya-Webhook-Secret must match stored option.
	 *  2. Domain allowlist: request Host must be in comma-separated list.
	 *
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return true|\WP_Error
	 */
	public function authenticate_request( \WP_REST_Request $request ) {
		// ── Secret header check ────────────────────────────────────────────
		$stored_secret = get_option( 'upaya_webhook_secret', '' );
		if ( $stored_secret ) {
			$provided = $request->get_header( 'X-Upaya-Webhook-Secret' );
			if ( ! hash_equals( $stored_secret, (string) $provided ) ) {
				$this->logger->warning( 'Upaya webhook: rejected — invalid secret.' );
				return new \WP_Error(
					'upaya_webhook_unauthorized',
					__( 'Unauthorized.', 'upaya-cargo-woocommerce' ),
					[ 'status' => 401 ]
				);
			}
		}

		// ── Domain allowlist check ─────────────────────────────────────────
		// IMPORTANT: a server-to-server webhook (like Upaya's) sends NO Origin
		// header, so the sender's domain is simply not knowable here. We only
		// enforce the allowlist when an Origin/Referer host is actually present
		// (i.e. a browser-based caller). We must NOT fall back to our own
		// HTTP_HOST — doing so compared the allowlist against babypasa's own
		// domain and silently 403'd every legitimate Upaya webhook.
		// The secret-header check above is the real authentication mechanism.
		$allowed_domains_raw = get_option( 'upaya_webhook_allowed_domains', '' );
		if ( $allowed_domains_raw ) {
			$allowed = array_filter( array_map( 'trim', explode( ',', $allowed_domains_raw ) ) );

			$origin_host = strtolower( (string) wp_parse_url(
				$request->get_header( 'origin' ) ?: ( $request->get_header( 'referer' ) ?: '' ),
				PHP_URL_HOST
			) );

			if ( ! empty( $allowed ) && '' !== $origin_host ) {
				$matched = false;
				foreach ( $allowed as $domain ) {
					if ( strtolower( $domain ) === $origin_host ) {
						$matched = true;
						break;
					}
				}

				if ( ! $matched ) {
					$this->logger->warning( "Upaya webhook: rejected — origin host '{$origin_host}' not in allowlist." );
					return new \WP_Error(
						'upaya_webhook_forbidden',
						__( 'Forbidden.', 'upaya-cargo-woocommerce' ),
						[ 'status' => 403 ]
					);
				}
			}
		}

		return true;
	}

	/**
	 * Processes a validated webhook request.
	 *
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_request( \WP_REST_Request $request ) {
		// Log every hit immediately, before any validation, so we can confirm
		// Upaya is reaching the endpoint even when the body is empty/malformed.
		// Logged at INFO level so it appears regardless of the debug-mode toggle.
		$this->logger->log(
			'Upaya webhook HIT from ' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			. ' — raw body: ' . $request->get_body(),
			'info'
		);

		$payload = $request->get_json_params();

		if ( empty( $payload ) ) {
			$this->logger->warning( 'Upaya webhook: empty or non-JSON body received.' );
			return new \WP_Error(
				'upaya_webhook_bad_request',
				__( 'Empty payload.', 'upaya-cargo-woocommerce' ),
				[ 'status' => 400 ]
			);
		}

		// Required fields.
		foreach ( [ 'tracking_code', 'status', 'order_reference_id' ] as $field ) {
			if ( empty( $payload[ $field ] ) ) {
				$this->logger->warning( "Upaya webhook: missing required field '{$field}'." );
				return new \WP_Error(
					'upaya_webhook_bad_request',
					sprintf(
						/* translators: %s: field name */
						__( 'Missing required field: %s', 'upaya-cargo-woocommerce' ),
						$field
					),
					[ 'status' => 400 ]
				);
			}
		}

		$this->logger->debug( 'Upaya webhook received: ' . wp_json_encode( $payload ) );

		$processor = new UPAYA_Webhook_Processor( $this->logger );
		$result    = $processor->process( $payload );

		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Upaya webhook processing failed: ' . $result->get_error_message() );
			return new \WP_REST_Response(
				[ 'success' => false, 'message' => $result->get_error_message() ],
				200  // Always 200 so Upaya does not retry on business-logic failures.
			);
		}

		return new \WP_REST_Response( [ 'success' => true ], 200 );
	}
}

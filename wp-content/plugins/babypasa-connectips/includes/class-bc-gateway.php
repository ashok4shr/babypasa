<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

/**
 * ConnectIPS payment gateway for BabyPasa.
 *
 * Flow:
 *  1. process_payment() stores TXNID/REFERENCEID in order meta, redirects to
 *     our wc-api redirect endpoint which renders a hidden form that auto-posts
 *     to ConnectIPS.
 *  2. ConnectIPS redirects back to /connectips/payment/{success,failure}.
 *  3. Success handler validates server-to-server, then sets order → 'processing'.
 *     This fires woocommerce_order_status_processing, which triggers Upaya
 *     auto-submit with cod_amount = 0 (payment already collected).
 *  4. Failure handler marks order → 'failed' and restores the cart.
 */
class BC_Gateway extends WC_Payment_Gateway {

	/** Gateway slug — used in wc-api hooks and DB option keys. */
	const ID = 'babypasa_connectips';

	/** ConnectIPS API paths (appended to base URL). */
	const PATH_LOGIN    = '/connectipswebgw/loginpage';
	const PATH_VALIDATE = '/connectipswebws/api/creditor/validatetxn';

	/** Order meta keys. */
	const META_TXNID    = '_bc_txn_id';
	const META_REFID    = '_bc_reference_id';
	const META_RESPONSE = '_bc_response';

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	public function __construct() {
		$this->id                 = self::ID;
		$this->icon               = BC_URL . 'assets/img/connectips-logo.svg';
		$this->has_fields         = false;
		$this->method_title       = __( 'ConnectIPS', 'babypasa-connectips' );
		$this->method_description = __( 'Pay securely via ConnectIPS internet banking.', 'babypasa-connectips' );
		$this->order_button_text  = __( 'Proceed to ConnectIPS', 'babypasa-connectips' );

		$this->init_form_fields();
		$this->init_settings();

		BC_Logger::init( 'yes' === $this->get_option( 'debug_mode' ) );

		// Admin save hook — processes file uploads + encrypts secrets.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_settings_api_sanitized_fields_' . $this->id,   [ $this, 'sanitize_settings' ] );

		// generate_file_html, generate_info_html, generate_enc_password_html are
		// defined as class methods below; WooCommerce calls them directly via
		// $this->generate_{type}_html($key, $data) — no add_action needed.

		// wc-api endpoints.
		add_action( 'woocommerce_api_' . self::ID . '_redirect', [ $this, 'handle_redirect' ] );
		add_action( 'woocommerce_api_' . self::ID . '_success',  [ $this, 'handle_success' ] );
		add_action( 'woocommerce_api_' . self::ID . '_failure',  [ $this, 'handle_failure' ] );

		// Note: the redirect page outputs its own inline <script> for auto-submit.
		// No additional enqueue is needed.
	}

	// -------------------------------------------------------------------------
	// WooCommerce settings form
	// -------------------------------------------------------------------------

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled' => [
				'title'   => __( 'Enable / Disable', 'babypasa-connectips' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable ConnectIPS payment gateway', 'babypasa-connectips' ),
				'default' => 'no',
			],
			'title' => [
				'title'       => __( 'Title', 'babypasa-connectips' ),
				'type'        => 'text',
				'description' => __( 'Payment method name shown to customers at checkout.', 'babypasa-connectips' ),
				'default'     => __( 'ConnectIPS', 'babypasa-connectips' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'   => __( 'Description', 'babypasa-connectips' ),
				'type'    => 'textarea',
				'default' => __( 'Pay securely via ConnectIPS internet banking.', 'babypasa-connectips' ),
				'css'     => 'max-width:400px;',
			],
			'merchant_id' => [
				'title'       => __( 'Merchant ID', 'babypasa-connectips' ),
				'type'        => 'text',
				'description' => __( 'Provided by NCHL upon registration.', 'babypasa-connectips' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'app_id' => [
				'title'       => __( 'Application ID', 'babypasa-connectips' ),
				'type'        => 'text',
				'description' => __( 'Unique application identifier provided by NCHL.', 'babypasa-connectips' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'app_name' => [
				'title'       => __( 'Application Name', 'babypasa-connectips' ),
				'type'        => 'text',
				'description' => __( 'Application name registered with ConnectIPS.', 'babypasa-connectips' ),
				'default'     => '',
				'desc_tip'    => true,
			],
			'private_key_file' => [
				'title'       => __( 'Private Key File (CREDITOR.pfx)', 'babypasa-connectips' ),
				'type'        => 'file',
				'description' => __( 'Upload the CREDITOR.pfx digital certificate file.', 'babypasa-connectips' ),
				'desc_tip'    => true,
			],
			'passphrase' => [
				'title'       => __( 'PFX Passphrase', 'babypasa-connectips' ),
				'type'        => 'enc_password',
				'description' => __( 'Passphrase for the CREDITOR.pfx file.', 'babypasa-connectips' ),
				'desc_tip'    => true,
			],
			'auth_password' => [
				'title'       => __( 'Basic Auth Password', 'babypasa-connectips' ),
				'type'        => 'enc_password',
				'description' => __( 'Basic authentication password for transaction validation API.', 'babypasa-connectips' ),
				'desc_tip'    => true,
			],
			'enable_test_mode' => [
				'title'       => __( 'Test Mode', 'babypasa-connectips' ),
				'type'        => 'checkbox',
				'label'       => __( 'Use ConnectIPS UAT/sandbox environment', 'babypasa-connectips' ),
				'default'     => 'no',
				'description' => __( 'When enabled, all transactions go to https://uat.connectips.com.', 'babypasa-connectips' ),
			],
			'redirect_urls' => [
				'title' => __( 'Callback URLs', 'babypasa-connectips' ),
				'type'  => 'info',
			],
			'debug_mode' => [
				'title'       => __( 'Debug Logging', 'babypasa-connectips' ),
				'type'        => 'checkbox',
				'label'       => __( 'Write gateway events to WooCommerce → Status → Logs', 'babypasa-connectips' ),
				'default'     => 'no',
			],
		];
	}

	// -------------------------------------------------------------------------
	// Custom field HTML renderers
	// -------------------------------------------------------------------------

	/** Renders a file-upload row for the PFX field. */
	public function generate_file_html( string $key, array $data ): string {
		$field_key = $this->get_field_key( $key );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo wp_kses_post( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<input accept=".pfx"
					       class="input-text regular-input"
					       type="file"
					       name="<?php echo esc_attr( $field_key ); ?>"
					       id="<?php echo esc_attr( $field_key ); ?>" />
					<?php if ( ! empty( $this->get_option( $key ) ) ) : ?>
						<p><?php
							echo esc_html__( 'Current file:', 'babypasa-connectips' );
							echo ' ' . esc_html( $this->get_option( $key ) );
						?></p>
					<?php endif; ?>
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/** Renders the info row showing the callback URLs. */
	public function generate_info_html( string $key, array $data ): string {
		$success_url = $this->get_callback_url( 'success' );
		$failure_url = $this->get_callback_url( 'failure' );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo wp_kses_post( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<p><?php esc_html_e( 'Provide these URLs to the ConnectIPS integration support team.', 'babypasa-connectips' ); ?></p>
				<p><strong><?php esc_html_e( 'Success URL:', 'babypasa-connectips' ); ?></strong><br>
				<input type="text" class="input-text regular-input" value="<?php echo esc_url( $success_url ); ?>" readonly style="width:100%;max-width:480px;" /></p>
				<p><strong><?php esc_html_e( 'Failure URL:', 'babypasa-connectips' ); ?></strong><br>
				<input type="text" class="input-text regular-input" value="<?php echo esc_url( $failure_url ); ?>" readonly style="width:100%;max-width:480px;" /></p>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/** Renders a password field that shows the decrypted value so admin can review it. */
	public function generate_enc_password_html( string $key, array $data ): string {
		$field_key       = $this->get_field_key( $key );
		$decrypted_value = BC_Encryption::decrypt( (string) $this->get_option( $key ) );
		$defaults        = [
			'title'       => '',
			'disabled'    => false,
			'class'       => '',
			'css'         => '',
			'placeholder' => '',
			'desc_tip'    => false,
			'description' => '',
		];
		$data = wp_parse_args( $data, $defaults );
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?>
					<?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>"
					       type="password"
					       name="<?php echo esc_attr( $field_key ); ?>"
					       id="<?php echo esc_attr( $field_key ); ?>"
					       style="<?php echo esc_attr( $data['css'] ); ?>"
					       value="<?php echo esc_attr( $decrypted_value ); ?>"
					       placeholder="<?php echo esc_attr( $data['placeholder'] ?? '' ); ?>"
					       <?php disabled( $data['disabled'] ?? false, true ); ?> />
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Settings sanitization (encryption + PFX processing)
	// -------------------------------------------------------------------------

	/**
	 * Called by woocommerce_settings_api_sanitized_fields_{id}.
	 * Encrypts passphrase and auth_password before they reach the DB.
	 * Processes uploaded PFX: extracts PEM → encrypts → stores.
	 */
	public function sanitize_settings( array $settings ): array {
		// Encrypt passphrase if a new plaintext value was submitted.
		if ( isset( $settings['passphrase'] ) ) {
			$plain               = $settings['passphrase'];
			$settings['passphrase'] = '' !== $plain ? BC_Encryption::encrypt( $plain ) : $this->get_option( 'passphrase' );
		}

		// Encrypt auth_password if a new plaintext value was submitted.
		if ( isset( $settings['auth_password'] ) ) {
			$plain               = $settings['auth_password'];
			$settings['auth_password'] = '' !== $plain ? BC_Encryption::encrypt( $plain ) : $this->get_option( 'auth_password' );
		}

		// Process PFX upload.
		$file_field = 'woocommerce_' . $this->id . '_private_key_file';
		if ( isset( $_FILES[ $file_field ] ) && ! empty( $_FILES[ $file_field ]['size'] ) ) {
			$uploaded   = $_FILES[ $file_field ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$passphrase = BC_Encryption::decrypt( $settings['passphrase'] );

			$pem = $this->extract_pem_from_pfx( $uploaded['tmp_name'], $passphrase );
			if ( $pem ) {
				$settings['private_key']      = BC_Encryption::encrypt( $pem );
				$settings['private_key_file'] = sanitize_file_name( $uploaded['name'] );
			} else {
				$this->add_error( __( 'ConnectIPS: failed to parse PFX file — check passphrase.', 'babypasa-connectips' ) );
				$settings['private_key_file'] = $this->get_option( 'private_key_file' );
			}

			wp_delete_file( $uploaded['tmp_name'] );
		} else {
			$settings['private_key_file'] = $this->get_option( 'private_key_file' );
		}

		return $settings;
	}

	// -------------------------------------------------------------------------
	// Payment processing
	// -------------------------------------------------------------------------

	/**
	 * Called by WooCommerce when the customer submits the checkout form.
	 * Creates the order in 'pending' state, stores the generated TXNID/REFERENCEID
	 * in order meta, then redirects to our form-render endpoint.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Order not found. Please try again.', 'babypasa-connectips' ), 'error' );
			return [ 'result' => 'fail' ];
		}

		$txn_id    = $this->generate_txn_id( $order_id );
		$ref_id    = $this->generate_reference_id( $order_id );

		$order->update_meta_data( self::META_TXNID, $txn_id );
		$order->update_meta_data( self::META_REFID,  $ref_id );
		$order->update_status( 'pending', __( 'Awaiting ConnectIPS payment.', 'babypasa-connectips' ) );
		$order->save();

		BC_Logger::info( "Payment initiated for order #{$order_id}.", [
			'txn_id' => $txn_id,
			'ref_id' => $ref_id,
		] );

		$redirect = add_query_arg(
			[ 'order_key' => $order->get_order_key() ],
			$this->get_callback_url( 'redirect' )
		);

		return [
			'result'   => 'success',
			'redirect' => $redirect,
		];
	}

	// -------------------------------------------------------------------------
	// wc-api endpoint handlers
	// -------------------------------------------------------------------------

	/**
	 * Renders the hidden form that auto-posts to ConnectIPS.
	 * Accessed via: /?wc-api=babypasa_connectips_redirect&order_key=wc_order_xxx
	 */
	public function handle_redirect(): void {
		$order_key = isset( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '';
		$order_id  = wc_get_order_id_by_order_key( $order_key );
		$order     = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order ) {
			BC_Logger::error( 'Redirect handler: order not found.', [ 'order_key' => $order_key ] );
			wp_die( esc_html__( 'Order not found.', 'babypasa-connectips' ) );
		}

		// Prevent re-entry on already-paid orders.
		if ( $order->is_paid() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$txn_id = $order->get_meta( self::META_TXNID );
		$ref_id = $order->get_meta( self::META_REFID );

		if ( ! $txn_id || ! $ref_id ) {
			BC_Logger::error( "Redirect handler: missing TXNID/REFID for order #{$order->get_id()}." );
			wp_die( esc_html__( 'Payment data missing. Please return to checkout and try again.', 'babypasa-connectips' ) );
		}

		$txn_date   = gmdate( 'd-m-Y' );
		$txn_amt    = (int) round( $order->get_total() * 100 ); // paisa
		$remarks    = sprintf( 'Order #%s', $order->get_order_number() );
		$particulars = $this->build_particulars( $order );

		$token_string = $this->build_token_string( [
			'merchantId'  => $this->get_option( 'merchant_id' ),
			'appId'       => $this->get_option( 'app_id' ),
			'appName'     => $this->get_option( 'app_name' ),
			'txnId'       => $txn_id,
			'txnDate'     => $txn_date,
			'txnCrncy'    => 'NPR',
			'txnAmt'      => $txn_amt,
			'referenceId' => $ref_id,
			'remarks'     => $remarks,
			'particulars' => $particulars,
		], 'payment' );

		$token = $this->sign( $token_string );

		if ( null === $token ) {
			BC_Logger::error( "Redirect handler: token generation failed for order #{$order->get_id()}." );
			wc_add_notice( __( 'Unable to initiate payment. Please try again.', 'babypasa-connectips' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$action_url = $this->api_base() . self::PATH_LOGIN;

		BC_Logger::info( "Rendering ConnectIPS form for order #{$order->get_id()}.", [
			'txn_id' => $txn_id,
			'amount' => $txn_amt,
		] );

		// Output a minimal HTML page with a hidden form that auto-submits.
		// wp_die() is intentionally not used — we need to control the full page.
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php esc_html_e( 'Redirecting to ConnectIPS…', 'babypasa-connectips' ); ?></title>
			<style>
				body { font-family: sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; background:#f7f7f8; }
				.bc-redirect-box { text-align:center; }
				.bc-spinner { width:40px; height:40px; border:4px solid #ddd; border-top-color:#FF2A61; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 16px; }
				@keyframes spin { to { transform:rotate(360deg); } }
				p { color:#555; }
			</style>
		</head>
		<body>
			<div class="bc-redirect-box">
				<div class="bc-spinner"></div>
				<p><?php esc_html_e( 'Redirecting to ConnectIPS. Please wait…', 'babypasa-connectips' ); ?></p>
			</div>
			<form id="bc-connectips-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
				<input type="hidden" name="MERCHANTID"  value="<?php echo esc_attr( $this->get_option( 'merchant_id' ) ); ?>">
				<input type="hidden" name="APPID"       value="<?php echo esc_attr( $this->get_option( 'app_id' ) ); ?>">
				<input type="hidden" name="APPNAME"     value="<?php echo esc_attr( $this->get_option( 'app_name' ) ); ?>">
				<input type="hidden" name="TXNID"       value="<?php echo esc_attr( $txn_id ); ?>">
				<input type="hidden" name="TXNDATE"     value="<?php echo esc_attr( $txn_date ); ?>">
				<input type="hidden" name="TXNCRNCY"    value="NPR">
				<input type="hidden" name="TXNAMT"      value="<?php echo esc_attr( $txn_amt ); ?>">
				<input type="hidden" name="REFERENCEID" value="<?php echo esc_attr( $ref_id ); ?>">
				<input type="hidden" name="REMARKS"     value="<?php echo esc_attr( $remarks ); ?>">
				<input type="hidden" name="PARTICULARS" value="<?php echo esc_attr( $particulars ); ?>">
				<input type="hidden" name="TOKEN"       value="<?php echo esc_attr( $token ); ?>">
			</form>
			<script>document.getElementById('bc-connectips-form').submit();</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Success callback from ConnectIPS.
	 * URL: /connectips/payment/success?TXNID=xxx
	 *
	 * Validates the payment server-to-server, then:
	 *  - SUCCESS → order→'processing' (triggers Upaya with cod_amount=0)
	 *  - FAILED  → order→'failed', cart restored
	 */
	public function handle_success(): void {
		$txn_id = isset( $_REQUEST['TXNID'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['TXNID'] ) ) : '';

		BC_Logger::info( 'Success callback received.', [
			'txn_id' => $txn_id,
			'params' => array_map( 'sanitize_text_field', $_REQUEST ), // phpcs:ignore WordPress.Security.NonceVerification
		] );

		if ( ! $txn_id ) {
			BC_Logger::error( 'Success callback: TXNID missing.' );
			wc_add_notice( __( 'Payment verification failed: no transaction ID.', 'babypasa-connectips' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$order = $this->find_order_by_txn_id( $txn_id );

		if ( ! $order ) {
			BC_Logger::error( "Success callback: no order found for TXNID {$txn_id}." );
			wc_add_notice( __( 'Payment received but order not found. Please contact support.', 'babypasa-connectips' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Guard: already processed (e.g. duplicate callback).
		if ( $order->has_status( [ 'processing', 'completed' ] ) ) {
			BC_Logger::info( "Success callback: order #{$order->get_id()} already processed, skipping validation." );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		$validation = $this->validate_payment( $order );

		if ( $validation['status'] ) {
			$order->update_meta_data( self::META_RESPONSE, wp_json_encode( $validation['raw'] ) );
			$order->payment_complete( $txn_id );
			// payment_complete() sets order to 'processing', which fires
			// woocommerce_order_status_processing → Upaya auto-submits with cod_amount=0.
			$order->add_order_note(
				/* translators: %s = TXNID */
				sprintf( __( 'Payment confirmed via ConnectIPS. TXNID: %s', 'babypasa-connectips' ), $txn_id )
			);
			$order->save();

			BC_Logger::info( "Order #{$order->get_id()} payment confirmed.", [ 'txn_id' => $txn_id ] );
			wc_add_notice( __( 'Your payment was successful. Thank you!', 'babypasa-connectips' ) );
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		} else {
			$order->update_meta_data( self::META_RESPONSE, wp_json_encode( $validation['raw'] ) );
			$order->update_status( 'failed', $validation['message'] );
			$order->save();

			$this->restore_cart( $order );

			BC_Logger::error( "Order #{$order->get_id()} payment validation failed.", [
				'txn_id'  => $txn_id,
				'message' => $validation['message'],
			] );
			wc_add_notice( __( 'Payment verification failed. Please try again.', 'babypasa-connectips' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Failure callback from ConnectIPS.
	 * URL: /connectips/payment/failure?TXNID=xxx
	 */
	public function handle_failure(): void {
		$txn_id = isset( $_REQUEST['TXNID'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['TXNID'] ) ) : '';

		BC_Logger::info( 'Failure callback received.', [ 'txn_id' => $txn_id ] );

		if ( $txn_id ) {
			$order = $this->find_order_by_txn_id( $txn_id );

			if ( $order && ! $order->has_status( [ 'processing', 'completed', 'cancelled' ] ) ) {
				// Determine if this was a user cancel or a payment failure.
				$referer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$is_from_connectips = str_contains( $referer, 'connectips.com' );

				if ( $is_from_connectips ) {
					$order->update_status( 'failed', __( 'Payment failed at ConnectIPS.', 'babypasa-connectips' ) );
				} else {
					$order->update_status( 'cancelled', __( 'Payment cancelled by customer.', 'babypasa-connectips' ) );
				}
				$order->save();
				$this->restore_cart( $order );
			}
		}

		wc_add_notice( __( 'Payment was unsuccessful. Please try again or choose a different payment method.', 'babypasa-connectips' ), 'error' );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	// -------------------------------------------------------------------------
	// Payment validation (server-to-server)
	// -------------------------------------------------------------------------

	/**
	 * Calls ConnectIPS validatetxn endpoint with Basic Auth + signed token.
	 *
	 * @return array{ status: bool, message: string, raw: array }
	 */
	private function validate_payment( WC_Order $order ): array {
		$merchant_id = $this->get_option( 'merchant_id' );
		$app_id      = $this->get_option( 'app_id' );
		$txn_id      = (string) $order->get_meta( self::META_TXNID );
		$txn_amt     = (int) round( $order->get_total() * 100 );

		$token_string = $this->build_token_string( [
			'merchantId'  => $merchant_id,
			'appId'       => $app_id,
			'referenceId' => $txn_id,
			'txnAmt'      => $txn_amt,
		], 'validation' );

		$token = $this->sign( $token_string );
		if ( null === $token ) {
			return [
				'status'  => false,
				'message' => __( 'Token generation failed.', 'babypasa-connectips' ),
				'raw'     => [],
			];
		}

		$auth_password = BC_Encryption::decrypt( (string) $this->get_option( 'auth_password' ) );
		$credentials   = base64_encode( $app_id . ':' . $auth_password );

		$payload = [
			'merchantId'  => (int) $merchant_id,
			'appId'       => $app_id,
			'referenceId' => $txn_id,
			'txnAmt'      => $txn_amt,
			'token'       => $token,
		];

		BC_Logger::debug( 'Validating transaction.', [
			'url'     => $this->api_base() . self::PATH_VALIDATE,
			'payload' => $payload,
		] );

		$response = wp_remote_post(
			$this->api_base() . self::PATH_VALIDATE,
			[
				'body'      => wp_json_encode( $payload ),
				'headers'   => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $credentials,
				],
				'timeout'   => 20,
				'sslverify' => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			BC_Logger::error( 'Validation HTTP error: ' . $response->get_error_message() );
			return [
				'status'  => false,
				'message' => $response->get_error_message(),
				'raw'     => [],
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		BC_Logger::debug( 'Validation response.', [ 'code' => $code, 'body' => $body ] );

		if ( 401 === $code ) {
			return [
				'status'  => false,
				'message' => __( 'Unauthorized — check Basic Auth password.', 'babypasa-connectips' ),
				'raw'     => $body ?? [],
			];
		}

		if ( 200 !== $code || ! is_array( $body ) ) {
			return [
				'status'  => false,
				'message' => __( 'Unexpected response from ConnectIPS. Please contact support.', 'babypasa-connectips' ),
				'raw'     => $body ?? [],
			];
		}

		$success = isset( $body['status'] ) && 'SUCCESS' === strtoupper( (string) $body['status'] );

		return [
			'status'  => $success,
			'message' => $body['statusDesc'] ?? ( $success ? 'SUCCESS' : 'FAILED' ),
			'raw'     => $body,
		];
	}

	// -------------------------------------------------------------------------
	// Token / signature generation
	// -------------------------------------------------------------------------

	/**
	 * Builds the canonical string that ConnectIPS requires to be signed.
	 *
	 * @param array  $data   Associative array of transaction data.
	 * @param string $action 'payment' | 'validation'
	 */
	private function build_token_string( array $data, string $action ): string {
		switch ( $action ) {
			case 'payment':
				return sprintf(
					'MERCHANTID=%s,APPID=%s,APPNAME=%s,TXNID=%s,TXNDATE=%s,TXNCRNCY=%s,TXNAMT=%s,REFERENCEID=%s,REMARKS=%s,PARTICULARS=%s,TOKEN=TOKEN',
					trim( $data['merchantId'] ),
					trim( $data['appId'] ),
					trim( $data['appName'] ),
					trim( $data['txnId'] ),
					trim( $data['txnDate'] ),
					trim( $data['txnCrncy'] ),
					trim( (string) $data['txnAmt'] ),
					trim( $data['referenceId'] ),
					trim( $data['remarks'] ),
					trim( $data['particulars'] )
				);

			case 'validation':
				return sprintf(
					'MERCHANTID=%s,APPID=%s,REFERENCEID=%s,TXNAMT=%s',
					trim( $data['merchantId'] ),
					trim( $data['appId'] ),
					trim( $data['referenceId'] ),
					trim( (string) $data['txnAmt'] )
				);

			default:
				return '';
		}
	}

	/**
	 * Signs $message with the merchant's RSA private key (SHA-256) and returns
	 * a base64-encoded signature, or null on failure.
	 */
	private function sign( string $message ): ?string {
		$pem = BC_Encryption::decrypt( (string) $this->get_option( 'private_key' ) );

		if ( '' === $pem ) {
			BC_Logger::error( 'Private key not configured or could not be decrypted.' );
			return null;
		}

		$signature = '';
		$result    = openssl_sign( $message, $signature, $pem, OPENSSL_ALGO_SHA256 );

		if ( ! $result ) {
			BC_Logger::error( 'openssl_sign failed: ' . openssl_error_string() );
			return null;
		}

		return base64_encode( $signature );
	}

	// -------------------------------------------------------------------------
	// PFX parsing
	// -------------------------------------------------------------------------

	/** Extracts the PEM private key from a PKCS12 (.pfx) file. Returns empty string on failure. */
	private function extract_pem_from_pfx( string $pfx_path, string $passphrase ): string {
		$pfx_content = file_get_contents( $pfx_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $pfx_content ) {
			BC_Logger::error( "Cannot read PFX file: {$pfx_path}" );
			return '';
		}

		$certs = [];
		if ( openssl_pkcs12_read( $pfx_content, $certs, $passphrase ) && ! empty( $certs['pkey'] ) ) {
			return $certs['pkey'];
		}

		// Fallback: try openssl CLI for legacy PFX formats.
		$tmp = tempnam( sys_get_temp_dir(), 'bc_pem_' );
		$cmd = sprintf(
			'openssl pkcs12 -in %s -nocerts -legacy -nodes -out %s -passin pass:%s 2>&1',
			escapeshellarg( $pfx_path ),
			escapeshellarg( $tmp ),
			escapeshellarg( $passphrase )
		);
		exec( $cmd, $output, $exit_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions

		$pem = '';
		if ( 0 === $exit_code && file_exists( $tmp ) ) {
			$pem = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			wp_delete_file( $tmp );
			if ( false !== strpos( $pem, 'PRIVATE KEY' ) ) {
				return $pem;
			}
		}

		wp_delete_file( $tmp );
		BC_Logger::error( 'Failed to extract PEM from PFX using both methods.' );
		return '';
	}

	// -------------------------------------------------------------------------
	// Transaction ID generation
	// -------------------------------------------------------------------------

	/**
	 * Generates a unique TXNID in BPE-{YYMMDD}{HASH8} format (18 chars max).
	 * ConnectIPS requires TXNID to be unique per transaction.
	 */
	private function generate_txn_id( int $order_id ): string {
		$date = gmdate( 'ymd' );
		$hash = substr( hash( 'sha256', $order_id . microtime( true ) . wp_rand() ), 0, 8 );
		return 'BPE-' . $date . strtoupper( $hash );
	}

	/**
	 * Generates a REFERENCEID in BPE-{YYMMDD}-{ORDER4}{SEQ2} format.
	 * Used internally to correlate ConnectIPS responses with orders.
	 */
	private function generate_reference_id( int $order_id ): string {
		$date      = gmdate( 'ymd' );
		$order_seg = str_pad( substr( (string) $order_id, -4 ), 4, '0', STR_PAD_LEFT );
		$seq       = str_pad( (string) ( (int) microtime( true ) % 100 ), 2, '0', STR_PAD_LEFT );
		return 'BPE-' . $date . '-' . $order_seg . $seq;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** Returns the ConnectIPS base URL (UAT or production). */
	private function api_base(): string {
		return 'yes' === $this->get_option( 'enable_test_mode' )
			? 'https://uat.connectips.com'
			: 'https://login.connectips.com';
	}

	/** Returns the pretty-path callback URL for the given endpoint suffix. */
	private function get_callback_url( string $type ): string {
		return home_url( '/connectips/payment/' . $type );
	}

	/**
	 * Finds the WC_Order whose _bc_txn_id meta matches the given TXNID.
	 * Searches recent orders to keep queries bounded.
	 */
	private function find_order_by_txn_id( string $txn_id ): ?WC_Order {
		$orders = wc_get_orders( [
			'limit'      => 1,
			'meta_key'   => self::META_TXNID, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => $txn_id,          // phpcs:ignore WordPress.DB.SlowDBQuery
		] );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Builds a product particulars string (≤200 chars) for the ConnectIPS form.
	 * No HTML allowed in this field — ConnectIPS rejects tokens with HTML.
	 */
	private function build_particulars( WC_Order $order ): string {
		$parts = [];
		foreach ( $order->get_items() as $item ) {
			$parts[] = $item->get_name() . ' x' . $item->get_quantity();
		}
		$particulars = implode( ', ', $parts );
		return mb_substr( $particulars, 0, 200 );
	}

	/**
	 * Restores the cart from a failed/cancelled order so the customer
	 * can return to checkout with their items still in the cart.
	 */
	private function restore_cart( WC_Order $order ): void {
		if ( ! WC()->cart ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			WC()->cart->add_to_cart(
				$item->get_product_id(),
				$item->get_quantity(),
				$item->get_variation_id()
			);
		}
	}


}

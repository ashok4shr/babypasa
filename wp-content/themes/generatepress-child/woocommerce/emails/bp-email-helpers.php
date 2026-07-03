<?php
/**
 * Shared email helper functions — BabyPasa client design.
 *
 * Single source of truth for the gateway-aware refund labels/notes (used by
 * E15 cancelled, E21 refunded, and the ready-to-wire E17/E20 return flow) and
 * the dynamic payment-tips box (E04 payment failed).
 *
 * Loaded via `require_once get_stylesheet_directory() . '/woocommerce/emails/bp-email-helpers.php';`
 * from the templates that need them. All functions are function_exists-guarded
 * so multiple require_once calls (or a stale inline copy) never fatal.
 *
 * @package GeneratePress_Child\WooCommerce\Emails
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'bp_email_refund_label' ) ) {
	/**
	 * Map an order's payment gateway to the client-facing refund method label.
	 *
	 * @param WC_Order $order Order object.
	 * @return string Refund method label.
	 */
	function bp_email_refund_label( $order ) {
		$gateway = (string) $order->get_payment_method();

		if ( false !== stripos( $gateway, 'connectips' ) ) {
			return 'Bank Transfer';
		}
		if ( false !== stripos( $gateway, 'esewa' ) ) {
			return 'eSewa Wallet';
		}
		if ( false !== stripos( $gateway, 'khalti' ) ) {
			return 'Khalti Wallet';
		}
		if ( false !== stripos( $gateway, 'cod' ) ) {
			return 'Cash on Delivery';
		}

		return $order->get_payment_method_title();
	}
}

if ( ! function_exists( 'bp_email_refund_note' ) ) {
	/**
	 * Gateway-specific refund note shown under the refund rows (empty = no note).
	 *
	 * @param WC_Order $order Order object.
	 * @return string Refund note, or empty string when none applies.
	 */
	function bp_email_refund_note( $order ) {
		$gateway = (string) $order->get_payment_method();

		if ( false !== stripos( $gateway, 'connectips' ) ) {
			return 'The refund will be credited to the bank account linked to your ConnectIPS transaction.';
		}
		if ( false !== stripos( $gateway, 'esewa' ) ) {
			return 'The refund will be credited to your eSewa wallet within 3–5 business days.';
		}
		if ( false !== stripos( $gateway, 'khalti' ) ) {
			return 'The refund will be credited to your Khalti wallet within 3–5 business days.';
		}

		return '';
	}
}

if ( ! function_exists( 'bp_email_payment_tips' ) ) {
	/**
	 * Build the gateway-specific payment-troubleshooting box for the E04
	 * payment-failed email (client design). Returns the full pink box HTML.
	 *
	 * Add a new gateway by adding one entry to $gateways — keyed by a substring
	 * matched against the order's payment-method slug (case-insensitive). No
	 * template change required.
	 *
	 * @param WC_Order $order Order object.
	 * @return string Tips box HTML (safe to echo).
	 */
	function bp_email_payment_tips( $order ) {
		$gateway = strtolower( (string) $order->get_payment_method() );

		$gateways = array(
			'connectips' => array(
				'label' => 'Having trouble with ConnectIPS?',
				'tips'  => array(
					'Make sure your bank account is linked to ConnectIPS and has sufficient balance.',
					'Check your daily transaction limit with your bank — it may need to be increased.',
					'Try clearing your browser cache or use a different browser and retry.',
					"Still not working? Contact us and we'll help you complete your order.",
				),
			),
			'esewa'      => array(
				'label' => 'Having trouble with eSewa?',
				'tips'  => array(
					'Check your eSewa wallet balance and top up if needed.',
					"Make sure you're entering the correct eSewa PIN.",
					'Try logging out of eSewa and back in, then retry.',
					"Still not working? Contact us and we'll help you complete your order.",
				),
			),
			'khalti'     => array(
				'label' => 'Having trouble with Khalti?',
				'tips'  => array(
					'Check your Khalti wallet balance and top up if needed.',
					'Ensure your Khalti account is verified (KYC complete).',
					'Try the Khalti app directly if the browser flow fails.',
					"Still not working? Contact us and we'll help you complete your order.",
				),
			),
			'bacs'       => array(
				'label' => 'Completing your bank transfer',
				'tips'  => array(
					'Use the account details provided to transfer the exact order amount.',
					'Include your order number as the payment reference.',
					'Transfers may take 1–2 business days to reflect.',
					"Contact us once transferred and we'll confirm your order.",
				),
			),
		);

		$default = array(
			'label' => 'Having trouble with your payment?',
			'tips'  => array(
				'Check your payment method has sufficient balance.',
				'Try a different browser or clear your cache.',
				'Use a different payment method if available.',
				"Contact us and we'll help you complete your order.",
			),
		);

		$config = $default;
		foreach ( $gateways as $slug => $cfg ) {
			if ( false !== strpos( $gateway, $slug ) ) {
				$config = $cfg;
				break;
			}
		}

		// Numbered tip rows.
		$rows = '';
		foreach ( $config['tips'] as $i => $tip ) {
			$num   = $i + 1;
			$rows .= '<tr>'
				. '<td style="padding:5px 0;vertical-align:top;width:24px;">'
				. '<table border="0" cellpadding="0" cellspacing="0" role="presentation"><tr>'
				. '<td width="18" height="18" align="center" valign="middle" style="width:18px;min-width:18px;height:18px;background:#ec4899;border-radius:9px;text-align:center;vertical-align:middle;">'
				. '<span style="font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:700;color:#ffffff;line-height:1;">' . esc_html( $num ) . '</span>'
				. '</td></tr></table>'
				. '</td>'
				. '<td style="padding:5px 0 5px 10px;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#be185d;line-height:1.5;">'
				. esc_html( $tip )
				. '</td>'
				. '</tr>';
		}

		// Pink box: header (bank icon + label) + numbered tips.
		return '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="background:#fce7f3;border-radius:8px;border:1px solid #fbcfe8;margin:0 0 4px;">'
			. '<tr><td style="padding:16px;">'
			. '<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:12px;"><tr>'
			. '<td style="vertical-align:middle;padding-right:8px;">'
			. '<table border="0" cellpadding="0" cellspacing="0" role="presentation"><tr>'
			. '<td width="28" height="28" align="center" valign="middle" style="width:28px;min-width:28px;height:28px;background:#ec4899;border-radius:6px;text-align:center;vertical-align:middle;">'
			// PNG (Gmail/Outlook strip inline SVG); absolute URL, white line-icon.
			. '<img src="' . esc_url( get_stylesheet_directory_uri() . '/assets/images/email-icons/bank.png' ) . '" width="16" height="16" alt="" style="display:inline-block;vertical-align:middle;border:0;" />'
			. '</td></tr></table>'
			. '</td>'
			. '<td style="vertical-align:middle;">'
			. '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:700;color:#9d174d;">' . esc_html( $config['label'] ) . '</p>'
			. '</td></tr></table>'
			. '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">' . $rows . '</table>'
			. '</td></tr></table>';
	}
}

if ( ! function_exists( 'bp_email_payment_badge' ) ) {
	/**
	 * Resolve the payment-status badge shown in the order emails.
	 *
	 * Single source of truth so the processing (E03) and Upaya delivery emails
	 * agree. Driven by the admin "Payment Status" box (_bp_payment_status), NOT by
	 * $order->get_date_paid(): WooCommerce sets date_paid on ANY transition into a
	 * paid status (processing/completed) regardless of gateway, so COD orders read
	 * as "Paid" — the exact bug this replaces.
	 *
	 * Resolution:
	 *   - Admin-set box present → fully_paid = Paid, partial = Partially Paid, else Unpaid.
	 *   - Otherwise (front-end orders) → a real online capture sets a transaction id
	 *     (e.g. ConnectIPS payment_complete()); COD never does → Paid vs Unpaid.
	 *
	 * @param WC_Order $order Order object.
	 * @return array{label:string,bg:string,color:string} Badge label + colours.
	 */
	function bp_email_payment_badge( $order ) {
		$paid    = array( 'label' => __( 'Paid', 'generatepress-child' ), 'bg' => '#dcfce7', 'color' => '#15803d' );
		$partial = array( 'label' => __( 'Partially Paid', 'generatepress-child' ), 'bg' => '#fef3c7', 'color' => '#b45309' );
		$unpaid  = array( 'label' => __( 'Unpaid', 'generatepress-child' ), 'bg' => '#fef9c3', 'color' => '#854d0e' );

		if ( ! $order instanceof WC_Order ) {
			return $unpaid;
		}

		$status = (string) $order->get_meta( '_bp_payment_status' );
		if ( '' !== $status ) {
			if ( 'fully_paid' === $status ) {
				return $paid;
			}
			if ( 'partial' === $status ) {
				return $partial;
			}
			return $unpaid; // not_paid.
		}

		// Front-end order (no admin box): trust a real online-gateway capture only.
		return $order->get_transaction_id() ? $paid : $unpaid;
	}
}

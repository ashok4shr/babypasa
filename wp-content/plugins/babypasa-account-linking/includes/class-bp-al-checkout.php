<?php
/**
 * Forward-looking hook: links a new guest order to an existing account the
 * moment checkout completes.
 *
 * Hooked to woocommerce_checkout_order_processed rather than
 * woocommerce_checkout_create_order: it fires just after $order->save(), so
 * the order already has an ID and add_order_note() works correctly. This
 * costs one extra save() versus mutating the order before its first insert,
 * traded deliberately for note-writing correctness.
 *
 * NOTE: this hook does not fire for orders created via the Store API/Checkout
 * Block (that path fires woocommerce_store_api_checkout_order_processed
 * instead). This site only uses the classic [woocommerce_checkout] shortcode
 * today (confirmed in the Phase 1 audit) — if a Checkout block is ever added,
 * this hook must be duplicated onto that action too.
 *
 * @package BabyPasa_Account_Linking
 */

defined( 'ABSPATH' ) || exit;

class BP_AL_Checkout {

	public function __construct() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_link' ), 20, 3 );
	}

	/**
	 * @param int                $order_id    Order id.
	 * @param array<string,mixed> $posted_data Posted checkout data (unused).
	 * @param WC_Order|null      $order       Order object, when supplied by the hook.
	 */
	public function maybe_link( $order_id, $posted_data, $order ): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Defensive: a linking failure must never break checkout completion.
		try {
			$result = BP_AL_Linker::link( $order, BP_AL_Linker::SOURCE_CHECKOUT );

			if ( BP_AL_Linker::RESULT_LINKED === $result ) {
				$note = BP_AL_Linker::note_for_result( $result, $order, BP_AL_Linker::SOURCE_CHECKOUT );
				if ( $note ) {
					$order->add_order_note( $note );
				}
				$order->save();
			}
		} catch ( \Throwable $e ) {
			wc_get_logger()->error(
				sprintf( 'Account-linking exception for order #%d: %s', $order_id, $e->getMessage() ),
				array( 'source' => 'bp-account-linking' )
			);
			// Order proceeds unlinked; nothing further to do.
		}
	}
}

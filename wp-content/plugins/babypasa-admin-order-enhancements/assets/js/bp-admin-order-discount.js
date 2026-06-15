/**
 * Manual order discount — admin order-items meta box.
 *
 * Wires the "Discount" button (rendered beside Refund) to a prompt + AJAX call
 * that stores the fixed discount amount on the order and reloads so the totals
 * summary (and the "Discount" line below Shipping) reflect the new total.
 *
 * Mirrors the UX of WooCommerce's own "Apply coupon" button (window.prompt).
 * Localised as `bpAoeDiscount`. Order ID is read from WooCommerce's own
 * `woocommerce_admin_meta_boxes.post_id` so it is correct for both the
 * auto-draft new-order screen and existing orders.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '#woocommerce-order-items' ).on( 'click', '.bp-aoe-add-discount', function ( e ) {
			e.preventDefault();

			var $btn    = $( this );
			var current = $btn.data( 'current' );
			current     = ( current === undefined || current === null ) ? '' : String( current );

			var input = window.prompt( bpAoeDiscount.i18n.prompt, current );
			if ( null === input ) {
				return; // cancelled
			}

			input = $.trim( input );
			var amount = '' === input ? 0 : parseFloat( input );

			if ( isNaN( amount ) || amount < 0 ) {
				window.alert( bpAoeDiscount.i18n.invalid );
				return;
			}

			var orderId = ( window.woocommerce_admin_meta_boxes && woocommerce_admin_meta_boxes.post_id )
				? woocommerce_admin_meta_boxes.post_id
				: 0;

			if ( ! orderId ) {
				window.alert( bpAoeDiscount.i18n.error );
				return;
			}

			var $items = $( '#woocommerce-order-items' );
			if ( $items.find( '.inside' ).block ) {
				$items.find( '.inside' ).block( {
					message: null,
					overlayCSS: { background: '#fff', opacity: 0.6 }
				} );
			}

			$.post( bpAoeDiscount.ajaxUrl, {
				action:   'bp_aoe_set_order_discount',
				security: bpAoeDiscount.nonce,
				order_id: orderId,
				amount:   amount
			} ).done( function () {
				// Reload ONLY the order-items panel — exactly how WooCommerce's own
				// coupon/fee buttons refresh the totals. This swaps just the
				// #woocommerce-order-items .inside markup (which clears our block
				// overlay) and leaves the rest of the page untouched, so unsaved
				// state elsewhere — the delivery-area form, added products, etc. — is
				// preserved. A full page reload would discard all of that.
				if ( window.woocommerce_admin_meta_boxes && woocommerce_admin_meta_boxes.order_item_nonce ) {
					$items.trigger( 'wc_order_items_reload' );
				} else {
					window.location.reload();
				}
			} ).fail( function () {
				if ( $items.find( '.inside' ).unblock ) {
					$items.find( '.inside' ).unblock();
				}
				window.alert( bpAoeDiscount.i18n.error );
			} );
		} );
	} );
} )( jQuery );

/* global bpAoe, jQuery */
( function ( $ ) {
	'use strict';

	// ── Selectors ────────────────────────────────────────────────────────────
	var SEL_HUB_AREA       = '#bp_billing_hub_area';
	var SEL_RATE_ROW       = '#bp-aoe-shipping-rate-row';
	var SEL_RATE_LABEL     = '#bp-aoe-rate-label';
	var SEL_SHIP_TOGGLE    = '#bp_ship_different';
	var SEL_SHIP_SECTION   = '#bp-aoe-shipping-address';
	var SEL_PAYMENT_RADIOS = 'input[name="_bp_payment_status"]';
	var SEL_AMOUNT_ROW     = '#bp-aoe-amount-paid-row';
	var SEL_AMOUNT_INPUT   = '#bp_amount_paid';

	// WooCommerce admin default billing/shipping UI elements to suppress.
	var SEL_WC_BILLING_ADDR    = '.order_data_column .billing-same-as-shipping, .order_data_column.billing > p.address, .order_data_column.billing > a.edit_address, .order_data_column.billing .edit_address';
	var SEL_WC_SHIPPING_ADDR   = '.order_data_column.shipping > p.address, .order_data_column.shipping > a.edit_address, .order_data_column.shipping .edit_address';
	var SEL_WC_BILLING_HEADER  = '.order_data_column h3.billing_address';
	var SEL_WC_SHIPPING_HEADER = '.order_data_column h3.shipping_address';

	var calcTimer    = null;
	var cachedRate   = null;
	var DEBOUNCE_MS  = 600;

	// Order-items recalc state.
	var recalcTimer          = null;
	var suppressItemsRecalc  = false; // true while WE reload the items box (avoid self-trigger).
	var lastItemsSignature   = null;  // product-line signature; changes only on real item edits.

	// ── Init ─────────────────────────────────────────────────────────────────
	$( document ).ready( function () {
		defaultStatusToProcessing();
		suppressDefaultAddressUI();
		bindAddressEvents();
		bindPaymentStatusEvents();
		bindCustomerSelect();
		bindOrderItemsRecalc();

		// If area already selected on page load (editing existing order), show rate.
		var $hubArea = $( SEL_HUB_AREA );
		if ( $hubArea.length && $hubArea.val() ) {
			fetchRate( $hubArea.val() );
		}
	} );

	// ── Default new manual orders to "Processing" ─────────────────────────────
	// This script only loads on the new-order creation screen (enqueue-scoped),
	// so we preselect Processing in the Order status dropdown. Guarded to the WC
	// default (wc-pending) so a deliberately chosen status is never overridden;
	// the admin can still switch it back before clicking Create. A new order's
	// baseline status is 'pending', so saving as Processing fires the normal
	// pending→processing transition (customer email + Upaya submission).
	function defaultStatusToProcessing() {
		var $status = $( '#order_status' );
		if ( ! $status.length || $status.val() !== 'wc-pending' ) {
			return;
		}
		// trigger('change') updates the select2 display WooCommerce renders.
		$status.val( 'wc-processing' ).trigger( 'change' );
	}

	// ── Pre-fill delivery form when a customer is selected ────────────────────
	function bindCustomerSelect() {
		$( document ).on( 'change', '#customer_user', function () {
			var userId = parseInt( $( this ).val(), 10 ) || 0;
			if ( ! userId ) {
				return;
			}

			var nonce = ( typeof woocommerce_admin_meta_boxes !== 'undefined' )
				? woocommerce_admin_meta_boxes.get_customer_details_nonce
				: '';

			$.ajax( {
				url:    bpAoe.ajax_url,
				method: 'POST',
				data:   {
					action:   'woocommerce_get_customer_details',
					user_id:  userId,
					security: nonce,
				},
				success: function ( res ) {
					if ( ! res ) {
						return;
					}

					var b  = res.billing || {};
					var bp = res.bp_aoe  || {};

					$( 'input[name="bp_address[first_name]"]' ).val( b.first_name || '' );
					$( 'input[name="bp_address[last_name]"]'  ).val( b.last_name  || '' );
					$( 'input[name="bp_address[phone]"]'      ).val( b.phone      || '' );
					$( 'input[name="bp_address[email]"]'      ).val( b.email      || '' );
					$( 'input[name="bp_address[address_1]"]'  ).val( b.address_1  || '' );
					$( 'input[name="bp_address[address_2]"]'  ).val( b.address_2  || '' );
					$( 'input[name="bp_address[alt_phone]"]'  ).val( bp.alt_phone || '' );
					$( 'input[name="bp_address[landmark]"]'   ).val( bp.landmark  || '' );

					// Prefer the server-built hub||area; fall back to state||city.
					var hubArea = bp.hub_area || '';
					if ( ! hubArea && b.state && b.city ) {
						hubArea = b.state + '||' + b.city;
					}

					// Set the area select (select2) and fire change so the rate
					// auto-calculates, but only if the value matches a real option.
					var $hubArea = $( SEL_HUB_AREA );
					if ( hubArea && $hubArea.find( 'option[value="' + hubArea.replace( /"/g, '\\"' ) + '"]' ).length ) {
						$hubArea.val( hubArea ).trigger( 'change' );
					}
				},
			} );
		} );
	}

	// ── Hide default WC billing/shipping address UI ───────────────────────────
	function suppressDefaultAddressUI() {
		// We keep the column headers but hide the address display and pencil-edit links.
		// Our form (injected via PHP hook) is shown instead.
		$( '.order_data_column' ).each( function () {
			var $col = $( this );
			// Hide the "No billing address set." / address text and "Edit" pencil link.
			$col.find( '.address' ).hide();
			$col.find( 'a.edit_address' ).hide();
			// Hide the hidden edit form WC reveals on pencil click.
			$col.find( '.edit_address' ).hide();
		} );
	}

	// ── Address form events ───────────────────────────────────────────────────
	function bindAddressEvents() {
		// Auto-calculate shipping on delivery area change (debounced).
		$( document ).on( 'change', SEL_HUB_AREA, function () {
			var val = $( this ).val();
			clearTimeout( calcTimer );
			if ( ! val ) {
				$( SEL_RATE_ROW ).hide();
				cachedRate = null;
				return;
			}
			$( SEL_RATE_ROW ).show();
			$( SEL_RATE_LABEL ).text( bpAoe.i18n.calculating );
			cachedRate = null;
			calcTimer = setTimeout( function () {
				fetchRate( val );
			}, DEBOUNCE_MS );
		} );

		// Ship-to-different-address toggle.
		$( document ).on( 'change', SEL_SHIP_TOGGLE, function () {
			if ( $( this ).is( ':checked' ) ) {
				$( SEL_SHIP_SECTION ).slideDown( 200 );
			} else {
				$( SEL_SHIP_SECTION ).slideUp( 200 );
			}
		} );
	}

	// ── Recalculate rate when order items change ──────────────────────────────
	// The delivery charge depends on which products are in the order (free-delivery
	// flag / free areas). WooCommerce only recalculated on area change before, so a
	// product added after the area was chosen left a stale charge. We re-run the
	// calc whenever the product line-items change (add/remove/qty).

	/**
	 * Signature of the current product line-items (order_item_id + qty). Stable
	 * across our own shipping-row reload (which changes only the shipping line),
	 * so it lets us ignore self-triggered mutations and avoid a loop.
	 */
	function currentItemsSignature() {
		var parts = [];
		$( '#woocommerce-order-items' ).find( 'tbody.woocommerce_order_items tr.item' ).each( function () {
			var $row = $( this );
			var id   = $row.attr( 'data-order_item_id' ) || '';
			var $qty = $row.find( 'input.quantity' );
			var qty  = $qty.length ? $qty.val() : $.trim( $row.find( '.quantity .view' ).text() ).replace( /[^\d.]/g, '' );
			parts.push( id + ':' + qty );
		} );
		return parts.join( '|' );
	}

	function maybeRecalcFromItems() {
		if ( suppressItemsRecalc ) {
			return;
		}
		var area = $( SEL_HUB_AREA ).val();
		if ( ! area ) {
			return; // No area chosen yet — nothing to recalc against.
		}
		var sig = currentItemsSignature();
		if ( sig === lastItemsSignature ) {
			return; // No product-line change (e.g. our own shipping reload).
		}
		lastItemsSignature = sig;

		clearTimeout( recalcTimer );
		$( SEL_RATE_ROW ).show();
		$( SEL_RATE_LABEL ).text( bpAoe.i18n.calculating );
		cachedRate = null;
		recalcTimer = setTimeout( function () {
			fetchRate( area );
		}, DEBOUNCE_MS );
	}

	function bindOrderItemsRecalc() {
		lastItemsSignature = currentItemsSignature();

		// WC fires this on its own items box after add/save/delete/qty reloads.
		$( '#woocommerce-order-items' ).on( 'wc_order_items_reloaded', maybeRecalcFromItems );

		// Fallback: catch item changes WC applies without firing that event.
		var box = document.getElementById( 'woocommerce-order-items' );
		if ( box && typeof MutationObserver !== 'undefined' ) {
			new MutationObserver( function () {
				maybeRecalcFromItems();
			} ).observe( box, { childList: true, subtree: true } );
		}
	}

	// ── Shipping rate calculation ────────────────────────────────────────────
	function fetchRate( hubArea ) {
		var orderId = getOrderId();

		$.ajax( {
			url:    bpAoe.ajax_url,
			method: 'POST',
			data:   {
				action:   'bp_aoe_calc_shipping',
				nonce:    bpAoe.calc_nonce,
				order_id: orderId,
				hub_area: hubArea,
			},
			success: function ( res ) {
				if ( res.success ) {
					cachedRate = res.data.rate;
					$( SEL_RATE_ROW ).show();
					$( SEL_RATE_LABEL ).text( res.data.label );
					applyRate( cachedRate );
				} else {
					$( SEL_RATE_LABEL ).text( bpAoe.i18n.unavailable + ': ' + ( res.data || '' ) );
					cachedRate = null;
				}
			},
			error: function () {
				$( SEL_RATE_LABEL ).text( bpAoe.i18n.unavailable );
				cachedRate = null;
			},
		} );
	}

	function applyRate( rate ) {
		var orderId = getOrderId();
		if ( ! orderId ) {
			return;
		}

		$.ajax( {
			url:    bpAoe.ajax_url,
			method: 'POST',
			data:   {
				action:   'bp_aoe_apply_shipping',
				nonce:    bpAoe.apply_nonce,
				order_id: orderId,
				rate:     rate,
			},
			success: function ( res ) {
				if ( res.success ) {
					// Reload the order items meta box so the shipping row and totals refresh.
					reloadOrderItems();
				}
			},
		} );
	}

	/**
	 * Triggers WooCommerce's own items-meta-box reload so the shipping line
	 * item and order totals update without a full page refresh.
	 */
	function reloadOrderItems() {
		var orderId = getOrderId();
		if ( ! orderId ) {
			return;
		}

		var data = {
			action:   'woocommerce_load_order_items',
			order_id: orderId,
			security: ( typeof woocommerce_admin_meta_boxes !== 'undefined' )
				? woocommerce_admin_meta_boxes.order_item_nonce
				: '',
		};

		$.ajax( {
			url:    bpAoe.ajax_url,
			method: 'POST',
			data:   data,
			success: function ( response ) {
				if ( response ) {
					// Suppress the items-recalc observer while we swap in the new
					// HTML (only the shipping row changed, not the products).
					suppressItemsRecalc = true;
					$( '#woocommerce-order-items' ).find( '.inside' ).html( response );
					// Re-init any WC JS components inside the refreshed HTML.
					$( document.body ).trigger( 'wc-enhanced-select-init' );
					// Product lines are unchanged; refresh the baseline signature and
					// release the guard after the mutation callbacks have flushed.
					lastItemsSignature = currentItemsSignature();
					setTimeout( function () {
						suppressItemsRecalc = false;
					}, 50 );
				}
			},
		} );
	}

	// ── Payment status events ────────────────────────────────────────────────
	function bindPaymentStatusEvents() {
		$( document ).on( 'change', SEL_PAYMENT_RADIOS, function () {
			if ( $( this ).val() === 'partial' ) {
				$( SEL_AMOUNT_ROW ).slideDown( 150 );
			} else {
				$( SEL_AMOUNT_ROW ).slideUp( 150 );
				$( SEL_AMOUNT_INPUT ).val( '' );
			}
		} );
	}

	// ── Utility ──────────────────────────────────────────────────────────────
	function getOrderId() {
		// Canonical source — WC localises the current order ID here on both the
		// legacy and HPOS order screens (present even on the new-order screen).
		if ( typeof woocommerce_admin_meta_boxes !== 'undefined' && woocommerce_admin_meta_boxes.post_id ) {
			var wcId = parseInt( woocommerce_admin_meta_boxes.post_id, 10 );
			if ( wcId ) {
				return wcId;
			}
		}
		// Legacy fallback: post ID hidden input.
		var $postId = $( '#post_ID' );
		if ( $postId.length && parseInt( $postId.val(), 10 ) ) {
			return parseInt( $postId.val(), 10 );
		}
		// HPOS fallback: read from URL param.
		var match = window.location.search.match( /[?&]id=(\d+)/ );
		return match ? parseInt( match[1], 10 ) : 0;
	}

} )( jQuery );

/**
 * Upaya Checkout — combined Hub+Area select handler.
 *
 * The combined #billing_hub_area select has options encoded as
 * "HubName||AreaName".  On change, JS splits the value and writes the two
 * hidden inputs #billing_state (hub) and #billing_city (area), then fires
 * WooCommerce's update_checkout so the shipping rate recalculates immediately
 * — without waiting for the customer to fill in a street address.
 *
 * A debounce guard prevents rapid repeated triggers on programmatic changes.
 */
( function ( $ ) {
	'use strict';

	var SEPARATOR  = '||';
	var updateTimer = null;

	/* ── SelectWoo helper ───────────────────────────────────────────────── */

	function initSelectWoo( $el ) {
		if ( $el.hasClass( 'select2-hidden-accessible' ) ) {
			try { $el.selectWoo( 'destroy' ); } catch ( e ) {}
		}
		$el.removeClass( 'enhanced' );

		if ( $.fn.selectWoo ) {
			// minimumResultsForSearch: 0 → search box always visible, no threshold.
			$el.selectWoo( { minimumResultsForSearch: 0 } ).addClass( 'enhanced' );
		} else if ( $.fn.select2 ) {
			$el.select2( { minimumResultsForSearch: 0 } ).addClass( 'enhanced' );
		}
	}

	/* ── Value helpers ──────────────────────────────────────────────────── */

	/**
	 * Split a combined "Hub||Area" value into { hub, area }.
	 * Returns empty strings if the value is missing or malformed.
	 */
	function splitCombined( val ) {
		if ( ! val || val.indexOf( SEPARATOR ) === -1 ) {
			return { hub: '', area: '' };
		}
		var idx = val.indexOf( SEPARATOR );
		return {
			hub:  val.slice( 0, idx ),
			area: val.slice( idx + SEPARATOR.length ),
		};
	}

	/**
	 * Write hub and area into the matching hidden state/city inputs for the
	 * given prefix ('billing' or 'shipping').
	 */
	function applyToHidden( prefix, hub, area ) {
		$( '#' + prefix + '_state' ).val( hub );
		$( '#' + prefix + '_city'  ).val( area );
	}

	/**
	 * Read the hidden state + city values for a given prefix and select the
	 * matching option in the combined dropdown. Called after update_checkout
	 * re-renders the form with WC-repopulated hidden inputs.
	 */
	function syncCombinedFromHidden( prefix ) {
		var hub  = $( '#' + prefix + '_state' ).val() || '';
		var area = $( '#' + prefix + '_city'  ).val() || '';

		if ( ! hub || ! area ) {
			return;
		}

		var val    = hub + SEPARATOR + area;
		var $combo = $( '#' + prefix + '_hub_area' );

		if ( $combo.length && $combo.val() !== val &&
				$combo.find( 'option[value="' + val + '"]' ).length ) {
			$combo.val( val );
			initSelectWoo( $combo );
		}
	}

	/* ── Checkout update trigger (debounced) ────────────────────────────── */

	/**
	 * Debounced wrapper around WC's update_checkout trigger.
	 * Prevents back-to-back triggers when JS sets field values programmatically.
	 */
	function triggerCheckoutUpdate() {
		clearTimeout( updateTimer );
		updateTimer = setTimeout( function () {
			$( 'body' ).trigger( 'update_checkout' );
		}, 300 );
	}

	/* ── Event binding ──────────────────────────────────────────────────── */

	$( function () {

		// Combined field changed — works for both billing and shipping selects.
		// The select IDs are #billing_hub_area and #shipping_hub_area.
		$( document.body ).on( 'change', '#billing_hub_area, #shipping_hub_area', function () {
			var id     = $( this ).attr( 'id' ) || '';
			var prefix = id.replace( '_hub_area', '' ); // 'billing' or 'shipping'
			var parts  = splitCombined( $( this ).val() );

			applyToHidden( prefix, parts.hub, parts.area );

			// Recalculate the delivery charge immediately after the area is chosen.
			if ( parts.hub && parts.area ) {
				triggerCheckoutUpdate();
			}
		} );

		// After WC re-renders the checkout, restore both combined dropdowns from
		// the hidden inputs WC has already repopulated from POST data.
		$( document.body ).on( 'updated_checkout', function () {
			syncCombinedFromHidden( 'billing' );
			syncCombinedFromHidden( 'shipping' );
			initSelectWoo( $( '#billing_hub_area' ) );
			initSelectWoo( $( '#shipping_hub_area' ) );
		} );

		// On initial page load: initialise SelectWoo on whichever combined selects
		// exist and sync their values from any pre-populated hidden inputs
		// (returning customers, validation-failure re-renders, saved addresses).
		$( '#billing_hub_area, #shipping_hub_area' ).each( function () {
			initSelectWoo( $( this ) );
		} );
		syncCombinedFromHidden( 'billing' );
		syncCombinedFromHidden( 'shipping' );

	} );

} )( jQuery );

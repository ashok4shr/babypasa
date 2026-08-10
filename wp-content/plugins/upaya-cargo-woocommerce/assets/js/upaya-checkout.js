/**
 * Upaya Checkout — combined Hub+Area select handler.
 *
 * The combined #billing_hub_area select has options encoded as
 * "HubName||AreaName". Rather than rendering the full multi-thousand-row
 * hub+area dataset into the page (which made SelectWoo unusably slow to open
 * and to search on mobile), the page only ever renders a placeholder plus —
 * when one is already selected — that single option.
 *
 * Search is served from a full hub+area index fetched ONCE in the background
 * (loadAreaIndex(), started immediately on page load and cached in
 * sessionStorage) and then filtered entirely in memory — no network round
 * trip, no "Searching…" delay, per keystroke. Until that index has finished
 * loading, SelectWoo's ajax transport falls back to the live per-keystroke
 * search endpoint (ajax_search_areas(), PHP) so the field is always fully
 * functional. Either path renders at most LOCAL_RESULT_LIMIT results, so the
 * DOM never holds more than a handful of options regardless of dataset size.
 *
 * On change, JS splits the value and writes the two hidden inputs
 * #billing_state (hub) and #billing_city (area), then fires WooCommerce's
 * update_checkout so the shipping rate recalculates immediately — without
 * waiting for the customer to fill in a street address.
 *
 * A debounce guard prevents rapid repeated triggers on programmatic changes.
 */
( function ( $ ) {
	'use strict';

	var SEPARATOR             = '||';
	var LOCAL_RESULT_LIMIT    = 20;
	var FALLBACK_SEARCH_DELAY = 300;
	var updateTimer           = null;
	var fallbackSearchTimer   = null;

	// Full hub+area list once loaded via loadAreaIndex(); null until then, at
	// which point selectWooConfig()'s transport falls back to the live search.
	var areaIndex = null;

	/* ── Background index prefetch ──────────────────────────────────────── */

	/**
	 * Fetches the full hub+area index once — sessionStorage first (instant,
	 * survives reopening the checkout tab within the same session), then a
	 * background AJAX call on a miss. Started eagerly on page load so the
	 * index is normally ready well before the customer opens the field.
	 */
	function loadAreaIndex() {
		var params     = window.upaya_checkout_params || {};
		var storageKey = 'upayaAreaIndex_v' + ( params.index_version || '0' );

		try {
			var cached = window.sessionStorage.getItem( storageKey );
			if ( cached ) {
				areaIndex = JSON.parse( cached );
				return;
			}
		} catch ( e ) {}

		if ( ! params.ajax_url || ! params.index_action ) {
			return;
		}

		$.ajax( {
			url:      params.ajax_url,
			type:     'GET',
			dataType: 'json',
			data: {
				action:   params.index_action,
				security: params.index_nonce,
			},
		} ).done( function ( response ) {
			if ( ! response || ! response.success || ! Array.isArray( response.data ) ) {
				return;
			}
			areaIndex = response.data;
			try {
				window.sessionStorage.setItem( storageKey, JSON.stringify( areaIndex ) );
			} catch ( e ) {}
		} );
	}

	/**
	 * Filters the in-memory index for a substring match against "Hub › Area",
	 * capped at LOCAL_RESULT_LIMIT — same cap and matching rule the server
	 * search already used, so behaviour is identical either way.
	 */
	function filterAreaIndex( term ) {
		var needle  = String( term || '' ).toLowerCase();
		var matches = [];

		for ( var i = 0; i < areaIndex.length && matches.length < LOCAL_RESULT_LIMIT; i++ ) {
			if ( areaIndex[ i ].text.toLowerCase().indexOf( needle ) !== -1 ) {
				matches.push( areaIndex[ i ] );
			}
		}

		return matches;
	}

	/* ── SelectWoo helper ───────────────────────────────────────────────── */

	/**
	 * Shared SelectWoo config. The underlying <select> never needs to hold
	 * more than the current value — SelectWoo only ever renders the handful
	 * of results a query returns, regardless of how large the full dataset
	 * is, whether those results came from the local index or the live search.
	 */
	function selectWooConfig() {
		return {
			minimumInputLength: 2,
			language: {
				inputTooShort: function () {
					return 'Type to search delivery areas…';
				},
				searching: function () {
					return 'Searching…';
				},
				noResults: function () {
					return 'No delivery area found';
				},
			},
			ajax: {
				// No top-level `delay` here on purpose: select2 applies it BEFORE
				// calling transport at all, which would add an artificial wait to
				// the local-filter path too. The live-fallback path debounces
				// itself instead, inside transport, below.
				url: ( window.upaya_checkout_params || {} ).ajax_url,
				dataType: 'json',
				data: function ( params ) {
					var settings = window.upaya_checkout_params || {};
					return {
						term:     params.term,
						action:   settings.search_action,
						security: settings.search_nonce,
					};
				},
				transport: function ( params, success, failure ) {
					if ( areaIndex ) {
						// Local index is ready — filter in memory, no network call,
						// no debounce: results are returned as fast as the browser
						// can call back.
						success( { success: true, data: filterAreaIndex( params.data.term ) } );
						return { abort: function () {} };
					}

					// Local index not loaded yet — fall back to the live server
					// search so the field stays fully functional in that brief
					// window, debounced manually since ajax.delay isn't set above.
					clearTimeout( fallbackSearchTimer );
					var jqXHR;
					fallbackSearchTimer = setTimeout( function () {
						jqXHR = $.ajax( params ).then( success, failure );
					}, FALLBACK_SEARCH_DELAY );

					return {
						abort: function () {
							clearTimeout( fallbackSearchTimer );
							if ( jqXHR && jqXHR.abort ) {
								jqXHR.abort();
							}
						},
					};
				},
				processResults: function ( response ) {
					return { results: ( response && response.success ) ? response.data : [] };
				},
				cache: true,
			},
		};
	}

	/**
	 * Initialises (or re-initialises) SelectWoo on the given combined select.
	 * Idempotent: does nothing if the widget is already enhanced, unless
	 * `force` is passed — used when the underlying value has just been
	 * changed programmatically and the display needs to catch up. Without
	 * this guard the widget was being torn down and rebuilt on every single
	 * checkout update, not just when the area itself changed.
	 */
	function initSelectWoo( $el, force ) {
		if ( ! $el.length ) {
			return;
		}

		var alreadyEnhanced = $el.hasClass( 'select2-hidden-accessible' );

		if ( alreadyEnhanced && ! force ) {
			return;
		}

		if ( alreadyEnhanced ) {
			try { $el.selectWoo( 'destroy' ); } catch ( e ) {}
			$el.removeClass( 'enhanced' );
		}

		if ( $.fn.selectWoo ) {
			$el.selectWoo( selectWooConfig() ).addClass( 'enhanced' );
		} else if ( $.fn.select2 ) {
			$el.select2( selectWooConfig() ).addClass( 'enhanced' );
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
	 * re-renders the form with WC-repopulated hidden inputs. Since the select
	 * no longer carries the full dataset, this only succeeds when the target
	 * option is already present in the DOM — i.e. the value the page itself
	 * rendered as pre-selected, or one the customer has already picked via
	 * search (SelectWoo inserts a real <option> for the current selection).
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
			initSelectWoo( $combo, true );
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

		// Start the background index fetch immediately — non-blocking, so it
		// normally finishes well before the customer reaches this field.
		loadAreaIndex();

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
		// the hidden inputs WC has already repopulated from POST data. Each
		// select's SelectWoo instance is left alone unless its value actually
		// needs to change (see syncCombinedFromHidden) — previously both were
		// unconditionally destroyed and rebuilt here on every single checkout
		// update, which was the main cost behind "changing the area once makes
		// reopening the dropdown slow every time after."
		$( document.body ).on( 'updated_checkout', function () {
			syncCombinedFromHidden( 'billing' );
			syncCombinedFromHidden( 'shipping' );
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

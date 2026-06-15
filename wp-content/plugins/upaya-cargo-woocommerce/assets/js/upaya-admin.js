/* global upayaAdmin, jQuery */

/**
 * Upaya Cargo — unified admin JavaScript.
 *
 * Handles both the settings page (context = 'settings') and the order
 * meta box (context = 'meta_box'), determined by the upayaAdmin.context
 * value injected via wp_localize_script.
 */
( function ( $ ) {
	'use strict';

	/* -----------------------------------------------------------------------
	 * Shared helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Shows an inline result message next to a trigger element.
	 *
	 * @param {jQuery} $el      The result <span> or container element.
	 * @param {string} message  Text to display.
	 * @param {boolean} isError True renders error colour.
	 */
	function showResult( $el, message, isError ) {
		$el.text( message )
			.removeClass( 'is-success is-error' )
			.addClass( isError ? 'is-error' : 'is-success' );
	}

	/* -----------------------------------------------------------------------
	 * Settings page
	 * --------------------------------------------------------------------- */

	function initSettingsPage() {

		// ── Test API Connection ──────────────────────────────────────────
		$( '#upaya-test-connection' ).on( 'click', function () {
			var $btn    = $( this );
			var $result = $( '#upaya-test-connection-result' );
			var origTxt = $btn.text();

			$btn.prop( 'disabled', true ).text( upayaAdmin.i18n.testing );
			$result.text( '' ).removeClass( 'is-success is-error' );

			$.post( upayaAdmin.ajax_url, {
				action : 'upaya_test_connection',
				nonce  : $btn.data( 'nonce' )
			} )
			.done( function ( res ) {
				showResult( $result, res.data, ! res.success );
			} )
			.fail( function () {
				showResult( $result, upayaAdmin.i18n.error || 'Error', true );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( origTxt );
			} );
		} );

		// ── Flush Location Cache ─────────────────────────────────────────
		$( '#upaya-flush-cache' ).on( 'click', function () {
			var $btn    = $( this );
			var $result = $( '#upaya-flush-cache-result' );
			var origTxt = $btn.text();

			$btn.prop( 'disabled', true ).text( upayaAdmin.i18n.flushing );
			$result.text( '' ).removeClass( 'is-success is-error' );

			$.post( upayaAdmin.ajax_url, {
				action : 'upaya_flush_location_cache',
				nonce  : $btn.data( 'nonce' )
			} )
			.done( function ( res ) {
				showResult( $result, res.data, ! res.success );
			} )
			.fail( function () {
				showResult( $result, upayaAdmin.i18n.error || 'Error', true );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( origTxt );
			} );
		} );

		// ── Manually Sync Pending Orders ─────────────────────────────────
		$( '#upaya-sync-pending' ).on( 'click', function () {
			var $btn    = $( this );
			var $result = $( '#upaya-sync-result' );
			var origTxt = $btn.text();

			$btn.prop( 'disabled', true ).text( upayaAdmin.i18n.syncing );
			$result.text( '' ).removeClass( 'is-success is-error' );

			$.post( upayaAdmin.ajax_url, {
				action : 'upaya_sync_pending_orders',
				nonce  : $btn.data( 'nonce' )
			} )
			.done( function ( res ) {
				showResult( $result, res.data, ! res.success );
				if ( res.success ) {
					$( '#upaya-pending-count' ).text( '0' );
				}
			} )
			.fail( function () {
				showResult( $result, upayaAdmin.i18n.error || 'Error', true );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( origTxt );
			} );
		} );
	}

	/* -----------------------------------------------------------------------
	 * Order meta box
	 * --------------------------------------------------------------------- */

	function initMetaBox() {

		function getBoxData() {
			var $box = $( '#upaya-meta-box-content' );
			return {
				order_id : $box.data( 'order-id' ),
				nonce    : $box.data( 'nonce' )
			};
		}

		function showMetaMessage( message, isError ) {
			var $msg = $( '#upaya-meta-message' );
			$msg.text( message )
				.css( 'color', isError ? '#dc3232' : '#46b450' );
		}

		// ── Re-submit order ──────────────────────────────────────────────
		$( document ).on( 'click', '.upaya-btn-resubmit', function () {
			if ( ! window.confirm(
				'Re-submit this order to Upaya Cargo?'
			) ) {
				return;
			}

			var $btn    = $( this );
			var d       = getBoxData();
			var origTxt = $btn.text();

			$btn.prop( 'disabled', true ).text( upayaAdmin.i18n.submitting );
			showMetaMessage( '' );

			$.post( upayaAdmin.ajax_url, {
				action   : 'upaya_resubmit_order',
				order_id : d.order_id,
				nonce    : d.nonce
			} )
			.done( function ( res ) {
				showMetaMessage( res.data, ! res.success );
				if ( res.success ) {
					$btn.hide();
				}
			} )
			.fail( function () {
				showMetaMessage( upayaAdmin.i18n.error || 'Error', true );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( origTxt );
			} );
		} );

		// ── Refresh tracking ─────────────────────────────────────────────
		$( document ).on( 'click', '.upaya-btn-refresh-tracking', function () {
			var $btn    = $( this );
			var d       = getBoxData();
			var origTxt = $btn.text();

			$btn.prop( 'disabled', true ).text( upayaAdmin.i18n.refreshing );
			showMetaMessage( '' );

			$.post( upayaAdmin.ajax_url, {
				action   : 'upaya_refresh_tracking',
				order_id : d.order_id,
				nonce    : d.nonce
			} )
			.done( function ( res ) {
				if ( ! res.success ) {
					showMetaMessage( res.data, true );
					return;
				}

				var t    = res.data;
				var html = '<hr><p><strong>Tracking</strong></p>';

				if ( t.status ) {
					html += '<p>Status: <strong>' +
						$( '<span>' ).text( t.status ).html() +
						'</strong></p>';
				}
				if ( t.estimated_delivery ) {
					html += '<p>Est. Delivery: <strong>' +
						$( '<span>' ).text( t.estimated_delivery ).html() +
						'</strong></p>';
				}
				if ( t.items && t.items.length ) {
					html += '<table class="widefat striped upaya-tracking-table">' +
						'<thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead>' +
						'<tbody>';
					$.each( t.items, function ( i, item ) {
						html += '<tr>' +
							'<td>' + $( '<span>' ).text( item.name     || '' ).html() + '</td>' +
							'<td>' + $( '<span>' ).text( item.quantity || '' ).html() + '</td>' +
							'<td>' + $( '<span>' ).text( item.price    || '' ).html() + '</td>' +
							'</tr>';
					} );
					html += '</tbody></table>';
				}

				// Replace existing tracking section (everything between the
				// first <hr> and .upaya-meta-actions).
				var $content = $( '#upaya-meta-box-content' );
				$content.find( 'hr' ).nextUntil( '.upaya-meta-actions' ).remove();
				$content.find( 'hr' ).remove();
				$content.find( '.upaya-meta-actions' ).before( html );
				showMetaMessage( 'Tracking refreshed.' );
			} )
			.fail( function () {
				showMetaMessage( upayaAdmin.i18n.error || 'Error', true );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( origTxt );
			} );
		} );
	}

	/* -----------------------------------------------------------------------
	 * Entry point
	 * --------------------------------------------------------------------- */

	$( function () {
		if ( typeof upayaAdmin === 'undefined' ) {
			return;
		}

		if ( upayaAdmin.context === 'settings' ) {
			initSettingsPage();
		} else if ( upayaAdmin.context === 'meta_box' ) {
			initMetaBox();
		}
	} );

}( jQuery ) );

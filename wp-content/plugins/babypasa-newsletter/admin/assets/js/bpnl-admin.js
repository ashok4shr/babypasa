/* Babypasa Newsletter — admin JavaScript */
/* global bpnlAdmin */

( function ( $ ) {
	'use strict';

	// ── Live preview ──────────────────────────────────────────────────────

	var SAMPLE_TOKENS = {
		'{{subscriber_email}}': 'customer@example.com',
		'{{unsubscribe_link}}': '#unsubscribe',
		'{{site_name}}':        'Baby Pasa',
	};

	function renderPreview( textarea ) {
		var previewId = textarea.getAttribute( 'data-preview' );
		if ( ! previewId ) { return; }

		var iframe = document.getElementById( previewId );
		if ( ! iframe ) { return; }

		var html = textarea.value;

		// Swap tokens for readable sample values.
		Object.keys( SAMPLE_TOKENS ).forEach( function ( token ) {
			html = html.split( token ).join( SAMPLE_TOKENS[ token ] );
		} );

		// Wrap bare HTML fragments in a minimal document so styles apply.
		var trimmed = html.trim().toLowerCase();
		if ( trimmed.indexOf( '<!doctype' ) !== 0 && trimmed.indexOf( '<html' ) !== 0 ) {
			html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
				+ '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;'
				+ 'margin:0;padding:20px;color:#1a1a1a;line-height:1.6;background:#f4f4f4;}'
				+ 'a{color:#0073aa;}</style></head><body>' + html + '</body></html>';
		}

		iframe.srcdoc = html;
	}

	function initEditors() {
		document.querySelectorAll( '.bpnl-html-editor' ).forEach( function ( ta ) {
			renderPreview( ta );
			ta.addEventListener( 'input', function () { renderPreview( this ); } );
		} );
	}

	// Run on DOM ready.
	document.addEventListener( 'DOMContentLoaded', initEditors );

	// ── Recipient selector ────────────────────────────────────────────────

	$( document ).on( 'change', 'input[name="bpnl_recipients_type"]', function () {
		if ( 'select' === $( this ).val() ) {
			$( '#bpnl-subscriber-select-wrap' ).slideDown( 200 );
		} else {
			$( '#bpnl-subscriber-select-wrap' ).slideUp( 200 );
		}
	} );

	// ── Send Newsletter ───────────────────────────────────────────────────

	$( document ).on( 'click', '#bpnl-send-newsletter', function () {
		var $btn     = $( this );
		var $spinner = $( '#bpnl-send-spinner' );
		var $result  = $( '#bpnl-send-result' );

		var recipientsType = $( 'input[name="bpnl_recipients_type"]:checked' ).val() || 'all';
		var recipients     = 'all';

		if ( 'select' === recipientsType ) {
			var selected = $( '#bpnl-subscriber-select' ).val();
			if ( ! selected || 0 === selected.length ) {
				$result
					.removeClass( 'bpnl-success' )
					.addClass( 'bpnl-error' )
					.text( 'Please select at least one subscriber.' )
					.show();
				return;
			}
			recipients = JSON.stringify( selected );
		}

		var subject  = $( '#bpnl_subject_newsletter' ).val();
		var body     = $( '#bpnl_body_newsletter' ).val();
		var reply_to = $( '#bpnl_reply_to_newsletter' ).val();

		if ( ! subject || ! body ) {
			$result
				.removeClass( 'bpnl-success' )
				.addClass( 'bpnl-error' )
				.text( 'Subject and body cannot be empty. Please save the template first.' )
				.show();
			return;
		}

		$btn.prop( 'disabled', true );
		$spinner.css( 'visibility', 'visible' );
		$result.hide().removeClass( 'bpnl-success bpnl-error' );

		$.post(
			bpnlAdmin.ajaxUrl,
			{
				action:     'bpnl_send_newsletter',
				nonce:      bpnlAdmin.nonce,
				recipients: recipients,
				subject:    subject,
				body:       body,
				reply_to:   reply_to,
			},
			function ( response ) {
				$btn.prop( 'disabled', false );
				$spinner.css( 'visibility', 'hidden' );

				if ( response.success ) {
					$result.addClass( 'bpnl-success' ).text( response.data.message ).show();
				} else {
					$result.addClass( 'bpnl-error' ).text( response.data.message ).show();
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false );
			$spinner.css( 'visibility', 'hidden' );
			$result.addClass( 'bpnl-error' ).text( 'Connection error. Please try again.' ).show();
		} );
	} );

} )( jQuery );

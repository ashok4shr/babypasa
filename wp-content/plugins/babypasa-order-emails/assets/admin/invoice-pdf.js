/* global jQuery, wp, bpInvoicePdf, tinyMCE */
( function ( $ ) {
	'use strict';

	var cmInstance = null;

	$( function () {
		// Colour picker.
		if ( $.fn.wpColorPicker ) {
			$( '.bp-color-field' ).wpColorPicker();
		}

		// CodeMirror for the raw template (only present for unfiltered_html users).
		var $raw = $( '#bp-raw-template' );
		if ( $raw.length && bpInvoicePdf.codeEditor && wp.codeEditor ) {
			cmInstance = wp.codeEditor.initialize( $raw[ 0 ], bpInvoicePdf.codeEditor );
		}

		// Media-library logo picker.
		var frame = null;
		$( '#bp-logo-select' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( frame ) {
				frame.open();
				return;
			}
			frame = wp.media( {
				title: bpInvoicePdf.i18n.selectLogo,
				button: { text: bpInvoicePdf.i18n.useLogo },
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				$( '#bp-logo-id' ).val( att.id );
				var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
				$( '#bp-logo-preview' ).attr( 'src', url ).show();
				$( '#bp-logo-remove' ).show();
			} );
			frame.open();
		} );

		$( '#bp-logo-remove' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '#bp-logo-id' ).val( '0' );
			$( '#bp-logo-preview' ).attr( 'src', '' ).hide();
			$( this ).hide();
		} );

		// Preview PDF — posts CURRENT (unsaved) field values to the AJAX endpoint
		// in a new tab.
		$( '#bp-pdf-preview' ).on( 'click', function ( e ) {
			e.preventDefault();
			syncEditors();

			var data = $( '#bp-invoice-pdf-form' ).serializeArray();
			var $form = $( '<form>', {
				method: 'POST',
				action: bpInvoicePdf.ajaxUrl,
				target: '_blank'
			} ).css( 'display', 'none' );

			$.each( data, function ( i, field ) {
				// Skip the save form's own action/nonce; we set preview's below.
				if ( 'action' === field.name || '_wpnonce' === field.name ) {
					return;
				}
				$form.append( $( '<input>', { type: 'hidden', name: field.name, value: field.value } ) );
			} );
			$form.append( $( '<input>', { type: 'hidden', name: 'action', value: 'bp_invoice_pdf_preview' } ) );
			$form.append( $( '<input>', { type: 'hidden', name: '_wpnonce', value: bpInvoicePdf.previewNonce } ) );

			$( 'body' ).append( $form );
			$form.trigger( 'submit' );
			$form.remove();
		} );

		// Confirm resets.
		$( '.bp-reset-btn' ).on( 'click', function ( e ) {
			if ( ! window.confirm( bpInvoicePdf.i18n.confirmReset ) ) {
				e.preventDefault();
			}
		} );
	} );

	// Push TinyMCE + CodeMirror content back into their textareas before serialize.
	function syncEditors() {
		if ( window.tinyMCE ) {
			tinyMCE.triggerSave();
		}
		if ( cmInstance && cmInstance.codemirror ) {
			cmInstance.codemirror.save();
		}
	}
} )( jQuery );

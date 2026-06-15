/**
 * BabyPasa Delivery Overrides — admin settings JS
 *
 * Handles dynamic add/remove of rule rows in the area-override settings table.
 * Relies on a hidden <tr id="bp-rule-row-template"> rendered by PHP with
 * index placeholder "__INDEX__" in all field names.
 */
( function ( $ ) {
	'use strict';

	// Count only real rule rows (not the placeholder "no rules" row).
	var rowIndex = $( '#bp-area-overrides-rows tr:not(.bp-no-rules-row)' ).length;

	$( '#bp-add-override-rule' ).on( 'click', function () {
		var $template = $( '#bp-rule-row-template' ).clone();

		// Give the cloned row a real index and a unique ID.
		var html = $template.html().replace( /__INDEX__/g, rowIndex );
		var $newRow = $( '<tr>' ).html( html );

		// Remove the "no rules" placeholder row if present.
		$( '.bp-no-rules-row' ).remove();

		$( '#bp-area-overrides-rows' ).append( $newRow );
		rowIndex++;
	} );

	// Event delegation so it works on dynamically added rows too.
	$( '#bp-area-overrides-rows' ).on( 'click', '.bp-remove-rule', function () {
		$( this ).closest( 'tr' ).remove();

		if ( $( '#bp-area-overrides-rows tr' ).length === 0 ) {
			$( '#bp-area-overrides-rows' ).append(
				'<tr class="bp-no-rules-row"><td colspan="6">' +
				'No rules yet. Click "Add Rule" to create one.' +
				'</td></tr>'
			);
		}
	} );
} )( jQuery );

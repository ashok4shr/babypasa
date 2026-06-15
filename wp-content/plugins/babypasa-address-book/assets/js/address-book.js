/**
 * BabyPasa Address Book — My Account management + Checkout fast-fill.
 *
 * Context is determined by bpAddressBook.context:
 *   'account'  — address CRUD on the My Account saved-addresses page
 *   'checkout' — address picker on the checkout page
 */
( function ( $ ) {
	'use strict';

	if ( typeof bpAddressBook === 'undefined' ) {
		return;
	}

	/* ── Checkout context ────────────────────────────────────────────────── */

	if ( bpAddressBook.context === 'checkout' ) {
		initCheckoutPicker();
		return;
	}

	/* ── My Account context ──────────────────────────────────────────────── */

	$( function () {
		initMyAccountAddressBook();
	} );

	/* ====================================================================
	 * CHECKOUT PICKER
	 * ==================================================================== */

	function initCheckoutPicker() {
		$( document ).ready( function () {
			// Click or keyboard-activate a saved address card.
			$( document.body ).on( 'click keypress', '.bp-picker-card', function ( e ) {
				if ( e.type === 'keypress' && e.which !== 13 ) {
					return;
				}

				var $card = $( this );
				var addr;

				try {
					addr = JSON.parse( $card.attr( 'data-address' ) );
				} catch ( err ) {
					return;
				}

				// Highlight selected card.
				$( '.bp-picker-card' ).removeClass( 'bp-picker-card--active' );
				$card.addClass( 'bp-picker-card--active' );

				fillCheckoutFields( addr );
			} );
		} );
	}

	function fillCheckoutFields( addr ) {
		// 1. Simple text fields.
		setField( '#billing_first_name', addr.first_name );
		setField( '#billing_last_name',  addr.last_name );
		setField( '#billing_address_1',  addr.address_1 );
		setField( '#billing_address_2',  addr.address_2 );
		setField( '#billing_postcode',   addr.postcode );
		setField( '#billing_phone',      addr.phone );
		setField( '#billing_email',      addr.email );

		// 2. Upaya custom fields.
		setField( '#billing_landmark',         addr.landmark );
		setField( '#billing_alternate_phone',  addr.alternate_phone );

		// 3. Pre-populate the hidden hub/area inputs so that after WooCommerce
		//    re-renders the form on update_checkout, Upaya's syncCombinedFromHidden()
		//    will restore the correct option automatically.
		$( '#billing_state' ).val( addr.state || '' );
		$( '#billing_city'  ).val( addr.city  || '' );

		// 4. Programmatically select the hub+area in the SelectWoo dropdown.
		//    This triggers upaya-checkout.js which writes billing_state / billing_city
		//    and fires the debounced update_checkout to recalculate the shipping rate.
		var $select     = $( '#billing_hub_area' );
		var hubAreaVal  = addr.hub_area || '';

		if ( $select.length && hubAreaVal ) {
			// Find the option via .filter() to avoid attribute selector issues with
			// special characters in hub/area names.
			var hasOption = $select.find( 'option' ).filter( function () {
				return $( this ).val() === hubAreaVal;
			} ).length > 0;

			if ( hasOption ) {
				// Set the native select value.
				$select.val( hubAreaVal );

				// Refresh SelectWoo's displayed text without firing app listeners.
				if ( $.fn.selectWoo ) {
					$select.trigger( 'change.select2' );
				} else if ( $.fn.select2 ) {
					$select.trigger( 'change.select2' );
				}

				// Fire the standard change event — upaya-checkout.js listens here,
				// splits the combined value, writes hidden inputs, then debounces
				// update_checkout (300 ms). Do NOT call update_checkout directly.
				$select.trigger( 'change' );
			}
			// If the option is not found (stale hub_area), the hidden inputs are
			// already set from step 3, so address fields are still populated; the
			// shipping rate may not recalculate until the user selects manually.
		}
	}

	function setField( selector, value ) {
		var $el = $( selector );
		if ( $el.length ) {
			$el.val( value || '' ).trigger( 'change' );
		}
	}

	/* ====================================================================
	 * MY ACCOUNT ADDRESS BOOK
	 * ==================================================================== */

	function initMyAccountAddressBook() {
		var $wrapper   = $( '#bp-address-form-wrapper' );
		var $form      = $( '#bp-address-form' );
		var $cardArea  = $( '#bp-address-cards' );
		var $notice    = $( '#bp-addr-notice' );
		var $formError = $( '#bp-addr-form-error' );

		if ( ! $wrapper.length ) {
			return; // Not on the saved-addresses page.
		}

		// Show form to add a new address.
		$( document.body ).on( 'click', '#bp-addr-add-new .bp-addr-add-btn', function () {
			resetForm();
			$( '#bp-address-form-title' ).text( 'Add New Address' );
			showForm();
		} );

		// Show form pre-filled for editing.
		$( document.body ).on( 'click', '.bp-addr-edit', function () {
			var addressId = $( this ).data( 'address-id' ).toString();
			var addr      = findAddress( addressId );
			if ( ! addr ) {
				return;
			}
			populateForm( addr );
			$( '#bp-address-form-title' ).text( 'Edit Address' );
			showForm( addr.hub_area );
		} );

		// Cancel.
		$( document.body ).on( 'click', '.bp-addr-cancel', function () {
			$wrapper.slideUp( 200 );
			resetForm();
		} );

		// AJAX save (create or update).
		$form.on( 'submit', function ( e ) {
			e.preventDefault();
			$formError.hide().text( '' );

			var $btn = $( '#bp-addr-save-btn' );
			$btn.prop( 'disabled', true ).text( bpAddressBook.i18n.saving );

			var formData = {};
			$form.serializeArray().forEach( function ( field ) {
				formData[ field.name ] = field.value;
			} );
			// Checkbox is omitted from serializeArray when unchecked.
			if ( ! formData.is_default ) {
				formData.is_default = '';
			}

			$.post( bpAddressBook.ajax_url, $.extend( {}, formData, {
				action: 'bp_save_address',
				nonce:  bpAddressBook.nonce,
			} ) )
			.done( function ( res ) {
				if ( res.success ) {
					bpAddressBook.addresses = res.data.addresses;
					$wrapper.slideUp( 200 );
					resetForm();
					rerenderCards();
					showNotice( 'Address saved successfully.', 'success' );
				} else {
					showFormError( res.data && res.data.message ? res.data.message : bpAddressBook.i18n.error_generic );
				}
			} )
			.fail( function () {
				showFormError( bpAddressBook.i18n.error_generic );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( 'Save Address' );
			} );
		} );

		// AJAX delete.
		$( document.body ).on( 'click', '.bp-addr-delete', function () {
			if ( ! window.confirm( bpAddressBook.i18n.confirm_delete ) ) {
				return;
			}
			var addressId = $( this ).data( 'address-id' ).toString();
			$.post( bpAddressBook.ajax_url, {
				action:     'bp_delete_address',
				nonce:      bpAddressBook.nonce,
				address_id: addressId,
			} )
			.done( function ( res ) {
				if ( res.success ) {
					bpAddressBook.addresses = res.data.addresses;
					rerenderCards();
					showNotice( 'Address deleted.', 'info' );
				} else {
					showNotice( res.data && res.data.message ? res.data.message : bpAddressBook.i18n.error_generic, 'error' );
				}
			} )
			.fail( function () {
				showNotice( bpAddressBook.i18n.error_generic, 'error' );
			} );
		} );

		// AJAX set default.
		$( document.body ).on( 'click', '.bp-addr-set-default', function () {
			var addressId = $( this ).data( 'address-id' ).toString();
			$.post( bpAddressBook.ajax_url, {
				action:     'bp_set_default_address',
				nonce:      bpAddressBook.nonce,
				address_id: addressId,
			} )
			.done( function ( res ) {
				if ( res.success ) {
					bpAddressBook.addresses = res.data.addresses;
					rerenderCards();
				} else {
					showNotice( res.data && res.data.message ? res.data.message : bpAddressBook.i18n.error_generic, 'error' );
				}
			} )
			.fail( function () {
				showNotice( bpAddressBook.i18n.error_generic, 'error' );
			} );
		} );

		/* ── Helpers ─────────────────────────────────────────────────────── */

		function showForm( hubAreaVal ) {
			$wrapper.slideDown( 200, function () {
				initHubAreaSelect();
				// Set SelectWoo value after re-initialising the widget.
				if ( hubAreaVal ) {
					$( '#bp_addr_hub_area' ).val( hubAreaVal ).trigger( 'change.select2' );
				}
			} );
			$( 'html, body' ).animate( { scrollTop: $wrapper.offset().top - 100 }, 300 );
		}

		function initHubAreaSelect() {
			var $sel = $( '#bp_addr_hub_area' );
			if ( ! $sel.length ) {
				return;
			}
			// Destroy previous instance if any.
			if ( $sel.hasClass( 'select2-hidden-accessible' ) ) {
				try {
					if ( $.fn.selectWoo ) {
						$sel.selectWoo( 'destroy' );
					} else if ( $.fn.select2 ) {
						$sel.select2( 'destroy' );
					}
				} catch ( e ) {}
			}
			$sel.removeClass( 'enhanced' );
			if ( $.fn.selectWoo ) {
				$sel.selectWoo( { minimumResultsForSearch: 0 } ).addClass( 'enhanced' );
			} else if ( $.fn.select2 ) {
				$sel.select2( { minimumResultsForSearch: 0 } ).addClass( 'enhanced' );
			}
		}

		function resetForm() {
			$form[ 0 ].reset();
			$( '#bp_addr_id' ).val( '' );
			$formError.hide().text( '' );
		}

		function populateForm( addr ) {
			$( '#bp_addr_id'              ).val( addr.id            || '' );
			$( '#bp_addr_nickname'        ).val( addr.nickname      || '' );
			$( '#bp_addr_first_name'      ).val( addr.first_name   || '' );
			$( '#bp_addr_last_name'       ).val( addr.last_name    || '' );
			$( '#bp_addr_hub_area'        ).val( addr.hub_area     || '' );
			$( '#bp_addr_address_1'       ).val( addr.address_1    || '' );
			$( '#bp_addr_address_2'       ).val( addr.address_2    || '' );
			$( '#bp_addr_postcode'        ).val( addr.postcode     || '' );
			$( '#bp_addr_phone'           ).val( addr.phone        || '' );
			$( '#bp_addr_alternate_phone' ).val( addr.alternate_phone || '' );
			$( '#bp_addr_email'           ).val( addr.email        || '' );
			$( '#bp_addr_landmark'        ).val( addr.landmark     || '' );
			$( '#bp_addr_is_default'      ).prop( 'checked', !! addr.is_default );
		}

		function findAddress( id ) {
			var addresses = bpAddressBook.addresses || [];
			for ( var i = 0; i < addresses.length; i++ ) {
				if ( addresses[ i ].id === id ) {
					return addresses[ i ];
				}
			}
			return null;
		}

		/**
		 * Re-render the address cards from bpAddressBook.addresses without a page reload.
		 * Rebuilds only the cards section, preserving the form wrapper.
		 */
		function rerenderCards() {
			var addresses   = bpAddressBook.addresses || [];
			var maxAddr     = 10; // Matches BP_Address_Book::MAX_ADDRESSES
			var html        = '';

			addresses.forEach( function ( addr ) {
				var isDefault = addr.is_default;
				html += '<div class="bp-address-card' + ( isDefault ? ' bp-address-card--default' : '' ) + '"'
					+ ' data-address-id="' + escAttr( addr.id ) + '">';

				html += '<div class="bp-address-card__header">'
					+ '<span class="bp-address-card__nickname">' + escHtml( addr.nickname ) + '</span>';
				if ( isDefault ) {
					html += '<span class="bp-address-card__badge">Default</span>';
				}
				html += '</div>';

				html += '<div class="bp-address-card__body">'
					+ '<p>' + escHtml( addr.first_name + ' ' + addr.last_name ) + '</p>'
					+ '<p>' + escHtml( addr.city + ', ' + addr.state ) + '</p>';
				if ( addr.address_1 ) {
					html += '<p>' + escHtml( addr.address_1 ) + '</p>';
				}
				if ( addr.address_2 ) {
					html += '<p>' + escHtml( addr.address_2 ) + '</p>';
				}
				if ( addr.landmark ) {
					html += '<p class="bp-address-card__landmark">Near: ' + escHtml( addr.landmark ) + '</p>';
				}
				html += '<p>' + escHtml( addr.phone ) + '</p>';
				html += '</div>';

				html += '<div class="bp-address-card__actions">'
					+ '<button class="bp-addr-edit button button-secondary" data-address-id="' + escAttr( addr.id ) + '">Edit</button>';
				if ( ! isDefault ) {
					html += '<button class="bp-addr-set-default button button-secondary" data-address-id="' + escAttr( addr.id ) + '">Set Default</button>'
						+ '<button class="bp-addr-delete button bp-button--danger" data-address-id="' + escAttr( addr.id ) + '">Delete</button>';
				}
				html += '</div>';
				html += '</div>';
			} );

			if ( addresses.length < maxAddr ) {
				html += '<div class="bp-address-card bp-address-card--add" id="bp-addr-add-new">'
					+ '<button class="bp-addr-add-btn" type="button">'
					+ '<span class="bp-addr-add-btn__icon">+</span> Add New Address'
					+ '</button></div>';
			}

			$cardArea.html( html );
		}

		function showNotice( message, type ) {
			$notice
				.removeClass( 'bp-address-book__notice--success bp-address-book__notice--error bp-address-book__notice--info' )
				.addClass( 'bp-address-book__notice--' + ( type || 'info' ) )
				.text( message )
				.removeClass( 'bp-address-book__notice--hidden' );

			setTimeout( function () {
				$notice.addClass( 'bp-address-book__notice--hidden' );
			}, 4000 );
		}

		function showFormError( message ) {
			$formError.text( message ).show();
			$( 'html, body' ).animate( { scrollTop: $formError.offset().top - 100 }, 200 );
		}

		// Minimal HTML escaping helpers — no external dependency required.
		function escHtml( str ) {
			return String( str || '' )
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;'  )
				.replace( />/g, '&gt;'  )
				.replace( /"/g, '&quot;' )
				.replace( /'/g, '&#39;' );
		}

		function escAttr( str ) {
			return escHtml( str );
		}
	}

} )( jQuery );

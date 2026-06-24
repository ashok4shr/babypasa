/**
 * Upaya Cargo — Phone & Alternate Phone columns on the WooCommerce
 * Analytics → Customers screen. Plain JS, no build step required.
 *
 * Pairs with UPAYA_Customers_Report::inject_phone_data() (PHP), which adds
 * `billing_phone` and `alternate_phone` to each row of the Customers REST
 * report. Here we register the matching columns on the React report table via
 * the `woocommerce_admin_report_table` hook.
 */
( function () {
	if ( ! window.wp || ! wp.hooks || ! wp.domReady ) {
		return;
	}

	wp.domReady( function () {
		wp.hooks.addFilter(
			'woocommerce_admin_report_table',
			'upaya/customers-phone-columns',
			function ( reportTableData ) {
				// Only modify the Customers endpoint.
				if ( 'customers' !== reportTableData.endpoint ) {
					return reportTableData;
				}

				// --- Add column headers, right after the first column (Name) ---
				reportTableData.headers.splice(
					1,
					0,
					{ label: 'Phone', key: 'billing_phone' },
					{ label: 'Alternate Phone', key: 'alternate_phone' }
				);

				// --- Add the matching cells in every row ---
				reportTableData.rows = reportTableData.rows.map( function ( row, index ) {
					var item = ( reportTableData.items && reportTableData.items.data
						? reportTableData.items.data[ index ]
						: null ) || {};

					var phoneCell = {
						display: item.billing_phone || '—',
						value: item.billing_phone || ''
					};
					var altPhoneCell = {
						display: item.alternate_phone || '—',
						value: item.alternate_phone || ''
					};

					// Insert at position 1 to match the header placement.
					row.splice( 1, 0, phoneCell, altPhoneCell );
					return row;
				} );

				return reportTableData;
			}
		);
	} );
} )();

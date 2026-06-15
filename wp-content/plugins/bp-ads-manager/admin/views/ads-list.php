<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var BP_Ads_List_Table $table */
?>
<div class="wrap bp-ads-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Ads Manager', 'bp-ads-manager' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-ads-add-new' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New Ad', 'bp-ads-manager' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php $table->views(); ?>

	<form id="bp-ads-list-form" method="post">
		<?php
		// Preserve the active tab across bulk-action submits and pagination.
		printf(
			'<input type="hidden" name="ad_type" value="%s">',
			esc_attr( $table->get_current_view() )
		);
		wp_nonce_field( 'bulk-ads' );
		$table->display();
		?>
	</form>
</div>

<script>
(function () {
	'use strict';

	var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

	// ── Toggle active status ─────────────────────────────────────────────────
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.bp-toggle-active');
		if (!btn) return;

		var id    = btn.dataset.id;
		var nonce = btn.dataset.nonce;
		btn.disabled = true;

		var fd = new FormData();
		fd.append('action', 'bp_toggle_ad_active');
		fd.append('id',     id);
		fd.append('nonce',  nonce);

		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.success) {
					btn.textContent = data.data.label;
					btn.className = 'bp-toggle-active ' + (data.data.new_state ? 'bp-toggle-on' : 'bp-toggle-off');
					// Update nonce for next click (toggle_active nonces are per-id, reuse same value since it is valid for 12 h).
				} else {
					alert('<?php echo esc_js( __( 'Could not update status. Please refresh and try again.', 'bp-ads-manager' ) ); ?>');
				}
			})
			.catch(function () {
				alert('<?php echo esc_js( __( 'Request failed. Check your connection.', 'bp-ads-manager' ) ); ?>');
			})
			.finally(function () { btn.disabled = false; });
	});

	// ── Delete single ad ──────────────────────────────────────────────────────
	document.addEventListener('click', function (e) {
		var link = e.target.closest('.bp-delete-ad');
		if (!link) return;
		e.preventDefault();

		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this ad? This cannot be undone.', 'bp-ads-manager' ) ); ?>')) {
			return;
		}

		var id    = link.dataset.id;
		var nonce = link.dataset.nonce;

		var fd = new FormData();
		fd.append('action', 'bp_delete_ad');
		fd.append('id',     id);
		fd.append('nonce',  nonce);

		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.success) {
					var row = link.closest('tr');
					if (row) row.remove();
				} else {
					alert('<?php echo esc_js( __( 'Delete failed. Please refresh and try again.', 'bp-ads-manager' ) ); ?>');
				}
			})
			.catch(function () {
				alert('<?php echo esc_js( __( 'Request failed. Check your connection.', 'bp-ads-manager' ) ); ?>');
			});
	});

	// ── Bulk delete confirmation ──────────────────────────────────────────────
	var form = document.getElementById('bp-ads-list-form');
	if (form) {
		form.addEventListener('submit', function (e) {
			var action = '';
			var selects = form.querySelectorAll('select[name="action"], select[name="action2"]');
			selects.forEach(function (s) { if (s.value !== '-1') action = s.value; });

			if (action === 'delete') {
				var checked = form.querySelectorAll('input[name="ad_ids[]"]:checked');
				if (checked.length === 0) {
					e.preventDefault();
					alert('<?php echo esc_js( __( 'Please select at least one ad.', 'bp-ads-manager' ) ); ?>');
					return;
				}
				if (!confirm('<?php echo esc_js( __( 'Delete the selected ads? This cannot be undone.', 'bp-ads-manager' ) ); ?>')) {
					e.preventDefault();
				}
			}
		});
	}
}());
</script>

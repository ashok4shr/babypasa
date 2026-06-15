/**
 * BabyPasa — Checkout enhancements.
 *
 * 1. Mobile "order summary" accordion: collapses ONLY the line-items review
 *    table to save vertical space. The totals are surfaced in the toggle bar,
 *    and the payment methods + Place Order button (also inside #order_review)
 *    are never hidden. Desktop is unaffected (CSS keeps the table open).
 * 2. Mirrors the live order total into the toggle bar, refreshed after every
 *    AJAX recalculation (updated_checkout).
 *
 * WooCommerce's checkout is jQuery-based; it replaces the review table on AJAX
 * and emits `updated_checkout`, so we re-apply our enhancements on that event.
 */
(function ($) {
	'use strict';
	if (!$) return;

	function currentTotal() {
		var $total = $('#order_review .order-total .woocommerce-Price-amount').last();
		return $total.length ? $total.html() : '';
	}

	function buildAccordion() {
		var review = document.getElementById('order_review');
		if (!review) return;

		var bar = review.querySelector('.bp-summary-toggle');
		if (!bar) {
			bar = document.createElement('button');
			bar.type = 'button';
			bar.className = 'bp-summary-toggle';
			bar.setAttribute('aria-expanded', 'false');
			bar.innerHTML =
				'<span class="bp-summary-toggle__label">' +
					'<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>' +
					'<span>Order summary</span>' +
					'<svg class="bp-summary-toggle__chevron" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>' +
				'</span>' +
				'<span class="bp-summary-toggle__total"></span>';

			// Insert the toggle as the very first child of #order_review.
			review.insertBefore(bar, review.firstChild);
			review.classList.add('bp-summary-collapsed'); // collapsed by default (mobile only via CSS)

			bar.addEventListener('click', function () {
				var collapsed = review.classList.toggle('bp-summary-collapsed');
				bar.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			});
		}

		var totalEl = bar.querySelector('.bp-summary-toggle__total');
		if (totalEl) totalEl.innerHTML = currentTotal();
	}

	$(document).on('updated_checkout', buildAccordion);
	$(function () { buildAccordion(); });
})(window.jQuery);

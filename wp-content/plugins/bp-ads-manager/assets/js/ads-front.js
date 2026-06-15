/* BP Ads Manager — Front-end popup logic */
/* global bpAdsConfig */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', init);

	function init() {
		if (!window.bpAdsConfig || !Array.isArray(bpAdsConfig.ads) || !bpAdsConfig.ads.length) {
			return;
		}

		// Sort by sort_order ascending so the sequence is deterministic.
		var ads = bpAdsConfig.ads.slice().sort(function (a, b) {
			return (a.sort_order || 0) - (b.sort_order || 0);
		});

		// Show popups sequentially: each one waits for the previous to close.
		showNextPopup(ads, 0);
	}

	/**
	 * Finds the next eligible popup in the sorted list and schedules it.
	 * Skips ads that fail device or cookie checks.
	 *
	 * @param {Array}  ads   Full sorted ads array.
	 * @param {number} index Current position in the array.
	 */
	function showNextPopup(ads, index) {
		if (index >= ads.length) return;

		var ad = ads[index];
		var w  = window.innerWidth;

		// Device targeting — skip and try the next one immediately.
		if (ad.device === 'mobile'  && w >= 768) { showNextPopup(ads, index + 1); return; }
		if (ad.device === 'desktop' && w <  768) { showNextPopup(ads, index + 1); return; }

		// Cookie / frequency check — skip and try the next one immediately.
		var cookieKey = 'bp_popup_' + ad.id + '_seen';
		if (ad.frequency === 'once' && getCookie(cookieKey)) { showNextPopup(ads, index + 1); return; }

		// Wait for this ad's delay, then show it.
		var delayMs = (parseInt(ad.popup_delay, 10) || 0) * 1000;
		setTimeout(function () {
			var overlay = document.getElementById('bp-popup-' + ad.id);
			if (!overlay) {
				// DOM element missing — skip silently.
				showNextPopup(ads, index + 1);
				return;
			}

			showPopup(overlay, ad, cookieKey, function onClosed() {
				// When this popup is closed, move to the next one.
				showNextPopup(ads, index + 1);
			});
		}, delayMs);
	}

	/**
	 * Makes the popup overlay visible and binds close handlers.
	 * Calls onClosed() after the closing transition finishes.
	 *
	 * @param {HTMLElement} overlay   The full-screen overlay element.
	 * @param {Object}      ad        Ad config.
	 * @param {string}      cookieKey Cookie name for this ad.
	 * @param {Function}    onClosed  Called after the popup is fully hidden.
	 */
	function showPopup(overlay, ad, cookieKey, onClosed) {
		overlay.style.display = 'flex';

		// Trigger CSS transition on next frame.
		requestAnimationFrame(function () {
			overlay.classList.add('bp-popup-visible');
		});

		// Set cookie so the same popup doesn't reappear for 15 minutes.
		if (ad.frequency === 'once') {
			setCookie(cookieKey, 15);
		}

		var modal = overlay.querySelector('.bp-popup-modal');

		// Clickable ad link — redirect on modal click, but NOT on the close button.
		if (ad.link_url && modal) {
			modal.style.cursor = 'pointer';
			modal.addEventListener('click', function (e) {
				if (e.target.closest('.bp-popup-close')) return;
				window.location.href = ad.link_url;
			});
		}

		// Internal close helper — fires onClosed after transition.
		function doClose() {
			closePopup(overlay, onClosed);
		}

		// Close button.
		var closeBtn = overlay.querySelector('.bp-ad-close');
		if (closeBtn) {
			closeBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				doClose();
			});
		}

		// Overlay click (outside modal).
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) doClose();
		});

		// ESC key.
		var escHandler = function (e) {
			if (e.key === 'Escape') {
				document.removeEventListener('keydown', escHandler);
				doClose();
			}
		};
		document.addEventListener('keydown', escHandler);
	}

	/**
	 * Hides the popup overlay with a CSS transition, then calls callback.
	 *
	 * @param {HTMLElement}   overlay
	 * @param {Function|null} callback  Optional — called after transition ends.
	 */
	function closePopup(overlay, callback) {
		overlay.classList.remove('bp-popup-visible');
		overlay.addEventListener('transitionend', function onEnd() {
			overlay.removeEventListener('transitionend', onEnd);
			overlay.style.display = 'none';
			if (typeof callback === 'function') callback();
		}, { once: true });
	}

	// ── Cookie helpers ────────────────────────────────────────────────────────

	/**
	 * Reads a cookie value by name.
	 *
	 * @param  {string}      name
	 * @return {string|null}
	 */
	function getCookie(name) {
		var match = document.cookie.match(
			new RegExp('(?:^|;\\s*)' + escapeRegExp(name) + '=([^;]*)')
		);
		return match ? decodeURIComponent(match[1]) : null;
	}

	/**
	 * Sets a cookie for the given number of minutes.
	 *
	 * @param {string} name
	 * @param {number} minutes
	 */
	function setCookie(name, minutes) {
		var expires = new Date();
		expires.setTime(expires.getTime() + minutes * 60 * 1000);
		document.cookie = encodeURIComponent(name) + '=1'
			+ ';expires=' + expires.toUTCString()
			+ ';path=/;SameSite=Lax';
	}

	/**
	 * Escapes a string for use inside a RegExp.
	 *
	 * @param  {string} str
	 * @return {string}
	 */
	function escapeRegExp(str) {
		return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}
}());

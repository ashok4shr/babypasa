/**
 * BabyPasa — Custom Single-Product Image Slider
 * --------------------------------------------------------------------------
 * Dependency-free replacement for WooCommerce's flexslider/photoswipe gallery.
 *
 *  • Slide track with GPU-accelerated transforms (percentage-based, so it is
 *    resize-proof without recalculating pixel widths).
 *  • Pointer-based swipe (mouse + touch), keyboard arrow navigation.
 *  • Slide counter (1 / N) — no thumbnail dots, keeps focus on the image.
 *  • Zoom is OPT-IN: a zoom button opens a full-screen viewer with click/
 *    double-tap to magnify and drag to pan. No hover zoom.
 *
 * One IIFE, no globals leaked. Re-entrant: skips galleries already booted.
 */
(function () {
	'use strict';

	var SWIPE_THRESHOLD = 40;   // px of horizontal travel to flip a slide
	var FS_ZOOM_SCALE   = 2.5;  // full-screen magnify factor
	var DOUBLE_TAP_MS   = 300;

	function clamp(v, min, max) {
		return v < min ? min : v > max ? max : v;
	}

	function ProductGallery(root) {
		this.root     = root;
		this.viewport = root.querySelector('.bp-gallery__viewport');
		this.track    = root.querySelector('.bp-gallery__track');
		this.slides   = Array.prototype.slice.call(root.querySelectorAll('.bp-gallery__slide'));
		this.stage    = root.querySelector('.bp-gallery__stage');
		this.prevBtn  = root.querySelector('.bp-gallery__nav--prev');
		this.nextBtn  = root.querySelector('.bp-gallery__nav--next');
		this.zoomBtn  = root.querySelector('.bp-gallery__zoom-btn');
		this.counter  = root.querySelector('.bp-gallery__counter-current');
		this.thumbsWrap = root.querySelector('.bp-gallery__thumbs');
		this.thumbs   = Array.prototype.slice.call(root.querySelectorAll('.bp-gallery__thumb'));

		this.index    = 0;
		this.count    = this.slides.length;
		this.fs       = null; // lazily-built full-screen viewer

		if (!this.track || this.count === 0) return;

		// Cache the large-image source for each slide once.
		this.sources = this.slides.map(function (slide) {
			var img = slide.querySelector('img');
			if (!img) return '';
			return img.getAttribute('data-large_image') || img.currentSrc || img.src;
		});

		this.bindNav();
		this.bindKeyboard();
		this.bindSwipe();
		this.bindZoom();
		this.bindThumbs();
		this.goTo(0, true);

		root.setAttribute('data-bp-gallery-ready', '1');
	}

	/* ── Slide navigation ──────────────────────────────────────────────── */
	ProductGallery.prototype.goTo = function (i, instant) {
		this.index = clamp(i, 0, this.count - 1);

		if (instant) this.track.style.transition = 'none';
		this.track.style.transform = 'translate3d(' + (-this.index * 100) + '%, 0, 0)';
		if (instant) {
			void this.track.offsetWidth; // force reflow, then restore transition
			this.track.style.transition = '';
		}

		this.slides.forEach(function (slide, idx) {
			slide.setAttribute('aria-hidden', idx === this.index ? 'false' : 'true');
		}, this);

		if (this.counter) this.counter.textContent = String(this.index + 1);
		if (this.prevBtn) this.prevBtn.disabled = this.index === 0;
		if (this.nextBtn) this.nextBtn.disabled = this.index === this.count - 1;

		this.syncThumbs(instant);
	};

	ProductGallery.prototype.next = function () { if (this.index < this.count - 1) this.goTo(this.index + 1); };
	ProductGallery.prototype.prev = function () { if (this.index > 0) this.goTo(this.index - 1); };

	/* ── Thumbnail strip ───────────────────────────────────────────────────
	 * Optional row of thumbnails rendered below the stage (only when the
	 * product has 2+ images). Clicking one drives the existing slider via
	 * goTo(); the active thumb is kept in sync from inside goTo() so it also
	 * follows swipe / arrow / full-screen navigation.
	 */
	ProductGallery.prototype.bindThumbs = function () {
		if (!this.thumbs.length) return;
		var self = this;
		this.thumbs.forEach(function (thumb) {
			thumb.addEventListener('click', function () {
				var i = parseInt(thumb.getAttribute('data-index'), 10);
				if (!isNaN(i)) self.goTo(i);
			});
		});
	};

	ProductGallery.prototype.syncThumbs = function (instant) {
		if (!this.thumbs.length) return;
		var wrap = this.thumbsWrap;
		var idx  = this.index;

		this.thumbs.forEach(function (thumb, i) {
			var active = i === idx;
			thumb.classList.toggle('is-active', active);
			if (active) {
				thumb.setAttribute('aria-current', 'true');
				if (wrap) {
					// Scroll only the strip (never the page) to reveal the active thumb.
					var target = thumb.offsetLeft - (wrap.clientWidth - thumb.offsetWidth) / 2;
					if (wrap.scrollTo) {
						wrap.scrollTo({ left: target, behavior: instant ? 'auto' : 'smooth' });
					} else {
						wrap.scrollLeft = target;
					}
				}
			} else {
				thumb.removeAttribute('aria-current');
			}
		});
	};

	ProductGallery.prototype.bindNav = function () {
		var self = this;
		if (this.nextBtn) this.nextBtn.addEventListener('click', function () { self.next(); });
		if (this.prevBtn) this.prevBtn.addEventListener('click', function () { self.prev(); });
	};

	ProductGallery.prototype.bindKeyboard = function () {
		var self = this;
		if (!this.stage) return;
		this.stage.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { e.preventDefault(); self.next(); }
			else if (e.key === 'ArrowLeft') { e.preventDefault(); self.prev(); }
			else if (e.key === 'Home') { e.preventDefault(); self.goTo(0); }
			else if (e.key === 'End') { e.preventDefault(); self.goTo(self.count - 1); }
		});
	};

	/* ── Swipe / drag ──────────────────────────────────────────────────── */
	ProductGallery.prototype.bindSwipe = function () {
		var self = this;
		var startX = 0, startY = 0, dx = 0, dy = 0;
		var dragging = false, locked = null;

		function down(e) {
			if (e.pointerType === 'mouse' && e.button !== 0) return;
			dragging = true;
			locked = null;
			self.dragMoved = false;
			startX = e.clientX;
			startY = e.clientY;
			dx = dy = 0;
			self.root.classList.add('is-dragging');
		}

		function move(e) {
			if (!dragging) return;
			dx = e.clientX - startX;
			dy = e.clientY - startY;

			if (locked === null && (Math.abs(dx) > 6 || Math.abs(dy) > 6)) {
				locked = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
			}
			if (locked !== 'x') return;

			if (Math.abs(dx) > 5) self.dragMoved = true;
			e.preventDefault();
			var atEdge = (self.index === 0 && dx > 0) || (self.index === self.count - 1 && dx < 0);
			var travel = atEdge ? dx * 0.3 : dx;
			var pct = (-self.index * 100) + (travel / self.viewport.offsetWidth) * 100;
			self.track.style.transform = 'translate3d(' + pct + '%, 0, 0)';
		}

		function up() {
			if (!dragging) return;
			dragging = false;
			self.root.classList.remove('is-dragging');
			if (locked === 'x' && Math.abs(dx) > SWIPE_THRESHOLD) {
				dx < 0 ? self.next() : self.prev();
			} else {
				self.goTo(self.index); // snap back
			}
		}

		this.viewport.addEventListener('pointerdown', down);
		this.viewport.addEventListener('pointermove', move, { passive: false });
		window.addEventListener('pointerup', up);
		window.addEventListener('pointercancel', up);
	};

	/* ── Zoom: opens the full-screen viewer at the current slide ───────── */
	ProductGallery.prototype.bindZoom = function () {
		var self = this;
		if (this.zoomBtn) {
			this.zoomBtn.addEventListener('click', function () { self.openFullscreen(self.index); });
		}
		// Tapping/clicking the active image also opens the full-screen viewer
		// (but not while the user is mid-swipe).
		this.slides.forEach(function (slide, idx) {
			slide.addEventListener('click', function () {
				if (self.root.classList.contains('is-dragging') || self.dragMoved) return;
				self.openFullscreen(idx);
			});
		});
	};

	/* ── Full-screen zoom viewer ───────────────────────────────────────── */
	ProductGallery.prototype.buildFullscreen = function () {
		if (this.fs) return this.fs;
		var self = this;

		var overlay = document.createElement('div');
		overlay.className = 'bp-gallery-fs';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', 'Product image viewer');
		overlay.innerHTML =
			'<button type="button" class="bp-gallery-fs__close" aria-label="Close">&times;</button>' +
			(this.count > 1 ? '<button type="button" class="bp-gallery-fs__nav bp-gallery-fs__nav--prev" aria-label="Previous image"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></button>' : '') +
			(this.count > 1 ? '<button type="button" class="bp-gallery-fs__nav bp-gallery-fs__nav--next" aria-label="Next image"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></button>' : '') +
			'<div class="bp-gallery-fs__stage"><img class="bp-gallery-fs__img" alt="" draggable="false" /></div>' +
			(this.count > 1 ? '<div class="bp-gallery-fs__counter"><span class="bp-gallery-fs__counter-current">1</span> / ' + this.count + '</div>' : '');

		document.body.appendChild(overlay);

		var img      = overlay.querySelector('.bp-gallery-fs__img');
		var stage    = overlay.querySelector('.bp-gallery-fs__stage');
		var closeBtn = overlay.querySelector('.bp-gallery-fs__close');
		var prev     = overlay.querySelector('.bp-gallery-fs__nav--prev');
		var nextEl   = overlay.querySelector('.bp-gallery-fs__nav--next');
		var counter  = overlay.querySelector('.bp-gallery-fs__counter-current');

		var zoomed = false, lastTap = 0;
		var panning = false, startX = 0, startY = 0, baseX = 0, baseY = 0, curX = 0, curY = 0;

		function maxOffset() {
			return {
				x: (stage.clientWidth  * (FS_ZOOM_SCALE - 1)) / 2,
				y: (stage.clientHeight * (FS_ZOOM_SCALE - 1)) / 2
			};
		}
		function applyTransform() {
			img.style.transform = zoomed
				? 'scale(' + FS_ZOOM_SCALE + ') translate(' + (curX / FS_ZOOM_SCALE) + 'px,' + (curY / FS_ZOOM_SCALE) + 'px)'
				: '';
		}
		function setZoom(on) {
			zoomed = on;
			curX = curY = 0;
			img.classList.toggle('is-zoomed', on);
			applyTransform();
		}

		function show(i) {
			self.fsIndex = clamp(i, 0, self.count - 1);
			setZoom(false);
			img.src = self.sources[self.fsIndex] || '';
			if (counter) counter.textContent = String(self.fsIndex + 1);
			if (prev)   prev.disabled   = self.fsIndex === 0;
			if (nextEl) nextEl.disabled = self.fsIndex === self.count - 1;
		}

		function close() {
			overlay.classList.remove('is-open');
			document.body.style.overflow = '';
			// Keep the inline slider in sync with where the user ended up.
			self.goTo(self.fsIndex);
			document.removeEventListener('keydown', onKey);
		}

		function onKey(e) {
			if (e.key === 'Escape') close();
			else if (e.key === 'ArrowRight' && nextEl) show(self.fsIndex + 1);
			else if (e.key === 'ArrowLeft' && prev) show(self.fsIndex - 1);
		}

		closeBtn.addEventListener('click', close);
		if (prev)   prev.addEventListener('click', function () { show(self.fsIndex - 1); });
		if (nextEl) nextEl.addEventListener('click', function () { show(self.fsIndex + 1); });

		// Click the dark backdrop (outside the image) to close.
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay || e.target === stage) close();
		});

		// Click / double-tap the image to toggle magnification.
		img.addEventListener('click', function (e) {
			e.stopPropagation();
			setZoom(!zoomed);
		});

		// Drag to pan while zoomed (pointer events cover mouse + touch).
		img.addEventListener('pointerdown', function (e) {
			if (!zoomed) return;
			panning = true; startX = e.clientX; startY = e.clientY; baseX = curX; baseY = curY;
			img.setPointerCapture && img.setPointerCapture(e.pointerId);
		});
		img.addEventListener('pointermove', function (e) {
			if (!panning) return;
			e.preventDefault();
			var lim = maxOffset();
			curX = clamp(baseX + (e.clientX - startX), -lim.x, lim.x);
			curY = clamp(baseY + (e.clientY - startY), -lim.y, lim.y);
			applyTransform();
		});
		img.addEventListener('pointerup', function () { panning = false; });

		this.fs = { overlay: overlay, show: show, close: close, onKey: onKey };
		this.fs._open = function (i) {
			show(i);
			overlay.classList.add('is-open');
			document.body.style.overflow = 'hidden';
			document.addEventListener('keydown', onKey);
		};
		return this.fs;
	};

	ProductGallery.prototype.openFullscreen = function (i) {
		this.buildFullscreen()._open(i);
	};

	/* ── Boot ──────────────────────────────────────────────────────────── */
	function init() {
		var galleries = document.querySelectorAll('.bp-gallery:not([data-bp-gallery-ready])');
		Array.prototype.forEach.call(galleries, function (el) { new ProductGallery(el); });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
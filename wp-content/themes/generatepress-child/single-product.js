/**
 * Single Product Page Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    initQuantityButtons();
    initSliders();
    initVideoModal();
    initProductTabs();
    initAddToCartRipple();
    initColorSwatches();
    initVariableSavings();
    // The sticky Add-to-Cart bar is now handled by the separately enqueued
    // assets/js/sticky-add-to-cart.js (handle: bp-sticky-add-to-cart). The old
    // in-file boundary-sticky was removed to avoid double-pinning the same bar.
});

/**
 * Handle quantity increment/decrement buttons
 */
function initQuantityButtons() {
    document.querySelectorAll('.bp-quantity-wrapper').forEach(wrapper => {
        const input = wrapper.querySelector('input.qty');
        const minusBtn = wrapper.querySelector('.bp-minus');
        const plusBtn = wrapper.querySelector('.bp-plus');

        if (!input || !minusBtn || !plusBtn) return;

        minusBtn.addEventListener('click', () => {
            const val = parseFloat(input.value);
            const step = parseFloat(input.getAttribute('step')) || 1;
            const min = parseFloat(input.getAttribute('min')) || 1;
            if (val > min) {
                input.value = val - step;
                input.dispatchEvent(new Event('change'));
            }
        });

        plusBtn.addEventListener('click', () => {
            const val = parseFloat(input.value);
            const step = parseFloat(input.getAttribute('step')) || 1;
            const max = parseFloat(input.getAttribute('max'));
            if (!max || val < max) {
                input.value = val + step;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
}

/**
 * Initialize Sliders (Related Products / Cross-sells)
 * Re-uses logic compatible with bp-product-slider
 */
function initSliders() {
    const sliders = document.querySelectorAll('.bp-product-slider');
    
    sliders.forEach(slider => {
        const track = slider.querySelector('.bp-slider-track');
        const section = slider.closest('.bp-products-section');
        const prevBtn = section ? section.querySelector('.bp-prev-btn') : null;
        const nextBtn = section ? section.querySelector('.bp-next-btn') : null;
        
        if (!track) return;

        let currentIndex = 0;
        
        function updateSlider() {
            const slideWidth = slider.offsetWidth / getVisibleSlides();
            track.style.transform = `translateX(-${currentIndex * slideWidth}px)`;
        }

        function getVisibleSlides() {
            if (window.innerWidth <= 600) return 1.5;
            if (window.innerWidth <= 900) return 2.5;
            if (window.innerWidth <= 1200) return 3.5;
            return 4.5;
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalSlides = track.children.length;
                const visible = getVisibleSlides();
                if (currentIndex < totalSlides - visible) {
                    currentIndex++;
                    updateSlider();
                }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateSlider();
                }
            });
        }

        // Handle resize
        window.addEventListener('resize', updateSlider);

        // Initial setup
        updateSlider();
    });
}

function initVideoModal() {
    var modal    = document.getElementById('bp-video-modal');
    if (!modal) return;

    var player   = modal.querySelector('.bp-video-player');
    var overlay  = modal.querySelector('.bp-video-modal-overlay');
    var closeBtn = modal.querySelector('.bp-video-modal-close');

    function openModal(videoUrl) {
        player.src = videoUrl;
        modal.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';

        // Autoplay as soon as the modal is visible. The thumbnail click is a
        // genuine user gesture, so most browsers allow playback WITH sound from
        // this call stack — no muted fallback needed. playsInline keeps iOS from
        // hijacking the modal with its native fullscreen player.
        player.playsInline = true;
        player.load();
        var playAttempt = player.play();
        if (playAttempt && typeof playAttempt.catch === 'function') {
            // Some browsers (or strict autoplay policies) reject the promise.
            // Swallow it so no uncaught error surfaces — the <video controls>
            // UI lets the user start playback manually in that case.
            playAttempt.catch(function () {});
        }
    }

    function closeModal() {
        modal.setAttribute('hidden', '');
        document.body.style.overflow = '';
        player.pause();
        player.removeAttribute('src');
        player.load();
    }

    document.querySelectorAll('.bp-video-thumb').forEach(function(thumb) {
        thumb.addEventListener('click', function() {
            openModal(this.dataset.video);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (overlay)  overlay.addEventListener('click', closeModal);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.hasAttribute('hidden')) closeModal();
    });
}

/**
 * Minimal underline-style product tabs (Details / Reviews).
 * Accessible: roving tabindex + arrow-key navigation between tabs.
 * Any in-page link to #reviews activates the Reviews tab and scrolls to it.
 */
function initProductTabs() {
    var wrap = document.getElementById('bp-product-tabs');
    if (!wrap) return;

    var tabs   = Array.prototype.slice.call(wrap.querySelectorAll('.bp-tab-btn'));
    var panels = Array.prototype.slice.call(wrap.querySelectorAll('.bp-tab-panel'));
    if (!tabs.length) return;

    function activate(tab, focus) {
        tabs.forEach(function (t) {
            var selected = t === tab;
            t.classList.toggle('is-active', selected);
            t.setAttribute('aria-selected', selected ? 'true' : 'false');
            t.tabIndex = selected ? 0 : -1;
        });
        panels.forEach(function (p) {
            var show = p.id === tab.getAttribute('aria-controls');
            p.classList.toggle('is-active', show);
            if (show) { p.removeAttribute('hidden'); } else { p.setAttribute('hidden', ''); }
        });
        if (focus) tab.focus();
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () { activate(tab); });
        tab.addEventListener('keydown', function (e) {
            var dir = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
            if (!dir) return;
            e.preventDefault();
            activate(tabs[(i + dir + tabs.length) % tabs.length], true);
        });
    });

    // Route #reviews links (e.g. "Be the first to review") to the Reviews tab.
    var reviewsTab = document.getElementById('bp-tab-reviews');
    if (reviewsTab) {
        document.querySelectorAll('a[href="#reviews"], a.woocommerce-review-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                activate(reviewsTab);
                document.getElementById('bp-product-tabs').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // If the page loads with #reviews in the URL, open that tab.
    if (window.location.hash === '#reviews' && reviewsTab) activate(reviewsTab);
}

/**
 * Material-style ripple + press feedback on the Add to Cart button.
 * Works for both the simple form button and the variable form button
 * (event delegation, so dynamically shown variation buttons are covered).
 */
function initAddToCartRipple() {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.single_add_to_cart_button') : null;
        if (!btn || btn.classList.contains('disabled')) return;

        var rect = btn.getBoundingClientRect();
        var size = Math.max(rect.width, rect.height);
        var ripple = document.createElement('span');
        ripple.className = 'bp-ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top  = (e.clientY - rect.top  - size / 2) + 'px';
        btn.appendChild(ripple);
        ripple.addEventListener('animationend', function () { ripple.remove(); });
    }, true);
}

/**
 * Convert color variation <select>s into circular color swatches.
 * The real <select> is kept (hidden) and driven by the swatches so WooCommerce's
 * variation engine keeps working untouched — swatches just proxy its value.
 */
function initColorSwatches() {
    var COLOR_MAP = {
        blue: '#2f6fed', brown: '#8b5a2b', green: '#3aa657', red: '#e23744',
        black: '#222', white: '#fff', grey: '#9e9e9e', gray: '#9e9e9e',
        yellow: '#f5c518', orange: '#ff8c1a', pink: '#ff6fa5', purple: '#8e44ad',
        beige: '#e8d8c3', navy: '#1f2d52'
    };

    document.querySelectorAll('.bp-variations-table select').forEach(function (select) {
        var name = (select.getAttribute('name') || '').toLowerCase();
        if (name.indexOf('color') === -1 && name.indexOf('colour') === -1) return; // styled <select> stays for non-color attrs

        var td = select.closest('td') || select.parentNode;
        var wrap = document.createElement('div');
        wrap.className = 'bp-swatches';

        var options = Array.prototype.slice.call(select.options).filter(function (o) { return o.value !== ''; });

        options.forEach(function (opt) {
            var slug = opt.value.toLowerCase();
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'bp-swatch';
            chip.setAttribute('data-value', opt.value);
            chip.setAttribute('title', opt.textContent);
            chip.setAttribute('aria-label', opt.textContent);
            chip.style.setProperty('--swatch-color', COLOR_MAP[slug] || slug || '#ccc');
            chip.addEventListener('click', function () {
                select.value = opt.value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            wrap.appendChild(chip);
        });

        td.classList.add('bp-has-swatches');
        select.insertAdjacentElement('beforebegin', wrap);

        function sync() {
            wrap.querySelectorAll('.bp-swatch').forEach(function (c) {
                var on = c.getAttribute('data-value') === select.value;
                c.classList.toggle('is-active', on);
                c.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }
        // Keep swatches in sync with WooCommerce changes (selection + "Clear").
        select.addEventListener('change', sync);
        sync();
    });
}

/**
 * For variable products, update the savings badge as variations are chosen.
 * Uses WooCommerce's own variation events on the form.
 */
function initVariableSavings() {
    var form    = document.querySelector('.variations_form');
    var savings = document.querySelector('.bp-savings');
    // WooCommerce's variation engine (wc-add-to-cart-variation) is jQuery-based,
    // so its found_variation/reset_data events are only observable via jQuery.
    if (!form || !savings || !window.jQuery) return;

    window.jQuery(form)
        .on('found_variation', function (evt, variation) { renderSavings(variation); })
        .on('reset_data hide_variation', function () {
            savings.hidden = true;
            savings.textContent = '';
        });

    function renderSavings(variation) {
        if (!variation || !variation.display_regular_price || !variation.display_price) {
            savings.hidden = true; return;
        }
        var reg = parseFloat(variation.display_regular_price);
        var now = parseFloat(variation.display_price);
        if (!(reg > 0 && now > 0 && now < reg)) { savings.hidden = true; return; }
        var pct = Math.round(((reg - now) / reg) * 100);
        // Mirror the currency formatting of the visible price as best we can.
        savings.innerHTML = 'Save ' + formatMoney(reg - now) + ' (' + pct + '%)';
        savings.hidden = false;
    }

    function formatMoney(amount) {
        // Reuse the symbol from the rendered price; default to a plain number otherwise.
        var priceEl = document.querySelector('.bp-single-price .woocommerce-Price-amount bdi, .bp-single-price');
        var symbol = '';
        if (priceEl) {
            var m = priceEl.textContent.match(/^[^0-9]+/);
            if (m) symbol = m[0].trim();
        }
        return symbol + amount.toFixed(2);
    }
}


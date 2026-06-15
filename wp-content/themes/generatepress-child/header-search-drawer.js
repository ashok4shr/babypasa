(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────────
    var toggles   = Array.from(document.querySelectorAll('.bp-search-toggle'));
    var drawer    = document.getElementById('bp-search-drawer');
    var closeBtn  = document.getElementById('bp-search-drawer-close');
    var overlay   = document.getElementById('bp-search-overlay');
    var input     = document.getElementById('bp-search-drawer-input');
    var results   = document.getElementById('bp-search-drawer-results');

    if (!toggles.length || !drawer) return;

    // ── Config from wp_localize_script ────────────────────────────────────
    var cfg          = window.bpSearchIndex || {};
    var ajaxUrl      = cfg.ajaxUrl      || '';
    var nonce        = cfg.nonce        || '';
    var indexVersion = cfg.indexVersion || '0';
    var i18n         = cfg.i18n         || {};
    var sessionKey   = 'bpProductIndex_v' + indexVersion;

    // ── Product index — starts empty, fetched lazily ──────────────────────
    var products     = [];
    var indexLoaded  = false;
    var indexLoading = false;
    var activeToggle = null;

    // ── Lazy-load: sessionStorage → AJAX fallback ─────────────────────────
    function loadIndex(callback) {
        if (indexLoaded) { callback(); return; }

        // Try sessionStorage first (zero network cost within same session)
        try {
            var cached = sessionStorage.getItem(sessionKey);
            if (cached) {
                products    = JSON.parse(cached);
                indexLoaded = true;
                callback();
                return;
            }
        } catch (e) {}

        // Prevent duplicate in-flight requests
        if (indexLoading) return;
        indexLoading = true;

        if (results) {
            results.innerHTML = '<div class="bp-search-loading">'
                + escHtml(i18n.loading || 'Loading…') + '</div>';
        }

        var body = new FormData();
        body.append('action', 'bp_get_search_index');
        body.append('nonce', nonce);

        fetch(ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success && Array.isArray(res.data)) {
                    products    = res.data;
                    indexLoaded = true;
                    try {
                        sessionStorage.setItem(sessionKey, JSON.stringify(products));
                    } catch (e) {}
                }
            })
            .catch(function () {})
            .finally(function () {
                indexLoading = false;
                callback();
            });
    }

    // ── Drawer open / close ───────────────────────────────────────────────
    function openDrawer(e) {
        activeToggle = e.currentTarget;

        // Close mobile nav first if it's open (avoids two drawers at once)
        var mobileNav     = document.querySelector('.bp-navigation');
        var mobileOverlay = document.querySelector('.bp-mobile-overlay');
        if (mobileNav && mobileNav.classList.contains('is-open')) {
            mobileNav.classList.remove('is-open');
            if (mobileOverlay) mobileOverlay.classList.remove('is-open');
        }

        drawer.classList.add('is-open');
        if (overlay) overlay.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        if (overlay) overlay.setAttribute('aria-hidden', 'false');
        toggles.forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
        activeToggle.setAttribute('aria-expanded', 'true');

        if (input) setTimeout(function () { input.focus(); }, 60);

        // Load index then render (instant if already loaded/cached)
        loadIndex(function () {
            renderResults(input ? input.value : '');
        });
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        if (overlay) overlay.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        if (overlay) overlay.setAttribute('aria-hidden', 'true');
        toggles.forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
        if (input)   input.value = '';
        if (results) results.innerHTML = '';
        if (activeToggle) activeToggle.focus();
    }

    toggles.forEach(function (t) { t.addEventListener('click', openDrawer); });
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay)  overlay.addEventListener('click', closeDrawer);

    // ── Keyboard: Escape closes drawer ───────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer();
    });

    // ── Focus trap inside drawer ──────────────────────────────────────────
    drawer.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        var focusable = Array.from(drawer.querySelectorAll(
            'input:not([disabled]), button:not([disabled]), a[href]'
        )).filter(function (el) { return el.offsetParent !== null; });
        if (!focusable.length) return;
        var first = focusable[0];
        var last  = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    });

    // ── Client-side search ────────────────────────────────────────────────
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlight(text, query) {
        if (!query) return escHtml(text);
        var safe = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return escHtml(text).replace(new RegExp('(' + safe + ')', 'gi'), '<strong>$1</strong>');
    }

    function renderResults(query) {
        if (!results) return;

        var term     = query.trim().toLowerCase();
        var filtered = term
            ? products.filter(function (p) {
                return p.name.toLowerCase().indexOf(term) !== -1
                    || (p.sku      && p.sku.toLowerCase().indexOf(term)      !== -1)
                    || (p.category && p.category.toLowerCase().indexOf(term) !== -1);
              })
            : products.slice();

        if (!filtered.length) {
            results.innerHTML = '<div class="bp-search-empty">'
                + escHtml(i18n.noResults || 'No products found.') + '</div>';
            return;
        }

        var html = filtered.map(function (p) {
            var badge = '';
            if (!p.in_stock) {
                badge = '<span class="bp-result-badge bp-badge-out-of-stock">'
                    + escHtml(i18n.outOfStock || 'Out of Stock') + '</span>';
            } else if (p.on_sale) {
                badge = '<span class="bp-result-badge bp-badge-sale">'
                    + escHtml(i18n.sale || 'Sale') + '</span>';
            }

            return '<a href="' + escHtml(p.url) + '" class="bp-result-item">'
                + '<div class="bp-result-img-wrapper">'
                + (p.image ? '<img src="' + escHtml(p.image) + '" alt="' + escHtml(p.name)
                           + '" class="bp-result-img" loading="lazy">' : '')
                + badge
                + '</div>'
                + '<div class="bp-result-content">'
                + '<div class="bp-result-title">' + highlight(p.name, term) + '</div>'
                + '<div class="bp-result-price">' + p.price + '</div>'
                + '</div>'
                + '</a>';
        }).join('');

        if (term && cfg.viewAllUrl) {
            var label = (i18n.viewAll || 'See all results for "%s"')
                .replace('%s', escHtml(query.trim()));
            html += '<a href="' + escHtml(cfg.viewAllUrl + '&s=' + encodeURIComponent(query.trim()))
                 + '" class="bp-view-all">' + label + '</a>';
        }

        results.innerHTML = html;
    }

    // Live filter — instant client-side once index is loaded
    if (input) {
        input.addEventListener('input', function () {
            var val = this.value;
            if (!indexLoaded) {
                loadIndex(function () { renderResults(val); });
            } else {
                renderResults(val);
            }
        });
    }
})();

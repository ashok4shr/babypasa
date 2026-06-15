/**
 * Quick-Add Modal — Variable Product Variation Picker
 *
 * Flow:
 *  1. User clicks .bp-quick-add-btn on a product card
 *  2. Modal opens with a spinner
 *  3. Variation data is fetched via AJAX (attributes + all variation combinations)
 *  4. Attribute <select> elements are rendered
 *  5. On each change, JS finds the matching variation_id
 *  6. "Add to Cart" button enabled only when a complete, in-stock variation is matched
 *  7. Clicking "Add to Cart" posts to bp_quick_add_to_cart, updates cart badge,
 *     closes the modal, and shows a toast notification
 */

(function () {
    'use strict';

    const overlay = document.getElementById('bp-quick-add-overlay');
    const modal   = document.getElementById('bp-quick-add-modal');
    const body    = modal ? modal.querySelector('.bp-quick-add-body') : null;

    if (!overlay || !modal || !body) return;

    let currentVariations = [];
    let currentProductId  = 0;

    // ── Open / Close ──────────────────────────────────────────────────────────

    function openModal() {
        overlay.classList.add('bp-qa-open');
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('bp-qa-open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('bp-qa-open');
        modal.classList.remove('bp-qa-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        currentVariations = [];
        currentProductId  = 0;
    }

    overlay.addEventListener('click', closeModal);
    modal.querySelector('.bp-quick-add-close').addEventListener('click', closeModal);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ── Button click (event delegation) ───────────────────────────────────────

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.bp-quick-add-btn');
        if (!btn) return;

        const productId = parseInt(btn.dataset.product_id, 10);
        if (!productId) return;

        currentProductId = productId;
        body.innerHTML   = '<div class="bp-qa-loading"><span class="bp-qa-spinner"></span></div>';
        openModal();
        fetchVariations(productId);
    });

    // ── Fetch variation data ───────────────────────────────────────────────────

    function fetchVariations(productId) {
        const data = new FormData();
        data.append('action',     'bp_get_product_variations');
        data.append('nonce',      bpQuickAdd.nonce);
        data.append('product_id', productId);

        fetch(bpQuickAdd.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    renderModal(res.data);
                } else {
                    body.innerHTML = '<div class="bp-qa-error">Could not load product options. Please <a href="' + window.location.href + '">try again</a>.</div>';
                }
            })
            .catch(function () {
                body.innerHTML = '<div class="bp-qa-error">Network error. Please check your connection.</div>';
            });
    }

    // ── Render modal content ───────────────────────────────────────────────────

    function renderModal(data) {
        currentVariations = data.variations || [];

        // Header
        const headerHTML =
            '<div class="bp-qa-header">' +
            '  <img class="bp-qa-image" src="' + escAttr(data.image) + '" alt="' + escAttr(data.name) + '">' +
            '  <div class="bp-qa-info">' +
            '    <h3 class="bp-qa-name">' + escHtml(data.name) + '</h3>' +
            '    <div class="bp-qa-price" id="bp-qa-price">' + data.price_html + '</div>' +
            '  </div>' +
            '</div>';

        // Attribute selects
        let attributesHTML = '<div class="bp-qa-attributes">';
        const attributes   = data.attributes || {};

        for (const [attrSlug, values] of Object.entries(attributes)) {
            const label = formatAttributeLabel(attrSlug);
            const selectId = 'bp-qa-attr-' + attrSlug;

            attributesHTML += '<div class="bp-qa-attribute">';
            attributesHTML += '<label for="' + escAttr(selectId) + '">' + escHtml(label) + '</label>';
            attributesHTML += '<select id="' + escAttr(selectId) + '" data-attribute="' + escAttr('attribute_' + attrSlug) + '">';
            attributesHTML += '<option value="">Choose ' + escHtml(label) + '</option>';

            values.forEach(function (value) {
                attributesHTML += '<option value="' + escAttr(value) + '">' + escHtml(formatValueLabel(value)) + '</option>';
            });

            attributesHTML += '</select></div>';
        }

        attributesHTML += '</div>';

        const footerHTML =
            '<div class="bp-qa-variation-msg" id="bp-qa-msg"></div>' +
            '<button class="bp-qa-add-btn" id="bp-qa-add-btn" disabled>Select options above</button>';

        body.innerHTML = headerHTML + attributesHTML + footerHTML;

        // Attach change listeners
        body.querySelectorAll('.bp-qa-attribute select').forEach(function (sel) {
            sel.addEventListener('change', onAttributeChange);
        });

        document.getElementById('bp-qa-add-btn').addEventListener('click', onAddToCart);
    }

    // ── Attribute change — find matching variation ─────────────────────────────

    function onAttributeChange() {
        const selects   = body.querySelectorAll('.bp-qa-attribute select');
        const chosen    = {};
        let   allChosen = true;

        selects.forEach(function (sel) {
            const attr = sel.dataset.attribute; // e.g. "attribute_pa_size"
            chosen[attr] = sel.value;
            if (!sel.value) allChosen = false;
        });

        const addBtn = document.getElementById('bp-qa-add-btn');
        const msgEl  = document.getElementById('bp-qa-msg');

        if (!allChosen) {
            addBtn.disabled   = true;
            addBtn.textContent = 'Select options above';
            msgEl.textContent  = '';
            msgEl.className    = 'bp-qa-variation-msg';
            return;
        }

        // Find matching variation
        const match = findVariation(chosen);

        if (!match) {
            addBtn.disabled    = true;
            addBtn.textContent = 'Unavailable';
            msgEl.textContent  = 'This combination is not available.';
            msgEl.className    = 'bp-qa-variation-msg';
            return;
        }

        if (!match.is_in_stock) {
            addBtn.disabled    = true;
            addBtn.textContent = 'Out of stock';
            msgEl.textContent  = 'Out of stock';
            msgEl.className    = 'bp-qa-variation-msg out-of-stock';
            return;
        }

        // Update price display
        if (match.price_html) {
            const priceEl = document.getElementById('bp-qa-price');
            if (priceEl) priceEl.innerHTML = match.price_html;
        }

        addBtn.disabled            = false;
        addBtn.textContent         = 'Add to Cart';
        addBtn.dataset.variationId = match.variation_id;
        msgEl.textContent          = '';
        msgEl.className            = 'bp-qa-variation-msg';
    }

    /**
     * Find a variation that matches all chosen attribute values.
     * WooCommerce variations can have empty attribute values meaning "any" — handle that.
     */
    function findVariation(chosen) {
        return currentVariations.find(function (v) {
            return Object.entries(chosen).every(function ([attr, val]) {
                const varVal = v.attributes[attr] || '';
                return varVal === '' || varVal === val;
            });
        }) || null;
    }

    // ── Add to cart ───────────────────────────────────────────────────────────

    function onAddToCart() {
        const addBtn = document.getElementById('bp-qa-add-btn');
        if (!addBtn || addBtn.disabled) return;

        const variationId = parseInt(addBtn.dataset.variationId, 10);
        if (!variationId) return;

        // Collect selected attribute values
        const attributes = {};
        body.querySelectorAll('.bp-qa-attribute select').forEach(function (sel) {
            attributes[sel.dataset.attribute] = sel.value;
        });

        // Disable button during request
        addBtn.disabled    = true;
        addBtn.textContent = 'Adding…';

        const data = new FormData();
        data.append('action',       'bp_quick_add_to_cart');
        data.append('nonce',        bpQuickAdd.nonce);
        data.append('product_id',   currentProductId);
        data.append('variation_id', variationId);

        Object.entries(attributes).forEach(function ([key, val]) {
            data.append('attributes[' + key + ']', val);
        });

        fetch(bpQuickAdd.ajaxUrl, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    // Apply WooCommerce fragments (cart count badge, mini-cart)
                    if (res.data.fragments) {
                        applyFragments(res.data.fragments);
                    }
                    closeModal();
                    showSuccessNotification();
                } else {
                    addBtn.disabled    = false;
                    addBtn.textContent = 'Add to Cart';
                    const msg = (res.data && res.data.message) || 'Could not add to cart.';
                    const msgEl = document.getElementById('bp-qa-msg');
                    if (msgEl) {
                        msgEl.textContent = msg;
                        msgEl.className   = 'bp-qa-variation-msg out-of-stock';
                    }
                }
            })
            .catch(function () {
                addBtn.disabled    = false;
                addBtn.textContent = 'Add to Cart';
            });
    }

    // ── Fragment application (cart count badge + mini-cart HTML) ──────────────

    function applyFragments(fragments) {
        Object.entries(fragments).forEach(function ([selector, html]) {
            document.querySelectorAll(selector).forEach(function (el) {
                el.outerHTML = html;
            });
        });
    }

    // ── Success toast using existing site notification system ─────────────────

    function showSuccessNotification() {
        // The babypasa-wishlist-compare plugin exposes bpShowNotification globally.
        if (typeof window.bpShowNotification === 'function') {
            window.bpShowNotification({
                title:   'Added to cart!',
                message: 'Your item has been added to the cart.',
                type:    'success',
            });
            return;
        }

        // Fallback: fire WooCommerce's native added_to_cart event so any cart
        // fragments listening on jQuery also update (e.g. mini-cart drawer).
        if (window.jQuery) {
            jQuery(document.body).trigger('added_to_cart');
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function escAttr(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;');
    }

    /** Convert "pa_color" → "Color", "pa_shoe-size" → "Shoe size" */
    function formatAttributeLabel(slug) {
        return slug
            .replace(/^pa_/, '')
            .replace(/-/g, ' ')
            .replace(/^./, function (c) { return c.toUpperCase(); });
    }

    /** Convert "red-rose" → "Red rose" */
    function formatValueLabel(val) {
        return String(val)
            .replace(/-/g, ' ')
            .replace(/^./, function (c) { return c.toUpperCase(); });
    }
}());

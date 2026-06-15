document.addEventListener('DOMContentLoaded', function() {

    // Mobile hamburger menu toggle
    const mobileToggle  = document.querySelector('.bp-mobile-menu-toggle');
    const mobileClose   = document.querySelector('.bp-mobile-menu-close');
    const navigation    = document.querySelector('.bp-navigation');
    const mobileOverlay = document.querySelector('.bp-mobile-overlay');

    if (mobileToggle && navigation) {
        // ── Stacking-context escape ───────────────────────────────────────────
        // .bp-bottom-header has position:sticky + z-index:999, which creates its
        // own stacking context. Any child — no matter how high its own z-index —
        // can never visually beat elements in the root context with z-index > 999
        // (e.g. .bp-shop-sidebar at 10000, .mbn-bar at 9999).
        //
        // Fix: on mobile, move both elements to <body> so they join the ROOT
        // stacking context directly, where their z-index values (10001/10002) work
        // as intended. position:fixed elements don't need a specific DOM parent for
        // layout — only for stacking — so the visual position is unaffected.
        // Desktop is skipped (toggle button is display:none) so the nav stays in
        // the header flex layout and the desktop menu is unchanged.
        if (window.getComputedStyle(mobileToggle).display !== 'none') {
            if (mobileOverlay) document.body.appendChild(mobileOverlay);
            document.body.appendChild(navigation);
        }

        const toggleMenu = () => {
            navigation.classList.toggle('is-open');
            if (mobileOverlay) mobileOverlay.classList.toggle('is-open');
        };

        mobileToggle.addEventListener('click', toggleMenu);
        if (mobileClose)   mobileClose.addEventListener('click', toggleMenu);
        if (mobileOverlay) mobileOverlay.addEventListener('click', toggleMenu);

        // Submenu accordion on mobile: tap toggles open/closed; only one branch open at a time.
        // Event delegation from the nav element covers top-level and nested parent items alike.
        navigation.addEventListener('click', function(e) {
            if (window.getComputedStyle(mobileToggle).display === 'none') return;

            const link = e.target.closest('.bp-menu li.menu-item-has-children > a');
            if (!link) return;

            e.preventDefault();

            const li      = link.parentElement;
            const isOpen  = li.classList.contains('mobile-open');

            // Collapse any open siblings at this level (and all their open descendants)
            Array.from(li.parentElement.children).forEach(sib => {
                if (sib !== li && sib.classList.contains('mobile-open')) {
                    sib.classList.remove('mobile-open');
                    sib.querySelectorAll('.mobile-open').forEach(el => el.classList.remove('mobile-open'));
                }
            });

            if (isOpen) {
                // Close: clear this item and all its open descendants
                li.classList.remove('mobile-open');
                li.querySelectorAll('.mobile-open').forEach(el => el.classList.remove('mobile-open'));
            } else {
                li.classList.add('mobile-open');
            }
        });
    }
});

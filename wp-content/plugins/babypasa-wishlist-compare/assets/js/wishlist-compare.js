jQuery(document).ready(function($) {
    
    // Config
    const maxCompare = 3;
    const compareStorageKey = 'bp_compare_list';
    
    // Data
    let wishlistItems = bpWishlistCompare.wishlist.map(Number) || [];
    
    function getCompareItems() {
        try {
            return JSON.parse(localStorage.getItem(compareStorageKey)) || [];
        } catch (e) {
            return [];
        }
    }
    
    function setCompareItems(items) {
        localStorage.setItem(compareStorageKey, JSON.stringify(items));
    }

    // Notification Builder (Slide-in from right)
    // notifType: optional string key — if provided, a second notification of the same type
    // will not be inserted while the first is still in the DOM.
    function showBpNotification(title, message, btnText = '', btnUrl = '', notifType = '') {
        let $container = $('.bp-notification-container');
        if (!$container.length) {
            $('body').append('<div class="bp-notification-container"></div>');
            $container = $('.bp-notification-container');
        }

        // Deduplication guard: skip if a notification of this type is already visible
        if (notifType && $container.find('.bp-notification[data-notif-type="' + notifType + '"]').length) {
            return;
        }

        const notificationId = 'bp-toast-' + Date.now();

        let actionsHtml = '';
        if (btnText && btnUrl) {
            actionsHtml = `<div class="bp-notification-actions"><a href="${btnUrl}" class="bp-notification-btn">${btnText}</a></div>`;
        }

        const typeAttr = notifType ? ` data-notif-type="${notifType}"` : '';

        const html = `
            <div class="bp-notification" id="${notificationId}"${typeAttr}>
                <button class="bp-notification-close">&times;</button>
                <div class="bp-notification-content">
                    <h4 class="bp-notification-title">${title}</h4>
                    <div class="bp-notification-message">${message}</div>
                </div>
                ${actionsHtml}
            </div>
        `;
        
        const $notification = $(html);
        $container.append($notification);
        
        // Trigger reflow for animation
        $notification[0].offsetWidth;
        $notification.addClass('bp-show');
        
        // Auto remove after 5 seconds
        const timeout = setTimeout(() => {
            $notification.removeClass('bp-show').addClass('bp-hiding');
            setTimeout(() => $notification.remove(), 300);
        }, 5000);

        $notification.find('.bp-notification-close').on('click', function() {
            clearTimeout(timeout);
            $notification.removeClass('bp-show').addClass('bp-hiding');
            setTimeout(() => $notification.remove(), 300);
        });
    }

    // WooCommerce Add to Cart Hook
    $('body').on('added_to_cart', function(event, fragments, cart_hash, $button) {
        let cartUrl = '/cart/'; // fallback
        if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.cart_url) {
            cartUrl = wc_add_to_cart_params.cart_url;
        }
        showBpNotification('Added to Cart', '<p>The item has been added to your cart.</p>', 'View Cart', cartUrl);
    });

    // Single Product AJAX Add to Cart Integration
    $(document).on('submit', 'form.cart', function(e) {
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');

        if ($form.closest('.product').hasClass('product-type-external')) return;
        
        e.preventDefault();
        if ($btn.hasClass('bp-loading')) return;

        $btn.addClass('bp-loading');
        
        var formData = new FormData($form[0]);

        // Fix: double quantity in cart — the wc-ajax=add_to_cart endpoint is reached
        // during a full WP request, so BOTH WC_Form_Handler::add_to_cart_action()
        // (wp_loaded, reads $_REQUEST['add-to-cart']) AND WC_AJAX::add_to_cart()
        // (template_redirect, reads $_POST['product_id']) run on the same request.
        // If the payload carries `add-to-cart`, the item is added TWICE (qty 1 → 2).
        // Strip it so only the product_id path below adds the item. Simple products
        // get `add-to-cart` from the submit button's name (previously appended here);
        // variable products get it from a hidden <input name="add-to-cart">, which
        // new FormData() already serialised — delete() covers both cases.
        formData.delete('add-to-cart');

        // Fix: cart icon update — wc-ajax=add_to_cart reads $_POST['product_id'] and
        // returns early if it's missing (the WooCommerce `add-to-cart` form field is
        // ignored). Without product_id the add silently no-ops, so nothing is added and
        // .bp-cart-count never refreshes. Send product_id: the submit button value for
        // simple products, the chosen variation id for variable products (the endpoint
        // expects the variation id as product_id).
        var productId = $form.hasClass('variations_form')
            ? $form.find('input.variation_id').val()
            : $btn.val();
        if (productId && productId !== '0') {
            formData.append('product_id', productId);
        }

        var ajaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart');

        $.ajax({
            type: 'POST',
            url: ajaxUrl,
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $btn.removeClass('bp-loading');
                
                if (response.error && response.product_url) {
                    window.location = response.product_url;
                    return;
                }
                
                // Trigger global WC event, updating fragments and popping our toast!
                $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $btn]);
            },
            error: function() {
                $btn.removeClass('bp-loading');
                // Fallback to normal submission if AJAX totally fails
                $form.off('submit').submit();
            }
        });
    });

    // Init buttons state on load
    function initButtonsState() {
        const compareItems = getCompareItems();
        
        $('.bp-wishlist-btn').each(function() {
            const id = parseInt($(this).data('product_id'));
            if (id && wishlistItems.includes(id)) {
                $(this).addClass('in-wishlist').attr('aria-pressed', 'true');
            }
        });
        
        $('.bp-compare-btn').each(function() {
            const id = parseInt($(this).data('product_id'));
            if (id && compareItems.includes(id)) {
                $(this).addClass('in-compare').attr('aria-pressed', 'true');
            }
        });
    }
    
    // Sidebar Refresh Logic
    function refreshSidebarWidgets() {
        const $sidebar = $('.bp-shop-sidebar');
        if (!$sidebar.length) return;

        const compareItems = getCompareItems();
        
        $.ajax({
            url: bpWishlistCompare.ajax_url,
            type: 'POST',
            data: {
                action: 'bp_get_sidebar_widget_data',
                nonce: bpWishlistCompare.nonce,
                wishlist_ids: wishlistItems,
                compare_ids: compareItems
            },
            success: function(response) {
                if (response.success) {
                    $('.bp-wishlist-content').html(response.data.wishlist);
                    $('.bp-compare-content').html(response.data.compare);
                    
                    // Update Counts
                    $('.bp-wishlist .bp-count').text('(' + response.data.wishlist_count + ' items)');
                    $('.bp-compare .bp-count').text('(' + response.data.compare_count + ' items)');
                    
                    if (response.data.wishlist_count === 0) $('.bp-wishlist .bp-count').hide();
                    else $('.bp-wishlist .bp-count').show();

                    if (response.data.compare_count === 0) $('.bp-compare .bp-count').hide();
                    else $('.bp-compare .bp-count').show();
                }
            }
        });
    }

    initButtonsState();
    refreshSidebarWidgets();

    // Wishlist Toggle
    $(document).on('click', '.bp-wishlist-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const productId = parseInt($btn.data('product_id'));
        
        if (!productId) return;

        if (!bpWishlistCompare.is_user_logged_in) {
            showBpNotification('Login Required', '<p>Please log in to add items to your wishlist and securely save them.</p>', 'Log In', bpWishlistCompare.login_url, 'login-required');
            return;
        }

        if ($btn.hasClass('bp-loading')) return;

        $btn.addClass('bp-loading');

        $.ajax({
            url: bpWishlistCompare.ajax_url,
            type: 'POST',
            data: {
                action: 'bp_toggle_wishlist',
                nonce: bpWishlistCompare.nonce,
                product_id: productId
            },
            success: function(response) {
                $btn.removeClass('bp-loading');
                if (response.success) {
                    wishlistItems = response.data.wishlist.map(Number);
                    
                    // Update all buttons for this product
                    $('.bp-wishlist-btn[data-product_id="' + productId + '"]').each(function() {
                        if (response.data.action === 'added') {
                            $(this).addClass('in-wishlist').attr('aria-pressed', 'true');
                        } else {
                            $(this).removeClass('in-wishlist').attr('aria-pressed', 'false');
                            // If we are on the wishlist page table
                            var $tableRow = $(this).closest('.bp-wishlist-table tr');
                            if ($tableRow.length) {
                                $tableRow.fadeOut(300, function() {
                                    $(this).remove();
                                    if ($('.bp-wishlist-table tbody tr').length === 0) {
                                        $('.bp-wishlist-table-wrapper').html('<div style="padding: 40px 0; text-align: center; color: #666; background: #fff; border-radius: 8px;">Your wishlist is currently empty.</div>');
                                    }
                                });
                            } else if ($(this).closest('.bp-products-grid').parent().find('h2:contains("My Wishlist")').length) {
                                $(this).closest('.bp-product-card, .product').fadeOut();
                            }
                        }
                    });

                    // Refresh Sidebar
                    refreshSidebarWidgets();

                    if (response.data.action === 'added') {
                        showBpNotification('Added to Wishlist', '<p>This item has been successfully added to your wishlist.</p>', 'View Wishlist', bpWishlistCompare.wishlist_url);
                    }
                } else {
                    showBpNotification('Error', '<p>' + (response.data.message || 'An error occurred.') + '</p>');
                }
            },
            error: function() {
                $btn.removeClass('bp-loading');
                showBpNotification('Connection Error', '<p>Could not connect. Please try again.</p>');
            }
        });
    });

    // Compare Toggle
    $(document).on('click', '.bp-compare-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const productId = parseInt($btn.data('product_id'));
        
        if (!productId) return;

        let compareItems = getCompareItems();
        const index = compareItems.indexOf(productId);

        if (index > -1) {
            // Remove
            compareItems.splice(index, 1);
            setCompareItems(compareItems);
            // Update all buttons
            $('.bp-compare-btn[data-product_id="' + productId + '"]').removeClass('in-compare').attr('aria-pressed', 'false');
        } else {
            // Add
            if (compareItems.length >= maxCompare) {
                showBpNotification('Compare Limit', '<p>You can only compare up to ' + maxCompare + ' items at a time. Please remove an item first.</p>');
                return;
            }
            compareItems.push(productId);
            setCompareItems(compareItems);
            // Update all buttons
            $('.bp-compare-btn[data-product_id="' + productId + '"]').addClass('in-compare').attr('aria-pressed', 'true');
            
            showBpNotification('Added to Compare', '<p>Item added to comparison list. You can add up to ' + maxCompare + ' items.</p>');
        }

        // Refresh Sidebar
        refreshSidebarWidgets();
    });

    // Sidebar Interactions
    $(document).on('click', '.bp-sidebar-remove-item', function(e) {
        e.preventDefault();
        const type = $(this).data('type');
        const productId = parseInt($(this).data('product_id'));

        if (type === 'wishlist') {
            // Trigger the normal wishlist toggle click logic to keep it simple and unified
            $('.bp-wishlist-btn[data-product_id="' + productId + '"]').first().click();
        } else {
            // Compare removal
            let compareItems = getCompareItems();
            const index = compareItems.indexOf(productId);
            if (index > -1) {
                compareItems.splice(index, 1);
                setCompareItems(compareItems);
                $('.bp-compare-btn[data-product_id="' + productId + '"]').removeClass('in-compare').attr('aria-pressed', 'false');
                refreshSidebarWidgets();
                if ($compareContainer.length) renderCompareTable();
            }
        }
    });

    $(document).on('click', '.bp-sidebar-clear-all', function(e) {
        e.preventDefault();
        const type = $(this).data('type');

        if (type === 'wishlist') {
            if (confirm('Are you sure you want to clear your entire wishlist?')) {
                $.ajax({
                    url: bpWishlistCompare.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'bp_clear_wishlist',
                        nonce: bpWishlistCompare.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            wishlistItems = [];
                            $('.bp-wishlist-btn').removeClass('in-wishlist').attr('aria-pressed', 'false');
                            refreshSidebarWidgets();
                        }
                    }
                });
            }
        } else {
            // Clear Compare
            setCompareItems([]);
            $('.bp-compare-btn').removeClass('in-compare').attr('aria-pressed', 'false');
            refreshSidebarWidgets();
            if ($compareContainer.length) renderCompareTable();
        }
    });

    // Compare Table Rendering
    var $compareContainer = $('#bp-compare-table-container');
    function renderCompareTable() {
        if (!$compareContainer.length) return;
        const compareItems = getCompareItems();
        
        if (compareItems.length === 0) {
            $compareContainer.html('<p class="bp-compare-empty">Your compare list is empty. Go to the <a href="/">shop</a> to add products.</p>');
            return;
        }

        $compareContainer.html('<div class="bp-spinner"></div>');

        $.ajax({
            url: bpWishlistCompare.ajax_url,
            type: 'POST',
            data: {
                action: 'bp_get_compare_data',
                nonce: bpWishlistCompare.nonce,
                product_ids: compareItems
            },
            success: function(response) {
                if (response.success) {
                    $compareContainer.html(response.data);
                } else {
                    $compareContainer.html(response.data || '<p>Error loading compare data.</p>');
                }
            }
        });
    }

    if ($compareContainer.length) {
        renderCompareTable();

        // Handle remove from inside compare table
        $(document).on('click', '.bp-compare-remove-btn', function(e) {
            e.preventDefault();
            const productId = parseInt($(this).data('product_id'));
            let compareItems = getCompareItems();
            
            const index = compareItems.indexOf(productId);
            if (index > -1) {
                compareItems.splice(index, 1);
                setCompareItems(compareItems);
                $('.bp-compare-btn[data-product_id="' + productId + '"]').removeClass('in-compare').attr('aria-pressed', 'false');
                renderCompareTable();
                refreshSidebarWidgets();
            }
        });
    }

});

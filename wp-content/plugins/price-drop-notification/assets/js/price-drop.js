jQuery(document).ready(function($) {
    
    // Subscribe to Price Drop
    $(document).on('click', '.bp-notify-link:not(.bp-notified)', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var productId = $btn.data('product_id');

        if ( ! bp_price_drop_params.is_logged_in ) {
            alert('Please log in to be notified of price drops.');
            return;
        }

        if ( $btn.hasClass('loading') ) return;

        $btn.addClass('loading').text('Subscribing...');

        $.ajax({
            url: bp_price_drop_params.ajax_url,
            type: 'POST',
            data: {
                action: 'bp_subscribe_price_drop',
                product_id: productId,
                nonce: bp_price_drop_params.nonce
            },
            success: function(response) {
                if ( response.success ) {
                    $btn.removeClass('loading')
                        .addClass('bp-notified')
                        .text('Added to Price drop notification');
                    
                    // Append the "Show alerts" link
                    if ( $btn.parent().find('.bp-show-alerts-link').length === 0 ) {
                        $btn.after(' <a href="' + bp_price_drop_params.account_url + '" class="bp-show-alerts-link">Show alerts</a>');
                    }
                } else {
                    $btn.removeClass('loading').text('Notify me when the price drops');
                    alert(response.data.message || 'Error occurred. Please try again.');
                }
            },
            error: function() {
                $btn.removeClass('loading').text('Notify me when the price drops');
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Remove Alert in My Account
    $(document).on('click', '.bp-remove-alert-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var productId = $btn.data('product_id');
        var $row = $btn.closest('tr');

        if ( $btn.hasClass('loading') ) return;
        $btn.addClass('loading').text('Removing...');

        $.ajax({
            url: bp_price_drop_params.ajax_url,
            type: 'POST',
            data: {
                action: 'bp_remove_price_drop_alert',
                product_id: productId,
                nonce: bp_price_drop_params.nonce
            },
            success: function(response) {
                if ( response.success ) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        // If no more rows, refresh or show empty message
                        if ( $('.woocommerce-orders-table tbody tr').length === 0 ) {
                            location.reload();
                        }
                    });
                } else {
                    $btn.removeClass('loading').text('Remove');
                    alert(response.data.message || 'Error occurred. Please try again.');
                }
            },
            error: function() {
                $btn.removeClass('loading').text('Remove');
                alert('An error occurred. Please try again.');
            }
        });
    });

});

jQuery(document).ready(function($){
    var mediaUploader;

    // Handle Image Upload
    $(document).on('click', '.bp-upload-button', function(e) {
        e.preventDefault();
        var button = $(this);
        var inputField = button.siblings('.bp-image-url');
        var previewImg = button.closest('.bp-slide-row').find('img');

        if (mediaUploader) {
            mediaUploader.open();
        } else {
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Image',
                button: { text: 'Choose Image' },
                multiple: false
            });
        }

        mediaUploader.off('select').on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            inputField.val(attachment.url).trigger('change');
            if (previewImg.length) {
                previewImg.attr('src', attachment.url).show();
            }
        });

        mediaUploader.open();
    });

    // Update preview when manual input changes
    $(document).on('input change', '.bp-image-url', function() {
        var url = $(this).val();
        var previewImg = $(this).closest('.bp-slide-row').find('img');
        if(url) {
            previewImg.attr('src', url).show();
        } else {
            previewImg.hide();
        }
    });

    // Add New Slide
    $('#bp-add-slide-btn').on('click', function(e) {
        e.preventDefault();
        var wrapper = $('#bp-hero-slides-wrapper');
        // Calculate new index
        var index = wrapper.find('.bp-slide-row').length;
        
        // Clone template
        var newSlide = $('.bp-slide-row-template').clone();
        newSlide.removeClass('bp-slide-row-template').addClass('bp-slide-row');
        newSlide.show();

        // Update name attributes properly
        newSlide.find('.bp-image-url').attr('name', 'bp_hero_slides[' + index + '][img]');
        newSlide.find('.bp-link-url').attr('name', 'bp_hero_slides[' + index + '][link]');

        wrapper.append(newSlide);
    });

    // Remove Slide
    $(document).on('click', '.bp-remove-slide', function(e) {
        e.preventDefault();
        if(confirm('Remove this slide?')) {
            $(this).closest('.bp-slide-row').remove();

            // Re-index all inputs so PHP saves it as a clean array
            $('#bp-hero-slides-wrapper .bp-slide-row').each(function(index) {
                $(this).find('.bp-image-url').attr('name', 'bp_hero_slides[' + index + '][img]');
                $(this).find('.bp-link-url').attr('name', 'bp_hero_slides[' + index + '][link]');
            });
        }
    });

    // === BABYPASA PRODUCT PICKER: START ===
    // Per-section product picker: WooCommerce Select2 search + jQuery UI sortable list,
    // syncing a hidden comma-separated ordered-ID input submitted with the settings form.
    (function(){
        var $blocks = $('.bp-picker-block');
        if (!$blocks.length) { return; }

        // Rebuild the hidden CSV input from the current sortable order.
        function syncInput($block) {
            var ids = $block.find('.bp-product-sortable li').map(function(){
                return $(this).data('id');
            }).get();
            $block.find('.bp-picker-input').val(ids.join(','));
            $block.find('.bp-picker-empty').toggle(ids.length === 0);
        }

        $blocks.each(function(){
            var $block = $(this);
            var $list  = $block.find('.bp-product-sortable');
            var $search = $block.find('.bp-product-search');

            // WooCommerce's wc-enhanced-select script auto-initialises .wc-product-search
            // selects. Only init manually if it hasn't already been enhanced, to avoid
            // double-binding the AJAX search.
            if ($.fn.selectWoo && !$search.hasClass('select2-hidden-accessible')) {
                $search.selectWoo({
                    minimumInputLength: 2,
                    placeholder: $search.data('placeholder'),
                    allowClear: true,
                    ajax: {
                        url: (window.ajaxurl || ''),
                        dataType: 'json',
                        delay: 250,
                        data: function(params){
                            return {
                                term: params.term,
                                action: 'woocommerce_json_search_products',
                                security: (window.wc_enhanced_select_params ? wc_enhanced_select_params.search_products_nonce : ''),
                                exclude_type: ''
                            };
                        },
                        processResults: function(data){
                            var terms = [];
                            if (data) {
                                $.each(data, function(id, text){
                                    terms.push({ id: id, text: text });
                                });
                            }
                            return { results: terms };
                        },
                        cache: true
                    }
                });
            }

            // Add selected product to the sortable list (skip duplicates), then clear the box.
            $search.on('select2:select', function(e){
                var data = e.params.data;
                var id = parseInt(data.id, 10);
                if (!id) { return; }
                if ($list.find('li[data-id="' + id + '"]').length) {
                    $search.val(null).trigger('change');
                    return;
                }
                // Strip the SKU prefix WooCommerce sometimes adds (e.g. "(#12) Name").
                var name = (data.text || '').replace(/^\(#\d+\)\s*/, '');
                var $li = $('<li></li>').attr('data-id', id);
                $li.append('<span class="bp-no-thumb"></span>');
                $li.append($('<span class="bp-sort-name"></span>').text(name));
                $li.append('<a href="#" class="bp-remove-product" aria-label="Remove" title="Remove">×</a>');
                $list.append($li);
                $search.val(null).trigger('change');
                syncInput($block);
            });

            // Make the list sortable.
            if ($list.sortable) {
                $list.sortable({
                    placeholder: 'bp-sort-placeholder',
                    update: function(){ syncInput($block); }
                });
            }
        });

        // Remove a product from a list.
        $(document).on('click', '.bp-remove-product', function(e){
            e.preventDefault();
            var $block = $(this).closest('.bp-picker-block');
            $(this).closest('li').remove();
            syncInput($block);
        });
    })();
    // === BABYPASA PRODUCT PICKER: END ===
});

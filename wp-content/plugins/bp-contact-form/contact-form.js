jQuery(document).ready(function ($) {

    // ── Notification helper ─────────────────────────────────────────────────
    // Reuses the same .bp-notification-* CSS classes as babypasa-wishlist-compare.
    // If that plugin is also loaded the two share the same container and look identical.
    function showBpNotification(title, message, btnText, btnUrl, notifType) {
        btnText   = btnText   || '';
        btnUrl    = btnUrl    || '';
        notifType = notifType || '';

        var $container = $('.bp-notification-container');
        if (!$container.length) {
            $('body').append('<div class="bp-notification-container"></div>');
            $container = $('.bp-notification-container');
        }

        // Deduplication: skip if same type is already showing
        if (notifType && $container.find('.bp-notification[data-notif-type="' + notifType + '"]').length) {
            return;
        }

        var id       = 'bp-toast-' + Date.now();
        var typeAttr = notifType ? ' data-notif-type="' + notifType + '"' : '';

        var actionsHtml = '';
        if (btnText && btnUrl) {
            actionsHtml = '<div class="bp-notification-actions"><a href="' + btnUrl + '" class="bp-notification-btn">' + btnText + '</a></div>';
        }

        var html =
            '<div class="bp-notification" id="' + id + '"' + typeAttr + '>' +
                '<button class="bp-notification-close">&times;</button>' +
                '<div class="bp-notification-content">' +
                    '<h4 class="bp-notification-title">' + title + '</h4>' +
                    '<div class="bp-notification-message">' + message + '</div>' +
                '</div>' +
                actionsHtml +
            '</div>';

        var $n = $(html);
        $container.append($n);

        // Trigger reflow so the transition fires
        $n[0].offsetWidth;
        $n.addClass('bp-show');

        var timer = setTimeout(function () {
            $n.removeClass('bp-show').addClass('bp-hiding');
            setTimeout(function () { $n.remove(); }, 300);
        }, 5000);

        $n.find('.bp-notification-close').on('click', function () {
            clearTimeout(timer);
            $n.removeClass('bp-show').addClass('bp-hiding');
            setTimeout(function () { $n.remove(); }, 300);
        });
    }

    // ── Form submission ─────────────────────────────────────────────────────
    $('#bcf-contact-form').on('submit', function (e) {
        e.preventDefault();

        var $form   = $(this);
        var $btn    = $form.find('.bcf-submit-btn');
        var origTxt = $btn.text();

        $btn.prop('disabled', true).text('Sending…');

        var data = $form.serialize() + '&action=bcf_submit';

        $.post(bcfData.ajax_url, data, function (res) {
            if (res.success) {
                showBpNotification(
                    'Message Sent',
                    '<p>' + res.data.message + '</p>',
                    '', '', 'bcf-success'
                );
                $form[0].reset();
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'An error occurred. Please try again.';
                showBpNotification(
                    'Error',
                    '<p>' + msg + '</p>',
                    '', '', 'bcf-error'
                );
            }
        }).fail(function () {
            showBpNotification(
                'Connection Error',
                '<p>Could not reach the server. Please check your connection and try again.</p>',
                '', '', 'bcf-error'
            );
        }).always(function () {
            $btn.prop('disabled', false).text(origTxt);
        });
    });

});

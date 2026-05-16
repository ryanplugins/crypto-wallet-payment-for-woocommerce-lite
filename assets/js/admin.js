/* global cwwliteAdmin, jQuery */
(function ($) {
    'use strict';

    var admin = window.cwwliteAdmin || {};

    // ── Manual verify button ──────────────────────────────────────────────────

    $(document).on('click', '.cwwlite-verify-btn', function (e) {
        e.preventDefault();

        var $btn     = $(this);
        var orderId  = $btn.data('order-id');
        var nonce    = $btn.data('nonce');
        var $spinner = $btn.siblings('.cwwlite-verify-spinner');

        if (!confirm('Mark this order as manually verified and change its status?')) return;

        $btn.prop('disabled', true);
        $spinner.show();

        $.ajax({
            url:    admin.ajaxUrl,
            method: 'POST',
            data: {
                action:   'ryanplugins_cwwlite_verify_order',
                order_id: orderId,
                nonce:    nonce,
            },
            success: function (resp) {
                if (resp && resp.success) {
                    $btn.replaceWith(
                        '<p style="color:#27ae60;font-weight:600;">✅ Marked as ' +
                        (resp.data.new_status || 'verified') +
                        '. Reloading…</p>'
                    );
                    $spinner.hide();
                    setTimeout(function () { location.reload(); }, 1400);
                } else {
                    alert('Error: ' + (resp.data || 'Unknown error'));
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            },
            error: function () {
                alert('Request failed. Please refresh and try again.');
                $btn.prop('disabled', false);
                $spinner.hide();
            },
        });
    });

}(jQuery));

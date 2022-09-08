(function($, bc_cache_ajax_object, location) {
    $(function() {
        // Register click handler.
        $('#wp-admin-bar-bc-cache').on('click', function() {

            var $button = $(this);

            if ($button.hasClass('bc-cache-is-working')) {
                // An AJAX request is currently in progress...
                return;
            }

            // Signal to user that BC Cache is working.
            $button.removeClass('bc-cache-success').removeClass('bc-cache-error').addClass('bc-cache-is-working');

            // Perform an Ajax request.
            $.ajax({
                url      : bc_cache_ajax_object.ajaxurl,
                data     : {action: 'bc_cache_flush_cache', _ajax_nonce: bc_cache_ajax_object.nonce},
                dataType : 'json',
                cache    : false,
                timeout  : 0, // no timeout
                error    : function() {
                    $button.addClass('bc-cache-error');
                },
                success  : function(response) {
                    $button.addClass(response.success ? 'bc-cache-success' : 'bc-cache-error');
                    if (response.success) {
                        // Update cache size (in "At a Glance" box).
                        $('#bc-cache-size').text(bc_cache_ajax_object.empty_cache_text);
                    }
                },
                complete : function() {
                    if ($('body').hasClass('tools_page_bc-cache-view')) {
                        // Reload Cache Viewer page.
                        location.reload();
                    } else {
                        // Restore button icon back to normal.
                        setTimeout(function() {
                            $button.removeClass('bc-cache-is-working').removeClass('bc-cache-error').removeClass('bc-cache-success');
                        }, 1000);
                    }
                }
            });
        });
    });
})(jQuery, bc_cache_ajax_object, window.location);

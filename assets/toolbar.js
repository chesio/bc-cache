(function($, bc_cache_ajax_object) {
  $(function() {
    // Register click handler
    $('#wp-admin-bar-bc-cache').on('click', function() {

      var $button = $(this);

      if ($button.hasClass('bc-cache-is-working')) {
        // An AJAX request is currently in progress ...
        return;
      }

      // Signal to user that Cachify is working
      $button.removeClass('bc-cache-success').removeClass('bc-cache-error').addClass('bc-cache-is-working');

      // Perform an Ajax request
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
        },
        complete : function() {
          setTimeout(function() {
            $button.removeClass('bc-cache-is-working').removeClass('bc-cache-error').removeClass('bc-cache-success');
          }, 1000);
        }
      });
    });
  });
})(jQuery, bc_cache_ajax_object);

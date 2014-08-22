(function() {
  jQuery(function($) {
    var fetchData, init, resetSinceTimes;
    init = function() {
      return $(document).on('click.sa', '#sa-options', function(e) {
        var id;
        e.preventDefault;
        id = $(e.target).attr('id');
        $('#' + id).parent().find('.spinner').css({
          display: 'inline-block'
        });
        switch (id) {
          case 'sa-btn-fetch':
            return fetchData();
          case 'sa-btn-reset':
            return resetSinceTimes();
        }
      });
    };
    fetchData = function(id) {
      return $.ajax({
        type: 'GET',
        url: ajaxurl,
        data: {
          action: 'fetch_social_feeds'
        }
      }).done(function(response) {
        if (response.message != null) {
          $('#sa-btn-fetch').parent().find('.message').html(response.message);
        }
        return $('#sa-btn-fetch').parent().find('.spinner').css({
          display: 'none'
        });
      });
    };
    resetSinceTimes = function() {
      return $.ajax({
        type: 'GET',
        url: ajaxurl,
        data: {
          action: 'reset_since_times'
        }
      }).done(function(response) {
        return $('#sa-btn-reset').parent().find('.spinner').css({
          display: 'none'
        });
      });
    };
    return init();
  });

}).call(this);

(function($, monetize) {
    var monetize_ajax = $.extend(monetize_ajax || {}, {
        zones: [],
        do_fetch: function() {
            var zel = $('div.monetize-zone');

            $(zel).each(function() {
                monetize_ajax.zones.push(
                    $(this).data('monetize-zone-id')
                );
            });

             $.ajax(
                {
                     type: 'post',
                     url: monetize.ajaxurl,
                     data: {
                         action: 'monetize-ajax-fetch',
                         data: {
                            zones: monetize_ajax.zones,
                            url: document.URL,
                            referer: document.referrer
                        }
                     },
                     dataType: 'json',
                     success: function(data) {
                        if(data !== -1) {
                            $(zel).each(function() {
                                var zid = $(this).data('monetize-zone-id');
                                if(data[zid]) {
                                    $(this).
                                    data(
                                        'monetize-impression-id',
                                        data[zid]['impression_id']
                                    ).
                                    attr('style', data[zid]['zone_css']).
                                    width(data[zid]['zone_width']).
                                    height(data[zid]['zone_height']).
                                    append(
                                        data[zid]['unit_html'] || ''
                                    );
                                }
                            });

                            $(document).trigger('monetize-click');
                            $(document).trigger('monetize-flash');
                        }
                    }
                }                     
            );
        }
    });
    
    monetize_ajax.do_fetch();
    
})(jQuery, monetize);
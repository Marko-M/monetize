/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
(function($, monetize_click) {
    monetize_click = $.extend(monetize_click || {}, {
        hover_impression_id: null,
        hover_timeout: null,
        do_ajax_click: function() {
             $.ajax(
                {
                     type: 'post',
                     url: monetize_click.ajaxurl,
                     data: {
                         action: 'monetize-ajax-click',
                         data: {impression_id: monetize_click.hover_impression_id}
                     },
                     dataType: 'json',
                     async: false
                 }                     
            );                
        },
        bind_click: function() {
            var zel = $('div.monetize-zone');
            
            $(zel).each(function() {
                var mid = $(this).data('monetize-impression-id');
                if(mid) {
                    $(this).hover(function() {
                        if(monetize_click.hover_timeout !== null) {
                            clearTimeout(monetize_click.hover_timeout);
                            monetize_click.hover_timeout = null;
                        }
                        monetize_click.hover_impression_id = mid;
                    }, function() {
                        monetize_click.hover_timeout = setTimeout(function() {
                            monetize_click.hover_impression_id = null;
                            monetize_click.hover_timeout = null;
                        }, 1000);
                    });;
                }
            });

            // Report click onbeforeunload but abort on keydown
            $(window).bind('beforeunload', function (e) {
                if(monetize_click.hover_impression_id !== null) {
                    monetize_click.do_ajax_click();
                }
            }).bind('keydown', function(e) {
                if(monetize_click.hover_impression_id !== null) {
                    monetize_click.hover_impression_id = null;
                }
            }).focus();
        }
    });

    $(document).bind('monetize-click', monetize_click.bind_click);
    
})(jQuery, monetize_click);
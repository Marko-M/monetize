/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
(function($, monetize_flash) {
    monetize_flash = $.extend(monetize_flash || {}, {
        do_flash: function() {
            // Deal with swf
            var fel = $('div.monetize-flash');
            if(fel.length > 0) {
                var foptions = {
                    width: "100%",
                    height: "100%",
                    menu: false,
                    scale: "noscale",
                    wmode: "opaque",
                    hasVersion: 8,
                    flashvars: {}
                };

                $(fel).each(function() {
                    foptions.swf = $(this).data('monetize-movie');

                    var clicktag = $(this).data('monetize-clicktag');
                    if(clicktag) {
                        foptions.flashvars.clickTAG = $(this).data('monetize-clicktag');
                    }

                    $(this).replaceWith($.flash.create(foptions));
                });
            }
        }
    });

    $(document).bind('monetize-flash', monetize_flash.do_flash);
    
})(jQuery, monetize_flash);
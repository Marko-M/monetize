(function($, monetize) {
    monetize = $.extend(monetize || {}, {
        get_script: function(url, callback, options) {
            options = $.extend(options || {}, {
                crossDomain: (monetize.script_suffix == '.dev')? true : false,
                dataType: "script",
                cache: true,
                success: callback,
                url: url
            });

            return $.ajax(options);
        },               
        init: function() {
            if($('div.monetize-zone').length > 0){
                if(monetize.wp_cache == 1) {
                    monetize.get_script(
                        [monetize.url,
                        '/js/monetize-ajax',
                        monetize.script_suffix,
                        '.js?',monetize.version].join('')
                    );                    
                } else {
                    $(document).trigger('monetize-click');
                    $(document).trigger('monetize-flash');   
                }
            }
        }
    });

    $(document).ready(monetize.init());
})(jQuery, monetize);
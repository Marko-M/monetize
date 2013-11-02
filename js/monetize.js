/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
(function(b,a){a=b.extend(a||{},{get_script:function(d,e,c){c=b.extend(c||{},{crossDomain:".dev"==a.script_suffix?!0:!1,dataType:"script",cache:!0,success:e,url:d});return b.ajax(c)},init:function(){0<b("div.monetize-zone").length&&(1==a.wp_cache?a.get_script([a.url,"/js/monetize-ajax",a.script_suffix,".js?",a.version].join("")):(b(document).trigger("monetize-click"),b(document).trigger("monetize-flash")))}});b(document).ready(a.init())})(jQuery,monetize);
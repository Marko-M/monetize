/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
(function(a,f){var d=a.extend(d||{},{zones:[],do_fetch:function(){var e=a("div.monetize-zone");a(e).each(function(){d.zones.push(a(this).data("monetize-zone-id"))});a.ajax({type:"post",url:f.ajaxurl,data:{action:"monetize-ajax-fetch",data:{zones:d.zones,url:document.URL,referer:document.referrer}},dataType:"json",success:function(b){-1!==b&&(a(e).each(function(){var c=a(this).data("monetize-zone-id");b[c]&&a(this).data("monetize-impression-id",b[c].impression_id).attr("style",b[c].zone_css).width(b[c].zone_width).height(b[c].zone_height).append(b[c].unit_html||
"")}),a(document).trigger("monetize-click"),a(document).trigger("monetize-flash"))}})}});d.do_fetch()})(jQuery,monetize);
/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
(function(a,b){b=a.extend(b||{},{do_flash:function(){var b=a("div.monetize-flash");if(0<b.length){var c={width:"100%",height:"100%",menu:!1,scale:"noscale",wmode:"opaque",hasVersion:8,flashvars:{}};a(b).each(function(){c.swf=a(this).data("monetize-movie");a(this).data("monetize-clicktag")&&(c.flashvars.clickTAG=a(this).data("monetize-clicktag"));a(this).replaceWith(a.flash.create(c))})}}});a(document).bind("monetize-flash",b.do_flash)})(jQuery,monetize_flash);
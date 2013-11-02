/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
(function(b,a){a=b.extend(a||{},{hover_impression_id:null,hover_timeout:null,do_ajax_click:function(){b.ajax({type:"post",url:a.ajaxurl,data:{action:"monetize-ajax-click",data:{impression_id:a.hover_impression_id}},dataType:"json",async:!1})},bind_click:function(){var d=b("div.monetize-zone");b(d).each(function(){var c=b(this).data("monetize-impression-id");c&&b(this).hover(function(){null!==a.hover_timeout&&(clearTimeout(a.hover_timeout),a.hover_timeout=null);a.hover_impression_id=c},function(){a.hover_timeout=
setTimeout(function(){a.hover_impression_id=null;a.hover_timeout=null},1E3)})});b(window).bind("beforeunload",function(){null!==a.hover_impression_id&&a.do_ajax_click()}).bind("keydown",function(){null!==a.hover_impression_id&&(a.hover_impression_id=null)}).focus()}});b(document).bind("monetize-click",a.bind_click)})(jQuery,monetize_click);
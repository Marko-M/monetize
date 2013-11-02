/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
(function(e,b){b=e.extend(b||{},{impressions_chart:function(){var a=e("div#monetize-impressions-trends-chart");if(0!=a.length){for(var a=a.data("monetize-impressions-trends"),c=[[b.i18n.date,b.i18n.impressions]],d=0;d<a.length;d++)c.push([a[d].date,parseInt(a[d].impressions)]);a=google.visualization.arrayToDataTable(c);c={legend:{position:"none"},titleTextStyle:{fontSize:14},title:b.i18n.impressions,vAxis:{viewWindowMode:"explicit",viewWindow:{max:"auto",min:0}}};(new google.visualization.ColumnChart(document.getElementById("monetize-impressions-trends-chart"))).draw(a,
c)}}});e(document).ready(function(){google.load("visualization","1",{packages:["corechart"],callback:b.impressions_chart})})})(jQuery,monetize_trends);
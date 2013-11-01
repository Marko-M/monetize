(function($, monetize_trends) {
    monetize_trends = $.extend(monetize_trends || {}, {
        impressions_chart: function(){
            var container = $('div#monetize-impressions-trends-chart');
            if(container.length != 0){
                var data_object = container.data('monetize-impressions-trends');

                var data = [[monetize_trends.i18n.date, monetize_trends.i18n.impressions]];
                for(var i = 0; i < data_object.length; i++){
                    data.push(
                        [data_object[i]['date'],
                        parseInt(data_object[i]['impressions'])]
                    );
                }

                var data_table = google.visualization.arrayToDataTable(data);

                var options = {
                    legend: {position: 'none'},
                    titleTextStyle: {fontSize: 14},
                    title: monetize_trends.i18n.impressions,
                    vAxis: {
                        viewWindowMode: 'explicit',
                        viewWindow:{
                          max: 'auto',
                          min: 0
                        }
                    }
                };

                var chart = new google.visualization.ColumnChart(
                    document.getElementById('monetize-impressions-trends-chart')
                );
                chart.draw(data_table, options);
            }
        }
    });

    $(document).ready(function(){
        google.load('visualization', '1', {packages:['corechart'], 'callback' : monetize_trends.impressions_chart});
    });
})(jQuery, monetize_trends);
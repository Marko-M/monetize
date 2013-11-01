(function($, monetize_trends) {
    monetize_trends = $.extend(monetize_trends || {}, {
        clicks_chart: function(){
            var container = $('div#monetize-clicks-trends-chart');
            if(container.length != 0){
                var data_object = container.data('monetize-clicks-trends');

                var data = [[monetize_trends.i18n.date, monetize_trends.i18n.clicks]];
                for(var i = 0; i < data_object.length; i++){
                    data.push(
                        [data_object[i]['date'],
                        parseInt(data_object[i]['clicks'])]
                    );
                }

                var data_table = google.visualization.arrayToDataTable(data);

                var options = {
                    legend: {position: 'none'},
                    titleTextStyle: {fontSize: 14},
                    title: monetize_trends.i18n.clicks,
                    vAxis: {
                        viewWindowMode: 'explicit',
                        viewWindow:{
                          max: 'auto',
                          min: 0
                        }
                    }
                };

                var chart = new google.visualization.ColumnChart(
                    document.getElementById('monetize-clicks-trends-chart')
                );
                chart.draw(data_table, options);
            }
        }
    });

    $(document).ready(function(){
        google.load('visualization', '1', {packages:['corechart'], 'callback' : monetize_trends.clicks_chart});
    });
})(jQuery, monetize_trends);
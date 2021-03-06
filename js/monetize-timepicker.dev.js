/*
Monetize 1.03
By: Marko Martinović
URL: http://www.techytalk.info/wordpress/monetize/
*/
jQuery(document).ready(function($){
    $.datepicker.regional['monetize'] = {
            closeText: monetize.i18n.closeText,
            prevText: monetize.i18n.prevText,
            nextText: monetize.i18n.nextText,
            currentText: monetize.i18n.currentText,
            monthNames: [
                monetize.i18n.monthNames[0],
                monetize.i18n.monthNames[1],
                monetize.i18n.monthNames[2],
                monetize.i18n.monthNames[3],
                monetize.i18n.monthNames[4],
                monetize.i18n.monthNames[5],
                monetize.i18n.monthNames[6],
                monetize.i18n.monthNames[7],
                monetize.i18n.monthNames[8],
                monetize.i18n.monthNames[9],
                monetize.i18n.monthNames[10],
                monetize.i18n.monthNames[11]
            ],
            monthNamesShort: [
                monetize.i18n.monthNamesShort[0],
                monetize.i18n.monthNamesShort[1],
                monetize.i18n.monthNamesShort[2],
                monetize.i18n.monthNamesShort[3],
                monetize.i18n.monthNamesShort[4],
                monetize.i18n.monthNamesShort[5],
                monetize.i18n.monthNamesShort[6],
                monetize.i18n.monthNamesShort[7],
                monetize.i18n.monthNamesShort[8],
                monetize.i18n.monthNamesShort[9],
                monetize.i18n.monthNamesShort[10],
                monetize.i18n.monthNamesShort[11]
            ],
            dayNames: [
                monetize.i18n.dayNames[0],
                monetize.i18n.dayNames[1],
                monetize.i18n.dayNames[2],
                monetize.i18n.dayNames[3],
                monetize.i18n.dayNames[4],
                monetize.i18n.dayNames[5],
                monetize.i18n.dayNames[6]
            ],
            dayNamesShort: [
                monetize.i18n.dayNamesShort[0],
                monetize.i18n.dayNamesShort[1],
                monetize.i18n.dayNamesShort[2],
                monetize.i18n.dayNamesShort[3],
                monetize.i18n.dayNamesShort[4],
                monetize.i18n.dayNamesShort[5],
                monetize.i18n.dayNamesShort[6]
            ],
            dayNamesMin: [
                monetize.i18n.dayNamesMin[0],
                monetize.i18n.dayNamesMin[1],
                monetize.i18n.dayNamesMin[2],
                monetize.i18n.dayNamesMin[3],
                monetize.i18n.dayNamesMin[4],
                monetize.i18n.dayNamesMin[5],
                monetize.i18n.dayNamesMin[6]
            ],
            weekHeader: monetize.i18n.weekHeader,
            dateFormat: monetize.i18n.dateFormat,
            firstDay: 0,
            isRTL: false,
            showMonthAfterYear: false,
            yearSuffix: ''
        };
    $.datepicker.setDefaults($.datepicker.regional['monetize']);


    $.timepicker.regional['monetize'] = {
        currentText: monetize.i18n.currentText,
        closeText: monetize.i18n.closeText,
        amNames: [
            monetize.i18n.amNames[0],
            monetize.i18n.amNames[1]
        ],
        pmNames: [
            monetize.i18n.pmNames[0],
            monetize.i18n.pmNames[1]
        ],
        timeFormat: monetize.i18n.timeFormat,
        timeOnlyTitle: monetize.i18n.timeOnlyTitle,
        timeText: monetize.i18n.timeText,
        hourText: monetize.i18n.hourText,
        minuteText: monetize.i18n.minuteText,
        secondText: monetize.i18n.secondText,
        millisecText: monetize.i18n.millisecText,
        timezoneText: monetize.i18n.timezoneText,
        timeSuffix: '',
        isRTL: false
    };
    $.timepicker.setDefaults($.timepicker.regional['monetize']);

    $('input[id^="monetize-filter-"]').datetimepicker({dateFormat : 'yy/mm/dd', timeFormat : 'HH:mm:ss', showSecond: true});
});
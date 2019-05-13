/**
 * PerformanceRangePicker.js - SnipWire dashboard.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

jQuery(document).ready(function($) {
    
    var settings = config.pickerSettings;
    var locale = config.pickerLocale;
    var rangeLabels = config.pickerRangeLabels;
    var start = settings.startDate ? moment(settings.startDate, 'YYYY-MM-DD') : moment().subtract(29, 'days');
    var end = settings.endDate ? moment(settings.endDate, 'YYYY-MM-DD') : moment();

    var form = $(settings.form);
    var picker = $(settings.element);
    var display = $(settings.display);
    var fieldFrom = $(settings.fieldFrom);
    var fieldTo = $(settings.fieldTo);

    function updatePicker(start, end) {
        // Display values based on locale setting
        display.html(start.format(locale.format) + locale.separator + end.format(locale.format));
        // Hidden form fields always get ISO date
        fieldFrom.val(start.format('YYYY-MM-DD'));
        fieldTo.val(end.format('YYYY-MM-DD'));
    }

    // Ranges needs to be set this way to make labels translatable!
    // In ES5 and earlier, you cannot use a variable as a property name inside an object literal. 
    // Only option is to do the following:
    var rangesObj = {};
    rangesObj[rangeLabels.today] = [moment(), moment()];
    rangesObj[rangeLabels.yesterday] = [moment().subtract(1, 'days'), moment().subtract(1, 'days')];
    rangesObj[rangeLabels.last7days] = [moment().subtract(6, 'days'), moment()];
    rangesObj[rangeLabels.last30days] = [moment().subtract(29, 'days'), moment()];
    rangesObj[rangeLabels.thismonth] = [moment().startOf('month'), moment().endOf('month')];
    rangesObj[rangeLabels.lastmonth] = [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')];

    picker.daterangepicker({
        startDate: start,
        endDate: end,
        opens: 'left',
        ranges: rangesObj,
        locale: {
            format: locale.format,
            separator: locale.separator,
            applyLabel: locale.applyLabel,
            cancelLabel: locale.cancelLabel,
            fromLabel: locale.fromLabel,
            toLabel: locale.toLabel,
            customRangeLabel: locale.customRangeLabel,
            weekLabel: locale.weekLabel,
            daysOfWeek: locale.daysOfWeek,
            monthNames: locale.monthNames,
            firstDay: 1
        },
    },
    // Callback
    function(start, end) {
        updatePicker(start, end);
        form.submit();
    });
    
    updatePicker(start, end);
}); 

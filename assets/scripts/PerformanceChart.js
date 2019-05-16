/**
 * PerformanceChart.js - SnipWire dashboard.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

jQuery(document).ready(function($) {
    var chartData = config.chartData;

    var options = {
        chart: {
            height: 300,
            type: 'area',
            zoom: {
                enabled: false
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'straight'
        },
        grid: {
            row: {
                colors: ['#f3f3f3', 'transparent'], // takes an array which will be repeated on columns
                opacity: 0.5
            }
        },
        xaxis: {
            type: 'datetime',
            categories: chartData['categories']
        },
        series: [{
            name: 'Orders',
            data: chartData['data']
        }]
    }
    
    var chart = new ApexCharts(
        document.querySelector('#PerformanceChart'),
        options
    );
    
    chart.render();
}); 

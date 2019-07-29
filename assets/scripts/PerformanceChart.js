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
            height: 320,
            zoom: {
                enabled: true
            }
        },
        dataLabels: {
            enabled: false
        },
        grid: {
            row: {
                //colors: ['#f0f3f7', 'transparent'],
                //opacity: 0.3
            },
            xaxis: {
                lines: {
                    show: true
                }
            }
        },
        stroke: {
            width: [0, 5],
            //colors: ['', '#3eb998'],
            curve: 'smooth'
        },
        xaxis: {
            type: 'datetime',
            categories: chartData['categories']
        },
        yaxis: [
            {
                title: {
                    text: chartData['salesLabel']
                },
                decimalsInFloat: 2
            },
            {
                opposite: true,
                title: {
                    text: chartData['ordersLabel']
                },
                decimalsInFloat: 0
            }
        ],
        series: [
            {
                type: 'column',
                name: chartData['salesLabel'],
                data: chartData['sales']
            },
            {
                type: 'line',
                name: chartData['ordersLabel'],
                data: chartData['orders']
            }
        ],
        legend: {
            position: 'top',
            horizontalAlign: 'center',
            fontSize: '14px',
            offsetY: -10,
            itemMargin: {
                horizontal: 10,
                vertical: 15
            },
            markers: {
                width: 14,
                height: 14,
                strokeWidth: 0,
                radius: 0
            }
        },
        noData: {
            text: chartData['noDataText'],
            align: 'center',
            verticalAlign: 'middle',
            offsetX: 0,
            offsetY: 0,
            style: {
                //color: undefined,
                fontSize: '16px'
                //fontFamily: undefined
            }
        }
    }
    
    var chart = new ApexCharts(
        document.querySelector('#PerformanceChart'),
        options
    );
    
    chart.render();
}); 

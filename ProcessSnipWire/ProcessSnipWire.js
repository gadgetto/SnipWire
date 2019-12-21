/**
 * ProcessSnipWire.js - JavaScript for ProcessSnipWire module.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

jQuery(document).ready(function() {
    var tabsOptions = config.tabsOptions;
    if (tabsOptions) {
        var $wireTabs = $('#' + tabsOptions.id);
        
        if ($wireTabs.length) {
            $wireTabs.WireTabs({
                id: tabsOptions.id,
                cookieName: tabsOptions.id,
                rememberTabs: tabsOptions.rememberTabs
            });

            $(document).on('click', '.pw-ready #' + tabsOptions.id + ' li a', function() {
                window.location.href = $(this).attr('href');
                return false;
            });
        }
    }
    
    // Items filter form - selector form submit
    $('.filter-form-select').on('change', function() {
        $(this).closest('form').submit();
    });

    var $ItemsFilterResetButton = $('.ItemsFilterResetButton');
    $ItemsFilterResetButton.on('click', function() {
        window.location.href = $ItemsFilterResetButton.attr('value');
        return false;
    });

    var orderActionStrings = config.orderActionStrings;

    $('.ResendInvoiceButton').on('click', function(e) {
        e.preventDefault();
        var a_href = $(this).attr('href');
        ProcessWire.confirm(
            orderActionStrings.confirm_resend_invoice,
            function() {
                // dialogue OK click
                window.location.href = a_href;
            }
        );
    });
    $('#SendRefundButton').on('click', function(e) {
        e.preventDefault();
        ProcessWire.confirm(
            orderActionStrings.confirm_send_refund,
            function() {
                // dialogue OK click
                $('#RefundForm').submit();
            }
        );
    });
});

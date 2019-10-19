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
            
            $(document).on('click', '#' + tabsOptions.id + ' li a', function() {
                window.location.href = $(this).attr('href');
                return false;
            });
        }
    }
});

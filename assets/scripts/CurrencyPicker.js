/**
 * CurrencyPicker.js - SnipWire dashboard.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2023 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

jQuery(document).ready(function($) {
    
    var settings = config.filterSettings;

    var $form = $(settings.form);
    var $fieldCurrency = $(settings.fieldCurrency);

    // Currency selector event
    $fieldCurrency.on('change', function() {
        $form.submit();
    });

}); 

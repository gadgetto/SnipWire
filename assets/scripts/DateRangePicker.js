/**
 * DateRangePicker.js - SnipWire dashboard.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Validate date inputs using moment.js
 *
 * @param string fromDate
 * @param string toDate
 * @return boolean|string
 */
function validateRange(fromDate, toDate, format) {
    // No validation if moment.js not available
    if (typeof moment !== 'function') return true;
    
    var nowMoment = moment();
    
    fromMoment = moment(fromDate, format);
    toMoment = moment(toDate, format);
    
    var validationResult = false;

    if (fromMoment.isAfter(toMoment, 'day')) {
        validationResult = 'fromAfterTo';
    } else if (fromMoment.isAfter(nowMoment, 'year')) {
        validationResult = 'yearAfterNow';
    } else if (toMoment.isAfter(nowMoment, 'year')) {
        validationResult = 'yearAfterNow';
    } else {
        validationResult = true;
    }

    return validationResult;
}

jQuery(document).ready(function($) {
    
    var settings = config.filterSettings;
    var periodRanges = config.periodRanges;
    var pickerMessages = config.pickerMessages;
    
    var $form = $(settings.form);
    var $resetButton = $(settings.resetButton);
    var $fieldSelect = $(settings.fieldSelect);
    var $fieldFrom = $(settings.fieldFrom);
    var $fieldTo = $(settings.fieldTo);
    
    // Date range reset button handler (enable clickable element inside InputfieldHeader)
    $resetButton.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var a_href = $(this).attr('href');
        window.location.href = a_href;
    });

    // Predefined period selector handler
    $fieldSelect.on('change', function() {
        var fieldSelectVal = $fieldSelect.val();
        if (fieldSelectVal !== 'custom') {
            $fieldFrom.val(periodRanges[fieldSelectVal].start);
            $fieldTo.val(periodRanges[fieldSelectVal].end);        
            $form.submit();
        }
    });

    // "From" field handler
    $fieldFrom.on('change', function() {
        var fieldFromVal = $fieldFrom.val();
        var fieldToVal = $fieldTo.val();
        var validationResult = validateRange(fieldFromVal, fieldToVal, settings.dateFormat)
        if (validationResult === true) {
            $form.submit();
        } else {
            Inputfields.highlight($fieldFrom, 2000);
            RuntimeNotification(pickerMessages[validationResult]);
        }

    });
    
    // "To" field handler
    $fieldTo.on('change', function() {
        var fieldFromVal = $fieldFrom.val();
        var fieldToVal = $fieldTo.val();
        var validationResult = validateRange(fieldFromVal, fieldToVal, settings.dateFormat)
        if (validationResult === true) {
            $form.submit();
        } else {
            Inputfields.highlight($fieldTo, 2000);
            RuntimeNotification(pickerMessages[validationResult]);
        }

    });
}); 

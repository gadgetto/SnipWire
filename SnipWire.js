/**
 * SnipWire - JavaScript helpers for config editor.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

var WebhooksEndpointUrl = {
    // Method borrowed from InputfieldPageName.js
    sanitize: function(path) {

        // Replace leading and trailing whitespace 
        path = jQuery.trim(path);
        path = path.toLowerCase();  

        // Replace all types of quotes with nothing
        path = path.replace(/['"\u0022\u0027\u00AB\u00BB\u2018\u2019\u201A\u201B\u201C\u201D\u201E\u201F\u2039\u203A\u300C\u300D\u300E\u300F\u301D\u301E\u301F\uFE41\uFE42\uFE43\uFE44\uFF02\uFF07\uFF62\uFF63]/g, '');
        
        // Replace invalid with dash
        path = path.replace(/[^\/-_.a-z0-9 ]/g, '-');
    
        // Convert whitespace to dash
        path = path.replace(/\s+/g, '-');
    
        // Convert multiple dashes or dots to single
        path = path.replace(/--+/g, '-');
    
        // Convert multiple dots to single
        path = path.replace(/\.\.+/g, '.');
    
        // Convert multiple slashes to single
        path = path.replace(/\/\/+/g, '\/');
    
        // Remove ugly combinations next to each other
        path = path.replace(/(\.-|-\.)/g, '-');
    
        // Remove leading or trailing dashes, underscores and dots
        path = path.replace(/(^[-_.]+|[-_.]+$)/g, '');

        // Check if path starts with / (and add if not)
        if(path.lastIndexOf('/', 0) !== 0) path = '/' + path;
        
        // Make sure it's not too long
        if (path.length > 128) path = $.trim(path).substring(0, 128).split('-').slice(0, -1).join(' '); // @adrian
    
        return path;
    },
    updatePreview: function(value) {
        var httpRoot = ProcessWire.config.SnipWire.httpRoot;
        var $previewPath = $('#webhooks_endpoint_url');
        $previewPath.val(value.length > 0 ? httpRoot + value : httpRoot);
    }
};

var ClipboardHelper = {
    copy: function(selector) {
        var $element = $(selector);
        var attr = $element.attr('disabled');
        var toggledDisabled = false;
        if (typeof attr !== typeof undefined && attr !== false) {
            $element.prop('disabled', false);
            toggledDisabled = true;
        }
        $element.prop('disabled', false);
        $element.focus();
        $element.select();
        document.execCommand('copy');
        if (toggledDisabled === true) {
            $element.prop('disabled', true);
        }
    }
};

jQuery(document).ready(function($) {

    $('#webhooks_endpoint').on('input', function() {
        var value = WebhooksEndpointUrl.sanitize($(this).val());
        WebhooksEndpointUrl.updatePreview(value);
    });

    $('#webhooks_endpoint_url_copy').on('click', function() {
        ClipboardHelper.copy('#webhooks_endpoint_url');
    });

}); 

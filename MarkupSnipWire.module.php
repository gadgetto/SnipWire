<?php namespace ProcessWire;

/**
 * MarkupSnipWire - Markup output for ProcessSnipWire.
 * (This module is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2018 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */
 
 class MarkupSnipWire extends WireData implements Module {
    
    public static function getModuleInfo() {
        return array(
            'title' => 'SnipWire Markup', 
            'summary' => 'Module that outputs markup for SnipWire module.', 
            'version' => 1, 
            'author'  => 'Martin Gartner',
            'href' => 'http://modules.processwire.com/processsnipwire', 
            'icon' => 'shopping-cart', 
            'singular' => true, 
            'autoload' => true, 
            'requires' => array(
                'PHP>=5.6.0', 
                'ProcessWire>=3.0.118',
                'ProcessSnipWire',
             )
        );
    }

    /**
     * Module init method
     *
     */
    public function init() {
        // Creating a new API variable
        $this->wire('snipwire', $this);
    }

    /**
     * Module ready method
     *
     */
    public function ready() {
        // Add a hook after page is rendered and add Snipcart CSS/JS
        $this->addHookAfter('Page::render', $this, 'addCSSJS');
    }


    /**
     * Include JavaScript and CSS files in output
     *
     */
    public function addCSSJS($event) {
        $modules = $this->wire('modules');
        $moduleSnipWire = $modules->get('ProcessSnipWire');

        /** @var Page $page */
        $page = $event->object;
                
        // Prevent adding to pages with system templates
        if ($page->template->flags & Template::flagSystem) return;
        
        // Get ProcessSnipWire module config
        $moduleConfig = $modules->getConfig('ProcessSnipWire');
        
        // Prevent adding to pages with selected templates (excluded_templates)
        if (in_array($page->template->name, $moduleConfig['excluded_templates'])) return;
        
        // Snipcart environment (TEST | LIVE?)
        if ($moduleConfig['snipcart_environment'] == 1) {
            $snipcartAPIKey = $moduleConfig['api_key'];
            $environmentStatus = '<!-- Snipcart LIVE mode -->';
        } else {
            $snipcartAPIKey = $moduleConfig['api_key_test'];
            $environmentStatus = '<!-- Snipcart TEST mode -->';
        }

        $cssResources = array();
        $jsResources = array();

        // Add Snipcart CSS resource
        $cssResources[] = '<link rel="stylesheet" href="https://cdn.snipcart.com/themes/2.0/base/snipcart.min.css">';
        
        // Add jQuery JS resource
        if ($moduleConfig['include_jquery']) {
            $jsResources[] = '<script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>';
        }
        
        // Add Snipcart JS resource
        $jsResources[] = $environmentStatus;
        $jsResources[] = '<script src="https://cdn.snipcart.com/scripts/2.0/snipcart.js" data-api-key="' . $snipcartAPIKey . '" id="snipcart"></script>';

        // Pick available Snipcart JS API keys from module config for API output
        $snipcartAPI = array();
        foreach ($moduleSnipWire->getSnipcartAPIkeys() as $key) {
            if (isset($moduleConfig[$key])) {
                $snipcartAPI[$key] = $moduleConfig[$key];
            }
        }

        // Prepare Snipcart JS config API output
        $jsAPI = '<script>' . PHP_EOL;
        $jsAPI .= 'Snipcart.api' . PHP_EOL;

        foreach ($snipcartAPI as $key => $value) {
            if ($key == 'credit_cards') {
                $value = $this->addCreditCardLabels($value);
            }
            if (is_array($value)) {
                $value = wireEncodeJSON($value, true);
            } else {
                $value = $value ? 'true' : 'false';
            }
            $jsAPI .= '.configure("' . $key . '", ' . $value . ')' . PHP_EOL;
        }
        
        $jsAPI = rtrim($jsAPI, PHP_EOL);
        $jsAPI .= ';' . PHP_EOL;
        $jsAPI .= '</script>';
        
        // Add Snipcart JS API config
        $jsResources[] = $jsAPI;
        
        // Output CSSJS
        reset($cssResources);
        foreach ($cssResources as $cssResource) {
            $event->return = str_replace('</head>', $cssResource . PHP_EOL . '</head>', $event->return);
        }
        
        reset($jsResources);
        foreach ($jsResources as $jsResource) {
            $event->return = str_replace('</body>', $jsResource . PHP_EOL . '</body>', $event->return);
        }
    }
    
    /**
     * Adds credit card labels to the given credit card keys and builds required array format for Snipcart
     *
     * Snipcart.api.configure('credit_cards', [
     *     {'type': 'visa', 'display': 'Visa'},
     *     {'type': 'mastercard', 'display': 'Mastercard'}
     * ]);
     *
     * @param array $cards The credit card array
     *
     */
    public function addCreditCardLabels($cards) {
        $cardsWithLabels = array();
        
        // Get ProcessSnipWire module config
        $creditcardLabels = ProcessSnipWireConfig::getCreditCardLabels();
        foreach ($cards as $card) {
            $cardsWithLabels[] = array(
                'type' => $card,
                'display' => isset($creditcardLabels[$card]) ? $creditcardLabels[$card] : $card,
            );
        }

        return $cardsWithLabels;
    }

}

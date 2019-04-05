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

    const snicpartAnchorTypeButton = 1;
    const snicpartAnchorTypeLink = 2;

    /**
     * Initalize module config variables (properties)
     *
     */
    public function __construct() {
        parent::__construct();

        // Path to jQuery JS file
        $this->set('jqueryJSFile', array(
            'path' => 'https://code.jquery.com/jquery-3.3.1.min.js',
            'integrity' => 'sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8='
        ));

        // Path to the Snipcart CSS file
        $this->set('snipcartCSSFile', array(
            'path' => 'https://cdn.snipcart.com/themes/2.0/base/snipcart.min.css',
            'integrity' => ''
        ));

        // Path to Snipcart JS file
        $this->set('snipcartJSFile', array(
            'path' => 'https://cdn.snipcart.com/scripts/2.0/snipcart.js',
            'integrity' => ''
        ));
        
        // Default button/link prompt
        $this->set('defaultLinkPrompt', $this->_('Buy now'));
    }
    
    /**
     * Module init method
     *
     */
    public function init() {
        /** @var MarkupSnipWire Custom SnipWire API variable */
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
        $cssResources[] = '<link rel="stylesheet" href="' . $this->snipcartCSSFile['path'] . '"'
            . (!empty($this->snipcartCSSFile['integrity']) ? ' integrity="' . $this->snipcartCSSFile['integrity'] . '"' : '')
            . ' crossorigin="anonymous">';
        
        // Add jQuery JS resource
        if ($moduleConfig['include_jquery']) {
            $jsResources[] = '<script src="' . $this->jqueryJSFile['path'] . '"'
            . (!empty($this->jqueryJSFile['integrity']) ? ' integrity="' . $this->jqueryJSFile['integrity'] . '"' : '')
            . ' crossorigin="anonymous"></script>';
        }
        
        // Add Snipcart JS resource
        $jsResources[] = $environmentStatus;
        $jsResources[] = '<script src="' . $this->snipcartJSFile['path']. '"'
            . (!empty($this->snipcartJSFile['integrity']) ? ' integrity="' . $this->snipcartJSFile['integrity'] . '"' : '')
            . ' data-api-key="' . $snipcartAPIKey . '"'
            . ' id="snipcart"'
            . ' crossorigin="anonymous"></script>';

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
     * Renders a Snipcart anchor (buy button or link)
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param string $prompt The button or link label
     * @param string $class Add class name to HTML tag (multiple class names separated by blank)
     * @param string $id Add id to HTML tag
     * @param integer $type The link type [default = snicpartAnchorTypeButton]
     *
     * @return string $out The HTML for a snipcart buy button or link (HTML button | a tag)
     *
     */
    public function anchor(Page $product, $prompt = '', $class = '', $id = '', $type = self::snicpartAnchorTypeButton) {
        $sanitizer = $this->wire('sanitizer');
        
        // @todo: Check if $product (Page) is a Snipcart product
        
        
        
        $out = '';
        
        $prompt = empty($prompt) ? $this->defaultLinkPrompt : $prompt; // @todo: add sanitizer (coul'd be also HTML content!)
        $class = empty($class) ? '' :  ' ' . $class;
        $id = empty($id) ? '' :  ' id="' . $id . '"';
        
        if ($type == self::snicpartAnchorTypeButton) {
            $open = '<button';
            $close = '</button>';
        } else { 
            $open = '<a href="#"';
            $close = '</a>';
        }

        $out .= $open;
        $out .= ' class="snipcart-add-item' . $class . '"';
        $out .= $id;

        // Snipcart data-item-* properties

        $out .= ' data-item-id="' . $product->id . '"';
        $out .= ' data-item-name="' . $product->title . '"';
        $out .= ' data-item-url="' . $product->url . '"';
        $out .= ' data-item-price="' . $product->snipcart_item_price . '"';
        $out .= ' data-item-description="' . $product->snipcart_item_description . '"';
        $out .= ' data-item-image="' . $product->snipcart_item_image->url . '"';
        
        
        

        $out .= '>';
        $out .= $prompt;
        $out .= $close;

        return $out;
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

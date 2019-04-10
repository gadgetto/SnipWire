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
    const snipcartProductTemplate = 'snipcart-product';

    /**
     * Initalize module config variables (properties)
     *
     */
    public function __construct() {
        parent::__construct();

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
        $cssResources[] = '<link rel="stylesheet" href="' . $moduleConfig['snipcart_css_path'] . '"'
            . (!empty($moduleConfig['snipcart_css_integrity']) ? ' integrity="' . $moduleConfig['snipcart_css_integrity'] . '"' : '')
            . ' crossorigin="anonymous">';
        
        // Add jQuery JS resource
        if ($moduleConfig['include_jquery']) {
            $jsResources[] = '<script src="' . $moduleConfig['jquery_js_path'] . '"'
            . (!empty($moduleConfig['jquery_js_integrity']) ? ' integrity="' . $moduleConfig['jquery_js_integrity'] . '"' : '')
            . ' crossorigin="anonymous"></script>';
        }
        
        // Add Snipcart JS resource
        $jsResources[] = $environmentStatus;
        $jsResources[] = '<script src="' . $moduleConfig['snipcart_js_path'] . '"'
            . (!empty($moduleConfig['snipcart_js_integrity']) ? ' integrity="' . $moduleConfig['snipcart_js_integrity'] . '"' : '')
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

        // Prepare Snipcart JS debugging switch
        $jsDebug = '<script>' . PHP_EOL;
        $jsDebug .= 'document.addEventListener("snipcart.ready", function() {' . PHP_EOL;
        $jsDebug .= '    Snipcart.DEBUG = ' . ($moduleConfig['snipcart_debug'] ? 'true' : 'false') . ';' . PHP_EOL;
        $jsDebug .= '});' . PHP_EOL;
        $jsDebug .= '</script>';

        // Add Snipcart JS debugging switch
        $jsResources[] = $jsDebug;
        
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
        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');

        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return '';
                
        $prompt = empty($prompt) ? $this->defaultLinkPrompt : $prompt; // @todo: add sanitizer (could be also HTML content!)
        $class = empty($class) ? '' :  ' ' . $class;
        $id = empty($id) ? '' :  ' id="' . $id . '"';
        
        if ($type == self::snicpartAnchorTypeButton) {
            $open = '<button';
            $close = '</button>';
        } else { 
            $open = '<a href="#"';
            $close = '</a>';
        }

        $out = $open;
        $out .= ' class="snipcart-add-item' . $class . '"';
        $out .= $id;

        $moduleConfig = $modules->getConfig('ProcessSnipWire');

        // Required Snipcart data-item-* properties
        
        $out .= ' data-item-id="' . $product->id . '"';
        $out .= ' data-item-name="' . $this->getProductName($product, $moduleConfig) . '"';
        $out .= ' data-item-url="' . $this->getProductUrl($product, $moduleConfig) . '"';
        $out .= ' data-item-price="' . $this->getProductPrice($product, $moduleConfig) . '"';
        
        // Optional Snipcart data-item-* properties

        if ($product->snipcart_item_description) {
            $out .= ' data-item-description="' . $product->snipcart_item_description . '"';
        }
        if ($productThumb = $this->getProductThumb($product, $moduleConfig)) {
            $out .= ' data-item-image="' . $productThumb->httpUrl . '"';
        }
        
        // @todo: add more data-item-* properties

        $out .= '>';
        $out .= $prompt;
        $out .= $close;

        return $out;
    }

    /**
     * Returns the product name using the selected product name field from ProcessSnipWire module config.
     * Fallback to $page->title field.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The ProcessSnipWire module config
     *
     * @return string $productName The product name
     *
     */
    public function getProductName(Page $product, $moduleConfig = array()) {
        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return '';
        
        if (empty($moduleConfig)) $moduleConfig = $this->wire('modules')->getConfig('ProcessSnipWire');
        if (!$product->hasField($moduleConfig['data_item_name_field']) || empty($product->{$moduleConfig['data_item_name_field']})) {
            $productName = $product->title;
        } else {
            $productName = $product->{$moduleConfig['data_item_name_field']};
        }
        return $productName;
    }

    /**
     * Returns the full product page url depending on ProcessSnipWire module config.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The ProcessSnipWire module config
     *
     * @return string $productUrl The product page url
     *
     */
    public function getProductUrl(Page $product, $moduleConfig = array()) {
        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return '';

        if (empty($moduleConfig)) $moduleConfig = $this->wire('modules')->getConfig('ProcessSnipWire');
        if ($moduleConfig['single_page_shop']) {
            $productUrl = $this->wire('pages')->get($moduleConfig['single_page_shop_page'])->httpUrl;
        } else {
            $productUrl = $product->httpUrl;
        }
        return $productUrl;
    }

    /**
     * Returns the product price (optionally formatted by currency property from ProcessSnipWire module config).
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The ProcessSnipWire module config
     * @param boolean $formatted (unformatted or formatted)
     *
     * @return string $productPrice The product price (unformatted or formatted)
     *
     */
    public function getProductPrice(Page $product, $moduleConfig = array(), $formatted = false) {
        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return '';

        if (empty($moduleConfig)) $moduleConfig = $this->wire('modules')->getConfig('ProcessSnipWire');
        if ($formatted) {
            
            
            $priceFormatted = new \NumberFormatter('us_US', \NumberFormatter::CURRENCY);
            $priceFormatted->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $productPrice = $priceFormatted->format($product->snipcart_item_price);
            
            
        } else {
            $productPrice = $product->snipcart_item_price;
        }
        return $productPrice;
    }
    
    /**
     * Helper method to get the formatted product price.
     *
     * @see function getProductPrice
     * @return string The formatted product price 
     *
     */
    public function getProductPriceFormatted(Page $product, $moduleConfig = array()) {
        return $this->getProductPrice($product, $moduleConfig, true);
    }

    /**
     * Generates a Thumbnail from first image of product page-image field and returns it.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The ProcessSnipWire module config
     *
     * @return null|Pageimage $productThumb The product thumbnail or null if no image found
     *
     */
    public function getProductThumb(Page $product, $moduleConfig = array()) {
        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return null;

        if (empty($moduleConfig)) $moduleConfig = $this->wire('modules')->getConfig('ProcessSnipWire');
        $productThumb = null;
        $image = $product->snipcart_item_image->first();
        if ($image) {
            $productThumb = $image->size($moduleConfig['cart_image_width'], $moduleConfig['cart_image_height'], [
                'cropping' => $moduleConfig['cart_image_cropping'] ? true : false,
                'quality' => $moduleConfig['cart_image_quality'],
                'hidpi' => $moduleConfig['cart_image_hidpi'] ? true : false,
                'hidpiQuality' => $moduleConfig['cart_image_hidpiQuality'],
            ]);
        }
        return $productThumb;
    }


    /**
     * Adds credit card labels to the given credit card keys and builds required array format for Snipcart
     *
     * Snipcart.api.configure("credit_cards", [
     *     {"type": "visa", "display": "Visa"},
     *     {"type": "mastercard", "display": "Mastercard"}
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

<?php namespace ProcessWire;

/**
 * MarkupSnipWire - Snipcart markup output for SnipWire.
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
            'summary' => 'Snipcart markup output for SnipWire.', 
            'version' => 1, 
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'singular' => true, 
            'autoload' => true, 
            'requires' => array(
                'PHP>=5.6.0', 
                'ProcessWire>=3.0.118',
                'SnipWire',
             )
        );
    }

    const snicpartAnchorTypeButton = 1;
    const snicpartAnchorTypeLink = 2;
    const snipcartProductTemplate = 'snipcart-product';

    /** @var array $snipcartAPIproperties All Snipcart configuration API properties (some are currently not in use here) */
    protected $snipcartAPIproperties = array(
        'credit_cards',
        'allowed_shipping_methods',
        'excluded_shipping_methods',
        'show_cart_automatically',
        'shipping_same_as_billing',
        'allowed_countries',
        'allowed_provinces',
        'provinces_for_country',
        'show_continue_shopping',
        'split_firstname_and_lastname',
    );


    /**
     * Initalize module config variables (properties)
     *
     */
    public function __construct() {
        $this->set('defaultLinkPrompt', $this->_('Buy now'));
        parent::__construct();
    }
    
    /**
     * Module init method
     *
     */
    public function init() {
        /** @var MarkupSnipWire $snipwire Custom ProcessWire API variable */
        $this->wire('snipwire', $this);
    }

    /**
     * Module ready method
     *
     */
    public function ready() {
        // Add a hook after page is rendered and add Snipcart CSS/JS
        $this->addHookAfter('Page::render', $this, 'renderCSSJS');
    }

    /**
     * Getter for snipcartAPIproperties
     *
     * @return array
     *
     */
    public function getSnipcartAPIproperties() {
        return $this->snipcartAPIproperties;
    }

    /**
     * Include JavaScript and CSS files in output
     *
     */
    public function renderCSSJS(HookEvent $event) {
        $modules = $this->wire('modules');

        /** @var Page $page */
        $page = $event->object;
                
        // Prevent adding to pages with system templates assigned
        if ($page->template->flags & Template::flagSystem) return;
        
        // Get SnipWire module config
        $moduleConfig = $modules->getConfig('SnipWire');
        if (!$moduleConfig || !isset($moduleConfig['submit_save_module'])) return;

        // Prevent adding to pages with excluded templates assigned
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

        // Pick available Snipcart JS API properties from module config for API output
        $snipcartAPI = array();
        foreach ($this->getSnipcartAPIproperties() as $key) {
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

        // Prepare Snipcart JS cart currency API output (only rendered if multiple currencies defined)
        if (count($moduleConfig['currencies']) > 1) {
            $jsAPI .= 'Snipcart.api.cart.currency("' . reset($moduleConfig['currencies']) . '");'; // first key is default cart currency!
        }

        $jsAPI .= '</script>' . PHP_EOL;

        // Add Snipcart JS API config
        $jsResources[] = $jsAPI;

        // Prepare Snipcart JS debugging switch
        $jsDebug = '<script>' . PHP_EOL;
        $jsDebug .= 'document.addEventListener("snipcart.ready", function() {' . PHP_EOL;
        $jsDebug .= '    Snipcart.DEBUG = ' . ($moduleConfig['snipcart_debug'] ? 'true' : 'false') . ';' . PHP_EOL;
        $jsDebug .= '});' . PHP_EOL;
        $jsDebug .= '</script>' . PHP_EOL;
        
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

        $moduleConfig = $modules->getConfig('SnipWire');

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
     * Returns the product name using the selected product name field from SnipWire module config.
     * Fallback to $page->title field.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The SnipWire module config
     *
     * @return string $productName The product name
     *
     */
    public function getProductName(Page $product, $moduleConfig = array()) {
        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return '';
        
        if (empty($moduleConfig)) $moduleConfig = $this->wire('modules')->getConfig('SnipWire');
        if (!$product->hasField($moduleConfig['data_item_name_field']) || empty($product->{$moduleConfig['data_item_name_field']})) {
            $productName = $product->title;
        } else {
            $productName = $product->{$moduleConfig['data_item_name_field']};
        }
        return $productName;
    }

    /**
     * Returns the full product page url depending on SnipWire module config.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The SnipWire module config
     *
     * @return string $productUrl The product page url
     *
     */
    public function getProductUrl(Page $product, $moduleConfig = array()) {
        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return '';

        if (empty($moduleConfig)) $moduleConfig = $this->wire('modules')->getConfig('SnipWire');
        if ($moduleConfig['single_page_shop']) {
            $productUrl = $this->wire('pages')->get($moduleConfig['single_page_shop_page'])->httpUrl;
        } else {
            $productUrl = $product->httpUrl;
        }
        return $productUrl;
    }

    /**
     * Returns the product price (optionally formatted by currency property from SnipWire module config).
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The SnipWire module config
     * @param boolean $formatted (unformatted or formatted)
     *
     * @return string $productPrice The product price (unformatted or formatted)
     *
     */
    public function getProductPrice(Page $product, $moduleConfig = array(), $formatted = false) {
        $snipREST = $this->wire('snipREST');

        // If $product (Page) is not a Snipcart product do nothing
        if ($product->template != self::snipcartProductTemplate) return '';
        
        $price = $product->snipcart_item_price;

        // If unformatted return as early as possible
        if (!$formatted) return $price;

        // $moduleConfig param not given
        if (empty($moduleConfig) || !is_array($moduleConfig)) {
            // Try to load currency from database (SnipWire config data)
            if ($currencies = $this->wire('modules')->getConfig('SnipWire', 'currencies')) {
                $currency = reset($currencies);
            }
        // Use currency from given $moduleConfig param
        } else {
            $currency = reset($moduleConfig['currencies']);
        }

        /*
        $currencyDefinition sample:
        
        array(
            'currency' => 'eur',
            'precision' => 2,
            'decimalSeparator' => ',',
            'thousandSeparator' => '.',
            'negativeNumberFormat' => '- %s%v',
            'numberFormat' => '%s%v',
            'currencySymbol' => '€',
        )
        */
        if (!$currencyDefinition = $snipREST->getSettings('currencies')) {
            $currencyDefinition = SnipWireConfig::getDefaultCurrencyDefinition();
        } else {
            // This woodoo is from https://www.php.net/manual/de/function.array-search.php#120784 :-)
            // Searches the $currencyDefinition array for $currency value and returns the first corresponding key 
            $first = array_search($currency, array_column($currencyDefinition, 'currency'));
            $currencyDefinition = $currencyDefinition[$first];
        }

        // Still no currency definition array? Return unformatted!
        if (!is_array($currencyDefinition)) $formatted = false;
                
        if ($formatted) {
            $floatPrice = (float) $price;
            if ($floatPrice < 0) {
                $numberFormatString = $currencyDefinition['negativeNumberFormat'];
                $floatPrice = $floatPrice * -1; // price needs to be unsingned ('-' sign position defined by $numberFormatString)
            } else {
                $numberFormatString = $currencyDefinition['numberFormat'];
            }
            $price = number_format($floatPrice, (integer) $currencyDefinition['precision'], (string) $currencyDefinition['decimalSeparator'], (string) $currencyDefinition['thousandSeparator']);
            $numberFormatString = str_replace('%s', '%1$s', $numberFormatString); // will be currencySymbol
            $numberFormatString = str_replace('%v', '%2$s', $numberFormatString); // will be value
            $price = sprintf($numberFormatString, $currencyDefinition['currencySymbol'], $price);
        }
        return $price;
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
     * @param array $moduleConfig The SnipWire module config
     *
     * @return null|Pageimage $productThumb The product thumbnail or null if no image found
     *
     */
    public function getProductThumb(Page $product, $moduleConfig = array()) {
        // Check if $product (Page) is a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return null;

        if (empty($moduleConfig)) $moduleConfig = $this->wire('modules')->getConfig('SnipWire');
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
        
        $creditcardLabels = SnipWireConfig::getCreditCardLabels();
        foreach ($cards as $card) {
            $cardsWithLabels[] = array(
                'type' => $card,
                'display' => isset($creditcardLabels[$card]) ? $creditcardLabels[$card] : $card,
            );
        }

        return $cardsWithLabels;
    }

}

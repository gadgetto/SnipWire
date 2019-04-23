<?php namespace ProcessWire;

/**
 * MarkupSnipWire - Snipcart markup output for SnipWire.
 * (This module is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
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
                'ProcessWire>=3.0.0',
                'SnipWire',
             )
        );
    }

    const snicpartAnchorTypeButton = 1;
    const snicpartAnchorTypeLink = 2;
    const snipcartProductTemplate = 'snipcart-product';

    /**
     * The module config of SnipWire module.
     * (this is to only have to query the DB once)
     *
     */
    protected $snipWireConfig = array();

    /**
     * Define the currency to be used in cart and catalogue.
     * ('eur' or 'usd' or 'cad' ...)
     *
     */
    private $currency = '';

    /**
     * Snipcart JS API configuration properties.
     *
     */
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
        // Single point to query DB for SnipWire module config
        $this->snipWireConfig = $this->wire('modules')->getConfig('SnipWire');
        
        // Initialize $currency with first currency from SnipWire module config
        if (!$this->snipWireConfig || !isset($this->snipWireConfig['submit_save_module'])) {
            $this->currency = 'eur';
        } else {
            $this->currency = reset($this->snipWireConfig['currencies']);
        }
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
     * Setter for current cart and catalogue currency.
     *
     * @param $currency The desired cart and catalogue currency
     *
     */
    public function setCurrency(string $currency) {
        // Get allowed currencies from module config (set to 'eur' if no module config available)
        $currencies = array();
        if (!$currencies = $this->wire('modules')->getConfig('SnipWire', 'currencies')) $currencies[] = 'eur';

        // Not a valid currency given? Fallback to first currency from module settings
        if (!$currency || !in_array($currency, $currencies)) $currency = reset($currencies);
        $this->currency = $currency;
    }
    
    /**
     * Getter for current cart and catalogue currency.
     *
     * @return string $currency
     *
     */
    public function getCurrency($currency) {
        return $this->currency;
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
     * Include JavaScript and CSS files in output.
     * (Method triggered after every page render)
     *
     */
    public function renderCSSJS(HookEvent $event) {
        $modules = $this->wire('modules');

        /** @var Page $page */
        $page = $event->object;
                
        // Prevent adding to pages with system templates assigned
        if ($page->template->flags & Template::flagSystem) return;
        
        // Prevent rendering if no module config
        if (!$this->snipWireConfig || !isset($this->snipWireConfig['submit_save_module'])) return;

        // Prevent adding to pages with excluded templates assigned
        if (in_array($page->template->name, $this->snipWireConfig['excluded_templates'])) return;
        
        // Snipcart environment (TEST | LIVE?)
        if ($this->snipWireConfig['snipcart_environment'] == 1) {
            $snipcartAPIKey = $this->snipWireConfig['api_key'];
            $environmentStatus = '<!-- Snipcart LIVE mode -->';
        } else {
            $snipcartAPIKey = $this->snipWireConfig['api_key_test'];
            $environmentStatus = '<!-- Snipcart TEST mode -->';
        }

        $cssResources = array();
        $jsResources = array();

        // Add Snipcart CSS resource
        $cssResources[] = '<link rel="stylesheet" href="' . $this->snipWireConfig['snipcart_css_path'] . '"'
            . (!empty($this->snipWireConfig['snipcart_css_integrity']) ? ' integrity="' . $this->snipWireConfig['snipcart_css_integrity'] . '"' : '')
            . ' crossorigin="anonymous">';
        
        // Add jQuery JS resource
        if ($this->snipWireConfig['include_jquery']) {
            $jsResources[] = '<script src="' . $this->snipWireConfig['jquery_js_path'] . '"'
            . (!empty($this->snipWireConfig['jquery_js_integrity']) ? ' integrity="' . $this->snipWireConfig['jquery_js_integrity'] . '"' : '')
            . ' crossorigin="anonymous"></script>';
        }
        
        // Add Snipcart JS resource
        $jsResources[] = $environmentStatus;
        $jsResources[] = '<script src="' . $this->snipWireConfig['snipcart_js_path'] . '"'
            . (!empty($this->snipWireConfig['snipcart_js_integrity']) ? ' integrity="' . $this->snipWireConfig['snipcart_js_integrity'] . '"' : '')
            . ' data-api-key="' . $snipcartAPIKey . '"'
            . ' id="snipcart"'
            . ' crossorigin="anonymous"></script>';

        // Pick available Snipcart JS API properties from module config for API output
        $snipcartAPI = array();
        foreach ($this->getSnipcartAPIproperties() as $key) {
            if (isset($this->snipWireConfig[$key])) {
                $snipcartAPI[$key] = $this->snipWireConfig[$key];
            }
        }

        // Add Snipcart JS API config
        $out = '<script>' . PHP_EOL;
        $out .= 'Snipcart.api';
        foreach ($snipcartAPI as $key => $value) {
            if ($key == 'credit_cards') $value = $this->addCreditCardLabels($value);
            if (is_array($value)) {
                $value = wireEncodeJSON($value, true);
            } else {
                $value = $value ? 'true' : 'false';
            }
            $out .= '.configure("' . $key . '",' . $value . ')';
        }
        $out .= ';' . PHP_EOL;

        $out .= 'document.addEventListener("snipcart.ready",function() {' . PHP_EOL;
        $out .= 'Snipcart.api.cart.currency("' . $this->currency . '");' . PHP_EOL;
        $out .= 'Snipcart.DEBUG = ' . ($this->snipWireConfig['snipcart_debug'] ? 'true' : 'false') . ';' . PHP_EOL;
        $out .= '});' . PHP_EOL;
        $out .= '</script>' . PHP_EOL;
        $jsResources[] = $out;
        
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
     * @param string $prompt 
     * @param array|string $options Options for the rendered html tag:
     *  - `id` (string): Additional id name to add (default='').
     *  - `class` (string): Any additional class names to add, separated by ' ' (default='').
     *  - `attr` (array): Any additional tag attributes to add, as attr => value (default: 'title' => 'Add to cart').
     *  - `label` (string): The button or link label (default='Add to cart').
     *  - `type` (integer) The anchor type - can be button or link [default=self::snicpartAnchorTypeButton]
     *
     * @return string $out The HTML for a snipcart buy button or link (HTML button | a tag)
     *
     */
    public function anchor(Page $product, $options = array()) {

        // Return early if $product (Page) is not a Snipcart product
        if ($product->template != self::snipcartProductTemplate) return '';

        $defaults = array(
            'type' => self::snicpartAnchorTypeButton,
            'class' => 'snipcart-add-item',
            'attr' => array('title' => $this->_('Add to cart')),
            'label' => $this->_('Add to cart'),
        );
        $options = $this->_mergeOptions($defaults, $options);

        $modules = $this->wire('modules');
        $sanitizer = $this->wire('sanitizer');
        
        if ($options['type'] == self::snicpartAnchorTypeLink) {
            $open = '<a href="#"';
            $close = '</a>';
        } else { 
            $open = '<button';
            $close = '</button>';
        }

        $out = $open;
        $out .= isset($options['id']) ? ' id="' . $options['id'] . '"' : '';
        $out .= isset($options['class']) ? ' class="' . $options['class'] . '"' : '';
        if (isset($options['attr']) && is_array($options['attr'])) {
            foreach($options['attr'] as $attr => $value) {
                $out .= ' ' . $attr . '="' . $value . '"';
            }
        }

        // Required Snipcart data-item-* properties
        
        $out .= ' data-item-id="' . $product->snipcart_item_id . '"';
        $out .= ' data-item-name="' . $this->getProductName($product, $this->snipWireConfig) . '"';
        $out .= ' data-item-url="' . $this->getProductUrl($product, $this->snipWireConfig) . '"';
        $out .= " data-item-price='" . $this->getProductPrice($product, $this->snipWireConfig) . "'";
        
        // Optional Snipcart data-item-* properties

        if ($product->snipcart_item_description) {
            $out .= ' data-item-description="' . $product->snipcart_item_description . '"';
        }
        if ($productThumb = $this->getProductThumb($product, $this->snipWireConfig)) {
            $out .= ' data-item-image="' . $productThumb->httpUrl . '"';
        }
        
        // @todo: add more data-item-* properties

        $out .= '>';
        $out .= $options['label'];
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
        
        if (empty($moduleConfig)) $moduleConfig = $this->snipWireConfig;
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

        if (empty($moduleConfig)) $moduleConfig = $this->snipWireConfig;
        if ($moduleConfig['single_page_shop']) {
            $productUrl = $this->wire('pages')->get($moduleConfig['single_page_shop_page'])->httpUrl;
        } else {
            $productUrl = $product->httpUrl;
        }
        return $productUrl;
    }

    /**
     * Returns the product price, raw or formatted by currency property from SnipWire module config.
     *
     * - if formatted = false and multiple currencies are configured, this will return a JSON encoded array: {"usd":20,"cad":25}.
     * - if formatted = true, this will return the price formatted by the selected (or first) currency: € 19,99
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The SnipWire module config
     * @param string $currencySelected The currency code to select the price (eur, usd, cad, ...) -> only in use if formatted = true
     * @param boolean $formatted (unformatted or formatted)
     *
     * @return string $productPrice The product price (unformatted or formatted)
     *
     */
    public function getProductPrice(Page $product, $moduleConfig = array(), $currencySelected = '', $formatted = false) {
        if ($product->template != self::snipcartProductTemplate) return '';
        
        if (empty($moduleConfig) || !is_array($moduleConfig)) {
            $currencies = $this->snipWireConfig['currencies'];
        } else {
            $currencies = $moduleConfig['currencies'];
        }
        if (!is_array($currencies)) return ''; 

        // Collect all price fields values
        $prices = array();
        foreach ($currencies as $currency) {
            $prices[$currency] = (float) $product->get("snipcart_item_price_$currency");
        }

        // ===== unformatted price(s) =====

        // If unformatted return as early as possible
        //  - sample for single currency: 19,99
        //  - sample for multi currency: {"usd":20,"cad":25}
        if (!$formatted) {
            return (count($prices) > 1) ? wireEncodeJSON($prices, true) : reset($prices);
        }

        // ===== formatted price =====
        
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
        
        // Get currency from method param or $snipwire->currency
        if (!$currencySelected) $currencySelected = $this->currency;
        $price = $prices[$currencySelected];
        
        if (!$currencyDefinition = $this->wire('snipREST')->getSettings('currencies')) {
            $currencyDefinition = SnipWireConfig::getDefaultCurrencyDefinition();
        } else {
            // Searches the $currencyDefinition array for $currencySelected value and returns the corresponding key
            $key = array_search($currencySelected, array_column($currencyDefinition, 'currency'));
            $currencyDefinition = $currencyDefinition[$key];
        }
                
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

        return $price;
    }
    
    /**
     * Returns the product price formatted by currency property from SnipWire module config.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param array $moduleConfig The SnipWire module config
     * @param string $currencySelected The currency code to select the price (eur, usd, cad, ...)
     *
     * @return string The formatted product price
     *
     * @see function getProductPrice
     *
     */
    public function getProductPriceFormatted(Page $product, $moduleConfig = array(), $currencySelected = '') {
        return $this->getProductPrice($product, $moduleConfig, $currencySelected, true);
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

        if (empty($moduleConfig)) $moduleConfig = $this->snipWireConfig;
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

    /**
     * Prepare and merge $options with $defaults arguments for markup methods.
     *
     * @param array $defaults
     * @param array $options
     * @return array
     *
     */
    private function _mergeOptions(array $defaults, array $options) {
        $defaultsClass = isset($defaults['class']) ? explode(' ', $defaults['class']) : array();
        $optionsClass = isset($options['class']) ? explode(' ', $options['class']) : array();
        $options['class'] = implode(' ', array_merge($defaultsClass, $optionsClass));

        // Prepare tag attributes
        $defaultsAttr = isset($defaults['attr']) && is_array($defaults['attr']) ? $defaults['attr'] : array();
        $optionsAttr = isset($options['attr']) && is_array($options['attr']) ? $options['attr'] : array();
        $options['attr'] = array_unique(array_merge($defaultsAttr, $optionsAttr));
        unset($defaults['attr']);
        
        return array_merge($defaults, $options);
    }

}

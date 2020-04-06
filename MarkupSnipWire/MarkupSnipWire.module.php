<?php
namespace ProcessWire;

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
 
require_once dirname(__DIR__) . '/helpers/CurrencyFormat.php';

use SnipWire\Helpers\CurrencyFormat;

class MarkupSnipWire extends WireData implements Module {
    
    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire Markup'), // Module Title
            'summary' => __('Snipcart markup output for SnipWire.'), // Module Summary
            'version' => '0.8.6',
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'singular' => true, 
            'autoload' => true, 
            'requires' => array(
                'ProcessWire>=3.0.148',
                'SnipWire',
                'PHP>=7.0.0',
             )
        );
    }

    const snicpartAnchorTypeButton = 1;
    const snicpartAnchorTypeLink = 2;

    /** @var array $snipwireConfig The module config of SnipWire module */
    protected $snipwireConfig = array();

    /** @var string $currency The currency to be used in cart and catalogue ('eur' or 'usd' or 'cad' ...) */
    private $currency = '';

    /** @var Page $customCartFieldsPage The "Custom Cart Fields" page */
    protected $customCartFieldsPage = array();

    /** @var string $cartCustomFields The content of the "snipcart_cart_custom_fields" field */
    protected $cartCustomFields = '';

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
        parent::__construct();
    }
    
    /**
     * Module init method
     *
     */
    public function init() {
        /** @var MarkupSnipWire $snipwire Custom ProcessWire API variable */
        $this->wire('snipwire', $this);
        
        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $this->snipwireConfig = $this->wire('modules')->get('SnipWire');

        // Init cart/catalogue currency
        $this->_initCurrency();

        // Get the "Custom Cart Fields" page (the corresponding template only allows one single page)
        $this->customCartFieldsPage = $this->wire('pages')->findOne('name=custom-cart-fields, template=snipcart-cart, include=hidden');
        
        // Get the "snipcart_cart_custom_fields" field content
        if ($this->customCartFieldsPage->hasField('snipcart_cart_custom_fields')) {
            $this->cartCustomFields = $this->customCartFieldsPage->snipcart_cart_custom_fields;
        }
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
     * Set cart and catalogue currency (ISO 4217 currency code) using $input, $session or module config.
     *
     */
    private function _initCurrency() {
        $input = $this->wire('input');
        $session = $this->wire('session');
        $sanitizer = $this->wire('sanitizer');

        $currencyParam = $this->snipwireConfig->currency_param ?? 'currency';

        // Get allowed currencies from module config (set to 'eur' if no currecy config available)
        $currencies = $this->snipwireConfig->currencies;
        if (empty($currencies) || !is_array($currencies)) $currencies[] = 'eur';

        // GET, POST, session
        $currency = $input->$currencyParam ?? $session->get($currencyParam);
        $currency = strtolower($currency);
        $currency = $sanitizer->option($currency, $currencies);

        // Not a valid currency given? Fallback to first currency from module config
        if (!$currency) $currency = reset($currencies);
        $this->currency = $currency;
    }
    
    /**
     * Getter for current cart and catalogue currency.
     *
     * @return string $currency The ISO 4217 currency code
     *
     */
    public function getCurrency() {
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

        $snipwireConfig = $this->snipwireConfig;

        /** @var Page $page */
        $page = $event->object;
                
        // Prevent adding to pages with system templates assigned
        if ($page->template->flags & Template::flagSystem) return;
        
        // Prevent rendering if module config was never saved
        if (!$snipwireConfig->submit_save_module) return;

        // Prevent adding to pages with excluded templates assigned
        if (in_array($page->template->name, $snipwireConfig->excluded_templates)) return;
        
        // Snipcart environment (TEST | LIVE?)
        if ($snipwireConfig->snipcart_environment == 1) {
            $snipcartAPIKey = $snipwireConfig->api_key;
            $environmentStatus = '<!-- Snipcart LIVE mode -->';
        } else {
            $snipcartAPIKey = $snipwireConfig->api_key_test;
            $environmentStatus = '<!-- Snipcart TEST mode -->';
        }

        $cssResources = array();
        $jsResources = array();

        // Add Snipcart CSS resource
        $cssResources[] = $environmentStatus;
        if ($snipwireConfig->include_snipcart_css) {
            $out  = '<link';
            $out .= ' rel="stylesheet"';
            $out .= ' href="' . $snipwireConfig->snipcart_css_path . '"';
            $out .= (!empty($snipwireConfig->snipcart_css_integrity) ? ' integrity="' . $snipwireConfig->snipcart_css_integrity . '"' : '');
            $out .= '>';
            $cssResources[] = $out;
        }
        
        // Add jQuery JS resource
        if ($snipwireConfig->include_jquery) {
            $out  = '<script';
            $out .= ' src="' . $snipwireConfig->jquery_js_path . '"';
            $out .= (!empty($snipwireConfig->jquery_js_integrity) ? ' integrity="' . $snipwireConfig->jquery_js_integrity . '"' : '');
            $out .= '></script>';
            $jsResources[] = $out;
        }
        
        // Add Snipcart JS resource + custom cart fields (if any)
        $jsResources[] = $environmentStatus;
        $out  = '<script';
        $out .= ' id="snipcart"';
        $out .= ' data-api-key="' . $snipcartAPIKey . '"';
        $out .= (!empty($this->cartCustomFields) && $snipwireConfig->cart_custom_fields_enabled ? ' ' . $this->cartCustomFields : '');
        $out .= ' src="' . $snipwireConfig->snipcart_js_path . '"';
        $out .= (!empty($snipwireConfig->snipcart_js_integrity) ? ' integrity="' . $snipwireConfig->snipcart_js_integrity . '"' : '');
        $out .= '></script>';
        $jsResources[] = $out;

        // Pick available Snipcart JS API properties from module config for API output
        $snipcartAPI = array();
        foreach ($this->getSnipcartAPIproperties() as $key) {
            if (isset($snipwireConfig->$key)) {
                $snipcartAPI[$key] = $snipwireConfig->$key;
            }
        }

        // Add Snipcart JS API config
        $out  = '<script>' . PHP_EOL;
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
        $out .= ';';

        $out .= 'document.addEventListener("snipcart.ready",function() {' . PHP_EOL;
        $out .= 'Snipcart.api.cart.currency("' . $this->currency . '");' . PHP_EOL;
        $out .= 'Snipcart.DEBUG = ' . ($snipwireConfig->snipcart_debug ? 'true' : 'false') . ';' . PHP_EOL;
        $out .= '});' . PHP_EOL;
        $out .= '</script>';
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
     * @param array|string $options Options for the rendered html tag:
     *  - `id` (string): Additional id name to add (default='').
     *  - `class` (string): Any additional class names to add, separated by ' ' (default='').
     *  - `attr` (array): Any additional tag attributes to add, as attr => value (default: 'title' => 'Add to cart').
     *  - `label` (string): The button or link label (default='Add to cart').
     *  - `type` (integer): The anchor type - can be button or link [default=self::snicpartAnchorTypeButton]
     * @return string $out The HTML for a Snipcart buy button or link (HTML button | a tag)
     *
     * @see: https://docs.snipcart.com/v3/setup/products
     *
     * Mandatory product attributes:
     * =============================
     * 
     * - data-item-name: string ProcessWire Page title by default - can be changed to any text field type.
     * - data-item-id: string Unique Stock Keeping Unit - SKU (will be prefilled with page ID).
     * - data-item-price: number Will be created by selecting the desired currency(s) in module config form.
     * - data-item-url: string URL where Snipcart crawler will find the Buy button.
     *   *) Will be set by SnipCart automatically (not defined as PW field)
     * - data-item-description: string Short product description, visible in cart and during checkout.
     * - data-item-image: string Thumbnail URL of product in cart. This must be an absolute URL.
     * 
     * Optional product attributes:
     * ============================
     * 
     * Product information:
     * 
     * - data-item-categories: string[] The categories this product belongs to. Example: data-item-categories="cat1, cat2, cat3"
     * - data-item-metadata: json-object Metadata for the product. Example: data-item-metadata='{"key": "value"}'
     *   *) Will be set by SnipCart automatically (not defined as PW field)
     * 
     * Product dimensions:
     * 
     * - data-item-weight: number Using grams as weight units. This is mandatory if you use any integrated shipping provider.
     * - data-item-width: number Using centimeters as dimension unit. Will be used if you enabled an integrated shipping provider.
     * - data-item-length: number Using centimeters as dimension unit. Will be used if you enabled an integrated shipping provider.
     * - data-item-height: number Using centimeters as dimension unit. Will be used if you enabled an integrated shipping provider.
     * 
     * Product quantity:
     * 
     * - data-item-quantity: number Set a default quantity for the item that you are about to add.
     * - data-item-max-quantity: number Maximum allowed quantity of product
     * - data-item-min-quantity: number Minimum allowed quantity for product
     * - data-item-quantity-step: integer The quantity of a product will increment by this value.
     * - data-item-stackable: boolean Setting this to false, adding the same product to the cart will result in two distinct items in the cart, instead of simply increasing the quantity.
     * 
     * Product taxes:
     * 
     * - data-item-taxable: boolean Set to false to exclude item from the taxes calculation. Default is true.
     * - data-item-taxes: string[] Using this option, you can define which tax will be applied on this product.
     * - data-item-has-taxes-included: boolean Set to true if the taxes you defined are included in your product prices.
     *   *) Will be set by SnipCart automatically (not defined as PW field)
     * 
     * Digital goods:
     * 
     *   data-item-file-guid: 
     * 
     * Subscriptions and recurring payments:
     * 
     * - data-item-payment-interval: string Sets interval for the recurring payment. It can be Day, Week, Month, or Year
     * - data-item-payment-interval-count: number Sets payment interval count (sample: data-item-payment-interval = "Month" and data-item-payment-interval-count = "2" --> customer will be charged every 2 months).
     * - data-item-payment-trial: number Enables trial period for customers. Specify the duration of the trial by number of days.
     * - data-item-recurring-shipping: boolean Set to false if you need to charge shipping only on the initial order (true by default).
     *   data-item-cancellation-action: (undocumented)
     *   data-item-pausing-action: (undocumented)
     * 
     * Others:
     * 
     * - data-item-shippable: boolean Setting this to false, the product will be flagged as an item that can not be shipped.
     *
     */
    public function anchor(Page $product, $options = array()) {
        // Return early if $product (Page) is not a Snipcart product
        if (!$this->isProductTemplate($product->template)) return '';

        $snipwireConfig = $this->snipwireConfig;

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
        // ========================================

        $out .= ' data-item-name="' . $this->getProductName($product) . '"';
        $out .= ' data-item-id="' . $product->snipcart_item_id . '"';
        $out .= " data-item-price='" . $this->getProductPrice($product) . "'";
        $out .= ' data-item-url="' . $this->getProductUrl($product) . '"';

        if ($product->snipcart_item_description) {
            $out .= ' data-item-description="' . $product->snipcart_item_description . '"';
        }

        if ($productThumb = $this->getProductThumb($product)) {
            $out .= ' data-item-image="' . $productThumb->httpUrl . '"';
        }

        // Optional Snipcart data-item-* properties
        // ========================================

        if ($productCategories = $this->getProductCategoriesString($product)) {
            $out .= ' data-item-categories="' . $productCategories . '"';
        }

        // Metadata to be stored with each product (PW page related data)
        $meta = array(
            'id' => $product->id,
            'created' => $product->created,
            'modified' => $product->modified,
            'published' => $product->published,
            'created_users_id' => $product->created_users_id,
            'modified_users_id' => $product->modified_users_id,
        );
        $out .= " data-item-metadata='" . wireEncodeJSON($meta) . "'";

        if ($product->snipcart_item_weight) {
            $out .= ' data-item-weight="' . $product->snipcart_item_weight . '"';
        }

        if ($product->snipcart_item_width) {
            $out .= ' data-item-width="' . $product->snipcart_item_width . '"';
        }

        if ($product->snipcart_item_length) {
            $out .= ' data-item-length="' . $product->snipcart_item_length . '"';
        }

        if ($product->snipcart_item_height) {
            $out .= ' data-item-height="' . $product->snipcart_item_height . '"';
        }

        $defaultQuantity = $product->snipcart_item_quantity ? $product->snipcart_item_quantity : 1;
        $out .= ' data-item-quantity="' . $defaultQuantity . '"';
        
        if ($product->snipcart_item_max_quantity) {
            $out .= ' data-item-max-quantity="' . $product->snipcart_item_max_quantity . '"';
        }

        if ($product->snipcart_item_min_quantity) {
            $out .= ' data-item-min-quantity="' . $product->snipcart_item_min_quantity . '"';
        }

        if ($product->snipcart_item_quantity_step) {
            $out .= ' data-item-quantity-step="' . $product->snipcart_item_quantity_step . '"';
        }

        if ($product->hasField('snipcart_item_stackable')) {
            $stackable = $product->snipcart_item_stackable ? 'true' : 'false';
        } else {
            $stackable = 'true';
        }
        $out .= ' data-item-stackable="' . $stackable . '"';

        if ($product->hasField('snipcart_item_taxable')) {
            $taxable = $product->snipcart_item_taxable ? 'true' : 'false';
        } else {
            $taxable = 'true';
        }
        $out .= ' data-item-taxable="' . $taxable . '"';

        // Only a single tax per product for now (Snipcart supports multiple taxes per product)
        if ($product->snipcart_item_taxes) {
            $out .= ' data-item-taxes="' . $product->snipcart_item_taxes . '"';
        }

        // This is a global property and is set for all products in SnipWire config editor
        if ($snipwireConfig->taxes_included) {
            $out .= ' data-item-has-taxes-included="true"';
        }
        
        if ($product->snipcart_item_payment_interval) {
            $out .= ' data-item-payment-interval="' . $product->snipcart_item_payment_interval->value . '"';
        }
        
        if ($product->snipcart_item_payment_interval_count) {
            $out .= ' data-item-payment-interval-count="' . $product->snipcart_item_payment_interval_count . '"';
        }
        
        if ($product->snipcart_item_payment_trial) {
            $out .= ' data-item-payment-trial="' . $product->snipcart_item_payment_trial . '"';
        }
        
        if ($product->hasField('snipcart_item_recurring_shipping')) {
            $recurringShipping = $product->snipcart_item_recurring_shipping ? 'true' : 'false';
            $out .= ' data-item-recurring-shipping="' . $recurringShipping . '"';
        }
        
        if ($product->hasField('snipcart_item_shippable')) {
            $shippable = $product->snipcart_item_shippable ? 'true' : 'false';
        } else {
            $shippable = 'true';
        }
        $out .= ' data-item-shippable="' . $shippable . '"';

        // Get the "snipcart_item_custom_fields" field content
        if ($product->hasField('snipcart_item_custom_fields')) {
            $customFields = $product->snipcart_item_custom_fields;
            if ($customFields) {
                $out .= ' ' . $customFields;
            }
        }

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
     *
     * @return string $productName The product name
     *
     */
    public function getProductName(Page $product) {
        // Check if $product (Page) is a Snipcart product
        if (!$this->isProductTemplate($product->template)) return '';
        
        $snipwireConfig = $this->snipwireConfig;
        if (!$product->hasField($snipwireConfig->data_item_name_field) || empty($product->{$snipwireConfig->data_item_name_field})) {
            $productName = $product->title;
        } else {
            $productName = $product->{$snipwireConfig->data_item_name_field};
        }
        return $productName;
    }

    /**
     * Returns the full product page url depending on SnipWire module config.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     *
     * @return string $productUrl The product page url
     *
     */
    public function getProductUrl(Page $product) {
        // Check if $product (Page) is a Snipcart product
        if (!$this->isProductTemplate($product->template)) return '';

        $snipwireConfig = $this->snipwireConfig;
        if ($snipwireConfig->single_page_shop) {
            $productUrl = $this->wire('pages')->get($snipwireConfig->single_page_shop_page)->httpUrl;
        } else {
            $productUrl = $product->httpUrl;
        }
        return $productUrl;
    }

    /**
     * Returns the product price, raw or formatted by currency property from SnipWire module config.
     *
     * - if formatted = false this will return a JSON encoded array: {"eur":19.99} or {"usd":20,"cad":25,...}.
     * - if formatted = true, this will return the price formatted by the selected (or first) currency: € 19,99
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param string $currencySelected The currency code to select the price for (eur, usd, cad, ...) -> only in use if formatted = true
     * @param boolean $formatted (unformatted or formatted)
     *
     * @return string $productPrice The product price (unformatted or formatted)
     *
     */
    public function getProductPrice(Page $product, $currencySelected = '', $formatted = false) {
        if (!$this->isProductTemplate($product->template)) return '';
        
        $currencies = $this->snipwireConfig->currencies;
        if (!is_array($currencies)) return ''; 

        // Collect all price fields values
        $prices = array();
        foreach ($currencies as $currency) {
            // Snipcart always needs a . as separator - so we may not typecasting (float) as it
            // would be locale aware so it could lead to , as decimal separator
            if ($price = $product->get("snipcart_item_price_$currency")) {
                $prices[$currency] = $price;
            }
        }

        // ===== unformatted price(s) =====

        // If unformatted return as early as possible
        if (!$formatted) return wireEncodeJSON($prices);

        // ===== formatted price =====
        
        // sample format:
        // 
        // array(
        //     'currency' => 'eur',
        //     'precision' => 2,
        //     'decimalSeparator' => ',',
        //     'thousandSeparator' => '.',
        //     'negativeNumberFormat' => '- %s%v',
        //     'numberFormat' => '%s%v',
        //     'currencySymbol' => '€',
        // )
        
        // Get currency from method param or $snipwire->currency
        if (!$currencySelected) $currencySelected = $this->currency;
        $price = $prices[$currencySelected];
        
        return CurrencyFormat::format($price, $currencySelected);
    }
    
    /**
     * Returns the product price formatted by currency property from SnipWire module config.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param string $currencySelected The currency code to select the price for (eur, usd, cad, ...)
     *
     * @return string The formatted product price
     *
     * @see function getProductPrice
     *
     */
    public function getProductPriceFormatted(Page $product, $currencySelected = '') {
        return $this->getProductPrice($product, $currencySelected, true);
    }

    /**
     * Generates a Thumbnail from first image of product page-image field and returns it.
     *
     * @param Page $product The product page which holds Snipcart related product fields
     *
     * @return null|Pageimage $productThumb The product thumbnail or null if no image found
     *
     */
    public function getProductThumb(Page $product) {
        // Check if $product (Page) is a Snipcart product
        if (!$this->isProductTemplate($product->template)) return null;

        $snipwireConfig = $this->snipwireConfig;
        $productThumb = null;
        if ($image = $product->snipcart_item_image->first()) {
            $productThumb = $image->size($snipwireConfig['cart_image_width'], $snipwireConfig['cart_image_height'], [
                'cropping' => $snipwireConfig['cart_image_cropping'] ? true : false,
                'quality' => $snipwireConfig['cart_image_quality'],
                'hidpi' => $snipwireConfig['cart_image_hidpi'] ? true : false,
                'hidpiQuality' => $snipwireConfig['cart_image_hidpiQuality'],
            ]);
        }
        return $productThumb;
    }

    /**
     * Returns product categories as array or comma seperated string (if any).
     *
     * @param Page $product The product page which holds Snipcart related product fields
     * @param bool $array Default is to return an array (specified by TRUE). If you want a comma seperated string instead, specify FALSE. 
     *
     * @return null|array|string The product categories as array, comma seperated string or empty array or empty string if none found
     *
     */
    public function getProductCategories(Page $product, $array = true) {
        // Check if $product (Page) is a Snipcart product
        if (!$this->isProductTemplate($product->template)) return null;

        $snipwireConfig = $this->snipwireConfig;
        $categories = array();
        if ($categoriesFieldName = $snipwireConfig['data_item_categories_field']) {
            if ($categoriesField = $product->$categoriesFieldName) {
                $categories = $categoriesField->each('title');
            }
        }
        return $array ? $categories : implode(',', $categories);
    }

    /**
     * Returns product categories as comma seperated string (if any).
     *
     * @param Page $product The product page which holds Snipcart related product fields
     *
     * @return null|string The product categories as comma seperated string or empty string if none found
     *
     */
    public function getProductCategoriesString(Page $product) {
        return $this->getProductCategories($product, false);
    }

    /**
     * Get an array of SnipWire product templates (defined in module settings).
     *
     * @param boolean $objects Whether to return an array of objects or object names only
     * @return array|WireArray A array of template objects or template names (if $objects = true) [default=true]
     *
     */
    public function getProductTemplates($objects = true) {
        $templates = $this->snipwireConfig->product_templates;
        if ($objects) {
            $productTemplates = new WireArray();
            foreach ($templates as $template) {
                if ($t = $this->wire('templates')->get($template)) $productTemplates->add($t);
            }
        } else {
            $productTemplates = $templates ?? array();
        }
        return $productTemplates;
    }

    /**
     * Check if this is a SnipWire product template.
     *
     * @param string|Template $template Template name or ProcessWire Template object
     * @return boolean
     *
     */
    public function isProductTemplate($template) {
        if (is_object($template) && ($template instanceof Template)) {
            $templateName = $template->name;
        } elseif (is_string($template)) {
            $templateName = $template;
        } else {
            return false;
        } 
        $productTemplates = $this->snipwireConfig->product_templates;
        return (in_array($templateName, $productTemplates)) ? true : false;
    }

    /**
     * Get a selection of fields from a SnipWire product template.
     * 
     * @param string $defaultFieldName Name of the field to be returned if no product templates available
     * @param array $allowedFieldTypes An array of allowed field types to be returned [optional]
     * @param array $excludeFieldNames An array of field names to be excluded from result [optional]
     * @return WireArray $selectedFields
     * 
     */
    public function getProductTemplateFields($defaultFieldName, $allowedFieldTypes = array(), $excludeFieldNames = array()) {
        $selectedFields = new WireArray();
        
        // Collect fields from all product templates and make unique
        $productTemplates = $this->getProductTemplates();
        foreach ($productTemplates as $productTemplate) {
            foreach ($productTemplate->fields as $field) {
                $selectedFields->add($field);
            }
        }
        $selectedFields = $selectedFields->unique(); // remove duplicates

        if (!empty($selectedFields)) {
            if (!empty($allowedFieldTypes)) $selectedFields = $selectedFields->find('type=' . implode('|', $allowedFieldTypes));
            if (!empty($excludeFieldNames)) $selectedFields = $selectedFields->find('!name%=' . implode('|', $excludeFieldNames));
        } else {
            $defaultField = $this->wire('fields')->get($defaultFieldName);
            if (!empty($defaultField->name)) {
                $selectedFields->add($defaultField);
            } else {
                // Create a placeholder field
                $placeholder = new Field();
                $placeholder->type = $this->wire('modules')->get('FieldtypeText');
                $placeholder->name = $defaultFieldName;
                $selectedFields->add($placeholder);
            }
        }
        return $selectedFields;
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

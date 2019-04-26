<?php namespace ProcessWire;

/**
 * SnipWireConfig - Config file for SnipWire.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class SnipWireConfig extends ModuleConfig {

    /** @var array $availableCreditCards Available creditcard types */
    protected $availableCreditCards = array(            
        'visa',
        'mastercard',
        'maestro',
        'amex',
        'dinersclub',
        'discover',
        'jcb',
        'cardbleue',
        'dankort',
        'cartasi',
        'postepay',
    );

    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Returns an array of credit card labels, indexed by card name
     * 
     * @return array
     * 
     */
    public static function getCreditCardLabels() {
        return array(
            'visa' => __('Visa'),
            'mastercard' => __('Mastercard'),
            'maestro' => __('Maestro'),
            'amex' => __('American Express'),
            'dinersclub' => __('Diners Club'),
            'discover' => __('Discover'),
            'jcb' => __('JCB'),
            'cardbleue' => __('Carte Bleue'),
            'dankort' => __('Dankort'),
            'cartasi' => __('CartaSi'),
            'postepay' => __('Postepay'),
        );
    }

    /**
     * Returns an array of worldwide supported currencies, as name => label
     * (comes from static file Currencies.php which holds all currencies 
     * supported by Snipcart -> copied from Snipcart dashboard)
     * 
     * @return array
     * 
     */
    public static function getSupportedCurrencies() {
        return require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'Currencies.php';
    }
    
    /**
     * Get the default currency definition.
     *
     * @param boolean $json Wether to return as Json formatted string and not array
     * @return array|string (Json)
     * 
     */
    public static function getDefaultCurrencyDefinition($json = false) {
        $defaultCurrency = array(
            'currency' => 'eur',
            'precision' => 2,
            'decimalSeparator' => ',',
            'thousandSeparator' => '.',
            'negativeNumberFormat' => '- %s%v',
            'numberFormat' => '%s%v',
            'currencySymbol' => '€',
        );
        return ($json) ? wireEncodeJSON($defaultCurrency, true) : $defaultCurrency;
    }

    /**
     * Default config
     *
     */
    public function getDefaults() {
        return array(
            'api_key' => 'YOUR_LIVE_API_KEY',
            'api_key_test' => 'YOUR_TEST_API_KEY',
            'api_key_secret' => 'YOUR_LIVE_API_KEY_SECRET',
            'api_key_secret_test' => 'YOUR_TEST_API_KEY_SECRET',
            'snipcart_environment' => 0,
            'single_page_shop' => 0,
            'single_page_shop_page' => 1,
            'credit_cards' => array('visa', 'mastercard', 'maestro'),
            'currencies' => array('eur'),
            'show_cart_automatically' => 1,
            'shipping_same_as_billing' => 1,
            'show_continue_shopping' => 1,
            'split_firstname_and_lastname' => 1,
            'taxes_included' => 1,
            'snipcart_debug' => 1,
            'snipcart_css_path' => 'https://cdn.snipcart.com/themes/2.0/base/snipcart.min.css',
            'snipcart_css_integrity' => '',
            'snipcart_js_path' => 'https://cdn.snipcart.com/scripts/2.0/snipcart.js',
            'snipcart_js_integrity' => '',
            'include_jquery' => 1,
            'jquery_js_path' => 'https://code.jquery.com/jquery-3.3.1.min.js',
            'jquery_js_integrity' => 'sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=',
            'excluded_templates' => array(),
            'cart_image_width' => 65,
            'cart_image_height' => 65,
            'cart_image_cropping' => 1,
            'cart_image_quality' => 70,
            'cart_image_hidpi' => 1,
            'cart_image_hidpiQuality' => 50,
            'data_item_name_field' => 'title',
        );
    }

    public function getInputfields() {
        $modules = $this->wire('modules');
        $snipREST = $this->wire('snipREST');
        
        $inputfields = parent::getInputfields();

        // Additional steps

        $redirectUrl = urlencode($_SERVER['REQUEST_URI']);

        $steps = array();
        $steps[] = array(
            'type' => 'checklist',
            'name' => 'product_package',
            'url' => '../setup/snipwire/install-product-package/?ret=' . $redirectUrl,
            //'url2' => '../setup/snipwire/uninstall-product-package/?ret=' . $redirectUrl, // disabled uninstaller
            'prompt' => $this->_('Install Snipcart products package'),
            'prompt2' => $this->_('Uninstall package'),
            'icon' => 'check-circle',
            'icon2' => 'times-circle',
            'description' => $this->_('Contains product templates, files, fields and some demo pages required to build a Snipcart product catalogue. This additional step is needed to prevent unintended deletion of your Snipcart products catalogue when main module is uninstalled. These resources need to be removed manually!'),
        );
        $steps[] = array(
            'type' => 'link',
            'name' => 'rest_test',
            'url' => '../setup/snipwire/test-snipcart-rest-connection/?ret=' . $redirectUrl,
            'prompt' => $this->_('Snipcart REST API connection test'),
            'description' => $this->_('Follow this link to send a test request to the Snipcart REST API (you first need to enter a valid API key in the settings below).'),
        );
        
        $stepsCounter = count($steps);
        
        if ($stepsCounter) {
            // Check which steps are already done and add flag
            $data = $modules->getConfig('SnipWire');
            for ($i = 0; $i < count($steps); $i++) {
                $steps[$i]['done'] = (isset($data[$steps[$i]['name']]) && $data[$steps[$i]['name']]) ? true : false;;
            }

            // Render steps
            $f = $modules->get('InputfieldMarkup');
            $f->attr('name', '_next_steps');
            $f->icon = 'cog';
            $f->label = $this->_('Additional steps');
            $f->value = '<ul class="uk-list uk-list-divider">';
            foreach ($steps as $step) {
                $f->value .= $this->renderStep($step);
            }
            $f->value .= '</ul>';
            
            $inputfields->add($f);
        }

        // Snipcart API configuration

        $fsAPI = $this->wire('modules')->get('InputfieldFieldset');
        $fsAPI->icon = 'plug';
        $fsAPI->label = $this->_('Snipcart API Configuration');
        $fsAPI->set('themeOffset', true);

        $f = $modules->get('InputfieldMarkup');
        $f->description = $this->_('To get your public JavaScript - and secret REST API keys, you will need a Snipcart account. To register, go to [https://app.snipcart.com/account/register](https://app.snipcart.com/account/register). Once you’ve signed up and confirmed your account, log in and head to the [Account > API Keys section](https://app.snipcart.com/dashboard/account/credentials). There you’ll find your public API keys and also need to create your secret API keys for live and test environment.');
        $fsAPI->add($f);
        
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'api_key');
        $f->label = $this->_('Snipcart Public JavaScript API Key');
        $f->required = true;
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'api_key_test');
        $f->label = $this->_('Snipcart Public JavaScript Test API Key');
        $f->required = true;
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'api_key_secret');
        $f->label = $this->_('Snipcart Secret API Key');
        $f->description = $this->_('The secret key is used to access all the data of your LIVE Snipcart environment via REST API.');
        $f->notes = $this->_('This key should never be visible to anyone!');
        $f->required = true;
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'api_key_secret_test');
        $f->label = $this->_('Snipcart Secret Test API Key');
        $f->description = $this->_('The secret test key is used to access all the data of your Snipcart TEST environment via REST API.');
        $f->notes = $this->_('This key should never be visible to anyone!');
        $f->required = true;
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldRadios');
        $f->attr('name', 'snipcart_environment');
        $f->label = $this->_('Snipcart Environment');
        $f->description = $this->_('Snipcart offers two separate and totally isolated environments to allow a secure staging without affecting the live environment.');
        $f->notes = $this->_('This changes the environment API key when including the Snipcart JS file in your template.');
        $f->optionColumns = 1;
        $f->addOption(0, 'TEST mode'); 
        $f->addOption(1, 'LIVE mode');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'single_page_shop'); 
        $f->label = $this->_('Single-Page Shop');
        $f->label2 = $this->_('This Snipcart shop runs on a single-page website');
        $f->description = $this->_('For single-page shops, the data-item-url field of each product will be filled with the full URL to the selected page.');
        $f->notes = $this->_('This tells the Snipcart crawler where to find your products to validate an order\'s integrity.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldPageListSelect');
        $f->attr('name', 'single_page_shop_page');
        $f->label = $this->_('Select Your Single-Page Shop Page');
        $f->required = true; // needs to be set when using requiredIf
        $f->requiredIf = 'single_page_shop=1';
        $f->showIf = 'single_page_shop=1';
        $fsAPI->add($f);

        $f = $modules->get('InputfieldAsmSelect');
        $f->attr('name', 'credit_cards');
        $f->label = 'Accepted Credit Cards';
        $f->description = $this->_('Specify which credit cards you want to accept during checkout.');
        $creditcardLabels = self::getCreditCardLabels();
        foreach ($this->availableCreditCards as $card) {
            $cardlabel = isset($creditcardLabels[$card]) ? $creditcardLabels[$card] : $card;
            $f->addOption($card, $cardlabel);
        }
        $f->required = true;
        $fsAPI->add($f);
        
        $f = $modules->get('InputfieldAsmSelect');
        $f->attr('name', 'currencies'); 
        $f->label = $this->_('Set Currencies'); 
        $f->description = $this->_('Selected currency(s) will be used in your shop catalogue and in the SnipCart shopping-cart system during checkout.');
        $f->description .= ' ';
        $f->description .= $this->_('As SnipWire fetches the available currency-list directly from Snipcart Dashboard, you will need to first setup the desired currency format(s) in your [SnipCart Dashboard - Regional Settings](https://app.snipcart.com/dashboard/settings/regional).');
        $f->description .= ' ';
        $f->description .= $this->_('Selecting a currency will also create a corresponding currency specific price input field and add it to the products template automatically.');
        $f->notes = $this->_('Selecting more than one curency will enable Snipcart\'s multiple currencies payments feature. The first currency in the list will be the default one used in your product catalogue and in Snipcart shopping-cart.');

        $supportedCurrencies = self::getSupportedCurrencies();
        $currencies = array();
        if (!$currencies = $snipREST->getSettings('currencies', WireCache::expireNever, true)) {
            $currencies[] = $this->getDefaultCurrencyDefinition();
        }
        foreach ($currencies as $currency) {
            $currencyName = $currency['currency'];
            $currencyLabel = isset($supportedCurrencies[$currency['currency']])
                ? $supportedCurrencies[$currency['currency']]
                : $currency['currency'];
            $f->addOption($currencyName, $currencyLabel);
        }
        $f->required = true;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'show_cart_automatically'); 
        $f->label = $this->_('Show Shopping Cart Automatically');
        $f->label2 = $this->_('Show cart automatically');
        $f->description = $this->_('If you want to prevent the cart from showing up everytime a product is added, you can disable it.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'shipping_same_as_billing'); 
        $f->label = $this->_('Use Billing Address for Shipping');
        $f->label2 = $this->_('Use billing address for shipping preselected');
        $f->description = $this->_('Whether the "Use this address for shipping" option on the billing address tab is pre-selected or not.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'show_continue_shopping'); 
        $f->label = $this->_('"Continue shopping" Button');
        $f->label2 = $this->_('Show the "Continue shopping" button');
        $f->description = $this->_('Use this setting if you want to show the "Continue shopping" button. This button will appear just beside the "Close cart" button.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'split_firstname_and_lastname'); 
        $f->label = $this->_('Split First Name and Last Name');
        $f->label2 = $this->_('Split the First name and Last name');
        $f->description = $this->_('Use this setting to split the First name and Last name in billing address and shipping address forms.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'taxes_included'); 
        $f->label = $this->_('Taxes Included in Prices');
        $f->label2 = $this->_('Taxes are included in product prices');
        $f->description = $this->_('Use this setting if the taxes you defined are included in your product prices');
        $f->columnWidth = 50;
        $fsAPI->add($f);
        
        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'snipcart_debug'); 
        $f->label = $this->_('Snipcart JavaScript Debug Mode');
        $f->label2 = $this->_('Enable Snipcart JavaScript debug mode');
        $f->description = $this->_('This will allow you to see JavaScript errors on your site, failing requests and logs from the services you use in your browsers developer console.');
        $f->notes = $this->_('All logs from the Snipcart script will be prefixed with Snipcart:');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Other Configuration Parameters');
        $f->value = 
        '<p class="detail">' .
            $this->_('Other SnipCart API settings can be configured through the Snipcart backend:') . ' ' .
            '<a href="https://app.snipcart.com/dashboard" target="_blank">https://app.snipcart.com/dashboard</a>' .
        '</p>';
        $fsAPI->add($f);

        $inputfields->add($fsAPI);

        // Markup configuration

        $fsMarkup = $modules->get('InputfieldFieldset');
        $fsMarkup->icon = 'html5';
        $fsMarkup->label = $this->_('Markup Output Configuration');
        $fsMarkup->set('themeOffset', true);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'snipcart_css_path');
        $f->label = $this->_('Path to Snipcart CSS File');
        $f->notes = $this->_('Use your own CSS file to change the Cart theme. Check the [Snipcart Theme Repository](https://github.com/snipcart/snipcart-theme) on GitHub for more info.');
        $f->required = true;
        $f->columnWidth = 60;
        $fsMarkup->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'snipcart_css_integrity');
        $f->label = $this->_('Snipcart CSS File Integrity Hash');
        $f->notes = $this->_('If empty - browsers Subresource Integrity check will be disabled.');
        $f->columnWidth = 40;
        $fsMarkup->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'snipcart_js_path');
        $f->label = $this->_('Path to Snipcart JS File');
        $f->required = true;
        $f->columnWidth = 60;
        $fsMarkup->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'snipcart_js_integrity');
        $f->label = $this->_('Snipcart JS File Integrity Hash');
        $f->notes = $this->_('If empty - browsers Subresource Integrity check will be disabled.');
        $f->columnWidth = 40;
        $fsMarkup->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'include_jquery'); 
        $f->label = $this->_('Include jQuery in Your Output');
        $f->label2 = $this->_('Include jQuery');
        $f->description = $this->_('Whether SnipWire should add the jQuery library to your output or not. If jQuery is already included in your template, you should not include it twice, so you can uncheck this option.');
        $f->notes = $this->_('Snipcart uses [jQuery](https://jquery.com/), so we need to make sure it is included in your output!');
        $fsMarkup->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'jquery_js_path');
        $f->label = $this->_('Path to jQuery JS File');
        $f->required = true; // needs to be set when using requiredIf
        $f->columnWidth = 60;
        $f->requiredIf = 'include_jquery=1';
        $f->showIf = 'include_jquery=1';
        $fsMarkup->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'jquery_js_integrity');
        $f->label = $this->_('jQuery JS File Integrity Hash');
        $f->notes = $this->_('If empty - browsers Subresource Integrity check will be disabled.');
        $f->columnWidth = 40;
        $f->showIf = 'include_jquery=1';
        $fsMarkup->add($f);

        $f = $modules->get('InputfieldAsmSelect');
        $f->attr('name', 'excluded_templates');
        $f->label = 'Exclude Templates from Snipcart Integration';
        $f->description = $this->_('The chosen templates will be excluded from Snipcart scripts (JS) and styles (CSS) integration.');
        $f->notes = $this->_('Leave empty for no limitation. Please note: system templates (admin, user, language, ...) are always excluded!');
        foreach ($this->getTemplates() as $t) {
            $name = $t->name;
            $label = !empty($t->label) ? $t->label . ' [' . $name. ']' :  $name;
            $f->addOption($name, $label);
        }
        $fsMarkup->add($f);
        
        $inputfields->add($fsMarkup);

        // Cart image configuration
        
        $fsCartImage = $modules->get('InputfieldFieldset');
        $fsCartImage->icon = 'picture-o';
        $fsCartImage->label = $this->_('Cart thumbnail sizing');
        $fsCartImage->description = $this->_('Snipcart uses the first image from preinstalled "snipcart_item_image" PageField as cart thumbnail. The following settings will define how the cart thumbnail variant is sized/cropped to the specified dimensions. Please refer to the [ProcessWire Docs](https://processwire.com/api/ref/pageimage/size/) how the size/crop paramaters behave.');
        $fsCartImage->set('themeOffset', true);
        
        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'cart_image_width');
        $f->label = $this->_('Width in px');
        $f->required = true;
        $f->columnWidth = 33;
        $fsCartImage->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'cart_image_height');
        $f->label = $this->_('Height in px');
        $f->required = true;
        $f->columnWidth = 33;
        $fsCartImage->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'cart_image_quality');
        $f->label = $this->_('Quality in %');
        $f->required = true;
        $f->columnWidth = 33;
        $fsCartImage->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'cart_image_hidpi'); 
        $f->label = $this->_('Use HiDPI/retina pixel doubling?');
        $f->label2 = $this->_('Use HiDPI');
        $f->columnWidth = 33;
        $fsCartImage->add($f);

        $f = $modules->get('InputfieldInteger');
        $f->attr('name', 'cart_image_hidpiQuality');
        $f->label = $this->_('HiDPI Quality in %');
        $f->required = true;
        $f->columnWidth = 33;
        $fsCartImage->add($f);

        $f = $modules->get('InputfieldCheckbox');
        $f->attr('name', 'cart_image_cropping'); 
        $f->label = $this->_('Crop');
        $f->label2 = $this->_('Crop thumbnail');
        $f->columnWidth = 33;
        $fsCartImage->add($f);

        $inputfields->add($fsCartImage);
        
        // SnipWire API configuration
        
        $fsSnipWire = $modules->get('InputfieldFieldset');
        $fsSnipWire->icon = 'plug';
        $fsSnipWire->label = $this->_('SnipWire Configuration');
        $fsSnipWire->set('themeOffset', true);
        
        if ($productTemplate = $this->wire('templates')->get(MarkupSnipWire::snipcartProductTemplate)) {
            $productTemplateFields = $productTemplate->fields;
        } else {
            $productTemplateFields = new FieldsArray();
            $defaults = $this->getDefaults();
            $defaultFieldName = $defaults['data_item_name_field'];
            $defaultField = $this->wire('fields')->get($defaultFieldName);
            $productTemplateFields->add($defaultField);
        }
        
        $allowedFieldTypes = array(
            'FieldtypeText',
            'FieldtypeTextLanguage',
            'FieldtypePageTitle',
            'FieldtypePageTitleLanguage',
        );

        $f = $modules->get('InputfieldSelect'); 
        $f->attr('name', 'data_item_name_field'); 
        $f->label = $this->_('Set Field for Snipcart Product Name'); 
        $f->notes = $this->_('Used as Snipcart anchor property "data-item-name"');
        $f->required = true;

        foreach ($productTemplateFields as $field) {
            if (in_array($field->type, $allowedFieldTypes)) {
                $f->addOption($field->name, $field->name, array());
            }
        }
        $fsSnipWire->add($f);

        $inputfields->add($fsSnipWire);

        return $inputfields;
    }

    /**
     * Render an additional setup step.
     * 
     * @param array $step 
     * @return string
     * 
     */
    protected function renderStep(array $step) {
        $out = '';

        $target = isset($step['target']) ? ' target="' . $step['target'] . '"' : '';
        
        $out .= '<li>';
        
        // Render as kind of checklist
        if ($step['type'] == 'checklist') {
            if ($step['done']) {
                $out .= $step['prompt'] . ' ';
                $out .= '<span style="color: green;">';
                if (isset($step['icon'])) $out .= wireIconMarkup($step['icon']) . ' ';
                $out .= $this->_('Done');
                $out .= '</span>';
                if (isset($step['url2']) && isset($step['prompt2'])) {
                    $out .= ' -- <a' . $target . ' href="' . $step['url2'] . '">';
                    if (isset($step['icon2'])) $out .= wireIconMarkup($step['icon2']) . ' ';
                    $out .= $step['prompt2'];
                    $out .= '</a>';
                }
            } else {
                $out .= '<a' . $target .' href="' . $step['url'] . '">' . $step['prompt'] . '</a>';
            }
        // Render as normal link
        } else {
            $out .= '<a' . $target .' href="' . $step['url'] . '">' . $step['prompt'] . '</a>';
        }
        if (isset($step['description'])) $out .= '<br><span class="detail">' . $step['description'] . '</span>';
        
        $out .= '</li>';
    
        return $out;
    }

    /**
     * Get all templates except system templates (name => label)
     * 
     * @return WireArray $templates
     * 
     */
    public function getTemplates() {
        $templates = new WireArray();
        foreach ($this->wire('templates') as $t) {
            if (!($t->flags & Template::flagSystem)) {
                $templates->add($t);
            }
        }
    
        return $templates;
    }

}
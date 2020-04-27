<?php
namespace ProcessWire;

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

wire('classLoader')->addNamespace('SnipWire\Helpers', __DIR__ . '/helpers');

use SnipWire\Helpers\CurrencyFormat;
use SnipWire\Helpers\Taxes;

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

    /**var string $snipWireRootUrl The root URL to ProcessSnipWire page */
    protected $snipWireRootUrl = '';

    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();
        $this->snipWireRootUrl = rtrim($this->wire('pages')->get('template=admin, name=snipwire, status<' . Page::statusTrash)->url, '/') . '/';
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
     * Returns an array of taxes provider labels, indexed by provider
     * 
     * @return array
     * 
     */
    public static function getTaxesProviderLabels() {
        return array(
            'snipcart' => __('Snipcart'),
            'integrated' => __('Integrated (SnipWire)'),
        );
    }

    /**
     * Default config
     * (overriding the method from parent class)
     *
     * @return array of 'fieldName' => 'default value'
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
            'cart_custom_fields_enabled' => 1,
            'snipcart_debug' => 1,
            'taxes_provider' => 'integrated',
            'taxes' => Taxes::getDefaultTaxesConfig(true), // JSON
            'taxes_included' => 1,
            'shipping_taxes_type' => Taxes::shippingTaxesHighestRate,
            'include_snipcart_css' => 1,
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
            'webhooks_endpoint' =>  '/webhooks/snipcart',
            'product_templates' => array(),
            'data_item_name_field' => 'title',
            'data_item_categories_field' => '',
            'currency_param' => 'currency',
            'snipwire_debug' => false,
        );
    }

    /**
     * Return an InputfieldWrapper of Inputfields necessary to configure this module
     * 
     * Values will be populated to the Inputfields automatically. However, you may also retrieve
     * any of the values from $this->[property]; as needed. 
     * 
     * @return InputfieldWrapper
     * 
     */
    public function getInputfields() {
        $modules = $this->wire('modules');
        $config = $this->wire('config');
        $snipwire = $this->wire('snipwire');
        $sniprest = $this->wire('sniprest');
        $defaults = $this->getDefaults();

        $inputfields = parent::getInputfields();

        // If something went wrong during installation process (e.g. required modules are missing) return early!
        if (!$snipwire || !$sniprest) return $inputfields;

        $modules->get('JqueryUI')->use('vex');
        $this->_includeAssets();

        //
        // ---- Additional steps ----
        //

        $redirectUrl = urlencode($_SERVER['REQUEST_URI']);

        $steps = array();
        $steps[] = array(
            'type' => 'link',
            'name' => 'snipcart_account',
            'url' => 'https://app.snipcart.com',
            'target' => '_blank',
            'prompt' => $this->_('Create a Snipcart account'),
            'description' => $this->_('Create or login to a Snipcart account.'),
        );
        $steps[] = array(
            'type' => 'link',
            'name' => 'snipcart_api_keys',
            'url' => 'https://app.snipcart.com/dashboard/account/credentials',
            'target' => '_blank',
            'prompt' => $this->_('Get your Snipcart API keys'),
            'description' => $this->_('To get your public JavaScript - and secret REST API keys, head to the Account > API Keys section. There you’ll find your public API keys and also need to create your secret API keys for live and test environment.'),
        );
        $steps[] = array(
            'type' => 'check',
            'name' => 'product_package',
            'url' => $this->snipWireRootUrl . 'install-product-package/?ret=' . $redirectUrl,
            'prompt' => $this->_('Install Snipcart products package'),
            'description' => $this->_('Contains product templates, files, fields and some demo pages required to build a Snipcart product catalogue. This additional step is needed to prevent unintended deletion of your Snipcart products catalogue when main module is uninstalled. These resources need to be removed manually!'),
            /*
            'followup' => array(
                'url' => $this->snipWireRootUrl . 'uninstall-product-package/?ret=' . $redirectUrl,
                'prompt' => $this->_('Uninstall package'),
                'icon' => 'times-circle',
            ),
            */
        );
        $steps[] = array(
            'type' => 'link',
            'name' => 'snipcart_domains',
            'url' => 'https://app.snipcart.com/dashboard/account/domains',
            'target' => '_blank',
            'prompt' => $this->_('Snipcart domains setup'),
            'description' => $this->_('Tell Snipcart where it can crawl your products. Go to Store Configuration > Domains & URLs and set your default domain name as well as additional allowed domains and sub-domains.'),
        );
        
        $stepsCounter = 0;
        $doneCounter = 0;
        foreach ($steps as $step) {
            if ($step['type'] == 'check') $stepsCounter++;
        }
        
        if ($stepsCounter) {
            // Check which steps are already done and add flag
            $snipwireConfig = $modules->getConfig('SnipWire');
            for ($i = 0; $i < count($steps); $i++) {
                $steps[$i]['done'] = (isset($snipwireConfig[$steps[$i]['name']]) && $snipwireConfig[$steps[$i]['name']]) ? true : false;
                if ($steps[$i]['done']) $doneCounter++;
            }

            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->attr('name', '_next_steps');
            $f->icon = 'cog';
            $f->label = $this->_('Additional steps');
            $f->value = '<ul class="uk-list uk-list-divider">';
            foreach ($steps as $step) {
                $f->value .= $this->renderStep($step);
            }
            $f->value .= '</ul>';
            $f->collapsed = $stepsCounter == $doneCounter ? Inputfield::collapsedYes : Inputfield::collapsedNo;
            
            $inputfields->add($f);
        }

        //
        // ---- Snipcart API configuration ----
        //

        /** @var InputfieldFieldset $fsAPI */
        $fsAPI = $modules->get('InputfieldFieldset');
        $fsAPI->icon = 'plug';
        $fsAPI->label = $this->_('Snipcart API Configuration');
        $fsAPI->set('themeOffset', true);

            /** @var InputfieldRadios $f */
            $f = $modules->get('InputfieldRadios');
            $f->attr('name', 'snipcart_environment');
            $f->label = $this->_('Snipcart Environment');
            $f->description = $this->_('Snipcart offers two separate isolated environments to allow a secure staging without affecting the live environment.');
            $f->notes = $this->_('Changes the environment API key when including the Snipcart JS file in templates.');
            $f->optionColumns = 1;
            $f->addOption(0, 'TEST mode'); 
            $f->addOption(1, 'LIVE mode');
            $f->columnWidth = 50;

        $fsAPI->add($f);
            
            /** @var InputfieldButton $btn */
            $btn = $modules->get('InputfieldButton');
            $btn->attr('id', 'rest_test');
            $btn->attr('href', $this->snipWireRootUrl . 'test-snipcart-rest-connection/?ret=' . $redirectUrl);
            $btn->text = $this->_('Connection Test');
            $btn->icon = 'plug';
            $btn->setSecondary(true);
            $btn->set('small', true);

            $connectionTestMarkup = $btn->render();
            
            /** @var InputfieldMarkup $f */
            $f = $modules->get('InputfieldMarkup');
            $f->label = $this->_('Snipcart REST API Connection Test');
            $f->description = $this->_('SnipWire will send a test request to the Snipcart REST API. You can check if your secret API key for the selected environment is correct.');
            $f->notes = $this->_('You first need to enter valid Secret API keys in the corresponding fields.');
            $f->value = $connectionTestMarkup;
            $f->columnWidth = 50;

        $fsAPI->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'api_key');
            $f->label = $this->_('Snipcart Public API Key');
            $f->description = $this->_('The public API key is used to access the public Snipcart `JavaScript API`.');
            $f->notes = $this->_('This key can be shared without security issues.');
            $f->required = true;
            $f->columnWidth = 50;
            $f->requiredIf = 'snipcart_environment=1';
            $f->showIf = 'snipcart_environment=1';

        $fsAPI->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'api_key_secret');
            $f->label = $this->_('Snipcart Secret API Key');
            $f->description = $this->_('The secret API key is used to access your Snipcart account via `REST API`.');
            $f->notes = $this->_('This key should never be visible to anyone!');
            $f->required = true;
            $f->columnWidth = 50;
            $f->requiredIf = 'snipcart_environment=1';
            $f->showIf = 'snipcart_environment=1';

        $fsAPI->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'api_key_test');
            $f->label = $this->_('Snipcart Public API Key (Test)');
            $f->description = $this->_('The public API key is used to access the public Snipcart `JavaScript API`.');
            $f->notes = $this->_('This key can be shared without security issues.');
            $f->required = true;
            $f->columnWidth = 50;
            $f->requiredIf = 'snipcart_environment=0';
            $f->showIf = 'snipcart_environment=0';

        $fsAPI->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'api_key_secret_test');
            $f->label = $this->_('Snipcart Secret API Key (Test)');
            $f->description = $this->_('The secret API key is used to access your Snipcart account via `REST API`.');
            $f->notes = $this->_('This key should never be visible to anyone!');
            $f->required = true;
            $f->columnWidth = 50;
            $f->requiredIf = 'snipcart_environment=0';
            $f->showIf = 'snipcart_environment=0';

        $fsAPI->add($f);

            /** @var InputfieldAsmSelect $f */
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

            /** @var InputfieldAsmSelect $f */
            $f = $modules->get('InputfieldAsmSelect');
            $f->attr('name', 'currencies'); 
            $f->label = $this->_('Set Currencies'); 
            $f->description =        $this->_('Selected currency(s) will be used in your shop catalogue and in the Snipcart shopping-cart system during checkout.');
            $f->description .= ' ' . $this->_('As SnipWire fetches the available currency-list directly from Snipcart Dashboard, you will need to first setup the desired currency format(s) in your [Snipcart Dashboard > Regional Settings](https://app.snipcart.com/dashboard/settings/regional).');
            $f->description .= ' ' . $this->_('Selecting a currency will also create a corresponding currency specific price input field which needs to be added to your product templates manually.');
            $f->notes =        $this->_('Selecting more than one currency will enable Snipcart\'s multiple currencies payments feature.');
            $f->notes .= ' ' . $this->_('The first currency in the list will be the default one used in your product catalogue, Snipcart shopping-cart and SnipWire dashboard.');
    
            $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
            $currencies = array();
            if (!$currencies = $sniprest->getSettings('currencies', WireCache::expireNever, true)) {
                $currencies[] = CurrencyFormat::getDefaultCurrencyDefinition();
            }
            foreach ($currencies as $currency) {
                $currencyName = $currency['currency'];
                $currencyLabel = isset($supportedCurrencies[$currency['currency']])
                    ? strtoupper($currency['currency']) . ' : ' . $supportedCurrencies[$currency['currency']]
                    : strtoupper($currency['currency']);
                $f->addOption($currencyName, $currencyLabel);
            }
            $f->required = true;

        $fsAPI->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'show_cart_automatically'); 
            $f->label = $this->_('Show Shopping Cart Automatically');
            $f->label2 = $this->_('Show cart automatically');
            $f->description = $this->_('If you want to prevent the cart from showing up everytime a product is added, you can disable it.');
            $f->columnWidth = 50;

        $fsAPI->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'shipping_same_as_billing'); 
            $f->label = $this->_('Use Billing Address for Shipping');
            $f->label2 = $this->_('Use billing address for shipping preselected');
            $f->description = $this->_('Whether the `Use this address for shipping` option on the billing address tab is pre-selected or not.');
            $f->columnWidth = 50;

        $fsAPI->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'show_continue_shopping'); 
            $f->label = $this->_('Continue shopping Button');
            $f->label2 = $this->_('Show the `Continue shopping` button');
            $f->description =        $this->_('Use this setting if you want to show the `Continue shopping` button.');
            $f->description .= ' ' . $this->_('This button will appear just beside the `Close cart` button.');
            $f->columnWidth = 50;

        $fsAPI->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'split_firstname_and_lastname'); 
            $f->label = $this->_('Split First Name and Last Name');
            $f->label2 = $this->_('Split the First name and Last name');
            $f->description = $this->_('Use this setting to split the First name and Last name in billing address and shipping address forms.');
            $f->columnWidth = 50;

        $fsAPI->add($f);

            $customCartFieldsPage = $this->wire('pages')->findOne('name=custom-cart-fields, template=snipcart-cart, include=hidden');
            if ($customCartFieldsPage->editable()) {
                $customCartFieldsPageEditUrl = $customCartFieldsPage->editUrl;
            }

            if ($customCartFieldsPageEditUrl) {
                /** @var InputfieldButton $btn */
                $btn = $modules->get('InputfieldButton');
                $btn->attr('href', $customCartFieldsPageEditUrl);
                $btn->addClass('pw-modal');
                $btn->text = $this->_('Custom Cart Fields');
                $btn->icon = 'cog';
                $btn->setSecondary(true);
                $btn->set('small', true);

                /** @var InputfieldMarkup $f */
                $f = $modules->get('InputfieldMarkup');
                $f->label = $this->_('Custom Cart Fields Configuration');
                $f->description = $this->_('Those fields will be automatically added to your checkout process as new tab/step called `Order infos`.');
                $f->value = $btn->render();
                $f->columnWidth = 50;

                $fsAPI->add($f);

                /** @var InputfieldCheckbox $f */
                $f = $modules->get('InputfieldCheckbox');
                $f->attr('name', 'cart_custom_fields_enabled'); 
                $f->label = $this->_('Enable/Disable Custom Cart Fields');
                $f->label2 = $this->_('Custom cart fields enabled');
                $f->description = $this->_('Use this setting to select whether custom cart fields should be enabled for checkout process.');
                $f->columnWidth = 50;
    
                $fsAPI->add($f);
            }

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'snipcart_debug'); 
            $f->label = $this->_('Snipcart JavaScript Debug Mode');
            $f->label2 = $this->_('Enable Snipcart JavaScript debug mode');
            $f->description = $this->_('This will allow you to see JavaScript errors on your site, failing requests and logs from the services you use in your browsers developer console.');
            $f->notes = $this->_('All logs from the Snipcart script will be prefixed with `Snipcart:`');
            $f->columnWidth = 100;

        $fsAPI->add($f);

        $inputfields->add($fsAPI);

        //
        // ---- Taxes configuration ----
        //

        /** @var InputfieldFieldset $fsAPI */
        $fsTaxes = $modules->get('InputfieldFieldset');
        $fsTaxes->icon = 'percent';
        $fsTaxes->label = $this->_('Taxes Configuration');
        $fsTaxes->set('themeOffset', true);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect');
            $f->attr('name', 'taxes_provider'); 
            $f->label = $this->_('Taxes Provider');
            $f->description = $this->_('Select the taxes provider which should be used.');
            $f->required = true;
            $f->columnWidth = 50;
            $f->addOptions(self::getTaxesProviderLabels());

        $fsTaxes->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'taxes_included'); 
            $f->label = $this->_('Taxes Included in Prices');
            $f->label2 = $this->_('Taxes are included in product prices');
            $f->description = $this->_('Use this setting if the taxes are included in your product prices.');
            $f->columnWidth = 50;

        $fsTaxes->add($f);

            $languageStrings = array(
                'tax_name' => $this->_('Tax name'),
                'number_for_invoice' => $this->_('Number for Invoice'),
                'rate' => $this->_('Rate'),
                'applies_on_shipping' => $this->_('Shipping'),
                'tax_name_ph' => $this->_('e.g. 20% VAT'),
                'number_for_invoice_ph' => $this->_('Opt. tax  number'),
                'rate_ph' => $this->_('e.g. 0.20'),
                'sort_drag_drop' => $this->_('Sort by dragging and dropping'),
                'remove_tax_setting' => $this->_('Remove tax setting'),
                'add_tax_setting' => $this->_('Add tax setting'),
                'js' => array(
                    'confirm_delete' => $this->_('Are you sure you want to delete this element?'),
                ),
            );
            $config->js('languageStrings', $languageStrings['js']);

            $taxesRepeaterMarkup =
            '<table id="TaxesRepeater">' .
                '<thead>' .
                    '<tr>' .
                        '<th></th>' .
                        '<th>' . $languageStrings['tax_name'] . '</th>' .
                        '<th>' . $languageStrings['number_for_invoice'] . '</th>' .
                        '<th>' . $languageStrings['rate'] . '</td>' .
                        '<th>' .$languageStrings['applies_on_shipping'] .'</td>' .
                        '<th></th>' .
                    '</tr>' .
                '</thead>' .
                '<tfoot>' .
                    '<tr>' .
                        '<td colspan="6">' .
                            '<a class="RepeaterAddItem" data-repeater-create>' . $languageStrings['add_tax_setting'] . '</a>' .
                        '</td>' .
                    '</tr>' .
                '</tfoot>' .
                '<tbody data-repeater-list="taxesgroup">' .
                    '<tr data-repeater-item>' .
                        '<td class="col-action">' . // @todo make drag&drop accessible!
                             '<span role="button" aria-label="' . $languageStrings['sort_drag_drop'] . '" class="RepeaterSortableIndicator">' . wireIconMarkup('arrows') . '</span>' .
                        '</td>' .
                        '<td class="col-data">' .
                            '<input type="text" aria-label="' . $languageStrings['tax_name'] . '" class="uk-input InputfieldMaxWidth" name="name" placeholder="' . $languageStrings['tax_name_ph'] . '">' .
                        '</td>' .
                        '<td class="col-data">' .
                            '<input type="text" aria-label="' . $languageStrings['number_for_invoice'] . '" class="uk-input InputfieldMaxWidth" name="numberForInvoice" placeholder="' . $languageStrings['number_for_invoice_ph'] . '">' .
                        '</td>' .
                        '<td class="col-data">' .
                            '<input type="text" aria-label="' . $languageStrings['rate'] . '" class="uk-input InputfieldMaxWidth" name="rate" pattern="[-+]?[0-9]*[.]?[0-9]+" placeholder="' . $languageStrings['rate_ph'] . '">' .
                        '</td>' .
                        '<td class="col-data">' .
                            '<label class="inline-checkbox">' .
                                '<input type="checkbox" aria-label="' . $languageStrings['applies_on_shipping'] . '" class="uk-checkbox" name="appliesOnShipping" value="1">' .
                            '</label>' .
                        '</td>' .
                        '<td class="col-action">' .
                            '<button type="button" class="RepeaterRemoveItem" title="' . $languageStrings['remove_tax_setting'] . '" data-repeater-delete>' . wireIconMarkup('trash-o') . '</button>' .
                        '</td>' .
                    '</tr>' .
                '</tbody>' .
            '</table>';
            
            $notes =
            '<p class="notes">' .
                $this->_('The first (none shipping) set in the list will be used as default tax when creating new products.') . 
                ' ' . $this->_('The first set in the list with a "Shipping" marker will be used as default shipping tax when "Shipping Taxes Handling" is set to "Apply a fixed tax rate".') .
            '</p>';

            // This text field (HTML input hidden by CSS) will be filled with jSON formatted taxes array provided by jquery.repeater.
            // onLoad jquery.repeater will use the stored array to pre-build the repeater fields.

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('id+name', 'taxes');
            $f->attr('class', 'hiddenTaxesInput');
            $f->label = $this->_('Taxes Configuration');
            $f->description =        $this->_('If activated, SnipWire acts as a taxes provider for Snipcart.');
            $f->description .= ' ' . $this->_('You first need to configure the taxes provider webhook in [Snipcart Dashboard > Taxes](https://app.snipcart.com/dashboard/taxes).');
            $f->description .= ' ' . $this->_('After that, define the tax rates here to be used by the Snipcart shop-system.');
            $f->maxlength = 1024 * 32;
            $f->showIf = 'taxes_provider=integrated';
            $f->requiredIf = 'taxes_provider=integrated';
            $f->appendMarkup = $taxesRepeaterMarkup . $notes;

        $fsTaxes->add($f);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect');
            $f->attr('name', 'shipping_taxes_type');
            $f->addClass('InputfieldMaxWidth');
            $f->label = $this->_('Shipping Taxes Handling');
            $f->description = $this->_('Select how shipping taxes should be calculated and applied.');
            $f->required = true;
            $f->columnWidth = 100;
            $f->showIf = 'taxes_provider=integrated';
            $f->requiredIf = 'taxes_provider=integrated';
            $f->addOptions(array(
                Taxes::shippingTaxesNone => $this->_('No shipping taxes'),
                Taxes::shippingTaxesFixedRate => $this->_('Apply a fixed tax rate'),
                Taxes::shippingTaxesHighestRate => $this->_('Apply predominant tax rate'),
                Taxes::shippingTaxesSplittedRate => $this->_('Proportionally split and apply tax rates'),
            ));   

        $fsTaxes->add($f);

        $inputfields->add($fsTaxes);

        //
        // ---- Markup configuration ----
        //

        /** @var InputfieldFieldset $fsMarkup */
        $fsMarkup = $modules->get('InputfieldFieldset');
        $fsMarkup->icon = 'html5';
        $fsMarkup->label = $this->_('Markup Output Configuration');
        $fsMarkup->set('themeOffset', true);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'include_snipcart_css'); 
            $f->label = $this->_('Include Snipcart CSS');
            $f->label2 = $this->_('Include Snipcart CSS');
            $f->description =        $this->_('Whether SnipWire should add the defined Snipcart stylesheet from the field below to your output or not.');
            $f->description .= ' ' . $this->_('To change the Cart theme, you can also include your own stylesheet using one of your preferred methods.');
            $f->description .= ' ' . $this->_('Check the [Snipcart Theme Repository](https://github.com/snipcart/snipcart-theme) on GitHub for more info.');

        $fsMarkup->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'snipcart_css_path');
            $f->label = $this->_('Path to Snipcart CSS File');
            $f->required = true;
            $f->columnWidth = 60;
            $f->requiredIf = 'include_snipcart_css=1';
            $f->showIf = 'include_snipcart_css=1';

        $fsMarkup->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'snipcart_css_integrity');
            $f->label = $this->_('Snipcart CSS File Integrity Hash');
            $f->columnWidth = 40;
            $f->showIf = 'include_snipcart_css=1';

        $fsMarkup->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'snipcart_js_path');
            $f->label = $this->_('Path to Snipcart JS File');
            $f->required = true;
            $f->columnWidth = 60;

        $fsMarkup->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'snipcart_js_integrity');
            $f->label = $this->_('Snipcart JS File Integrity Hash');
            $f->columnWidth = 40;

        $fsMarkup->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'include_jquery'); 
            $f->label = $this->_('Include jQuery');
            $f->label2 = $this->_('Include jQuery');
            $f->description =        $this->_('Whether SnipWire should add the jQuery library to your output or not.');
            $f->description .= ' ' . $this->_('If jQuery is already included in your template, you should not include it twice, so you can uncheck this option.');
            $f->notes = $this->_('Snipcart uses [jQuery](https://jquery.com/), so you need to make sure it is included in your output!');

        $fsMarkup->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'jquery_js_path');
            $f->label = $this->_('Path to jQuery JS File');
            $f->required = true;
            $f->columnWidth = 60;
            $f->requiredIf = 'include_jquery=1';
            $f->showIf = 'include_jquery=1';

        $fsMarkup->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'jquery_js_integrity');
            $f->label = $this->_('jQuery JS File Integrity Hash');
            $f->columnWidth = 40;
            $f->showIf = 'include_jquery=1';

        $fsMarkup->add($f);

            /** @var InputfieldAsmSelect $f */
            $f = $modules->get('InputfieldAsmSelect');
            $f->attr('name', 'excluded_templates');
            $f->label = 'Exclude Templates from Snipcart Integration';
            $f->description = $this->_('The selected templates will be excluded from Snipcart scripts (JS) and styles (CSS) integration.');
            $f->notes = $this->_('Leave empty for no limitation. Please note: system templates (admin, user, language, ...) are always excluded!');
            foreach ($this->_getTemplates() as $t) {
                $name = $t->name;
                $label = !empty($t->label) ? $t->label . ' [' . $name. ']' :  $name;
                $f->addOption($name, $label);
            }

        $fsMarkup->add($f);
        
        $inputfields->add($fsMarkup);

        //
        // ---- Cart image configuration ----
        //
        
        /** @var InputfieldFieldset $fsCartImage */
        $fsCartImage = $modules->get('InputfieldFieldset');
        $fsCartImage->icon = 'picture-o';
        $fsCartImage->label = $this->_('Cart Thumbnail Sizing');
        $fsCartImage->description =        $this->_('Snipcart uses the first image from preinstalled `snipcart_item_image` PageField as cart thumbnail.');
        $fsCartImage->description .= ' ' . $this->_('The following settings will define how the cart thumbnail variant is sized/cropped to the specified dimensions.');
        $fsCartImage->description .= ' ' . $this->_('Please refer to the [ProcessWire Docs](https://processwire.com/api/ref/pageimage/size/) how the size/crop paramaters behave.');
        $fsCartImage->set('themeOffset', true);
        
            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'cart_image_width');
            $f->label = $this->_('Width in px');
            $f->required = true;
            $f->columnWidth = 33;

        $fsCartImage->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'cart_image_height');
            $f->label = $this->_('Height in px');
            $f->required = true;
            $f->columnWidth = 33;

        $fsCartImage->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'cart_image_quality');
            $f->label = $this->_('Quality in %');
            $f->required = true;
            $f->columnWidth = 34;

        $fsCartImage->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'cart_image_hidpi'); 
            $f->label = $this->_('Use HiDPI/retina pixel doubling?');
            $f->label2 = $this->_('Use HiDPI');
            $f->columnWidth = 33;

        $fsCartImage->add($f);

            /** @var InputfieldInteger $f */
            $f = $modules->get('InputfieldInteger');
            $f->attr('name', 'cart_image_hidpiQuality');
            $f->label = $this->_('HiDPI Quality in %');
            $f->required = true;
            $f->columnWidth = 33;

        $fsCartImage->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'cart_image_cropping'); 
            $f->label = $this->_('Crop');
            $f->label2 = $this->_('Crop thumbnail');
            $f->columnWidth = 34;

        $fsCartImage->add($f);

        $inputfields->add($fsCartImage);

        //
        // ---- SnipWire API configuration ----
        //

        /** @var InputfieldFieldset $fsSnipWire */
        $fsSnipWire = $modules->get('InputfieldFieldset');
        $fsSnipWire->icon = 'plug';
        $fsSnipWire->label = $this->_('SnipWire Configuration');
        $fsSnipWire->set('themeOffset', true);

            $httpRootUrl = rtrim($config->urls->httpRoot, '/');
            $webhooksEndpoint = isset($snipwireConfig['webhooks_endpoint'])
                ? $snipwireConfig['webhooks_endpoint'] // from db
                : $this->webhooks_endpoint; // from getDefaults
            $webhooksEndpointFullUrl = $httpRootUrl . $webhooksEndpoint;
            
            $webhooksEndpointUrlMarkup = 
            '<div class="input-group">' .
                '<input type="text" class="uk-input" id="webhooks_endpoint_url" aria-label="' . $this->_('Absolute URL for SnipWire webhooks endpoint') .'" value="' . $webhooksEndpointFullUrl . '" disabled="disabled">' .
                '<div class="input-group-append">' .
                    '<button id="webhooks_endpoint_url_copy" class="ui-button ui-widget ui-corner-all ui-state-default" type="button">' .
                        '<span class="ui-button-text" title="' . $this->_('Copy absolute URL to clipboard') . '">' . wireIconMarkup('copy') . '</span>' .
                    '</button>' .
                '</div>' .
            '</div>';        
    
            // Set httpRoot as JavaScript property
            $config->js('SnipWire', array(
                'httpRoot' => $httpRootUrl,
            ));
    
            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('id+name', 'webhooks_endpoint');
            $f->label = $this->_('SnipWire Webhooks Endpoint');
            $f->maxlength = 128;
            $f->required = true;
            $f->description =        $this->_('To allow Snipcart to send webhooks POST requests to SnipWire, you must define the endpoint where your webhooks will be reachable.');
            $f->description .= ' ' . $this->_('After that, enter the absolute URL from the second field below in your Snipcart Dashboard under [Account > Webhooks section](https://app.snipcart.com/dashboard/webhooks).');
            $f->notes =        $this->_('The endpoint you provide must be relative to your site root with leading slash, e.g. /webhooks/snipcart.');
            $f->notes .= ' ' . $this->_('Please note that the webhooks path is only a virtual path and shouldn\'t point to an existing page!');
            $f->appendMarkup = $webhooksEndpointUrlMarkup;
            $f->pattern = '^\/(?!.*\/\/)([a-zA-Z-\/]+)$';

        $fsSnipWire->add($f);

            /** @var InputfieldAsmSelect $f */
            $f = $modules->get('InputfieldAsmSelect');
            $f->attr('name', 'product_templates');
            $f->label = 'SnipWire Product Templates';
            $f->description = $this->_('The selected templates will be enabled as SnipWire product templates. This means the required Snipcart scripts (JS) and styles (CSS) will be added automatically.');
            foreach ($this->_getTemplates() as $t) {
                $name = $t->name;
                $label = !empty($t->label) ? $t->label . ' [' . $name. ']' :  $name;
                $f->addOption($name, $label);
            }

        $fsSnipWire->add($f);
    
            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect'); 
            $f->attr('name', 'data_item_name_field'); 
            $f->label = $this->_('Select Field for Snipcart Product Name'); 
            $f->notes = $this->_('Allowed field types: `FieldtypeText(Language)`, `FieldtypePageTitle(Language)`');
            $f->required = true;
            $f->columnWidth = 50;
    
            $allowedFieldTypes = array(
                'FieldtypeText',
                'FieldtypeTextLanguage',
                'FieldtypePageTitle',
                'FieldtypePageTitleLanguage',
            );
            $excludeFieldNames = array(
                'snipcart_item_id',
                'snipcart_item_price_',
            );
            $productTemplateFields = $this->_getFields($allowedFieldTypes, $excludeFieldNames);
            foreach ($productTemplateFields as $ptField) {
                $f->addOption($ptField->name, $ptField->name . ' (' . $ptField->type . ')');
            }

        $fsSnipWire->add($f);

            /** @var InputfieldSelect $f */
            $f = $modules->get('InputfieldSelect'); 
            $f->attr('name', 'data_item_categories_field'); 
            $f->label = $this->_('Select Field for Snipcart Categories'); 
            $f->notes = $this->_('Allowed field types: `FieldtypePage`');
            $f->columnWidth = 50;

            $allowedFieldTypes = array(
                'FieldtypePage',
            );
            $productTemplateFields = $this->_getFields($allowedFieldTypes);
            $f->addOption('', $this->_('-- Categories disabled --'));
            foreach ($productTemplateFields as $ptField) {
                $f->addOption($ptField->name, $ptField->name . ' (' . $ptField->type . ')');
            }

        $fsSnipWire->add($f);

            /** @var InputfieldText $f */
            $f = $modules->get('InputfieldText');
            $f->attr('name', 'currency_param');
            $f->label = $this->_('Currency Parameter Name');
            $f->description = $this->_('Set the name of the GET, POST and SESSION parameter to be used for setting cart and catalogue currency.');
            $f->notes = $this->_('Can be used to switch the currency (used in templates and markup output) via form submit or session variable.');

        $fsSnipWire->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'single_page_shop'); 
            $f->label = $this->_('Single-Page Shop');
            $f->label2 = $this->_('This Snipcart shop runs on a single-page website');
            $f->description = $this->_('For single-page shops, the `data-item-url` field of each product will be filled with the full URL to the selected page.');
            $f->notes = $this->_('This tells the Snipcart crawler where to find your products to validate an order\'s integrity.');
            $f->columnWidth = 100;

        $fsSnipWire->add($f);

            /** @var InputfieldPageListSelect $f */
            $f = $modules->get('InputfieldPageListSelect');
            $f->attr('name', 'single_page_shop_page');
            $f->label = $this->_('Select Your Single-Page Shop Page');
            $f->required = true; // needs to be set when using requiredIf
            $f->requiredIf = 'single_page_shop=1';
            $f->showIf = 'single_page_shop=1';

        $fsSnipWire->add($f);

            /** @var InputfieldCheckbox $f */
            $f = $modules->get('InputfieldCheckbox');
            $f->attr('name', 'snipwire_debug'); 
            $f->label = $this->_('SnipWire Debug Mode');
            $f->label2 = $this->_('Enable SnipWire debug mode');
            $f->description = $this->_('This will enable SnipWires extended messages, warnings and errors logging into the ProcessWire log system.');
            $f->columnWidth = 100;

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
        
        // Render as checklist
        if ($step['type'] == 'check') {

            if ($step['done']) {
                $out .= $step['prompt'] . ' ';
                $out .= '<span style="color: green;">';
                $out .= wireIconMarkup('check-circle') . ' ';
                $out .= $this->_('Done');
                $out .= '</span>';
        
                // Is there a followup?
                if (isset($step['followup'])) {
                    $fup = $step['followup'];
                    $out .= ' -- <a' . $target . ' href="' . $fup['url'] . '">';
                    if (isset($fup['icon'])) $out .= wireIconMarkup($fup['icon']) . ' ';
                    $out .= $fup['prompt'];
                    $out .= '</a>';
                }
            } else {
                $out .= '<a' . $target .' href="' . $step['url'] . '">' . $step['prompt'] . '</a>';
            }

        // Render as link
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
    private function _getTemplates() {
        $templates = new WireArray();
        foreach ($this->wire('templates') as $t) {
            // System templates + cart template excluded
            if (!($t->flags & Template::flagSystem) && $t->name != 'snipcart-cart') {
                $templates->add($t);
            }
        }
        return $templates;
    }

    /**
     * Get a selection of fields suitable for SnipWire product templates.
     * 
     * @param array $allowedFieldTypes An array of allowed field types to be returned [optional]
     * @param array $excludeFieldNames An array of field names to be excluded from result [optional]
     * @return WireArray $fields
     * 
     */
    private function _getFields($allowedFieldTypes = array(), $excludeFieldNames = array()) {        
        $fields = new WireArray();
        foreach ($this->wire('fields') as $f) {
            // Title field is mandatory!
            if ($f->name == 'title') $fields->add($f);
            // System fields excluded
            if (!($f->flags & Field::flagSystem)) {
                $fields->add($f);
            }
        }
        if (!empty($allowedFieldTypes)) $fields = $fields->find('type=' . implode('|', $allowedFieldTypes));
        if (!empty($excludeFieldNames)) $fields = $fields->find('!name%=' . implode('|', $excludeFieldNames));
        return $fields;
    }

    /**
     * Include asset files for SnipWire config editor.
     *
     */
    private function _includeAssets() {
        $config = $this->wire('config');

        $info = SnipWire::getModuleInfo();
        $version = (int) isset($info['version']) ? $info['version'] : 0;
        $versionAdd = "?v=$version";

        $config->styles->add($config->urls->SnipWire . 'assets/styles/SnipWireConfig.css' . $versionAdd);
        $config->scripts->add($config->urls->SnipWire . 'vendor/jquery.repeater.js/jquery.repeater.min.js?v=1.2.1');
        $config->scripts->add($config->urls->SnipWire . 'assets/scripts/SnipWireConfig.min.js' . $versionAdd);
    }

}

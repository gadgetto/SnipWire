<?php namespace ProcessWire;

/**
 * ProcessSnipWireConfig - Config file for ProcessSnipWire.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2018 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class ProcessSnipWireConfig extends ModuleConfig {

    /** @var array Available creditcard types */
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

     public function __construct() {
        parent::__construct();

        // Array of credit card labels of cardname => label
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

    public function getDefaults() {
        return array(
            'api_key' => 'YOUR_LIVE_API_KEY',
            'api_key_test' => 'YOUR_TEST_API_KEY',
            'snipcart_environment' => 0,
            'single_page_website' => 0,
            'credit_cards' => array('visa', 'mastercard', 'maestro'),
            'show_cart_automatically' => 0,
            'shipping_same_as_billing' => 1,
            'show_continue_shopping' => 1,
            'split_firstname_and_lastname' => 1,
            'include_jquery' => 1,
            'excluded_templates' => array(),
        );
    }

    public function getInputfields() {
        $modules = $this->wire('modules');
        
        $inputfields = parent::getInputfields();

        // Additional setup steps

        $steps = array();
        $steps[] = array(
            'name' => 'product_package',
            'url' => '../setup/snipwire/install-product-package/',
            'uninstall_url' => '../setup/snipwire/uninstall-product-package/',
            'prompt' => $this->_('Install Snipcart products package'),
            'description' => $this->_('This contains product templates, files, fields and some demo pages required by Snipcart. This additional step is needed to prevent unintended deletion of your Snipcart products catalogue when main module is uninstalled.'),
            'target' => '_blank',
        );
        
        $stepsCounter = count($steps);
        
        if ($stepsCounter) {
            // Check which steps are already done and add flag
            $data = $modules->getConfig('ProcessSnipWire');
            for ($i = 0; $i < count($steps); $i++) {
                $steps[$i]['done'] = (isset($data[$steps[$i]['name']]) && $data[$steps[$i]['name']]) ? true : false;;
            }

            // Render steps
            $f = $modules->get('InputfieldMarkup');
            $f->attr('name', '_next_steps');
            $f->icon = 'cog';
            $f->label = $this->_('Additional installation steps');
            $f->description = $this->_('To finish setup, the following steps are needed:');
            $f->value = '<ul class="uk-list uk-list-divider">';
            foreach ($steps as $step) {
                $target = isset($step['target']) ? ' target="' . $step['target'] . '"' : '';
                $f->value .= '<li>';
                if (!$step['done']) {
                    $f->value .= '<a' . $target .' href="' . $step['url'] . '">' . $step['prompt'] . '</a>';
                } else {
                    $f->value .= $step['prompt'] . ' <span style="color: green;">' . wireIconMarkup('check-circle') . ' ' . $this->_('Done') . '</span>';
                    $f->value .= ' -- <a' . $target .' href="' . $step['uninstall_url'] . '">' . wireIconMarkup('times-circle') . ' ' . $this->_('Uninstall package') . '</a>';
                }
                $f->value .= '<br><small>' . $step['description'] . '</small>';
                $f->value .= '</li>';
            }
            $f->value .= '</ul>';
            $f->notes = $this->_('Some links above will open in a new window/tab. Close each after finishing to return here.');
            
            $inputfields->add($f);
        }

        // Settings for Snipcart API

        $fsAPI = $this->wire('modules')->get('InputfieldFieldset');
        $fsAPI->label = $this->_('Snipcart API Settings');

        $f = $modules->get('InputfieldMarkup');
        $f->description = $this->_('To get your API keys, you will need a Snipcart account. To register, go to [https://app.snipcart.com/account/register](https://app.snipcart.com/account/register). Once you’ve signed up and confirmed your account, log in and head to the Account > API Keys section, where you’ll find your API keys.');
        $fsAPI->add($f);
        
        $f = $modules->get('InputfieldText');
        $f->attr('name', 'api_key');
        $f->label = $this->_('Snipcart Public Live API Key');
        $f->required = true;
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldText');
        $f->attr('name', 'api_key_test');
        $f->label = $this->_('Snipcart Public Test API Key');
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

        $f = $this->modules->get('InputfieldCheckbox');
        $f->attr('name', 'single_page_website'); 
        $f->label = $this->_('Single-Page Website');
        $f->label2 = $this->_('This Snipcart shop runs on a single-page website');
        $f->description = $this->_('For single-page websites, the data-item-url field will be filled with only the basic domain name, such as www.example.com');
        $f->notes = $this->_('This tells the Snipcart crawler where to find your products to validate an order\'s integrity.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $this->wire('modules')->get('InputfieldAsmSelect');
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

        $f = $this->modules->get('InputfieldCheckbox');
        $f->attr('name', 'show_cart_automatically'); 
        $f->label = $this->_('Show Shopping Cart Automatically');
        $f->label2 = $this->_('Show cart automatically');
        $f->description = $this->_('If you want to prevent the cart from showing up everytime a product is added, you can disable it.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $this->modules->get('InputfieldCheckbox');
        $f->attr('name', 'shipping_same_as_billing'); 
        $f->label = $this->_('Use Billing Address for Shipping');
        $f->label2 = $this->_('Use billing address for shipping preselected');
        $f->description = $this->_('Whether the "Use this address for shipping" option on the billing address tab is pre-selected or not.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $this->modules->get('InputfieldCheckbox');
        $f->attr('name', 'show_continue_shopping'); 
        $f->label = $this->_('"Continue shopping" Button');
        $f->label2 = $this->_('Show the "Continue shopping" button');
        $f->description = $this->_('Use this setting if you want to show the "Continue shopping" button. This button will appear just beside the "Close cart" button.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $this->modules->get('InputfieldCheckbox');
        $f->attr('name', 'split_firstname_and_lastname'); 
        $f->label = $this->_('Split First Name and Last Name');
        $f->label2 = $this->_('Split the First name and Last name');
        $f->description = $this->_('Use this setting to split the First name and Last name in billing address and shipping address forms.');
        $f->columnWidth = 50;
        $fsAPI->add($f);

        $f = $modules->get('InputfieldMarkup');
        $f->label = $this->_('Other Configuration Parameters');
        $f->value = 
        '<p>' .
            $this->_('Other SnipCart settings needs to be configured through the Snipcart backend:') . ' ' .
            '<a href="https://app.snipcart.com/dashboard" target="_blank">https://app.snipcart.com/dashboard</a>' .
        '</p>';
        $f->notes = $this->_('e.g. allowed shipping methods, excluded shipping methods, allowed countries, allowed provinces, provinces for country, ...');
        $fsAPI->add($f);

        $inputfields->add($fsAPI);

        // Markup configuration

        $fsOther = $this->wire('modules')->get('InputfieldFieldset');
        $fsOther->label = $this->_('Markup Output Configuration');

        $f = $this->modules->get('InputfieldCheckbox');
        $f->attr('name', 'include_jquery'); 
        $f->label = $this->_('Include jQuery in Your Output');
        $f->label2 = $this->_('Include jQuery');
        $f->description = $this->_('Whether SnipWire should add the jQuery library to your output or not. If jQuery is already included in your template, you should not include it twice, so you can uncheck this option.');
        $f->notes = $this->_('Snipcart uses [jQuery](https://jquery.com/), so we need to make sure it is included in your output!');
        $fsOther->add($f);

        $f = $this->wire('modules')->get('InputfieldAsmSelect');
        $f->attr('name', 'excluded_templates');
        $f->label = 'Exclude Templates from Snipcart Integration';
        $f->description = $this->_('The chosen templates will be excluded from Snipcart scripts (JS) and styles (CSS) integration.');
        $f->notes = $this->_('Leave empty for no limitation. Please note: system templates (admin, user, language, ...) are always excluded!');
        foreach ($this->getTemplates() as $t) {
            $name = $t->name;
            $label = !empty($t->label) ? $t->label . ' [' . $name. ']' :  $name;
            $f->addOption($name, $label);
        }
        $fsOther->add($f);

        $inputfields->add($fsOther);

        return $inputfields;
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
<?php namespace ProcessWire;

/**
 * SnipWire - Full Snipcart shopping cart integration for ProcessWire CMF.
 * (This module is the master for all other SnipWire modules and files)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class SnipWire extends WireData implements Module, ConfigurableModule {

    /**
     * Returns information for SnipWire module.
     *
     */
    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire'),
            'summary' => __('Full Snipcart shopping cart integration for ProcessWire.'),
            'version' => 1, 
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'singular' => true, 
            'autoload' => true, 
            'installs' => array(
                'ProcessSnipWire',
                'MarkupSnipWire',
            ),
            'requires' => array(
                'ProcessWire>=3.0.0',
            ),
        );
    }

    const snipWireLogName = 'snipwire';

    /**
     * Returns a template array for a currency specific price input field.
     *
     * Currency specific price input fields will be created on demand by selecting 
     * currencies in SnipWireModuleConfig.
     * 
     * @return array
     * 
     */
    public static function getCurrencyFieldTemplate() {
        return array(
            'name' => 'snipcart_item_price_',
            'type' => 'FieldtypeText',
            'label' => __('Product Price'),
            'notes' => __('Decimal with a dot (.) as separator e.g. 19.99'),
            'maxlength' => 20,
            'required' => true,
            'pattern' => '[-+]?[0-9]*[.]?[0-9]+',
            'tags' => 'Snipcart',
            '_addToTemplates' => 'snipcart-product',  // comma separated list of template names
        );
    }

    /**
     * Initalize module config variables (properties).
     * (Called before module config is populated)
     *
     */
    public function __construct() {
        parent::__construct();
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'services/SnipREST.php';
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'services/Webhooks.php';
    }

    /**
     * Initialize the module and set required hooks.
     * (Called after module config is populated)
     * 
     */
    public function init() {
        /** @var SnipREST $sniprest Custom ProcessWire API variable */
        $this->wire('sniprest', new SnipREST());
        $this->addHookAfter('Modules::saveConfig', $this, 'manageCurrencyPriceFields');
        $this->addHookBefore('Inputfield(name=snipcart_item_id)::render', $this, 'presetSKU');
        $this->addHookAfter('Pages::added', $this, 'presetTaxable');
        $this->addHookBefore('ProcessPageView::execute', $this, 'checkWebhookRequest');
    }

    /**
     * Manage currency specific price input fields based on module "currencies" property.
     * (Method triggered after module config save)
     *
     * - Fields will be created on demand and added to the products template automatically.
     * - If Field to create already exists, it will be re-added to the products template.
     *
     */
    public function manageCurrencyPriceFields(HookEvent $event) {
        $currencies = isset($event->arguments[1]['currencies']) ? $event->arguments[1]['currencies'] : false;
        if (empty($currencies) || !is_array($currencies)) return;

        $fields = $this->wire('fields');
        $fieldgroups = $this->wire('fieldgroups');
        $templates = $this->wire('templates');
        $modules = $this->wire('modules');
        
        $fieldTemplate = self::getCurrencyFieldTemplate();
        $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
        $fieldsToTemplate = array();
    
        foreach ($currencies as $currency) {
            $fieldName = $fieldTemplate['name'] . $currency;
            $fieldsToTemplate[] = $fieldName;
            if ($fields->get($fieldName)) continue; // No need to create - already exists!
            $fieldLabelCurrencyAdd = isset($supportedCurrencies[$currency])
                ? $supportedCurrencies[$currency]
                : $currency;

            $f = new Field();
            $f->type = $modules->get($fieldTemplate['type']);
            $f->name = $fieldName;
            $f->label = $fieldTemplate['label'] . ' (' . $fieldLabelCurrencyAdd . ')';
            $f->notes = $fieldTemplate['notes'];
            $f->maxlength = $fieldTemplate['maxlength'];
            $f->required = $fieldTemplate['required'];
            $f->pattern = $fieldTemplate['pattern'];
            $f->tags = $fieldTemplate['tags'];
            $f->save();
            $this->message($this->_('Created currency specific field: ') . $fieldName);
            $fieldsToTemplate[] = $fieldName;
        }

        // Add fields to template */
        if (!empty($fieldsToTemplate)) {
            foreach ($fieldsToTemplate as $name) {
                foreach (explode(',', $fieldTemplate['_addToTemplates']) as $tn) {
                    if ($t = $templates->get($tn)) {
                        $fg = $t->fieldgroup;
                        if ($fg->hasField($name)) continue; // No need to add - already added!
                        $f = $fields->get($name);
                        $fg->add($f);
                        $fg->save();
                    } else {
                        $out = sprintf($this->_("Could not add field [%s] to template [%s]. The template does not exist. Please install Snipcart products package first!"), $name, $tn);
                        $this->warning($out);
                    }
                }
            }
        }
    }

    /**
     * Check for webohook request and process them.
     * (Method triggered before ProcessPageView execute)
     *
     */
    public function checkWebhookRequest(HookEvent $event) {
        if ($webhooksEndpoint = $this->get('webhooks_endpoint')) {
            if ($this->sanitizer->url($this->input->url) == $webhooksEndpoint) {
                /** @var Webhooks $webhooks Custom ProcessWire API variable */
                $this->wire('webhooks', new Webhooks());
                $this->wire('webhooks')->process();
                $event->replace = true;
                // @note: Tracy Debug won't work from here on as normal page rendering is omitted!
            }
        }
    }

    /**
     * Preset value of field snipcart_item_id (SKU) with page ID.
     * (Method triggered before Inputfield render)
     *
     */
    public function presetSKU(HookEvent $event) {
        if ($event->object->name == 'snipcart_item_id' && $event->object->value == '') {
            $event->object->set('value', $this->input->get->id);
        }
    }
    
    /**
     * Preset value of checkbox field snipcart_item_taxable so it's checked by default.
     * (Method triggered after Pagers added)
     *
     */
    public function presetTaxable(HookEvent $event) {
        $page = $event->arguments(0);
        if ($page->template == MarkupSnipWire::snipcartProductTemplate) {
            $page->setAndSave('snipcart_item_taxable', 1);
        }
    }
    
    /**
     * Called on module uninstall
     *
     */
    public function ___uninstall() {
        // Remove all caches created by SnipWire (SnipWire namespace)
        $this->wire('cache')->deleteFor('SnipWire');
        // Remove all logs created by SnipWire
        $this->wire('log')->delete(self::snipWireLogName);
        $this->wire('log')->delete(Webhooks::snipWireWebhooksLogName);
    }

}

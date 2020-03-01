<?php
namespace ProcessWire;

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

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Functions.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'CurrencyFormat.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Taxes.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'SnipREST.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'ExchangeREST.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'Webhooks.php';

use SnipWire\Helpers\CurrencyFormat;
use SnipWire\Helpers\Taxes;
use SnipWire\Services\ExchangeREST;
use SnipWire\Services\SnipREST;
use SnipWire\Services\Webhooks;
use SnipWire\Services\WireHttpExtended;

class SnipWire extends WireData implements Module, ConfigurableModule {

    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire'), // Module Title
            'summary' => __('Full Snipcart shopping cart integration for ProcessWire.'), // Module Summary
            'version' => '0.8.3', 
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'singular' => true, 
            'autoload' => true, 
            'installs' => array(
                'ProcessSnipWire',
                'MarkupSnipWire',
                'FieldtypeSnipWireTaxSelector',
            ),
            'requires' => array(
                'ProcessWire>=3.0.148',
                'PHP>=7.0.0',
            ),
        );
    }

    const snipWireLogName = 'snipwire';

    /** @var array $snipwireConfig The module config of SnipWire module */
    protected $snipwireConfig = array();

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
        );
    }

    /**
     * Initalize module config variables (properties).
     * (Called before module config is populated)
     *
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Initialize the module and set required hooks.
     * (Called after module config is populated)
     * 
     */
    public function init() {
        /** @var SnipREST $sniprest Custom ProcessWire API variable */
        $this->wire('sniprest', new SnipREST());
        /** @var ExchangeREST $exchangerest Custom ProcessWire API variable */
        $this->wire('exchangerest', new ExchangeREST());

        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $this->snipwireConfig = $this->wire('modules')->get('SnipWire');

        if ($this->snipwireConfig->taxes_provider == 'integrated') {
            $this->addHookBefore('Modules::saveConfig', $this, 'validateTaxesRepeater');
            $this->addHookAfter('Pages::added', $this, 'presetProductTaxesField');
        }
        $this->addHookAfter('Modules::saveConfig', $this, 'manageCurrencyPriceFields');
        $this->addHookAfter('Pages::added', $this, 'presetProductFields');
        $this->addHookAfter('Pages::saveReady', $this, 'checkSKUUnique');
        $this->addHookBefore('ProcessPageView::execute', $this, 'checkWebhookRequest');

        $this->addHookAfter('Pages::saved', $this, 'publishSnipcartProduct');
        $this->addHookAfter('Pages::unpublished', $this, 'unpublishSnipcartProduct');
        $this->addHookAfter('Pages::trashed', $this, 'unpublishSnipcartProduct');
        
		$this->addHookBefore('Modules::uninstall', $this, 'convertFieldtypeTaxSelector');
    }

    /**
     * Validate and sanitize taxes repeater input fields.
     * (Method triggered before module config save)
     *
     */
    public function validateTaxesRepeater(HookEvent $event) {
        $class = $event->arguments(0);
        if (is_object($class)) $class = $class->className();
        // Get class name without namespace
        $className = wireClassName($class);
        if ($className != 'SnipWire') return;

        $fields = $this->wire('modules')->getModuleConfigInputfields($className);
        $taxesField = $fields->get('taxes');

        $config = $event->arguments(1);
        $taxes = wireDecodeJSON($config['taxes']);

        if (!count($taxes)) {
            $taxesField->error(
                $this->_('Taxes repeater has no entries. At least 1 tax setting is required.')
            );
            return;
        }
        foreach ($taxes as $key => $tax) {
            if (empty($tax['name']) || empty($tax['rate'])) {
                $taxesField->error(sprintf(
                    $this->_('Taxes repeater row [%s]: "Tax name" and "Rate" may not be empty'),
                    $key + 1
                ));
            }
            if (!empty($tax['rate']) && !\SnipWire\Helpers\checkPattern($tax['rate'], '^[-+]?[0-9]*[.]?[0-9]+$')) {
                $taxesField->error(sprintf(
                    $this->_('Taxes repeater row [%s]: "Rate" value needs to be float'),
                    $key + 1
                ));
            }
        }
    }

    /**
     * Manage currency specific price input fields based on module "currencies" property.
     * (Method triggered after module config save)
     *
     * - Fields will be created on demand (if not exists).
     *
     */
    public function manageCurrencyPriceFields(HookEvent $event) {
        $class = $event->arguments(0);
        if (is_object($class)) $class = $class->className();
        // Get class name without namespace
        $className = wireClassName($class);
        if ($className != 'SnipWire') return;

        $config = $event->arguments(1);

        $currencies = isset($config['currencies']) ? $config['currencies'] : false;
        if (empty($currencies) || !is_array($currencies)) return;

        $fields = $this->wire('fields');
        $modules = $this->wire('modules');
        
        $fieldTemplate = self::getCurrencyFieldTemplate();
        $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
        
        foreach ($currencies as $currency) {
            $fieldName = $fieldTemplate['name'] . $currency;
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
            $out = sprintf(
                $this->_('Created currency specific field: [%s].'),
                $fieldName
            );
            $out .= ' ' . $this->_('You need to add this field to your product templates manually!');
            $this->message($out);
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
     * Preset value of field snipcart_item_taxes (VAT) with first element of taxes config.
     * Preset value of checkbox field snipcart_item_taxable so it's checked by default.
     * Preset value of checkbox field snipcart_item_shippable so it's checked by default.
     * (Method triggered after Pages added)
     *
     */
    public function presetProductFields(HookEvent $event) {
        $snipwire = $this->wire('snipwire');
        if (!$snipwire) return;

        $page = $event->arguments(0);
        if ($snipwire->isProductTemplate($page->template)) {
            $page->setAndSave('snipcart_item_id', $page->id);
            $page->setAndSave('snipcart_item_taxable', 1);
            $page->setAndSave('snipcart_item_shippable', 1);
            $page->setAndSave('snipcart_item_stackable', 1);
        }
    }

    /**
     * Preset value of field snipcart_item_taxes (VAT) with first element of taxes config.
     * (Method triggered after Pages added)
     *
     */
    public function presetProductTaxesField(HookEvent $event) {
        $snipwire = $this->wire('snipwire');
        if (!$snipwire) return;

        $page = $event->arguments(0);
        if ($snipwire->isProductTemplate($page->template)) {
            $defaultTax = Taxes::getFirstTax(false, Taxes::taxesTypeProducts);
            $page->setAndSave('snipcart_item_taxes', $defaultTax['name']);
        }
    }

    /**
     * Check if the SKU value is unique across all product pages.
     * (Method triggered after Pages saveReady -> just before page is saved)
     *
     * @throws WireException
     *
     */
    public function checkSKUUnique(HookEvent $event) {
        $snipwire = $this->wire('snipwire');
        if (!$snipwire) return;

        $page = $event->arguments(0);
        if ($snipwire->isProductTemplate($page->template)) {
            $field = $page->getField('snipcart_item_id');
            $sku = $page->snipcart_item_id; // SKU field value
            
            if ($page->isChanged('snipcart_item_id')) {
                $exists = $this->wire('pages')->get("snipcart_item_id=$sku");
                if ($exists->id) {
                    // value is not unique!
                    $error = $this->_('SKU must be unique'); 
                    $exception = sprintf(
                        $this->_('SKU [%s] is already in use'),
                        $sku
                    ); 
                    $inputfield = $page->getInputfield($field);
                    $inputfield->error($error); 
                    throw new WireException($exception); // Prevent saving of non-unique value!
                }
            }
        }
    }

    /**
     * Automatically creates/restores a Snipcart product by manually fetching URL (archived flag is set to false).
     * (Method triggered after a page has just been saved)
     *
     */
    public function publishSnipcartProduct(HookEvent $event) {
        $snipwire = $this->wire('snipwire');
        $sniprest = $this->wire('sniprest');
        $log = $this->wire('log');
        if (!$snipwire || !$sniprest) return;

        $page = $event->arguments(0);
        if ($snipwire->isProductTemplate($page->template)) {
            if ($page->isPublic()) {
                // Only fetch if published and viewable!
                $snipcart_item_id = $page->snipcart_item_id;
                $response = $sniprest->postProduct($page->httpUrl);
                
                $content = $response[$page->httpUrl][WireHttpExtended::resultKeyContent];
                $httpCode = $response[$page->httpUrl][WireHttpExtended::resultKeyHttpCode];
                $error = $response[$page->httpUrl][WireHttpExtended::resultKeyError];
                
                if ($httpCode == 200 || $httpCode == 201) {
                    $id = isset($content[0]['id']) ? $content[0]['id'] : '';
                    $message = sprintf(
                        $this->_('Fetched Snipcart product with SKU [%1$s] / ID [%2$s]'),
                        $snipcart_item_id,
                        $id
                    );
                } else {
                    $message = sprintf(
                        $this->_('Snipcart product with SKU [%1$s] could not be fetched. %2$s'),
                        $snipcart_item_id,
                        $error
                    );
                }
                $log->save(self::snipWireLogName, $message);
            }
        }
    }

    /**
     * Archive a Snipcart product so it won't be visible in the products listing anymore (archived flag is set to true).
     * (Method triggered after a published page has just been unpublished)
     *
     */
    public function unpublishSnipcartProduct(HookEvent $event) {
        $snipwire = $this->wire('snipwire');
        $sniprest = $this->wire('sniprest');
        $log = $this->wire('log');
        if (!$snipwire || !$sniprest) return;

        $page = $event->arguments(0);
        if ($snipwire->isProductTemplate($page->template)) {
            $snipcart_item_id = $page->snipcart_item_id;
            
            if ($id = $sniprest->getProductId($snipcart_item_id)) {
                $response = $sniprest->deleteProduct($id);

                $httpCode = $response[$id][WireHttpExtended::resultKeyHttpCode];
                $error = $response[$id][WireHttpExtended::resultKeyError];
                
                if ($httpCode == 200 || $httpCode == 201) {
                    $message = sprintf(
                        $this->_('Archived Snipcart product with SKU [%1$s] / ID [%2$s]'),
                        $snipcart_item_id,
                        $id
                    );
                } else {
                    $message = sprintf(
                        $this->_('Snipcart product with SKU [%1$s] could not be archived. [%2$s]'),
                        $snipcart_item_id,
                        $error
                    );
                }
            } else {
                $message = sprintf(
                    $this->_('Snipcart product could not be archived! SKU [%s] not found'),
                    $snipcart_item_id
                );
            }
            $log->save(self::snipWireLogName, $message);
        }
    }

    /**
     * Check if there are fields which uses FieldtypeSnipWireTaxSelector and convert them to FieldtypeText.
     * This is needed to enable uninstallation of the custom fieldtype and preserve products data!
     * (Method triggered before modules uninstall)
     *
     * @param HookEvent $event
     *
     */
    public function convertFieldtypeTaxSelector(HookEvent $event) {   
        $class = $event->arguments(0);
        if ($class == 'SnipWire') {
            $fields = $this->wire('fields')->find('type=FieldtypeSnipWireTaxSelector');
            if ($fields->count()) {
                foreach ($fields as $field) {
                    $field->type = 'FieldtypeText';
                    $field->save();
                    // This step is needed because ProcessWire removes tags when fieldtype is changed!
                    $field->tags = 'Snipcart';
                    $field->save();
                }
            }
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

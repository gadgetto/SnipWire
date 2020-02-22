<?php
namespace ProcessWire;

/**
 * FieldtypeSnipWireTaxSelector - Special (internal SnipWire) Fieldtype which fetches 
 * available taxes setting from SnipWire module config and builds a dropdown list.
 * (This file is part of the SnipWire package)
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * Based on FieldtypeDropdownDynamic by @BitPoet
 * https://github.com/BitPoet/FieldtypeDropdownDynamic
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Taxes.php';

use SnipWire\Helpers\Taxes;

class FieldtypeSnipWireTaxSelector extends FieldtypeText {

    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire Fieldtype TaxSelector'), // Module Title
            'summary' => __('Fieldtype which fetches taxes setting from SnipWire module config and builds a dropdown list.'), // Module Summary
            'version' => '0.8.2',
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'requires' => array(
                'ProcessWire>=3.0.148',
                'SnipWire',
                'InputfieldSelect',
                'PHP>=7.0.0',
            ),
        );
    }

	/**
	 * Initialize the Fieldtype.
	 *
	 */
	public function init() {
		parent::init();
		$this->allowTextFormatters(false);
	}

	/**
	 * Return all Fieldtypes derived from FieldtypeText, which we will consider compatible.
	 *
	 */
    public function ___getCompatibleFieldtypes(Field $field) {
		$fieldtypes = $this->wire(new Fieldtypes());
        foreach ($this->wire('fieldtypes') as $fieldtype) {
            if ($fieldtype instanceof FieldtypeText) {
                $fieldtypes->add($fieldtype);
            }
        }
        return $fieldtypes;
    }

    /**
     * Return the associated Inputfield.
     * (Default: InputfieldSelect)
     *
     */
    public function getInputfield(Page $page, Field $field) {
        $inputfieldClass = $field->get('inputfieldClass'); 
        if (!$inputfieldClass) $inputfieldClass = 'InputfieldSelect';

        $taxesType = $field->get('taxesType');
        if (!$taxesType) $taxesType = Taxes::taxesTypeAll;

        $inputfield = $this->wire('modules')->get($inputfieldClass);
        if (!$inputfield) $inputfield = $this->wire('modules')->get('InputfieldSelect'); 
        
        $taxes = Taxes::getTaxesConfig(false, $taxesType);

        /*
        Sample $taxes array:
        
        array(
            array(
                'name' => '20% VAT',
                'numberForInvoice' => '',
                'rate' => '0.20',
                'appliesOnShipping' => array(), // empty array --> taxesTypeProducts (jquery.repeater checkbox values are arrays)
            ),
             array(
                'name' => '10% VAT',
                'numberForInvoice' => '',
                'rate' => '0.10',
                'appliesOnShipping' => array() // empty array --> taxesTypeProducts (jquery.repeater checkbox values are arrays)
            ),            
             array(
                'name' => '20% VAT',
                'numberForInvoice' => '',
                'rate' => '0.20',
                'appliesOnShipping' => array(1) // array value = 1 --> taxesTypeShipping (jquery.repeater checkbox values are arrays)
            ),            
       );
        */
        foreach ($taxes as $tax) {
            $tax['attributes'] = array();
            if ($tax['name'] == $field->value) $tax['attributes'] = array_merge($tax['attributes'], array('selected'));
            $inputfield->addOption($tax['name'], $tax['name'], $tax['attributes']);
        }
        return $inputfield; 
    }

    /**
	 * Return the fields required to configure an instance of this Fieldtype
     *
     */
    public function ___getConfigInputfields(Field $field) {
        $inputfields = parent::___getConfigInputfields($field);

        $modules = $this->wire('modules');
        
        /** @var InputfieldSelect $f */
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'inputfieldClass');
        $f->label = $this->_('What should be used for input?');
        $f->description = $this->_('Some input types also provide more settings on the Input tab (visible after you save).'); 

        foreach ($modules as $module) {
            if (strpos($module->className(), 'Inputfield') !== 0) continue;
            if ($module instanceof ModulePlaceholder) {
                $module = $modules->getModule($module->className(), array('noInit' => true));
            }
            if ($module instanceof InputfieldSelect) {
                $name = str_replace('Inputfield', '', $module->className());
                if ($module instanceof InputfieldSelectMultiple) {
                    // not allowed
                } else {
                    $f->addOption($module->className(), $name);
                }
            }
        }
        
        $value = $field->get('inputfieldClass');
        if (!$value) $value = 'InputfieldSelect';
        $f->attr('value', $value);
        
        $inputfields->add($f);
        
        /** @var InputfieldSelect $f */
        $f = $modules->get('InputfieldSelect');
        $f->attr('name', 'taxesType');
        $f->label = $this->_('Which type of taxes should be listed as options?');
        $f->addOption(Taxes::taxesTypeProducts, $this->_('Product taxes'));
        $f->addOption(Taxes::taxesTypeShipping, $this->_('Shipping taxes'));
        $f->addOption(Taxes::taxesTypeAll, $this->_('All types'));
        $value = $field->get('taxesType');
        if (!$value) $value = Taxes::taxesTypeProducts;
        $f->attr('value', $value);

        $inputfields->add($f);
        
        return $inputfields;
    }
}
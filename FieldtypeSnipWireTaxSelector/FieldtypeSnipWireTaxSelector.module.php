<?php  namespace ProcessWire;

/**
 * FieldtypeSnipWireTaxSelector - Special Fieldtype which fetches available taxes setting 
 * from SnipWire module config and builds a dropdown list.
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

class FieldtypeSnipWireTaxSelector extends FieldtypeText {

    public static function getModuleInfo() {
        return array(
            'title' => __('SnipWire Fieldtype TaxSelector'),
            'summary' => __('Special Fieldtype which fetches available taxes setting from SnipWire module config and builds a dropdown list.'),
            'version' => '1',
            'author'  => 'Martin Gartner',
            'icon' => 'shopping-cart', 
            'requires' => array(
                'ProcessWire>=3.0.0',
                'SnipWire',
                'InputfieldSelect',
            ),
        );
    }

    /**
     * Include Taxes class.
     *
     */
    public function __construct() {
        parent::__construct();
        require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Taxes.php';
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
        
        $inputfield = $this->wire('modules')->get($inputfieldClass);
        if (!$inputfield) $inputfield = $this->wire('modules')->get('InputfieldSelect'); 
        
        $taxes = Taxes::getTaxesConfig();

        /*
        Sample array:
        
        array(
            'name' => 'vat_20',
            'numberForInvoice' => '20% VAT',
            'rate' => '0.20',
            'appliesOnShipping' => array(0),
        ),
        array(
            'name' => 'shipping_10',
            'numberForInvoice' => '10% VAT (Shipping)',
            'rate' => '0.10',
            'appliesOnShipping' => array(1),
        ),            
        */
        //bd($taxes);
        foreach ($taxes as $tax) {
            $tax['attributes'] = array();
            if ($tax['name'] == $field->value) $tax['attributes'] = array_merge($tax['attributes'], array('selected'));
            $inputfield->addOption($tax['name'], $tax['numberForInvoice'], $tax['attributes']);
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

        return $inputfields;
    }
}
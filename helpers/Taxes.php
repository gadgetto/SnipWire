<?php
namespace SnipWire\Helpers;

/**
 * Taxes - helper class
 *  - to fetch taxes definition from SnipWire module config
 *  - and to calculate taxes
 * (This file is part of the SnipWire package)
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class Taxes {
    
    const taxesTypeProducts = 1;
    const taxesTypeShipping = 2;
    const taxesTypeAll = 3;

    const shippingTaxesNone = 1;
    const shippingTaxesFixedRate = 2;
    const shippingTaxesHighestRate = 3;
    const shippingTaxesSplittedRate = 4;

    /**
     * Get the default taxes definition.
     *
     * @param boolean $json Wether to return as JSON formatted string and not array
     * @return array|string String of JSON data
     * 
     */
    public static function getDefaultTaxesConfig($json = false) {
        $defaultTaxes = array(
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
       return ($json) ? \ProcessWire\wireEncodeJSON($defaultTaxes, true) : $defaultTaxes;
    }

    /**
     * Get the taxes definition from module config.
     *
     * @param boolean $json Wether to return as JSON formatted string and not array
     * @param integer $type The taxes type (product = 1, shipping = 2, all = 3) [default: taxesTypeAll]
     * @param string $name The name of the tax (optional to get a specific taxes definition)
     * @return array|string String of JSON data
     * 
     */
    public static function getTaxesConfig($json = false, $type = self::taxesTypeAll, $name = '') {
        $taxes = \ProcessWire\wire('modules')->getConfig('SnipWire', 'taxes'); // JSON string
        if ($taxes) {
            $taxes = \ProcessWire\wireDecodeJSON($taxes);
        } else {
            $taxes = self::getDefaultTaxesConfig();
        }
        
        $selectedTaxes = array();
        // Filter taxes based on type if necessary
        // (Special handling is required because jquery.repeater checkbox values are always arrays)
        if ($type == self::taxesTypeProducts) {
            foreach ($taxes as $tax) {
                if (empty($tax['appliesOnShipping'][0])) $selectedTaxes[] = $tax;
            }
        } elseif ($type == self::taxesTypeShipping) {
            foreach ($taxes as $tax) {
                if (!empty($tax['appliesOnShipping'][0])) $selectedTaxes[] = $tax;
            }
        } else {
            $selectedTaxes = $taxes;
        }

        // Get a single tax definition by tax name
        if ($name) {
            $singleSelected = array();
            foreach ($selectedTaxes as $tax) {
                if ($tax['name'] == $name) {
                    $singleSelected = $tax;
                    break;
                }
            }
            $selectedTaxes = $singleSelected;
        }
        return ($json) ? \ProcessWire\wireEncodeJSON($selectedTaxes, true) : $selectedTaxes;
    }

    /**
     * Get the first tax definition from module config.
     *
     * @param boolean $json Wether to return as JSON formatted string and not array
     * @param integer $type The taxes type (product = 1, shipping = 2, all = 3) [default: taxesTypeAll]
     * @return array|string String of JSON data
     * 
     */
    public static function getFirstTax($json = false, $type = self::taxesTypeAll) {
        $taxes = self::getTaxesConfig(false, $type);
        foreach ($taxes as $tax) {
            $firstTax = $tax;
            break;
        }
        return ($json) ? \ProcessWire\wireEncodeJSON($firstTax, true) : $firstTax;
    }

    /**
     * Get the taxes_included (= hasTaxesIncluded) setting from module config.
     *
     * @return boolean
     * 
     */
    public static function getTaxesIncludedConfig() {
        $taxesIncluded = \ProcessWire\wire('modules')->getConfig('SnipWire', 'taxes_included');
        if (is_null($taxesIncluded)) $taxesIncluded = 1; // default
        return $taxesIncluded ? true : false;
    }

    /**
     * Get the shipping_taxes_type setting from module config.
     *
     * @return integer
     * 
     */
    public static function getShippingTaxesTypeConfig() {
        $shippingTaxesType = \ProcessWire\wire('modules')->getConfig('SnipWire', 'shipping_taxes_type');
        if (empty($shippingTaxesType)) $shippingTaxesType = self::shippingTaxesHighestRate; // default
        return $shippingTaxesType;
    }

    /**
     * Calculate the tax on a given price.
     *
     * @param float $value The value the tax has to be calculated from
     * @param float $rate The tax rate as decimal: percentage/100 (e.g. 0.20)
     * @param boolean $includedInPrice If the tax is included in price or excluded:
     *  - true: taxes won't be added on top of cart total
     *  - false: taxes will be added on top of cart total
     * @param integer $digits The number of decimal places the value will be rounded
     * @return string The calulated tax value
     * 
     */
    public static function calculateTax($value, $rate, $includedInPrice = true, $digits = 2) {
        if ($includedInPrice) {
            $divisor = 1 + $rate;
            $valueBeforeVat = $value / $divisor;
            $tax = $value - $valueBeforeVat;
        } else {
            $tax = $value * $rate;
        }
        return number_format($tax, $digits, '.', '');
    }

}

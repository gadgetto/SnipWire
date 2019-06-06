<?php namespace ProcessWire;

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
       );
        return ($json) ? wireEncodeJSON($defaultTaxes, true) : $defaultTaxes;
    }

    /**
     * Get the taxes definition from module config.
     *
     * @param boolean $json Wether to return as JSON formatted string and not array
     * @return array|string String of JSON data
     * 
     */
    public static function getTaxesConfig($json = false) {
        $taxes = wire('modules')->getConfig('SnipWire', 'taxes'); // JSON string
        if (!$taxes) $taxes = self::getDefaultTaxesConfig(true); // JSON string
        return ($json) ? $taxes : wireDecodeJSON($taxes);
    }

    /**
     * Get the first tax definition from module config.
     *
     * @param boolean $json Wether to return as JSON formatted string and not array
     * @return array|string String of JSON data
     * 
     */
    public static function getFirstTax($json = false) {
        $taxes = self::getTaxesConfig($json);
        $firstTax = $taxes[0];
        return ($json) ? wireEncodeJSON($firstTax, true) : $firstTax;
    }

    /**
     * Calculate the tax on a given product price.
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

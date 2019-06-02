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
            )
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

}
<?php
namespace SnipWire\Helpers;

/**
 * CurrencyFormat - helper class for SnipWire to handle currency formatting.
 * (This file is part of the SnipWire package)
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

use ProcessWire\WireData;

class CurrencyFormat extends WireData {
    
    /** @var array $currenciesCache An array of available currency formats (currency formats memory cache) */
    public static $currenciesCache = null;

    /**
     * Set the static curencies definition cache.
     *
     * @return void
     * 
     */
    public static function setStaticCurrenciesCache() {
        if (!$currencies = \ProcessWire\wire('sniprest')->getSettings('currencies')) {
            $currencies = self::getDefaultCurrencyDefinition();
        }
        // Cache currency definitons in static property (DB is queried only once!)
        self::$currenciesCache = $currencies;
    }

    /**
     * Returns an array of worldwide supported currencies, as name => label
     * (comes from static file CurrenciesTable.php which holds all currencies 
     * supported by Snipcart -> copied from Snipcart dashboard)
     * 
     * @return array
     * 
     */
    public static function getSupportedCurrencies() {
        return require __DIR__ . '/CurrenciesTable.php';
    }
    
    /**
     * Get the default currency definition.
     *
     * @param boolean $json Wether to return as JSON formatted string and not array
     * @return array|string String of JSON data
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
        return ($json) ? \ProcessWire\wireEncodeJSON($defaultCurrency, true) : $defaultCurrency;
    }

    /**
     * Get a specific currency definition by it's currency tag.
     *
     * @param string $currency The currency tag [default: `eur`]
     * @param string $key The array key to be returned [default: ``]
     * @param boolean $json Wether to return as JSON formatted string and not array (ignored if $key param is set) [default: false]
     * @return array|json|string|boolean
     * 
     */
    public static function getCurrencyDefinition($currency = 'eur', $key = '', $json = false) {
        if (empty(self::$currenciesCache)) self::setStaticCurrenciesCache();

        // Searches the static $currencys array for $currency tag and returns the corresponding key
        $cacheKey = array_search(
            $currency,
            array_column(self::$currenciesCache, 'currency')
        );
        if ($cacheKey === false) return false;
        
        $currencyDefinition = self::$currenciesCache[$cacheKey];
        if ($key && isset($currencyDefinition[$key])) {
            $currencyDefinition = $currencyDefinition[$key];
            $json = false;
        }
        return ($json) ? \ProcessWire\wireEncodeJSON($currencyDefinition, true) : $currencyDefinition;
    }

    /**
     * Format the given price based on selected currency.
     *
     * @param int|float|string|array $price The price value to format (can be multi currency)
     * @param string $currency The currency tag [default: `eur`]
     * @return string The formatted price (can be empty if something goes wrong)
     * 
     */
    public static function format($price, $currency = 'eur') {
        if (empty($price)) $price = 0.0;
        $currencyDefinition = self::getCurrencyDefinition($currency);

        // $price can be single or multi-currency
        /*
        "price": 1199.0
        
        -- or --
        
        "price": {
            "eur": 1199.0,
            "usd": 1342.3,
            ...
        },
        */

        $info = '';
        if (is_array($price)) {
            if (array_key_exists($currency, $price)) {
                $info = \ProcessWire\__('multi currency');
            } else {
                $info = \ProcessWire\__('currency not found');
                $currency = array_keys($price)[0]; // fallback to first currency in array
            }
            $price = $price[$currency];
        }

        $floatPrice = \ProcessWire\wire('sanitizer')->float($price);
        if ($floatPrice < 0) {
            $numberFormatString = $currencyDefinition['negativeNumberFormat'];
            $floatPrice = $floatPrice * -1; // price needs to be unsingned ('-' sign position defined by $numberFormatString)
        } else {
            $numberFormatString = $currencyDefinition['numberFormat'];
        }
        $price = number_format(
            $floatPrice,
            (integer) $currencyDefinition['precision'],
            (string) $currencyDefinition['decimalSeparator'],
            (string) $currencyDefinition['thousandSeparator']
        );
        $numberFormatString = str_replace('%s', '%1$s', $numberFormatString); // will be currencySymbol
        $numberFormatString = str_replace('%v', '%2$s', $numberFormatString); // will be value

        $formattedPrice = sprintf($numberFormatString, $currencyDefinition['currencySymbol'], $price);
        if ($info) $formattedPrice .= '<br><small class="ui-priority-secondary">' . $info . '</small>';

        return $formattedPrice;
    }

    /**
     * Format the given prices array (multi currency).
     *
     * @param array $prices The prices array to format ("currency" => price)
     * @param boolean $verbose Should the verbose currency name be added to output [default: false]
     * @param string $separator The separator for each formatted currency string [default: '<br>']
     * @return string The formatted prices (can be empty string if something goes wrong)
     * 
     */
    public static function formatMulti($prices, $verbose = false, $separator = '<br>') {
        if (empty($prices) || !is_array($prices)) return '';
        $supportedCurrencies = CurrencyFormat::getSupportedCurrencies();

        // $prices needs to be multi-currency
        /*
        {
            "eur": 1199.0,
            "usd": 1342.3,
            ...
        },
        */

        $formattedPrices = array();

        foreach ($prices as $currency => $price) {
            $currencyDefinition = self::getCurrencyDefinition($currency);
            
            $floatPrice = \ProcessWire\wire('sanitizer')->float($price);
            if ($floatPrice < 0) {
                $numberFormatString = $currencyDefinition['negativeNumberFormat'];
                $floatPrice = $floatPrice * -1; // price needs to be unsingned ('-' sign position defined by $numberFormatString)
            } else {
                $numberFormatString = $currencyDefinition['numberFormat'];
            }
            $price = number_format(
                $floatPrice,
                (integer) $currencyDefinition['precision'],
                (string) $currencyDefinition['decimalSeparator'],
                (string) $currencyDefinition['thousandSeparator']
            );
            $numberFormatString = str_replace('%s', '%1$s', $numberFormatString); // will be currencySymbol
            $numberFormatString = str_replace('%v', '%2$s', $numberFormatString); // will be value

            $formattedPrice = sprintf($numberFormatString, $currencyDefinition['currencySymbol'], $price);
            if ($verbose) $formattedPrice .= ' <small>' . $supportedCurrencies[$currency] . '</small>';
            $formattedPrices[] = $formattedPrice;
        }

        return implode($separator, $formattedPrices);
    }
}

<?php namespace ProcessWire;

/**
 * SnipREST - interface class for Snipcart REST API that lets you manage your data remotely.
 * (This file is part of the SnipWire package)
 *
 * Only accepts application/json content type -> always specify "Accept: application/json" header 
 * in every request.
 *
 * The main API endpoint is https://app.snipcart.com/api
 *
 * Snipcart is using HTTP Basic Auth.
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2018 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class SnipREST extends WireHttp {

    const apiEndpoint = 'https://app.snipcart.com/api';
    const resourcePathOrders = '/orders';
    const resourcePathSubscriptions = '/subscriptions';
    const resourcePathCustomers = '/customers';
    const resourcePathDiscounts = '/discounts';
    const resourcePathProducts = '/products';
    const resourcePathCartsAbandoned = '/carts/abandoned';
    const resourcePathShippingMethods = '/shipping_methods';
    const resourcePathSettingsGeneral = '/settings/general';
    const resourcePathSettingsDomain = '/settings/domain';
    
    const settingsCacheName = 'SnipcartSettingsGeneral';
    const settingsCacheExpires = 60; // seconds
    

    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();
        
        // Get ProcessSnipWire module config
        $moduleConfig = $this->wire('modules')->getConfig('ProcessSnipWire');
        
        // Snipcart environment (TEST | LIVE?)
        if ($moduleConfig['snipcart_environment'] == 1) {
            $snipcartAPIKey = $moduleConfig['api_key_secret'];
        } else {
            $snipcartAPIKey = $moduleConfig['api_key_secret_test'];
        }

        // Set headers required by Snipcart
        // -> Authorization: Basic <credentials>, where credentials is the base64 encoding of the secret API key and empty(!) password joined by a colon
        $this->setHeaders(array(
            'cache-control' => 'no-cache',
            'Authorization' => 'Basic ' . base64_encode($snipcartAPIKey . ':'),
            'Accept' => 'application/json',
        ));        
    }

    /**
     * Get the available currencies from Snipcart dashboard as array.
     * This method uses the WireCache to prevent reloading Snipcart data on each request.
     *
     * Sample array:
     *
     * Array
     * (
     *     [0] => Array
     *         (
     *             [currency] => eur
     *             [precision] => 2
     *             [decimalSeparator] => ,
     *             [thousandSeparator] => .
     *             [negativeNumberFormat] => - %s%v
     *             [numberFormat] => %s%v
     *             [currencySymbol] => €
     *             [id] => a9bd71db-657b-4f91-a261-7cc41f8fd7f3
     *         )
     * )
     *
     * @return bool|array False if request failed or currencies array
     *
     */
    public function getCurrencies($forceNew = false) {
        if ($forceNew) $this->wire('cache')->delete(self::settingsCacheName);

        // Try to get currencies array from cache (re-fetch only every 60 seconds)
        $response = $this->wire('cache')->get(self::settingsCacheName, self::settingsCacheExpires, function() {
            return $this->getJSON(self::apiEndpoint . self::resourcePathSettingsGeneral);
        });

        return $response['currencies'];
    }

}

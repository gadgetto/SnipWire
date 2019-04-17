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
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
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
    const settingsCacheExpires = 300; // seconds
    

    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();
        $moduleConfig = $this->wire('modules')->getConfig('SnipWire');
        // Need to check if module configuration is available (if configuration form was never submitted, the necessary keys aren't available!)
        if ($moduleConfig && isset($moduleConfig['submit_save_module'])) {
            // Snipcart environment (TEST | LIVE?)
            $snipcartAPIKey = ($moduleConfig['snipcart_environment'] == 1) ? $moduleConfig['api_key_secret'] : $moduleConfig['api_key_secret_test'];
            
            // Set headers required by Snipcart
            // -> Authorization: Basic <credentials>, where credentials is the base64 encoding of the secret API key and empty(!) password joined by a colon
            $this->setHeaders(array(
                'cache-control' => 'no-cache',
                'Authorization' => 'Basic ' . base64_encode($snipcartAPIKey . ':'),
                'Accept' => 'application/json',
            ));
        }
    }


    /**
     * Snipcart REST API connection test.
     * (uses resourcePathSettingsDomain for test request)
     *
     * @return mixed True on success or string of status code on error
     * 
     */
    public function testConnection() {
        if (!$this->headers) return $this->_('Missing request headers for Snipcart REST connection.');
        return ($this->get(self::apiEndpoint . self::resourcePathSettingsDomain)) ? true : $this->getError();
    }

    /**
     * Get the available settings from Snipcart dashboard as array.
     * This method uses the WireCache to prevent reloading Snipcart data on each request.
     *
     * @var string $key Which settings key to return (fallback to full settings array if $key doesnt exist)
     * @var mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @var boolean $forceRefresh Wether to refresh the settings cache
     * @return boolean|array False if request failed or settings array
     *
     */
    public function getSettings($key = '', $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->headers) return false;
        if ($forceRefresh) $this->wire('cache')->deleteFor('SnipWire', self::settingsCacheName);

        // Try to get settings array from cache first
        $response = $this->wire('cache')->getFor('SnipWire', self::settingsCacheName, self::settingsCacheExpires, function() {
            return $this->getJSON(self::apiEndpoint . self::resourcePathSettingsGeneral);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }

}

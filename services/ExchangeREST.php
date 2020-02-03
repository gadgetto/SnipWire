<?php
namespace SnipWire\Services;

/**
 * ExchangeREST - service class for Foreign exchange rates API which is a free service for 
 * current and historical foreign exchange rates published by the European Central Bank.
 * (This file is part of the SnipWire package)
 *
 * @see https://exchangeratesapi.io
 * @see https://github.com/exchangeratesapi/exchangeratesapi
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'CurrencyFormat.php';
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'WireHttpExtended.php';

use SnipWire\Helpers\CurrencyFormat;
use ProcessWire\WireCache;

class ExchangeREST extends WireHttpExtended {

    const apiEndpoint = 'https://api.exchangeratesapi.io/';
    const resPathLatest = 'latest';

    const cacheNamespace = 'SnipWire';
    const cacheNamePrefixExchangeRates = 'Exchangerates';
    
    /** @var array $_supportedCurrencies Currencies supported by Exchangerates API */
    private $_supportedCurrencies = array();
    
    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();

        $this->_supportedCurrencies = CurrencyFormat::getSupportedCurrencies();
        
        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $snipwireConfig = $this->wire('modules')->get('SnipWire');
        
        // Snipcart environment (TEST | LIVE?)
        /*
        $snipcartAPIKey = ($snipwireConfig->snipcart_environment == 1)
            ? $snipwireConfig->api_key_secret
            : $snipwireConfig->api_key_secret_test;
        */
        
        // Set headers required by Exchangerates API
        $this->setHeaders(array(
            'cache-control' => 'no-cache',
            'Accept' => 'application/json',
        ));
    }

    /**
     * Returns messages texts (message, warning, error) based on given key.
     *
     * @return string (will be empty if key not found)
     *
     */
    public static function getMessagesText($key) {
        $texts = array(
            'no_headers' => \ProcessWire\__('Missing request headers for Exchangerates API connection.'),
            'connection_failed' => \ProcessWire\__('Connection to Exchangerates API failed'),
            'unsupported_currency' => \ProcessWire\__('The specified currency %s is currently not supported by Exchangerates API.'),
        );
        return array_key_exists($key, $texts) ? $texts[$key] : '';
    }

    /**
     * Get the latest foreign exchange reference rates as array.
     *
     * Uses WireCache to prevent reloading data on each request.
     *
     * @param string $currency The currency to quote against (default: eur)
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save() [default: 1 day]
     * @param boolean $forceRefresh Wether to refresh the cache
     * @return boolean|array False if request failed or conversions array
     *
     */
    public function getLatest($currency = 'eur', $expires = WireCache::expireDaily, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixExchangeRates);

        $query = '';
        if (!empty($currency)) {
            if (array_key_exists(strtolower($currency), $this->_supportedCurrencies)) {
                $query = '?base=' . strtoupper($currency);
            } else {
                $this->error(sprintf(self::getMessagesText('unsupported_currency'), $currency));
            }
        }

        // Segmented orders cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixExchangeRates . '.' . md5($query);

        // Try to get settings array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, self::cacheNamePrefixExchangeRates, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathLatest . $query);
        });

        if ($response === false) $response = array();
        $data[self::resPathLatest] = array(
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        );
        return $data;
    }

}

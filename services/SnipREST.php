<?php namespace ProcessWire;

/**
 * SnipREST - service class for Snipcart REST API that lets you manage your data remotely.
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

require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'CurlMulti.php';

class SnipREST extends CurlMulti {

    const apiEndpoint = 'https://app.snipcart.com/api/';
    const resourcePathOrders = 'orders';
    const resourcePathDataOrdersSales = 'data/orders/sales'; // undocumented
    const resourcePathDataOrdersCount = 'data/orders/count'; // undocumented
    const resourcePathDataPerformance = 'data/performance'; // undocumented
    const resourcePathSubscriptions = 'subscriptions';
    const resourcePathCustomers = 'customers';
    const resourcePathDiscounts = 'discounts';
    const resourcePathProducts = 'products';
    const resourcePathCartsAbandoned = 'carts/abandoned';
    const resourcePathShippingMethods = 'shipping_methods';
    const resourcePathSettingsGeneral = 'settings/general'; // undocumented
    const resourcePathSettingsDomain = 'settings/domain';
    const resourcePathRequestValidation = 'requestvalidation'; // + HTTP_X_SNIPCART_REQUESTTOKEN
    
    const cacheNamespace = 'SnipWire';
    const cacheNamePrefixSettings = 'Settings';
    const cacheNamePrefixProducts = 'Products';
    const cacheNamePrefixOrders = 'Orders';
    const cacheNamePrefixCustomers = 'Customers';
    const cacheNamePrefixPerformance = 'Performance';
    const cacheNamePrefixOrdersCount = 'OrdersCount';

    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();

        $snipwireConfig = $this->wire('modules')->getConfig('SnipWire');
        // Need to check if module configuration is available (if configuration form was never submitted, 
        // the necessary keys aren't available!)
        if ($snipwireConfig && isset($snipwireConfig['submit_save_module'])) {
            // Snipcart environment (TEST | LIVE?)
            $snipcartAPIKey = ($snipwireConfig['snipcart_environment'] == 1)
                ? $snipwireConfig['api_key_secret']
                : $snipwireConfig['api_key_secret_test'];
            
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
     * Returns messages texts (message, warning, error) based on given key.
     *
     * @return string (will be empty if key not found)
     *
     */
    public static function getMessagesText($key) {
        $texts = array(
            'no_headers' => __('Missing request headers for Snipcart REST connection.'),
            'connection_failed' => __('Connection to Snipcart failed'),
            'dashboard_no_curl' => __('cURL extension not available - the SnipWire Dashboard will respond very slow without.'),
        );
        return array_key_exists($key, $texts) ? $texts[$key] : '';
    }

    /**
     * Get the available settings from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key Which settings key to return (fallback to full settings array if $key doesnt exist)
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return boolean|array False if request failed or settings array
     *
     */
    public function getSettings($key = '', $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixSettings);

        // Try to get settings array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, self::cacheNamePrefixSettings, $expires, function() {
            return $this->getJSON(self::apiEndpoint . self::resourcePathSettingsGeneral);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }


    /**
     * Get all dashboard results using cURL multi (fallback to single requests if cURL not available)
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @return mixed Dashboard data as array (each package indexed by full URL) or false if something went wrong
     *
     */
    public function getDashboardData($start, $end) {

        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$this->hasCURL) {
            $this->warning(self::getMessagesText('dashboard_no_curl'));
            // Get data without cURL multi
            return $this->getDashboardDataSingle($start, $end);
        }
        
        // ---- Performance boxes data ----
        
        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resourcePathDataPerformance . $query);

        // ---- Performance chart data ----

        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resourcePathDataOrdersSales . $query);
        $this->addMultiCURLRequest(self::apiEndpoint . self::resourcePathDataOrdersCount . $query);

        // ---- Top 10 customers ----
        
        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resourcePathCustomers . $query);

        // ---- Top 10 products ----

        $selector = array(
            'offset' => 0,
            'limit' => 10,
            'archived' => 'false',
            'excludeZeroSales' => 'true',
            'orderBy' => 'SalesValue',
            'from' => $start,
            'to' => $end,
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resourcePathProducts . $query);

        // ---- Latest 10 orders ----

        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resourcePathOrders . $query);

        return $this->getMultiJSON();
    }

    /**
     * Get all dashboard results using single requests (cURL multi not available)
     *
     * @param string $start ISO 8601 date format string
     * @param string $end ISO 8601 date format string
     * @return mixed Dashboard data as array (indexed by `resourcePath...`) or false if something went wrong
     *
     */
    public function getDashboardDataSingle($start, $end) {

        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }

        $data = array();
        
        // ---- Performance boxes data ----
        
        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        );
        $performance = $this->getPerformance($selector, 300);
        if ($performance === false) {
            $this->error(SnipREST::getMessagesText('connection_failed'));
            $performance = array();
        }
        $data[self::resourcePathDataPerformance] = array(CurlMulti::resultKeyContent => $performance);

        // ---- Performance chart data ----

        $selector = array(
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        );
        $chartSalesCount = $this->getSalesCount($selector, 300);
        if ($chartSalesCount === false) {
            $this->error(SnipREST::getMessagesText('connection_failed'));
            $chartSalesCount = array();
        }
        $data[self::resourcePathDataOrdersSales] = array(CurlMulti::resultKeyContent => $chartSalesCount);

        $chartOrdersCount = $this->getOrdersCount($selector, 300);
        if ($chartOrdersCount === false) {
            $this->error(SnipREST::getMessagesText('connection_failed'));
            $chartOrdersCount = array();
        }
        $data[self::resourcePathDataOrdersCount] = array(CurlMulti::resultKeyContent => $chartOrdersCount);
        
        // ---- Top 10 customers ----
        
        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );
        $customers = $this->getCustomersItems($selector, 300);
        if ($customers === false) {
            $this->error(SnipREST::getMessagesText('connection_failed'));
            $customers = array();
        }
        $data[self::resourcePathCustomers] = array(CurlMulti::resultKeyContent => $customers);

        // ---- Top 10 products ----

        $selector = array(
            'offset' => 0,
            'limit' => 10,
            'archived' => 'false',
            'excludeZeroSales' => 'true',
            'orderBy' => 'SalesValue',
            'from' => $start,
            'to' => $end,
        );
        $products = $sniprest->getProductsItems($selector, 300);
        if ($products === false) {
            $this->error(SnipREST::getMessagesText('connection_failed'));
            $products = array();
        }
        $data[self::resourcePathProducts] = array(CurlMulti::resultKeyContent => $products);

        // ---- Latest 10 orders ----

        $selector = array(
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        );
        $orders = $sniprest->getOrdersItems($selector, 300);
        if ($orders === false) {
            $this->error(SnipREST::getMessagesText('connection_failed'));
            $orders = array();
        }
        $data[self::resourcePathOrders] = array(CurlMulti::resultKeyContent => $orders);
        
        return $data;
    }

    /**
     * Get all orders from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your order collection. (Possible values: InProgress, Processed, Disputed, Shipped, Delivered, Pending, Cancelled)
     *  - `invoiceNumber` (string) The invoice number of the order to retrieve
     *  - `placedBy` (string) The name of the person who made the purchase
     *  - `from` (datetime) Will return only the orders placed after this date
     *  - '`to` (datetime) Will return only the orders placed before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or array
     *
     */
    public function getOrders($key = '', $options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixOrders);

        $allowedOptions = array('offset', 'limit', 'status', 'invoiceNumber', 'placedBy', 'from', 'to');
        $defaultOptions = array(
            'offset' => 0,
            'limit' => 20,
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented orders cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrders . '.' . md5($query);
        
        // Try to get orders array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resourcePathOrders . $query);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }

    /**
     * Get orders items from Snipcart dashboard.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your order collection. (Possible values: InProgress, Processed, Disputed, Shipped, Delivered, Pending, Cancelled)
     *  - `invoiceNumber` (string) The invoice number of the order to retrieve
     *  - `placedBy` (string) The name of the person who made the purchase
     *  - `from` (datetime) Will return only the orders placed after this date
     *  - '`to` (datetime) Will return only the orders placed before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return boolean|integer False if request failed or items as array
     * 
     */
    public function getOrdersItems($options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        return $this->getOrders('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get all products from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `userDefinedId` string The custom product ID
     *  - `archived` boolean (as string) "true" or "false" (undocumented!)
     *  - `excludeZeroSales`  boolean (as string) "true" or "false"  (undocumented!)
     *  - `orderBy` string The order by key (undocumented!)
     *  - `from` (datetime) Will return only the customers created after this date
     *  - '`to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or array
     *
     */
    public function getProducts($key = '', $options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixProducts);

        $allowedOptions = array('offset', 'limit', 'userDefinedId', 'archived', 'excludeZeroSales', 'orderBy', 'from', 'to');
        $defaultOptions = array(
            'offset' => 0,
            'limit' => 20,
            'orderBy' => 'SalesValue',
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixProducts . '.' . md5($query);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resourcePathProducts . $query);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }

    /**
     * Get products items from Snipcart dashboard.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `userDefinedId` string The custom product ID
     *  - `archived` boolean (as string) "true" or "false" (undocumented!)
     *  - `excludeZeroSales`  boolean (as string) "true" or "false"  (undocumented!)
     *  - `orderBy` string The order by key (undocumented!)
     *  - `from` (datetime) Will return only the customers created after this date
     *  - '`to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or items as array
     * 
     */
    public function getProductsItems($options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        return $this->getProducts('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get the all customers from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your customers collection. (Possible values: Confirmed = created an account, Unconfirmed = checked out as guests)
     *  - `email` (string) The email of the customer who placed the order
     *  - `name` (string) The name of the customer who placed the order
     *  - `from` (datetime) Will return only the customers created after this date
     *  - '`to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or array
     *
     */
    public function getCustomers($key = '', $options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixCustomers);

        $allowedOptions = array('offset', 'limit', 'status', 'email', 'name', 'from', 'to');
        $defaultOptions = array(
            'offset' => 0,
            'limit' => 20,
        );
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCustomers . '.' . md5($query);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resourcePathCustomers . $query);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }

    /**
     * Get customers items from Snipcart dashboard.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0] #required
     *  - `limit` (int) Number of results to fetch. [default = 20] #required
     *  - `status` (string) A status criteria for your customers collection. (Possible values: Confirmed = created an account, Unconfirmed = checked out as guests)
     *  - `email` (string) The email of the customer who placed the order
     *  - `name` (string) The name of the customer who placed the order
     *  - `from` (datetime) Will return only the customers created after this date
     *  - '`to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or items as array
     * 
     */
    public function getCustomersItems($options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        return $this->getCustomers('items', $options, $expires, $forceRefresh);
    }

    /**
     * Get the store performance from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the performance after this date
     *  - '`to` (datetime) Will return only the performance before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or array
     *
     */
    public function getPerformance($options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixPerformance);

        $allowedOptions = array('from', 'to');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixPerformance . '.' . md5($query);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resourcePathDataPerformance . $query);
        });
        return $response;
    }

    /**
     * Get the amount of sales from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the performance after this date
     *  - '`to` (datetime) Will return only the performance before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or array
     *
     */
    public function getSalesCount($options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::resourcePathDataOrdersSales);

        $allowedOptions = array('from', 'to');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::resourcePathDataOrdersSales . '.' . md5($query);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resourcePathDataOrdersSales . $query);
        });
        return $response;
    }

    /**
     * Get the number of orders from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the performance after this date
     *  - '`to` (datetime) Will return only the performance before this date
     * @param mixed $expires Lifetime of this cache, in seconds, OR one of the options from $cache->save()
     * @param boolean $forceRefresh Wether to refresh the settings cache
     * @return mixed False if request failed or array
     *
     */
    public function getOrdersCount($options = array(), $expires = WireCache::expireNever, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixOrdersCount);

        $allowedOptions = array('from', 'to');
        $defaultOptions = array();
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);

        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrdersCount . '.' . md5($query);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resourcePathDataOrdersCount . $query);
        });
        return $response;
    }

    /**
     * Snipcart REST API connection test.
     * (uses resourcePathSettingsDomain for test request)
     *
     * @return mixed $status True on success or string of status code on error
     * 
     */
    public function testConnection() {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        return ($this->get(self::apiEndpoint . self::resourcePathSettingsDomain)) ? true : $this->getError();
    }
    
    /**
     * Completely refresh Snipcart settings cache.
     *
     * @return boolean|array False if request failed or settings array
     *
     */
    public function refreshSettings() {
        return $this->getSettings('', WireCache::expireNever, true);
    }

}

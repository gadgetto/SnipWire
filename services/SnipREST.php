<?php
namespace SnipWire\Services;

/**
 * SnipREST - service class for Snipcart REST API that lets you manage your data remotely.
 * (This file is part of the SnipWire package)
 *
 * Only accepts application/json content type -> always specify "Accept: application/json" header 
 * in every request.
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

use ProcessWire\WireCache;

class SnipREST extends WireHttpExtended {

    const apiEndpoint = 'https://app.snipcart.com/api/';
    const resPathDataPerformance = 'data/performance'; // undocumented
    const resPathDataOrdersSales = 'data/orders/sales'; // undocumented
    const resPathDataOrdersCount = 'data/orders/count'; // undocumented
    const resPathOrders = 'orders';
    const resPathOrdersNotifications = 'orders/{token}/notifications';
    const resPathOrdersRefunds = 'orders/{token}/refunds';
    const resPathSubscriptions = 'subscriptions';
    const resPathSubscriptionsInvoices = 'subscriptions/{id}/invoices';
    const resPathSubscriptionsPause = 'subscriptions/{id}/pause';
    const resPathSubscriptionsResume = 'subscriptions/{id}/resume';
    const resPathSubscriptionsDelete = 'subscriptions/{id}';
    const resPathCartsAbandoned = 'carts/abandoned';
    const resPathCartsAbandonedNotifications = 'carts/{id}/notifications';
    const resPathCustomers = 'customers';
    const resPathCustomersOrders = 'customers/{id}/orders';
    const resPathProducts = 'products';
    const resPathDiscounts = 'discounts';
    const resPathSettingsGeneral = 'settings/general'; // undocumented
    const resPathSettingsDomain = 'settings/domain';
    const resPathSettingsAllowedDomains = 'settings/alloweddomains';
    const resPathShippingMethods = 'shipping_methods';
    const resPathRequestValidation = 'requestvalidation'; // + HTTP_X_SNIPCART_REQUESTTOKEN
    const snipcartInvoiceUrl = 'https://app.snipcart.com/invoice/{token}'; // currently not possible via API
    
    const cacheNamespace = 'SnipWire';
    const cacheExpireDefault = 900; // max. cache expiration time in seconds
    
    const cacheNamePrefixDashboard = 'Dashboard';
    const cacheNamePrefixPerformance = 'Performance';
    const cacheNamePrefixOrders = 'Orders';
    const cacheNamePrefixOrdersSales = 'OrdersSales';
    const cacheNamePrefixOrdersCount = 'OrdersCount';
    const cacheNamePrefixOrdersNotifications = 'OrdersNotifications';
    const cacheNamePrefixOrdersDetail = 'OrdersDetail';
    const cacheNamePrefixSubscriptions = 'Subscriptions';
    const cacheNamePrefixSubscriptionsDetail = 'SubscriptionsDetail';
    const cacheNamePrefixSubscriptionsInvoices = 'SubscriptionsInvoices';
    const cacheNamePrefixCartsAbandoned = 'CartsAbandoned';
    const cacheNamePrefixCartsAbandonedNotifications = 'CartsAbandonedNotifications';
    const cacheNamePrefixCartsAbandonedDetail = 'CartsAbandonedDetail';
    const cacheNamePrefixCustomers = 'Customers';
    const cacheNamePrefixCustomersOrders = 'CustomersOrders';
    const cacheNamePrefixCustomersDetail = 'CustomersDetail';
    const cacheNamePrefixProducts = 'Products';
    const cacheNamePrefixProductsDetail = 'ProductsDetail';
    const cacheNamePrefixDiscounts = 'Discounts';
    const cacheNamePrefixDiscountsDetail = 'DiscountsDetail';
    const cacheNamePrefixSettings = 'Settings';
    
    /**
     * Construct/initialize
     * 
     */
    public function __construct() {
        parent::__construct();
        
        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $snipwireConfig = $this->wire('modules')->get('SnipWire');
        
        // Snipcart environment (TEST | LIVE?)
        $snipcartAPIKey = ($snipwireConfig->snipcart_environment == 1)
            ? $snipwireConfig->api_key_secret
            : $snipwireConfig->api_key_secret_test;
        
        // Set headers required by Snipcart
        // -> Authorization: Basic <credentials>, where credentials is the base64 encoding of the secret API key and empty(!) password joined by a colon
        $this->setHeaders([
            'cache-control' => 'no-cache',
            'Authorization' => 'Basic ' . base64_encode($snipcartAPIKey . ':'),
            'Accept' => 'application/json',
        ]);
    }
    
    /**
     * Returns messages texts (message, warning, error) based on given key.
     *
     * @return string (will be empty if key not found)
     *
     */
    public static function getMessagesText($key) {
        $texts = [
            'no_headers' => \ProcessWire\__('Missing request headers for Snipcart REST connection'),
            'connection_failed' => \ProcessWire\__('Connection to Snipcart failed'),
            'cache_refreshed' => \ProcessWire\__('Snipcart cache for this section refreshed'),
            'full_cache_refreshed' => \ProcessWire\__('Full Snipcart cache refreshed'),
            'dashboard_no_curl' => \ProcessWire\__('cURL extension not available - the SnipWire Dashboard will respond very slow without'),
            'no_order_token' => \ProcessWire\__('No order token provided'),
            'no_subscription_id' => \ProcessWire\__('No subscription ID provided'),
            'no_cart_id' => \ProcessWire\__('No cart ID provided'),
            'no_customer_id' => \ProcessWire\__('No customer ID provided'),
            'no_product_id' => \ProcessWire\__('No product ID provided'),
            'no_product_url' => \ProcessWire\__('No product URL provided'),
            'no_userdefined_id' => \ProcessWire\__('No userdefined ID provided'),
            'no_discount_id' => \ProcessWire\__('No discount ID provided'),
        ];
        return array_key_exists($key, $texts) ? $texts[$key] : '';
    }
    
    /**
     * Get the available settings from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key Which settings key to return (fallback to full settings array if $key doesnt exist)
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return boolean|array False if request failed or settings array
     *
     */
    public function getSettings($key = '', $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixSettings);
        
        // Try to get settings array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, self::cacheNamePrefixSettings, $expires, function() {
            return $this->getJSON(self::apiEndpoint . self::resPathSettingsGeneral);
        });
        return ($key && isset($response[$key])) ? $response[$key] : $response;
    }
    
    /**
     * Get all dashboard results using cURL multi (fallback to single requests if cURL not available)
     *
     * @param string $start ISO 8601 date format string [#required]
     * @param string $end ISO 8601 date format string [#required]
     * @param string $currency Currency string [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return mixed Dashboard data as array (each package indexed by full URL)
     *
     */
    public function getDashboardData($start, $end, $currency, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->hasCURL) {
            $this->warning(self::getMessagesText('dashboard_no_curl'));
            // Get data without cURL multi
            return $this->_getDashboardDataSingle($start, $end, $currency);
        }
        
        // Segmented orders cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixDashboard . '.' . md5($start . $end . $currency);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get orders array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($start, $end, $currency) {
            return $this->_getDashboardDataMulti($start, $end, $currency);
        });
        
        return $response;
    }
    
    /**
     * Get all dashboard results using multi cURL requests
     *
     * @param string $start ISO 8601 date format string [#required]
     * @param string $end ISO 8601 date format string [#required]
     * @param string $currency Currency string [#required]
     * @return array $data Dashboard data as array (indexed by `resPath...`)
     *
     */
    private function _getDashboardDataMulti($start, $end, $currency) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        // ---- Part of performance boxes data ----
        
        $selector = [
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        ];
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathDataPerformance . $query);
        
        // ---- Part of performance boxes + performance chart data ----
        
        $selector = [
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
            'currency' => $currency,
        ];
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathDataOrdersSales . $query);
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathDataOrdersCount . $query);
        
        // ---- Top 10 customers ----
        
        $selector = [
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        ];
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathCustomers . $query);
        
        // ---- Top 10 products ----
        
        $selector = [
            'limit' => 10,
            'archived' => 'false',
            'excludeZeroSales' => 'true',
            'orderBy' => 'SalesValue',
            'from' => $start,
            'to' => $end,
        ];
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathProducts . $query);
        
        // ---- Latest 10 orders ----
        
        $selector = [
            'limit' => 10,
            'from' => $start,
            'to' => $end,
            'format' => 'Excerpt',
        ];
        $query = !empty($selector) ? '?' . http_build_query($selector) : '';
        $this->addMultiCURLRequest(self::apiEndpoint . self::resPathOrders . $query);
        
        return $this->getMultiJSON();
    }
    
    /**
     * Get all dashboard results using single requests
     *
     * @param string $start ISO 8601 date format string [#required]
     * @param string $end ISO 8601 date format string [#required]
     * @param string $currency Currency string [#required]
     * @return array $data Dashboard data as array (indexed by `resPath...`)
     *
     */
    private function _getDashboardDataSingle($start, $end, $currency) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $data = [];
        
        // ---- Part of performance boxes data ----
        
        $selector = [
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
        ];
        $data[] = $this->getPerformance($selector);
        
        // ---- Part of performance boxes + performance chart data ----
        
        $selector = [
            'from' => $start ? strtotime($start) : '', // UNIX timestamp required
            'to' => $end ? strtotime($end) : '', // UNIX timestamp required
            'currency' => $currency,
        ];
        
        $data[] = $this->getSalesCount($selector);
        $data[] = $this->getOrdersCount($selector);
        
        // ---- Top 10 customers ----
        
        $selector = [
            'limit' => 10,
            'from' => $start,
            'to' => $end,
        ];
        $data[] = $this->getCustomers($selector);
        
        // ---- Top 10 products ----
        
        $selector = [
            'offset' => 0,
            'limit' => 10,
            'archived' => 'false',
            'excludeZeroSales' => 'true',
            'orderBy' => 'SalesValue',
            'from' => $start,
            'to' => $end,
        ];
        $data[] = $this->getProducts($selector);
        
        // ---- Latest 10 orders ----
        
        $selector = [
            'limit' => 10,
            'from' => $start,
            'to' => $end,
            'format' => 'Excerpt',
        ];
        $data[] = $this->getOrders($selector);
        
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
     *  - `paymentStatus` (string) A payment status criteria for your order collection. (Possible values: Paid, PaidDeferred, Deferred)
     *  - `invoiceNumber` (string) The invoice number of the order to retrieve
     *  - `placedBy` (string) The name of the person who made the purchase
     *  - `from` (datetime) Will return only the orders placed after this date
     *  - `to` (datetime) Will return only the orders placed before this date
     *  - 'format' (string) Get a simplified version of orders payload + faster query (Possible values: Excerpt) #undocumented
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getOrders($key = '', $options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $allowedOptions = ['offset', 'limit', 'status', 'paymentStatus', 'invoiceNumber', 'placedBy', 'from', 'to', 'format'];
        $defaultOptions = [
            'offset' => 0,
            'limit' => 20,
        ];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrders . '.' . md5($query);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathOrders . $query);
        });
        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        
        $dataKey = self::resPathOrders;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
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
     *  - `to` (datetime) Will return only the orders placed before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getOrdersItems($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getOrders('items', $options, $expires, $forceRefresh);
    }
    
    /**
     * Get a single order from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $token The Snipcart $token of the order to be returned [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getOrder($token, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrdersDetail . '.' . md5($token);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($token) {
            return $this->getJSON(self::apiEndpoint . self::resPathOrders . '/' . $token);
        });
        
        $dataKey = self::resPathOrders . '/' . $token;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get all notifications of an order from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $token The Snipcart token of the order [#required]
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0]
     *  - `limit` (int) Number of results to fetch. [default = 20]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getOrderNotifications($token, $options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathOrdersNotifications,
            ['token' => $token]
        );
        
        $allowedOptions = ['offset', 'limit'];
        $defaultOptions = [
            'offset' => 0,
            'limit' => 20,
        ];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrdersNotifications . '.' . md5($token); // @todo: currently query is not used for cache name
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($url, $query) {
            return $this->getJSON($url . $query);
        });
        
        $dataKey = \ProcessWire\wirePopulateStringTags(
            self::resPathOrdersNotifications,
            ['token' => $token]
        );
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Creates a new notification on a specified order.
     *
     * (Includes sending some information to your customer or generating automatic emails)
     *
     * @param string $token The Snipcart token of the order [#required]
     * @param array $options An array of options that will be sent as POST params:
     *  - `type` (string) Type of notification. (Possible values: Comment, OrderStatusChanged, OrderShipped, TrackingNumber, Invoice) [default: TrackingNumber]
     *  - `deliveryMethod` (string) 'Email' send by email, 'None' keep it private. [default: Email]
     *  - `message` (string) Message of the notification. Optional when used with type 'TrackingNumber'.
     * @return array $data
     * 
     */
    public function postOrderNotification($token, $options = []) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $allowedOptions = ['type', 'deliveryMethod', 'message'];
        $defaultOptions = [
            'type' => 'TrackingNumber',
            'deliveryMethod' => 'Email',
        ];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathOrdersNotifications,
            ['token' => $token]
        );
        $requestbody = \ProcessWire\wireEncodeJSON($options);
        
        $response = $this->post($url, $requestbody);
        
        $dataKey = \ProcessWire\wirePopulateStringTags(
            self::resPathOrdersNotifications,
            ['token' => $token]
        );
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Creates a new refund on a specified order.
     *
     * (Includes sending some information to your customer or generating automatic emails)
     *
     * @param string $token The Snipcart token of the order [#required]
     * @param array $options An array of options that will be sent as POST params:
     *  - `amount` (float) The amount to be refunded
     *  - `comment` (string) The reason for the refund
     *  - `notifyCustomer` (boolean) Send reason for refund with customer notification
     * @return array $data
     * 
     */
    public function postOrderRefund($token, $options = []) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $allowedOptions = ['amount', 'comment', 'notifyCustomer'];
        $defaultOptions = [];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathOrdersRefunds,
            ['token' => $token]
        );
        $requestbody = \ProcessWire\wireEncodeJSON($options);
        
        $response = $this->post($url, $requestbody);
        
        $dataKey = \ProcessWire\wirePopulateStringTags(
            self::resPathOrdersRefunds,
            ['token' => $token]
        );
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Updates the status of an order.
     *
     * (Includes sending some information to your customer or generating automatic emails)
     *
     * @param string $token The Snipcart token of the order [#required]
     * @param array $options An array of options that will be sent as POST params:
     *  - `status` (string) The order status (Possible values: InProgress, Processed, Disputed, Shipped, Delivered, Pending, Cancelled)
     *  - `paymentStatus` (string) The order payment status (Possible values: Paid, Deferred, PaidDeferred, ChargedBack, Refunded, Paidout, Failed, Pending, Expired, Cancelled, Open, Authorized)
     *  - `trackingNumber` (string) The tracking number associated to the order
     *  - `trackingUrl` (string) The URL where the customer will be able to track its order
     * @return array $data
     * 
     */
    public function putOrderStatus($token, $options = []) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$token) {
            $this->error(self::getMessagesText('no_order_token'));
            return false;
        }
        // Add necessary header for PUT request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $allowedOptions = ['status', 'paymentStatus', 'trackingNumber', 'trackingUrl'];
        $defaultOptions = [];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = self::apiEndpoint . self::resPathOrders. '/' . $token;
        $requestbody = \ProcessWire\wireEncodeJSON($options, true);
        
        $response = $this->send($url, $requestbody, 'PUT');
        
        $dataKey = $token;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get all subscriptions from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0]
     *  - `limit` (int) Number of results to fetch. [default = 20]
     *  - `status` (string) A criteria to return items having the specified status. (Possible values: Active, Paused, Canceled)
     *  - `userDefinedPlanName` (string) A criteria to return items matching the specified plan name.
     *  - `userDefinedCustomerNameOrEmail` (string) A criteria to return items belonging to the specified customer name or email.
     *  - `from` (datetime) Filter subscriptions to return items that start on specified date.
     *  - `to` (datetime) Filter subscriptions to return items that end on specified date.
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getSubscriptions($key = '', $options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        // 'limit' must not be 0 (otherwise the result will not return items)!
        // @todo: add this to other endpoint queries too!
        if (isset($options['limit']) && $options['limit'] === 0) $options['limit'] = 100;
        
        $allowedOptions = ['offset', 'limit', 'status', 'userDefinedPlanName', 'userDefinedCustomerNameOrEmail', 'from', 'to'];
        $defaultOptions = [
            'offset' => 0,
            'limit' => 20,
        ];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixSubscriptions . '.' . md5($query);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathSubscriptions . $query);
        });
        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        
        $dataKey = self::resPathSubscriptions;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get subscriptions items from Snipcart dashboard.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0]
     *  - `limit` (int) Number of results to fetch. [default = 20]
     *  - `status` (string) A criteria to return items having the specified status. (Possible values: Active, Paused, Canceled)
     *  - `userDefinedPlanName` (string) A criteria to return items matching the specified plan name.
     *  - `userDefinedCustomerNameOrEmail` (string) A criteria to return items belonging to the specified customer name or email.
     *  - `from` (datetime) Filter subscriptions to return items that start on specified date.
     *  - `to` (datetime) Filter subscriptions to return items that end on specified date.
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getSubscriptionsItems($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getSubscriptions('items', $options, $expires, $forceRefresh);
    }
    
    /**
     * Get a single subscription from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart item id of the subscription to be returned [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getSubscription($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_subscription_id'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixSubscriptionsDetail . '.' . md5($id);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathSubscriptions . '/' . $id);
        });
        
        $dataKey = self::resPathSubscriptions . '/' . $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get all invoices of a subscription from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart item id of the subscription [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getSubscriptionInvoices($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_subscription_id'));
            return false;
        }
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathSubscriptionsInvoices,
            ['id' => $id]
        );
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixSubscriptionsInvoices . '.' . md5($id);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($url) {
            return $this->getJSON($url);
        });
        
        $dataKey = \ProcessWire\wirePopulateStringTags(
            self::resPathSubscriptionsInvoices,
            ['id' => $id]
        );
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Pause an active subscription.
     *
     * (Under the hood, a 100% discount will be applied to the Stripe subscription)
     *
     * @param string $id The Snipcart item id of the subscription to be paused [#required]
     * @return array $data
     * 
     */
    public function postSubscriptionPause($id) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_subscription_id'));
            return false;
        }
        // Add necessary header for POST request
        $this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathSubscriptionsPause,
            ['id' => $id]
        );
        
        // Snipcart doesn't expect a body here, but we provide 
        // a placeholder request body, as WireHttp requires one!
        $options = ['id' => $id];
        $requestbody = \ProcessWire\wireEncodeJSON($options);
        
        $response = $this->post($url, $requestbody);
        
        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Resume a paused subscription.
     *
     * (Under the hood, the 100% discount previously created on Stripe's subscription will be deleted)
     *
     * @param string $id The Snipcart item id of the subscription to be resumed [#required]
     * @return array $data
     * 
     */
    public function postSubscriptionResume($id) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_subscription_id'));
            return false;
        }
        // Add necessary header for POST request
        $this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathSubscriptionsResume,
            ['id' => $id]
        );
        
        // Snipcart doesn't expect a body here, but we provide 
        // a placeholder request body, as WireHttp requires one!
        $options = ['id' => $id];
        $requestbody = \ProcessWire\wireEncodeJSON($options);
        
        $response = $this->post($url, $requestbody);
        
        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Delete a subscription.
     *
     * (Under the hood, the subscription will not be deleted but set to "cancelled")
     *
     * @param string $id The Snipcart item id of the subscription to be cancelled [#required]
     * @return array $data
     * 
     */
    public function deleteSubscription($id) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_subscription_id'));
            return false;
        }
        // Add necessary header for DELETE request
        $this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathSubscriptionsDelete,
            ['id' => $id]
        );
        
        $rawResponse = $this->send($url, [], 'DELETE');
        $response = json_decode($rawResponse, true);
        
        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get the abandoned carts from Snipcart dashboard as array.
     *
     * The Snipcart API has no pagination in this case!
     * (only "Load more" button possible)
     *
     *   From the response use
     *     - `continuationToken`
     *     - `hasMoreResults`
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `limit` (int) Number of results to fetch. [default = 50]
     *  - `continuationToken` (string) The contionuation token for abandoned cart pager [default = null]
     *  - `timeRange` (string) A time range criteria for abandoned carts. (Possible values: Anytime, LessThan4Hours, LessThanADay, LessThanAWeek, LessThanAMonth)
     *  - `minimalValue` (float) The minimum total cart value of results to fetch
     *  - `email` (string) The email of the customer who placed the order
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getAbandonedCarts($key = '', $options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $allowedOptions = ['limit', 'continuationToken', 'timeRange', 'minimalValue', 'email'];
        $defaultOptions = [
            'limit' => 50,
            'continuationToken' => null,
        ];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCartsAbandoned . '.' . md5($query);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathCartsAbandoned . $query);
        });
        $response = ($key && isset($response[$key])) ? $response[$key] : $response;
        
        $dataKey = self::resPathCartsAbandoned;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get the abandoned carts items from Snipcart dashboard as array.
     *
     * The Snipcart API handles pagination different in this case!
     * (need to use prev / next button instead of pagination)
     *
     *   From the response use
     *     - `continuationToken`
     *     - `hasMoreResults`
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `limit` (int) Number of results to fetch. [default = 50]
     *  - `continuationToken` (string) The contionuation token for abandoned cart pager [default = null]
     *  - `timeRange` (string) A time range criteria for abandoned carts. (Possible values: Anytime, LessThan4Hours, LessThanADay, LessThanAWeek, LessThanAMonth)
     *  - `minimalValue` (float) The minimum total cart value of results to fetch
     *  - `email` (string) The email of the customer who placed the order
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getAbandonedCartsItems($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getAbandonedCarts('items', $options, $expires, $forceRefresh);
    }
    
    /**
     * Get a single abandoned cart from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the cart to be returned [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getAbandonedCart($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_cart_id'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCartsAbandonedDetail . '.' . md5($id);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathCartsAbandoned . '/' . $id);
        });
        
        $dataKey = self::resPathCartsAbandoned . '/' . $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Creates a new notification on a specified abandoned cart.
     *
     * (Includes sending some information to your customer or generating automatic emails)
     *
     * @param string $token The Snipcart id of the abandoned cart [#required]
     * @param array $options An array of options that will be sent as POST params:
     *  - `type` (string) Type of notification. (Possible values: Comment)
     *  - `deliveryMethod` (string) 'Email' send by email, 'None' keep it private.
     *  - `message` (string) Message of the notification
     * @return array $data
     * 
     */
    public function postAbandonedCartNotification($id, $options = []) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_cart_id'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $allowedOptions = ['type', 'deliveryMethod', 'message'];
        $defaultOptions = [
            'type' => 'Comment',
            'deliveryMethod' => 'Email',
        ];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathCartsAbandonedNotifications,
            ['id' => $id]
        );
        $requestbody = \ProcessWire\wireEncodeJSON($options);
        
        $response = $this->post($url, $requestbody);
        
        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get the all customers from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0]
     *  - `limit` (int) Number of results to fetch. [default = 20]
     *  - `status` (string) A status criteria for your customers collection. (Possible values: Confirmed = created an account, Unconfirmed = checked out as guests)
     *  - `email` (string) The email of the customer who placed the order
     *  - `name` (string) The name of the customer who placed the order
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getCustomers($key = '', $options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $allowedOptions = ['offset', 'limit', 'status', 'email', 'name', 'from', 'to'];
        $defaultOptions = [
            'offset' => 0,
            'limit' => 20,
        ];
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
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathCustomers . $query);
        });
        $response = ($key && isset($response[$key])) ? $response[$key] : $response;

        $dataKey = self::resPathCustomers;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get customers items from Snipcart dashboard.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0]
     *  - `limit` (int) Number of results to fetch. [default = 20]
     *  - `status` (string) A status criteria for your customers collection. (Possible values: Confirmed = created an account, Unconfirmed = checked out as guests)
     *  - `email` (string) The email of the customer who placed the order
     *  - `name` (string) The name of the customer who placed the order
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getCustomersItems($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getCustomers('items', $options, $expires, $forceRefresh);
    }
    
    /**
     * Get all orders of a customer from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the customer [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getCustomersOrders($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_customer_id'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCustomersOrders . '.' . md5($id);
        
        $url = \ProcessWire\wirePopulateStringTags(
            self::apiEndpoint . self::resPathCustomersOrders,
            ['id' => $id]
        );
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($url) {
            return $this->getJSON($url);
        });
        
        $dataKey = self::resPathCustomersOrders;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get a single customer from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the customer to be returned [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getCustomer($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_customer_id'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixCustomersDetail . '.' . md5($id);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathCustomers . '/' . $id);
        });
        
        $dataKey = self::resPathCustomers . '/' . $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get all products from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0]
     *  - `limit` (int) Number of results to fetch. [default = 20]
     *  - `userDefinedId` string The custom product ID
     *  - `keywords` string A keyword to search for
     *  - `archived` boolean (as string) "true" or "false" (undocumented!)
     *  - `excludeZeroSales`  boolean (as string) "true" or "false"  (undocumented!)
     *  - `orderBy` string The order by key (undocumented!)
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getProducts($key = '', $options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $allowedOptions = ['offset', 'limit', 'userDefinedId', 'keywords', 'archived', 'excludeZeroSales', 'orderBy', 'from', 'to'];
        $defaultOptions = [
            'offset' => 0,
            'limit' => 20,
            'orderBy' => 'SalesValue',
        ];
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
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathProducts . $query);
        });
        $response = ($key && isset($response[$key])) ? $response[$key] : $response;

        $dataKey = self::resPathProducts;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get products items from Snipcart dashboard.
     *
     * @param string $key The array key to be returned
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `offset` (int) Number of results to skip. [default = 0]
     *  - `limit` (int) Number of results to fetch. [default = 20]
     *  - `userDefinedId` string The custom product ID
     *  - `archived` boolean (as string) "true" or "false" (undocumented!)
     *  - `excludeZeroSales`  boolean (as string) "true" or "false"  (undocumented!)
     *  - `orderBy` string The order by key (undocumented!)
     *  - `from` (datetime) Will return only the customers created after this date
     *  - `to` (datetime) Will return only the customers created before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     * 
     */
    public function getProductsItems($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        return $this->getProducts('items', $options, $expires, $forceRefresh);
    }
    
    /**
     * Get a single product from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the product to be returned [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getProduct($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_product_id'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixProductsDetail . '.' . md5($id);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathProducts . '/' . $id);
        });
        
        $dataKey = self::resPathProducts . '/' . $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get the id of a Snipcart product by it's userDefinedId.
     *
     * @param string $userDefinedId The user defined id of a product (SKU) [#required]
     * @return boolean|string $id The Snipcart product id or false if not found or something went wrong
     *
     */
    public function getProductId($userDefinedId) {
        if (!$userDefinedId) {
            $this->error(self::getMessagesText('no_userdefined_id'));
            return false;
        }
        $options = [
            'offset' => 0,
            'limit' => 1,
            'orderBy' => '',
            'userDefinedId' => $userDefinedId,
        ];
        // Get a specific item
        $data = $this->getProductsItems($options, WireCache::expireNow); // Get uncached result
        
        $dataKey = self::resPathProducts;
        if ($data[$dataKey][WireHttpExtended::resultKeyHttpCode] == 200) {
            $id = $data[$dataKey][WireHttpExtended::resultKeyContent][0]['id'];
        } else {
            $id = false;
        }
        return $id;
    }
    
    /**
     * Fetch the URL passed in parameter and generate product(s) found on this page.
     *
     * @param string $fetchUrl The URL of the page to be fetched [#required]
     * @return array $data
     * 
     */
    public function postProduct($fetchUrl) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$fetchUrl) {
            $this->error(self::getMessagesText('no_product_url'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $options = [
            'fetchUrl' => $fetchUrl,
        ];
        
        $url = self::apiEndpoint . self::resPathProducts;
        $requestbody = \ProcessWire\wireEncodeJSON($options);
        
        $response = json_decode($this->send($url, $requestbody, 'POST'), true);
        
        $dataKey = $fetchUrl;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Update a specific product.
     *
     * @param string $id The Snipcart id of the product to be updated [#required]
     * @param array $options An array of options that will be sent as POST params:
     *  - `inventoryManagementMethod` (string) Specifies how inventory should be tracked for this product. (Possible values: Single, Variant)
     *  - `variants` (array) Allows to set stock per product variant
     *  - `stock` (integer) The number of items in stock. (Will be used when `inventoryManagementMethod` = Single)
     *  - `allowOutOfStockPurchases` (boolean) Allow out-of-stock purchase.
     * @return array $data
     * 
     */
    public function putProduct($id, $options = []) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_product_id'));
            return false;
        }
        // Add necessary header for PUT request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $allowedOptions = ['inventoryManagementMethod', 'variants', 'stock', 'allowOutOfStockPurchases'];
        $defaultOptions = [];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = self::apiEndpoint . self::resPathProducts . '/' . $id;
        $requestbody = \ProcessWire\wireEncodeJSON($options);

        $response = json_decode($this->send($url, $requestbody, 'PUT'), true);

        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Delete a specific product.
     * (the product isn't actually deleted, but it's "archived" flag is set to true)
     *
     * @param string $id The Snipcart id of the product to be deleted (archived) [#required]
     * @return array $data
     * 
     */
    public function deleteProduct($id) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_product_id'));
            return false;
        }
        // Add necessary header for DELETE request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $url = self::apiEndpoint . self::resPathProducts . '/' . $id;
        
        $response = json_decode($this->send($url, [], 'DELETE'), true);
        
        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get discounts from Snipcart dashboard as array.
     *
     * The Snipcart API has no pagination in this case!
     * The Snipcart API has no options for query!
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getDiscounts($expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixDiscounts;
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() {
            return $this->getJSON(self::apiEndpoint . self::resPathDiscounts);
        });
        
        $dataKey = self::resPathDiscounts;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get a single discount from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param string $id The Snipcart id of the discount to be returned [#required]
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getDiscount($id, $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_discount_id'));
            return false;
        }
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixDiscountsDetail . '.' . md5($id);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($id) {
            return $this->getJSON(self::apiEndpoint . self::resPathDiscounts . '/' . $id);
        });
        
        $dataKey = self::resPathDiscounts . '/' . $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Updates a Snipcart discount.
     *
     * @param string $id The Snipcart id of the discount [#required]
     * @param array $options An array of options that will be sent as PUT params:
     *  - `id` (string) The Snipcart id of the discount
     *  - `name` (string) The discount friendly name
     *  - `expires` (date) The date when this discount should expire
     *  - `maxNumberOfUsages` (integer) The max. number of usages for the discount / if null, discount never expires
     *  - `currency` (string) The currency for amounts
     *  - `combinable` (boolean) Whether the discount should be combinable with other discounts
     *  - `type` (string) The type of action that the discount will apply (Possible values: FixedAmount, Rate, AlternatePrice, Shipping, FixedAmountOnItems, RateOnItems, FixedAmountOnCategory, RateOnCategory, GetFreeItems, AmountOnSubscription, RateOnSubscription)
     *  - `amount` (decimal) The amount that will be deducted from order total
     *  - `rate` (decimal) The rate in percentage that will be deducted from order total
     *  - `alternatePrice` (string) The name of the alternate price list to use
     *  - `shippingDescription` (string) The shipping method name that will be displayed to your customers
     *  - `shippingCost` (decimal) The shipping amount that will be available to your customers
     *  - `shippingGuaranteedDaysToDelivery` (integer) The number of days it will take for shipping
     *  - `productIds` (string) Comma separated list of product IDs (SKUs) #required if "trigger" is "QuantityOfAProduct" and "onlyOnSameProducts" is "true"
     *  - `maxDiscountsPerItem` (integer)
     *  - `categories` (string) Comma separated list of product categories
     *  - `numberOfItemsRequired` (integer) Number of items required #required if "type" is "GetFreeItems"
     *  - `numberOfFreeItems` (integer) Number of free items #required if "type" is "GetFreeItems"
     *  - `trigger` (string) Condition that will trigger the discount #required (Possible values: Code, Product, Total, QuantityOfAProduct, CartContainsOnlySpecifiedProducts, CartContainsSomeSpecifiedProducts, CartContainsAtLeastAllSpecifiedProducts)
     *  - `code` (string) The code that will need to be entered by the customer #required if "trigger" is "Code"
     *  - `itemId` (string) The unique ID (SKU) of your product 
     *  - `totalToReach` (decimal) The min. order amount
     *  - `maxAmountToReach` (decimal) The max. order amount
     *  - `quantityInterval` (boolean)
     *  - `quantityOfAProduct` (integer)
     *  - `maxQuantityOfAProduct` (integer)
     *  - `onlyOnSameProducts` (boolean)
     *  - `quantityOfProductIds` (string) Comma separated list of unique product IDs (SKUs)
     *  - `archived` (boolean) Whether the discount is archived or not
     * @return array $data
     * 
     */
    public function putDiscount($id, array $options) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_discount_id'));
            return false;
        }
        // Add necessary header for PUT request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $allowedOptions = [
            'id', 'name', 'expires', 'maxNumberOfUsages', 'currency', 'combinable',
            'type', 'amount', 'rate', 'alternatePrice', 'shippingDescription', 'shippingCost',
            'shippingGuaranteedDaysToDelivery', 'productIds', 'maxDiscountsPerItem', 'categories',
            'numberOfItemsRequired', 'numberOfFreeItems', 'trigger', 'code', 'itemId', 'totalToReach',
            'maxAmountToReach', 'quantityInterval', 'quantityOfAProduct', 'maxQuantityOfAProduct',
            'onlyOnSameProducts', 'quantityOfProductIds', 'archived',
        ];
        $defaultOptions = [];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = self::apiEndpoint . self::resPathDiscounts. '/' . $id;
        $requestbody = \ProcessWire\wireEncodeJSON($options, true);
        
        $response = $this->send($url, $requestbody, 'PUT');
        
        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => $response,
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Creates a Snipcart discount.
     *
     * @param string $id The Snipcart id of the discount
     * @param array $options An array of options that will be sent as PUT params: [#required]
     *  - `name` (string) The discount friendly name
     *  - `expires` (date) The date when this discount should expire
     *  - `maxNumberOfUsages` (integer) The max. number of usages for the discount / if null, discount never expires
     *  - `currency` (string) The currency for amounts
     *  - `combinable` (boolean) Whether the discount should be combinable with other discounts
     *  - `type` (string) The type of action that the discount will apply (Possible values: FixedAmount, Rate, AlternatePrice, Shipping, FixedAmountOnItems, RateOnItems, FixedAmountOnCategory, RateOnCategory, GetFreeItems, AmountOnSubscription, RateOnSubscription)
     *  - `amount` (decimal) The amount that will be deducted from order total
     *  - `rate` (decimal) The rate in percentage that will be deducted from order total
     *  - `alternatePrice` (string) The name of the alternate price list to use
     *  - `shippingDescription` (string) The shipping method name that will be displayed to your customers
     *  - `shippingCost` (decimal) The shipping amount that will be available to your customers
     *  - `shippingGuaranteedDaysToDelivery` (integer) The number of days it will take for shipping
     *  - `productIds` (string) Comma separated list of product IDs (SKUs) #required if "trigger" is "QuantityOfAProduct" and "onlyOnSameProducts" is "true"
     *  - `maxDiscountsPerItem` (integer)
     *  - `categories` (string) Comma separated list of product categories
     *  - `numberOfItemsRequired` (integer) Number of items required #required if "type" is "GetFreeItems"
     *  - `numberOfFreeItems` (integer) Number of free items #required if "type" is "GetFreeItems"
     *  - `trigger` (string) Condition that will trigger the discount #required (Possible values: Code, Product, Total, QuantityOfAProduct, CartContainsOnlySpecifiedProducts, CartContainsSomeSpecifiedProducts, CartContainsAtLeastAllSpecifiedProducts)
     *  - `code` (string) The code that will need to be entered by the customer #required if "trigger" is "Code"
     *  - `itemId` (string) The unique ID (SKU) of your product 
     *  - `totalToReach` (decimal) The min. order amount
     *  - `maxAmountToReach` (decimal) The max. order amount
     *  - `quantityInterval` (boolean)
     *  - `quantityOfAProduct` (integer)
     *  - `maxQuantityOfAProduct` (integer)
     *  - `onlyOnSameProducts` (boolean)
     *  - `quantityOfProductIds` (string) Comma separated list of unique product IDs (SKUs)
     *  - `archived` (boolean) Whether the discount is archived or not
     * @return array $data
     * 
     */
    public function postDiscount(array $options) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        // Add necessary header for POST request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $allowedOptions = [
            'name', 'expires', 'maxNumberOfUsages', 'currency', 'combinable',
            'type', 'amount', 'rate', 'alternatePrice', 'shippingDescription', 'shippingCost',
            'shippingGuaranteedDaysToDelivery', 'productIds', 'maxDiscountsPerItem', 'categories',
            'numberOfItemsRequired', 'numberOfFreeItems', 'trigger', 'code', 'itemId', 'totalToReach',
            'maxAmountToReach', 'quantityInterval', 'quantityOfAProduct', 'maxQuantityOfAProduct',
            'onlyOnSameProducts', 'quantityOfProductIds', 'archived',
        ];
        $defaultOptions = [];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        
        $url = self::apiEndpoint . self::resPathDiscounts;
        $requestbody = \ProcessWire\wireEncodeJSON($options, true);
        
        $response = $this->send($url, $requestbody, 'POST');
        
        $data = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Deletes a Snipcart discount.
     *
     * @param string $id The Snipcart id of the discount [#required]
     * @return array $data
     * 
     */
    public function deleteDiscount($id) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        if (!$id) {
            $this->error(self::getMessagesText('no_discount_id'));
            return false;
        }
        // Add necessary header for PUT request
		$this->setHeader('content-type', 'application/json; charset=utf-8');
        
        $url = self::apiEndpoint . self::resPathDiscounts . '/' . $id;
        
        $response = json_decode($this->send($url, [], 'DELETE'), true);
        
        $dataKey = $id;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get the store performance from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the performance after this date
     *  - `to` (datetime) Will return only the performance before this date
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getPerformance($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $allowedOptions = ['from', 'to'];
        $defaultOptions = [];
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
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathDataPerformance . $query);
        });
        
        $dataKey = self::resPathDataPerformance;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get the sales (amount of sales by day) from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the sales after this date
     *  - `to` (datetime) Will return only the sales before this date
     *  - `currency` (string) Will return only sales with this currency
     * @param mixed $expires Lifetime of this cache, in seconds
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getSalesCount($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $allowedOptions = ['from', 'to', 'currency'];
        $defaultOptions = [];
        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        $query = '';
        if (!empty($options)) $query = '?' . http_build_query($options);
        
        // Segmented cache (each query is cached self-contained)
        $cacheName = self::cacheNamePrefixOrdersSales . '.' . md5($query);
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathDataOrdersSales . $query);
        });
        
        $dataKey = self::resPathDataOrdersSales;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Get the order counts (number of orders by days) from Snipcart dashboard as array.
     *
     * Uses WireCache to prevent reloading Snipcart data on each request.
     *
     * @param array $options An array of filter options that will be sent as URL params:
     *  - `from` (datetime) Will return only the order counts after this date
     *  - `to` (datetime) Will return only the order counts before this date
     *  - `currency` (string) Will return only order counts with this currency
     * @param mixed $expires Lifetime of this cache
     * @param boolean $forceRefresh Wether to refresh this cache
     * @return array $data
     *
     */
    public function getOrdersCount($options = [], $expires = self::cacheExpireDefault, $forceRefresh = false) {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        
        $allowedOptions = ['from', 'to', 'currency'];
        $defaultOptions = [];
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
        
        if ($forceRefresh) $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        
        // Try to get array from cache first
        $response = $this->wire('cache')->getFor(self::cacheNamespace, $cacheName, $expires, function() use($query) {
            return $this->getJSON(self::apiEndpoint . self::resPathDataOrdersCount . $query);
        });
        
        $dataKey = self::resPathDataOrdersCount;
        $data[$dataKey] = [
            WireHttpExtended::resultKeyContent => ($response !== false) ? $response : [],
            WireHttpExtended::resultKeyHttpCode => $this->getHttpCode(),
            WireHttpExtended::resultKeyError => $this->getError(),
        ];
        return $data;
    }
    
    /**
     * Snipcart REST API connection test.
     * (uses resPathSettingsDomain for test request)
     *
     * @return mixed $status True on success or string of status code on error
     * 
     */
    public function testConnection() {
        if (!$this->getHeaders()) {
            $this->error(self::getMessagesText('no_headers'));
            return false;
        }
        return ($this->get(self::apiEndpoint . self::resPathSettingsDomain)) ? true : $this->getError();
    }
    
    /**
     * Delete the full Snipcart cache for all sections.
     *
     * @return boolean
     * 
     */
    public function deleteFullCache() {
        return $this->wire('cache')->deleteFor(self::cacheNamespace);
    }
    
    /**
     * Delete a single or the full order cache (WireCache).
     *
     * @param string $token The Snipcart $token of the order (if no token provided, the full order cache is deleted)
     * @return void
     *
     */
    public function deleteOrderCache($token = '') {
        if (!$token) {
            $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixOrders . '*');
        } else {
            $cacheName = self::cacheNamePrefixOrdersDetail . '.' . md5($token);
            $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
            
            $cacheName = self::cacheNamePrefixOrdersNotifications . '.' . md5($token);
            $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        }
    }
    
    /**
     * Delete a single or the full subscription cache (WireCache).
     *
     * @param string $id The Snipcart $id of the subscription (if no id provided, the full subscription cache is deleted)
     * @return void
     *
     */
    public function deleteSubscriptionCache($id = '') {
        if (!$id) {
            $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixSubscriptions . '*');
        } else {
            $cacheName = self::cacheNamePrefixSubscriptionsDetail . '.' . md5($id);
            $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        }
    }
    
    /**
     * Delete a single or the full abandoned cart cache (WireCache).
     *
     * @param string $id The Snipcart $id of the abandoned cart (if no id provided, the full abandoned cart cache is deleted)
     * @return void
     *
     */
    public function deleteAbandonedCartsCache($id = '') {
        if (!$id) {
            $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixCartsAbandoned . '*');
        } else {
            $cacheName = self::cacheNamePrefixCartsAbandonedDetail . '.' . md5($id);
            $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
            
            $cacheName = self::cacheNamePrefixCartsAbandonedNotifications . '.' . md5($id);
            $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        }
    }
    
    /**
     * Delete a single or the full discount cache (WireCache).
     *
     * @param string $id The Snipcart $id of the discount (if no id provided, the full discount cache is deleted)
     * @return void
     *
     */
    public function deleteDiscountCache($id = '') {
        if (!$id) {
            $this->wire('cache')->deleteFor(self::cacheNamespace, self::cacheNamePrefixDiscounts . '*');
        } else {
            $cacheName = self::cacheNamePrefixDiscountsDetail . '.' . md5($id);
            $this->wire('cache')->deleteFor(self::cacheNamespace, $cacheName);
        }
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

<?php namespace ProcessWire;

/**
 * Webhooks - service class for SnipWire to provide webhooks for Snipcart.
 * (This file is part of the SnipWire package)
 *
 * Replaces the ProcessWire page rendering as a whole. It will only accept 
 * POST request from Snipcart. 
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'Taxes.php';

class Webhooks extends WireData {

    const snipWireWebhooksLogName = 'snipwire-webhooks';
    const snipcartRequestTokenServerVar = 'HTTP_X_SNIPCART_REQUESTTOKEN';
    
    // Snipcart webhook events
    const webhookOrderCompleted = 'order.completed';
    const webhookOrderStatusChanged = 'order.status.changed';
    const webhookOrderPaymentStatusChanged = 'order.paymentStatus.changed';
    const webhookOrderTrackingNumberChanged = 'order.trackingNumber.changed';
    const webhookSubscriptionCreated = 'subscription.created';
    const webhookSubscriptionCancelled = 'subscription.cancelled';
    const webhookSubscriptionPaused = 'subscription.paused';
    const webhookSubscriptionResumed = 'subscription.resumed';
    const webhookSubscriptionInvoiceCreated = 'subscription.invoice.created';
    const webhookShippingratesFetch = 'shippingrates.fetch';
    const webhookTaxesCalculate = 'taxes.calculate';
    const webhookCustomerUpdated = 'customauth:customer_updated'; // not documented
    
    const webhookModeLive = 'Live';
    const webhookModeTest = 'Test';

    /** @var boolean Turn on/off debug mode for Webhooks class */
    private $debug = false;
    
    /** @var string $serverProtocol The server protocol (e.g. HTTP/1.1) */
    protected $serverProtocol = '';
    
    /** @var array $webhookEventsIndex All available webhook events */
    protected $webhookEventsIndex = array();
    
    /** @var string $event The current Snipcart event */
    protected $event = '';
    
    /** @var array $payload The current JSON decoded POST input */
    protected $payload = null;
    
    /** @var integer $responseStatus The response status code for SnipCart */
    private $responseStatus = null;
    
    /** @var string (JSON) $responseBody The JSON formatted response array for Snipcart */
    private $responseBody = '';
    
    /**
     * Set class properties and default header.
     *
     */
    public function __construct() {
        $this->serverProtocol = $_SERVER['SERVER_PROTOCOL'];
        
        $this->webhookEventsIndex = array(
            self::webhookOrderCompleted => 'handleOrderCompleted',
            self::webhookOrderStatusChanged => 'handleOrderStatusChanged',
            self::webhookOrderPaymentStatusChanged => 'handleOrderPaymentStatusChanged',
            self::webhookOrderTrackingNumberChanged => 'handleOrderTrackingNumberChanged',
            self::webhookSubscriptionCreated => 'handleSubscriptionCreated',
            self::webhookSubscriptionCancelled => 'handleSubscriptionCancelled',
            self::webhookSubscriptionPaused => 'handleSubscriptionPaused',
            self::webhookSubscriptionResumed => 'handleSubscriptionResumed',
            self::webhookSubscriptionInvoiceCreated => 'handleSubscriptionInvoiceCreated',
            self::webhookShippingratesFetch => 'handleShippingratesFetch',
            self::webhookTaxesCalculate => 'handleTaxesCalculate',
            self::webhookCustomerUpdated => 'handleCustomerUpdated',
        );

        // Get SnipWire module config.
        // (Holds merged data from DB and default config. 
        // This works because of using the ModuleConfig class)
        $snipwireConfig = $this->wire('modules')->get('SnipWire');
        $this->debug = $snipwireConfig->snipwire_debug;

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }

    /**
     * Process webhooks requests.
     *
     * @return void
     *
     */
    public function process() {
        $sniprest = $this->wire('sniprest');
        $log = $this->wire('log');

        if (!$this->_isValidRequest()) {
            // 404 Not Found
            header($this->serverProtocol . ' ' . $sniprest->getHttpStatusCodeString(404));
            return;
        }
        if (!$this->_hasValidRequestData()) {
            // 400 Bad Request 
            header($this->serverProtocol . ' ' . $sniprest->getHttpStatusCodeString(400));
            return;
        }
        $response = $this->_handleWebhookData();
        header($this->serverProtocol . ' ' . $sniprest->getHttpStatusCodeString($this->responseStatus));
        if (!empty($this->responseBody)) {
            header('Content-Type: application/json; charset=utf-8');
            echo $this->responseBody;
        }
        
        if ($this->debug) {
            $log->save(
                self::snipWireWebhooksLogName,
                '[DEBUG] Webhooks request success: responseStatus = ' . $this->responseStatus
            );
            $log->save(
                self::snipWireWebhooksLogName,
                '[DEBUG] Webhooks request success: responseBody = ' . $this->responseBody
            );
        }
    }

    /**
     * Getter for responseStatus.
     *
     * @return integer The current response status code
     *
     */
    public function getResponseStatus() {
        return $this->responseStatus;
    }

    /**
     * Getter for responseBody.
     *
     * @return string The current response body (JSON formatted)
     *
     */
    public function getResponseBody() {
        return $this->responseBody;
    }

    /**
     * Validate a Snipcart webhook endpoint request.
     * - check request method and content type
     * - check the request token (= handshake)
     *
     * @return boolean
     *
     */
    private function _isValidRequest() {
        $sniprest = $this->wire('sniprest');
        $log = $this->wire('log');

        // Perform multiple checks for valid request
        if (
            $this->getServerVar('REQUEST_METHOD') != 'POST' || 
            stripos($this->getServerVar('CONTENT_TYPE'), 'application/json') === false
        ) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Invalid webhooks request: no POST data or content not json'
            );
            return false;
        }
        if (($requestToken = $this->getServerVar(self::snipcartRequestTokenServerVar)) === false) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Invalid webhooks request: no request token'
            );
            return false;
        }
        $handshakeUrl = $sniprest::apiEndpoint . $sniprest::resPathRequestValidation . DIRECTORY_SEPARATOR . $requestToken;
        if (($handshake = $sniprest->get($handshakeUrl)) === false) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Snipcart REST connection for checking request token failed: ' . $sniprest->getError()
            );
            return false;
        }
        if (empty($handshake) || $sniprest->getHttpCode(false) != 200) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Invalid webhooks request: no response'
            );
            return false;
        }
        $json = json_decode($handshake, true);
        if (!$json) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Invalid webhooks request: response not json'
            );
            return false;
        }
        if (!isset($json['token']) || $json['token'] !== $requestToken) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Invalid webhooks request: invalid token'
            );
            return false;
        }
        return true;  
    }

    /**
     * Check if request has valid data and set $payload and $event class properties if OK.
     *
     * @return boolean
     *
     */
    private function _hasValidRequestData() {
        $log = $this->wire('log');
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);
        
        // Perform multiple checks for valid request data
        $check = false;
        if (is_null($payload) || !is_array($payload)) {
            $log->save(
                self::snipWireWebhooksLogName, 
                'Webhooks request: invalid request data - not an array'
            );
        
        } elseif (!isset($payload['eventName'])) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - key eventName missing'
            );
            
        } elseif (!array_key_exists($payload['eventName'], $this->webhookEventsIndex)) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - unknown event'
            );
            
        } elseif (!isset($payload['mode']) || !in_array($payload['mode'], array(self::webhookModeLive, self::webhookModeTest))) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - wrong or missing mode'
            );
            
        } elseif (!isset($payload['content'])) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - missing content'
            );
            
        } else {
            $this->event = $payload['eventName'];
            $this->payload = $payload;
            $check = true;
        }
        return $check;
    }

    /**
     * Route the request to the appropriate handler method.
     *
     */
    private function _handleWebhookData() {
        $log = $this->wire('log');
        
        if (empty($this->event)) {
            $log->save(
                self::snipWireWebhooksLogName,
                '_handleWebhookData: $this->event not set'
            );
            $this->responseStatus = 500; // Internal Server Error
            return;
        }
        $methodName = $this->webhookEventsIndex[$this->event];
        if (!method_exists($this, '___' . $methodName)) {
            $log->save(
                self::snipWireWebhooksLogName,
                '_handleWebhookData: method does not exist ' . $methodName
            );
            $this->responseStatus = 500; // Internal Server Error
            return;
        }
        
        // Call the appropriate handler
        $this->{$methodName}();
    }

    /**
     * Webhook handler for order completed.
     *
     */
    public function ___handleOrderCompleted() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleOrderCompleted'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for order status changed.
     *
     */
    public function ___handleOrderStatusChanged() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleOrderStatusChanged'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for payment status changed.
     *
     */
    public function ___handleOrderPaymentStatusChanged() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleOrderPaymentStatusChanged'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for tracking number changed.
     *
     */
    public function ___handleOrderTrackingNumberChanged() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleOrderTrackingNumberChanged'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for subscription created.
     *
     */
    public function ___handleSubscriptionCreated() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleSubscriptionCreated'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for subscription cancelled.
     *
     */
    public function ___handleSubscriptionCancelled() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleSubscriptionCancelled'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for subscription paused.
     *
     */
    public function ___handleSubscriptionPaused() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleSubscriptionPaused'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for subscription resumed.
     *
     */
    public function ___handleSubscriptionResumed() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleSubscriptionResumed'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for subscription invoice created.
     *
     */
    public function ___handleSubscriptionInvoiceCreated() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleSubscriptionInvoiceCreated'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for shipping rates fetching.
     *
     */
    public function ___handleShippingratesFetch() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleShippingratesFetch'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Webhook handler for taxes calculation.
     *
     */
    public function ___handleTaxesCalculate() {        
        $log = $this->wire('log');
        
        if ($this->debug) $log->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleTaxesCalculate'
        );
        
        // Sample payload array: https://docs.snipcart.com/webhooks/taxes
        
        $payload = $this->payload;
        $content = $payload['content'];
        $items = isset($content['items']) ? $content['items'] : null;
        
        if (!$items) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data for taxes calculation - missing items'
            );
            $this->responseStatus = 400; // Bad Request
            return;
        }

        // Collect all taxes and calculate total prices from payload
        $itemTaxes = array();
        foreach ($items as $item) {
            if (!$item['taxable']) continue;
            $taxName = $item['taxes'][0]; // we currently only support a single tax per product!
            if (!isset($itemTaxes[$taxName])) {
                $itemTaxes[$taxName] = $item['totalPriceWithoutTaxes'];
            } else {
                $itemTaxes[$taxName] += $item['totalPriceWithoutTaxes'];
            }
        }
        
        $hasTaxesIncluded = Taxes::getTaxesIncludedConfig();

        // Generate taxes response array for Snipcart
        $taxesResponse = array();
        foreach ($itemTaxes as $name => $value) {
            $taxesConfig = Taxes::getTaxesConfig(false, Taxes::taxesTypeProducts, $name);
            $taxesResponse[] = array(
                'name' => $name,
                'amount' => Taxes::calculateTax($value, $taxesConfig['rate'], $hasTaxesIncluded, 2),
                'rate' => $taxesConfig['rate'],
                'numberForInvoice' => $taxesConfig['numberForInvoice'],
                'includedInPrice' => $hasTaxesIncluded,
            );
        }
        $taxes = array('taxes' => $taxesResponse);
        
        $this->responseStatus = 202; // Accepted
        $this->responseBody = wireEncodeJSON($taxes, true);
    }

    /**
     * Webhook handler for customer updated.
     *
     */
    public function ___handleCustomerUpdated() {
        if ($this->debug) $this->wire('log')->save(
            self::snipWireWebhooksLogName,
            '[DEBUG] Webhooks request: handleCustomerUpdated'
        );
        $this->responseStatus = 202; // Accepted
    }

    /**
     * Get PHP server and execution environment information from superglobal $_SERVER
     *
     * @param string $var The required key
     * @return string|boolean Returns value of $_SEREVER key or false if not exists
     *
     * (This could return an empty string so needs to checked with === false)
     *
     */
    public function getServerVar($var) {
        return isset($_SERVER[$var]) ? $_SERVER[$var] : false;
    }
    
}

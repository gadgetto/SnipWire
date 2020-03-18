<?php
namespace SnipWire\Services;

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

require_once dirname(__DIR__) . '/helpers/CurrencyFormat.php';
require_once dirname(__DIR__) . '/helpers/Taxes.php';

use SnipWire\Helpers\CurrencyFormat;
use SnipWire\Helpers\Taxes;
use ProcessWire\WireData;
use ProcessWire\WireException;

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

    /** @var array $snipwireConfig The module config of SnipWire module */
    protected $snipwireConfig = array();

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
     * Set class properties.
     *
     */
    public function __construct() {        
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
        $this->snipwireConfig = $this->wire('modules')->get('SnipWire');
        $this->debug = $this->snipwireConfig->snipwire_debug;
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
        
        // Set default header
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');

        $this->serverProtocol = $_SERVER['SERVER_PROTOCOL'];

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
                $this->_('Invalid webhooks request: no POST data or content not json')
            );
            return false;
        }
        if (($requestToken = $this->getServerVar(self::snipcartRequestTokenServerVar)) === false) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Invalid webhooks request: no request token')
            );
            return false;
        }
        $handshakeUrl = $sniprest::apiEndpoint . $sniprest::resPathRequestValidation . '/' . $requestToken;
        if (($handshake = $sniprest->get($handshakeUrl)) === false) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Snipcart REST connection for checking request token failed:') . ' ' . $sniprest->getError()
            );
            return false;
        }
        if (empty($handshake) || $sniprest->getHttpCode(false) != 200) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Invalid webhooks request: no response')
            );
            return false;
        }
        $json = json_decode($handshake, true);
        if (!$json) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Invalid webhooks request: response not json')
            );
            return false;
        }
        if (!isset($json['token']) || $json['token'] !== $requestToken) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Invalid webhooks request: invalid token')
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
                $this->_('Webhooks request: invalid request data - not an array')
            );
        
        } elseif (!isset($payload['eventName'])) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Webhooks request: invalid request data - key eventName missing')
            );
            
        } elseif (!array_key_exists($payload['eventName'], $this->webhookEventsIndex)) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Webhooks request: invalid request data - unknown event')
            );
            
        } elseif (!isset($payload['mode']) || !in_array($payload['mode'], array(self::webhookModeLive, self::webhookModeTest))) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Webhooks request: invalid request data - wrong or missing mode')
            );
            
        } elseif (!isset($payload['content'])) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Webhooks request: invalid request data - missing content')
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
                $this->_('_handleWebhookData: $this->event not set')
            );
            $this->responseStatus = 500; // Internal Server Error
            return;
        }
        $methodName = $this->webhookEventsIndex[$this->event];
        if (!method_exists($this, '___' . $methodName)) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('_handleWebhookData: method does not exist') . ' ' . $methodName
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

        // No taxes handling if taxes provider is other then "integrated"
        if ($this->snipwireConfig->taxes_provider != 'integrated') {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Webhooks request: handleTaxesCalculate - the integrated taxes provider is disabled in module settings')
            );
            $this->responseStatus = 204; // No Content
            return;
        }

        // Sample payload array: https://docs.snipcart.com/webhooks/taxes
        
        $payload = $this->payload;
        $content = isset($payload['content']) ? $payload['content'] : null;
        if ($content) {
            $items = isset($content['items']) ? $content['items'] : null;
            $shippingInformation = isset($content['shippingInformation']) ? $content['shippingInformation'] : null;
            $itemsTotal = isset($content['itemsTotal']) ? $content['itemsTotal'] : null;
            $currency = isset($content['currency']) ? $content['currency'] : null;
        }
        
        if (!$items || !$shippingInformation || !$itemsTotal || !$currency) {
            $log->save(
                self::snipWireWebhooksLogName,
                $this->_('Webhooks request: handleTaxesCalculate - invalid request data for taxes calculation')
            );
            $this->responseStatus = 400; // Bad Request
            return;
        }

        $hasTaxesIncluded = Taxes::getTaxesIncludedConfig();
        $shippingTaxesType = Taxes::getShippingTaxesTypeConfig();
        $currencyPrecision = CurrencyFormat::getCurrencyDefinition($currency, 'precision');
        if (!$currencyPrecision) $currencyPrecision = 2;

        $taxNamePrefix = $hasTaxesIncluded
            ? $this->_('incl.') // Tax name prefix if taxes included in price (keep it short)
            : '+';
        $taxNamePrefix .= ' ';

        // Collect and group all tax names and total prices (before taxes) from items in payload
        $itemTaxes = array();
        foreach ($items as $item) {
            if (!$item['taxable']) continue;
            $taxName = $item['taxes'][0]; // we currently only support a single tax per product!
            if (!isset($itemTaxes[$taxName])) {
                // add new array entry
                $itemTaxes[$taxName] = array(
                    'sumPrices' => $item['totalPriceWithoutTaxes'],
                    'splitRatio' => 0, // is calculatet later
                );
            } else {
                // add price to existing sumPrices
                $itemTaxes[$taxName]['sumPrices'] += $item['totalPriceWithoutTaxes'];
            }
        }

        // Calculate and add proportional ratio (for splittet shipping tax calculation)
        foreach ($itemTaxes as $name => $values) {
            $itemTaxes[$name]['splitRatio'] = round(($values['sumPrices'] / $itemsTotal), 2); // e.g. 0.35 = 35%
        }
        unset($name, $values);

        /*
        Results in $itemTaxes (sample) array:
        
        array(
            '20% VAT' => array(
                "sumPrices' => 300
                'splitRatio' => 0.67
            )
            '10% VAT' => array(
                'sumPrices' => 150
                'splitRatio' => 0.33
            )
        )
        
        Sample splitRatio calculation: 300 / (300 + 150) = 0.67 = 67%
        */

        //
        // Prepare item & shipping taxes response
        //

        $taxesResponse = array();
        $taxConfigMax = array();
        $maxRate = 0;

        foreach ($itemTaxes as $name => $values) {
            $taxConfig = Taxes::getTaxesConfig(false, Taxes::taxesTypeProducts, $name);
            if (!empty($taxConfig)) {
                $taxesResponse[] = array(
                    'name' => $taxNamePrefix . $name,
                    'amount' => Taxes::calculateTax($values['sumPrices'], $taxConfig['rate'], $hasTaxesIncluded, $currencyPrecision),
                    'rate' => $taxConfig['rate'],
                    'numberForInvoice' => $taxConfig['numberForInvoice'],
                    'includedInPrice' => $hasTaxesIncluded,
                    //'appliesOnShipping' // not needed,
                );

                // Get tax config with highest rate (for shipping tax calculation)
                if ($shippingTaxesType == Taxes::shippingTaxesHighestRate) {
                    if ($taxConfig['rate'] > $maxRate) {
                        $maxRate = $taxConfig['rate'];
                        $taxConfigMax = $taxConfig;
                    }
                }
            }
        }
        unset($name, $values);

        if ($shippingTaxesType != Taxes::shippingTaxesNone) {
            $shippingFees = isset($shippingInformation['fees'])
                ? $shippingInformation['fees']
                : 0;
            $shippingMethod = isset($shippingInformation['method'])
                ? ' (' . $shippingInformation['method'] . ')'
                : '';

            if ($shippingFees > 0) {
                switch ($shippingTaxesType) {
                    case Taxes::shippingTaxesFixedRate :
                        $taxConfig = Taxes::getFirstTax(false, Taxes::taxesTypeShipping);
                        if (!empty($taxConfig)) {
                            $taxesResponse[] = array(
                                'name' => $taxNamePrefix . $taxConfig['name'] . $shippingMethod,
                                'amount' => Taxes::calculateTax($shippingFees, $taxConfig['rate'], $hasTaxesIncluded, $currencyPrecision),
                                'rate' => $taxConfig['rate'],
                                'numberForInvoice' => $taxConfig['numberForInvoice'],
                                'includedInPrice' => $hasTaxesIncluded,
                                //'appliesOnShipping' // not needed,
                            );
                        }
                        break;

                    case Taxes::shippingTaxesHighestRate :
                        if (!empty($taxConfigMax)) {
                            $taxesResponse[] = array(
                                'name' => $taxNamePrefix . $taxConfigMax['name'] . $shippingMethod,
                                'amount' => Taxes::calculateTax($shippingFees, $taxConfigMax['rate'], $hasTaxesIncluded, $currencyPrecision),
                                'rate' => $taxConfigMax['rate'],
                                'numberForInvoice' => $taxConfigMax['numberForInvoice'],
                                'includedInPrice' => $hasTaxesIncluded,
                                //'appliesOnShipping' // not needed,
                            );
                        }
                        break;

                    case Taxes::shippingTaxesSplittedRate :
                        foreach ($itemTaxes as $name => $values) {
                            $shippingFeesSplit = round(($shippingFees * $values['splitRatio']), 2);
                            $taxConfig = Taxes::getTaxesConfig(false, Taxes::taxesTypeProducts, $name);
                            if (!empty($taxConfig)) {
                                $taxesResponse[] = array(
                                    'name' => $taxNamePrefix . $taxConfig['name'] . $shippingMethod,
                                    'amount' => Taxes::calculateTax($shippingFeesSplit, $taxConfig['rate'], $hasTaxesIncluded, $currencyPrecision),
                                    'rate' => $taxConfig['rate'],
                                    'numberForInvoice' => $taxConfig['numberForInvoice'],
                                    'includedInPrice' => $hasTaxesIncluded,
                                    //'appliesOnShipping' // not needed,
                                );
                            }
                        }
                        break;                
                }
            }
        }

        $taxes = array('taxes' => $taxesResponse);
        
        $this->responseStatus = 202; // Accepted
        $this->responseBody = \ProcessWire\wireEncodeJSON($taxes, true);
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

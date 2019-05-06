<?php namespace ProcessWire;

/**
 * Webhooks - helper class for SnipWire to provide webhooks for Snipcart.
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

	/** @var string $serverProtocol The server protocol (e.g. HTTP/1.1) */
	protected $serverProtocol = '';
	
	/** @var array $webhookEventsIndex All available webhook events */
	protected $webhookEventsIndex = array();
	
	/** @var string $event The current Snipcart event */
	protected $event = '';
	
	/** @var array $body The current Json decoded POST input */
	protected $body = null;
	
	/** @var string (Json) $response The Json response for Snipcart */
	protected $response = '';
	
	/**
	 * Set custom error and exception handlers.
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
		$snipREST = $this->wire('snipREST');

		if (!$this->_isValidRequest()) {
			// 404 Not Found
			header($this->serverProtocol . ' ' . $snipREST->getHttpStatusCodeString(404));
			return;
		}
		if (!$this->_hasValidRequestData()) {
			// 400 Bad Request 
			header($this->serverProtocol . ' ' . $snipREST->getHttpStatusCodeString(400));
			return;
		}
		$responseCode = $this->_handleWebhookData();
		header($this->serverProtocol . ' ' . $snipREST->getHttpStatusCodeString($responseCode));
		
		// debug
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request success: responseCode = ' . $responseCode);
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
		$snipREST = $this->wire('snipREST');
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
		$handshakeUrl = $snipREST::apiEndpoint . $snipREST::resourcePathRequestValidation . DIRECTORY_SEPARATOR . $requestToken;
		if (($handshake = $snipREST->get($handshakeUrl)) === false) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Snipcart REST connection for checking request token failed: ' . $snipREST->getError()
            );
            return false;
		}
		if (empty($handshake) || $snipREST->getHttpCode(false) != 200) {
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
	 * Check if request has valid data and set $body and $event class properties if OK.
	 *
	 * @return boolean
	 *
	 */
	private function _hasValidRequestData() {
		$log = $this->wire('log');
		$rawBody = file_get_contents('php://input');
		$body = json_decode($rawBody, true);
		
		// Perform multiple checks for valid request data
		$check = false;
		if (is_null($body) || !is_array($body)) {
            $log->save(
                self::snipWireWebhooksLogName, 
			    'Webhooks request: invalid request data - not an array'
            );
        
		} elseif (!isset($body['eventName'])) {
			$log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - key eventName missing'
            );
            
		} elseif (!array_key_exists($body['eventName'], $this->webhookEventsIndex)) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - unknown event'
            );
            
		} elseif (!isset($body['mode']) || !in_array($body['mode'], array(self::webhookModeLive, self::webhookModeTest))) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - wrong or missing mode'
            );
            
		} elseif (!isset($body['content'])) {
            $log->save(
                self::snipWireWebhooksLogName,
                'Webhooks request: invalid request data - missing content'
            );
            
		} else {
    		$this->event = $body['eventName'];
    		$this->body = $body;
    		$check = true;
		}
		return $check;
	}

	/**
	 * Route the request to the appropriate handler method.
	 *
	 * @return int HTTP response code
	 *
	 */
	private function _handleWebhookData() {
		$log = $this->wire('log');
		
		if (empty($this->event)) {
		    $log->save(
		        self::snipWireWebhooksLogName,
		        '_handleWebhookData: $this->event not set'
            );
    		return 500; // Internal Server Error
        }
		$methodName = $this->webhookEventsIndex[$this->event];
		if (!method_exists($this, '___' . $methodName)) {
		    $log->save(
		        self::snipWireWebhooksLogName,
		        '_handleWebhookData: method does not exist ' . $methodName
            );
    		return 500; // Internal Server Error
        }
		
		// Call the appropriate handler
		return $this->{$methodName}();
	}

	public function ___handleOrderCompleted() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleOrderCompleted');
		return 200; // OK
	}

	public function ___handleOrderStatusChanged() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleOrderStatusChanged');
		return 200; // OK
	}

	public function ___handleOrderPaymentStatusChanged() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleOrderPaymentStatusChanged');
		return 200; // OK
	}

	public function ___handleOrderTrackingNumberChanged() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleOrderTrackingNumberChanged');
		return 200; // OK
	}

	public function ___handleSubscriptionCreated() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleSubscriptionCreated');
		return 200; // OK
	}

	public function ___handleSubscriptionCancelled() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleSubscriptionCancelled');
		return 200; // OK
	}

	public function ___handleSubscriptionPaused() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleSubscriptionPaused');
		return 200; // OK
	}

	public function ___handleSubscriptionResumed() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleSubscriptionResumed');
		return 200; // OK
	}

	public function ___handleSubscriptionInvoiceCreated() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleSubscriptionInvoiceCreated');
		return 200; // OK
	}

	public function ___handleShippingratesFetch() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleShippingratesFetch');
		return 200; // OK
	}

	public function ___handleTaxesCalculate() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleTaxesCalculate');
		return 200; // OK
	}

	public function ___handleCustomerUpdated() {
		$this->wire('log')->save(self::snipWireWebhooksLogName, 'Webhooks request: handleCustomerUpdated');
		return 200; // OK
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

<?php namespace ProcessWire;

/**
 * Webhooks - helper class for SnipWire to provide webhooks for Snipcart.
 * (This file is part of the SnipWire package)
 *
 * Replaces the ProcessWire page rendering as a whole. It will only accept 
 * POST request from Snipcart and the response will always be Json. 
 * 
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2019 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 */

class Webhooks extends WireData {

    const snipWireWebhooksLogName = 'snipwire_webhooks';
    const snipcartRequestTokenServerVar = 'HTTP_X_SNIPCART_REQUESTTOKEN';
    
    protected $serverProtocol = '';
    
    /**
     * Set custom error and exception handlers.
     *
     */
    public function __construct() {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        $this->serverProtocol = $_SERVER['SERVER_PROTOCOL']; 
    }

    /**
     * Process all webhooks requests.
     *
     */
    public function process() {
        if (!$this->validateRequest()) {
            // When something goes wrong, force a 404 Not Found!
            header($this->serverProtocol . ' ' . $this->wire('snipREST')->getHttpStatusCodeString(404));
            return;
        }
        header('Content-type: application/json');

        $this->wire('log')->save(self::snipWireWebhooksLogName, 'Valid webhooks request from Snipcart');
        echo '{"foo":"bar"}';

        // handle all webhooks here...
        
        
        
    }

    /**
     * Secure the webhook endpoint by validating a Snipcart request based on request token (= handshake).
     *
     * @return boolean
     *
     */
    public function validateRequest() {
        $snipREST = $this->wire('snipREST');
        $log = $this->wire('log');
        
        if (!$snipREST->getHeaders()) return false;
        
        if (($requestToken = $this->getServerVar(self::snipcartRequestTokenServerVar)) === false) {
            $log->save(self::snipWireWebhooksLogName, 'Invalid webhooks request: no request token');
            return false;
        }
        $handshakeUrl = $snipREST::apiEndpoint . $snipREST::resourcePathRequestValidation . DIRECTORY_SEPARATOR . $requestToken;
        if (($handshake = $snipREST->get($handshakeUrl)) === false) {
            $log->save('Snipcart REST connection for checking request token failed: ' . $snipREST->getError());
            return false;
        }
        if (empty($handshake) || $snipREST->getHttpCode != 200) {
            $log->save('Invalid webhooks request: no response');
            return false;
        }
        $json = @json_decode($handshake);
        if ($json) {
            $log->save('Invalid webhooks request: response not json');
            return false;
        }
        if ($json->token !== $requestToken) {
            $log->save('Invalid webhooks request: invalid token');
            return false;
        }
        return true;  
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

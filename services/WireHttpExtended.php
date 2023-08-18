<?php

namespace SnipWire\Services;

/**
 * WireHttpExtended - service class (wrapper for WireHttp) that allows processing of multiple cURL handles asynchronously.
 * (This file is part of the SnipWire package)
 *
 * Licensed under MPL 2.0 (see LICENSE file provided with this package)
 * Copyright 2023 by Martin Gartner
 *
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 */

use ProcessWire\WireException;
use ProcessWire\WireHttp;

class WireHttpExtended extends WireHttp
{
    const resultKeyContent = 'content';
    const resultKeyHttpCode = 'http_code';
    const resultKeyError = 'error';

    /** @var array $_curlMultiOptions cURL options used for all sessions */
    private $_curlMultiOptions = [];

    /** @var array $_multiHandle The cURL multi handle */
    private $_multiHandle = null;

    /** @var array $_curlHandles The cURL handles */
    private $_curlHandles = [];

    /** @var array $resultsMulti Holds results from all requests */
    protected $resultsMulti = [];

    /**
     * Constructor/initialize.
     */
    public function __construct()
    {
        parent::__construct();

        // Set default options
        if ($this->hasCURL) $this->setCurlMultiOptions();
    }

    /**
     * Set cURL options to be used for all requests.
     *
     * @param array $options cURL options (will set default options if param not set)
     */
    public function setCurlMultiOptions($options = [])
    {
        $this->_curlMultiOptions = $this->sanitizeOptions($options);
    }

    /**
     * Get multi cURL options (used in all requests).
     *
     * @return array multi cURL options.
     */
    public function getCurlMultiOptions()
    {
        return $this->_curlMultiOptions;
    }

    /**
     * Sanitize a multi cURL options array.
     *
     * @param array $options cURL options array:
     *  - `connect_timeout` (float) Number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
     *  - `timeout` (float) Maximum number of seconds to allow cURL functions to execute.
     *  - `useragent` (string) Contents of the "User-Agent: " header to be used in an HTTP(S) request.
     *  - `proxy` (string) The HTTP proxy to tunnel requests through.
     * @return array $options Sanitized cURL options
     */
    protected function sanitizeOptions($options)
    {
        $allowedOptions = ['connect_timeout', 'timeout', 'useragent', 'proxy'];
        $defaultOptions = [
            'connect_timeout' => $this->getTimeout(), // WireHttp method
            'timeout' => $this->getTimeout(), // WireHttp method
            'useragent' => $this->getUserAgent(), // WireHttp method
        ];
        if (!empty($options['connect_timeout'])) $options['connect_timeout'] = (float)$options['connect_timeout'];
        if (!empty($options['timeout'])) $options['timeout'] = (float)$options['timeout'];

        $options = array_merge(
            $defaultOptions,
            array_intersect_key(
                $options, array_flip($allowedOptions)
            )
        );
        return $options;
    }

    /**
     * Add requests(s) to be processed asynchronous.
     *
     * @param string|array $url URL(s) for request(s)
     * @param string $method The HTTP method
     * @param array $customOptions Custom cURL options for current request(s):
     *  - `connect_timeout` (float) Number of seconds to wait while trying to connect. Use 0 to wait indefinitely.
     *  - `timeout` (float) Maximum number of seconds to allow cURL functions to execute.
     *  - `useragent` (string) Contents of the "User-Agent: " header to be used in an HTTP(S) request.
     *  - `proxy` (string) The HTTP proxy to tunnel requests through.
     */
    public function addMultiCURLRequest($url, $method = 'GET', $customOptions = [])
    {
        if (!$url) return;

        $options = !empty($customOptions)
            ? $this->sanitizeOptions($customOptions)
            : $this->_curlMultiOptions;

        // Add multiple requests if necessary
        if (is_array($url)) {
            foreach ($url as $u) $this->addCurlMultiRequest($u, $customOptions);
            return;
        }

        // Init new cURL session
        $this->_curlHandles[$url] = curl_init();

        // ---- Set all cURL options ----

        if (version_compare(PHP_VERSION, '5.6') >= 0) {
            // CURLOPT_SAFE_UPLOAD value is default true (setopt not necessary)
            // and PHP 7+ removes this option
        } elseif (version_compare(PHP_VERSION, '5.5') >= 0) {
            curl_setopt($this->_curlHandles[$url], CURLOPT_SAFE_UPLOAD, true);
        } else {
            // not reachable: PHP version blocked
        }

        curl_setopt($this->_curlHandles[$url], CURLOPT_CONNECTTIMEOUT, $options['connect_timeout']);
        curl_setopt($this->_curlHandles[$url], CURLOPT_TIMEOUT, $options['timeout']);
        curl_setopt($this->_curlHandles[$url], CURLOPT_USERAGENT, $options['useragent']);
        curl_setopt($this->_curlHandles[$url], CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_curlHandles[$url], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_curlHandles[$url], CURLOPT_HEADER, false);
        if (count($this->headers)) {
            // cURL needs headers as array of header lines!
            $headerlines = [];
            foreach ($this->headers as $key => $value) {
                $headerlines[] = $key . ': ' . $value;
            }
            curl_setopt($this->_curlHandles[$url], CURLOPT_HTTPHEADER, $headerlines);
        }
        if (!empty($options['proxy'])) {
            curl_setopt($this->_curlHandles[$url], CURLOPT_PROXY, $options['proxy']);
        }

        // @todo: handle other HTTP methods - currently only GET supported

        curl_setopt($this->_curlHandles[$url], CURLOPT_HTTPGET, true);
        curl_setopt($this->_curlHandles[$url], CURLOPT_URL, $url);
    }

    /**
     * Send GET requests to multiple URLs using cURL multi requests.
     *
     * @return array The cURL multi result
     * @throws WireException
     */
    public function getMulti()
    {
        return $this->sendMultiCURL();
    }

    /**
     * Send to multiple URLs that respond with JSON (using GET request) and return the resulting array.
     *
     * @return array The cURL multi result
     * @throws WireException
     */
    public function getMultiJSON()
    {
        $decoded = [];
        if (empty($results = $this->getMulti())) return $decoded;
        foreach ($results as $key => $result) {
            $decodedContent = json_decode($result[self::resultKeyContent], true); // assoc!
            $decoded[$key] = [
                self::resultKeyContent => json_last_error() === JSON_ERROR_NONE ? $decodedContent : [],
                self::resultKeyHttpCode => $result[self::resultKeyHttpCode],
                self::resultKeyError => $result[self::resultKeyError],
            ];
        }
        return $decoded;
    }

    /**
     * Process multi cURL and get results.
     *
     * @return array The cURL multi result:
     * @throws WireException
     *
     * Sample result:
     *
     *    [
     *        'https://app.domain.com/api/orders' => [
     *            'content' => 'The response content...',
     *            'http_code' => 200,
     *            'error' => ''
     *        ],
     *        ']https://app.domain.com/api/wrongurl' => [
     *            'content' => '',
     *            'http_code' => 404
     *            'error' => 'String containing the last error for the current session'
     *        ]
     *    ]
     *
     */
    public function sendMultiCURL()
    {
        if (empty($this->_curlHandles)) throw new WireException("No cURL handles available in {$this->className}::sendMultiCURL()");

        $this->lastSendType = 'curlmulti'; // WireHttp property

        // Create new cURL multi handle and add requests
        $this->_multiHandle = curl_multi_init();
        foreach ($this->_curlHandles as $curl) {
            curl_multi_add_handle($this->_multiHandle, $curl);
        }

        // Start executing requests
        $running = null;
        do {
            $status = curl_multi_exec($this->_multiHandle, $running);
        } while ($running > 0);

        // Loop and continue processing requests
        while ($running && $status == CURLM_OK) {
            // Wait for network
            $ready = curl_multi_select($this->_multiHandle);
            if ($ready != -1) {
                // Pull in any new data (at least handle timeouts)
                do {
                    $status = curl_multi_exec($this->_multiHandle, $running);
                } while ($running > 0);
            }
        }

        // Check for any errors
        /*
        if ($status != CURLM_OK) {
            trigger_error("Curl multi read error $status\n", E_USER_WARNING);
        }
        */

        // Get content of each request and remove cURL handle
        foreach ($this->_curlHandles as $key => $curl) {

            // Get the content of that cURL handle as string
            $content = !curl_error($curl) ? curl_multi_getcontent($curl) : '';
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            $this->resultsMulti[$key] = [
                self::resultKeyContent => $content,
                self::resultKeyHttpCode => $httpCode,
                self::resultKeyError => $curlError,
            ];

            curl_multi_remove_handle($this->_multiHandle, $curl);
            curl_close($curl);
        }

        curl_multi_close($this->_multiHandle);

        return $this->resultsMulti;
    }

    /**
     * Getter for $headers from WireHttp.
     *
     * @return array $headers (can be empty)
     * @since 3.0.131 this method is already available in WireHttp (we keep it for older versions)
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get a full http status code string from WireHttp $httpCodes.
     *
     * @param int $code Specify the HTTP code number
     * @return string (empty string if $code doesn't exist)
     */
    public function getHttpStatusCodeString($code)
    {
        if (isset($this->httpCodes[$code])) {
            return $code . ' ' . $this->httpCodes[$code];
        }
        return '';
    }
}

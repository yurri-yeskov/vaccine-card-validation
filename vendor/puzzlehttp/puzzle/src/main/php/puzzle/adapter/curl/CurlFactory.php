<?php

/**
 * Creates curl resources from a request and response object
 */
class puzzle_adapter_curl_CurlFactory
{
    private static $_TEST_MODE = false;

    private $_closure_handleOptions;

    /**
     * Creates a cURL handle based on a transaction.
     *
     * @param puzzle_adapter_TransactionInterface    $transaction    Holds a request and response
     * @param puzzle_message_MessageFactoryInterface $messageFactory Used to create responses
     * @param null|resource                                 $handle         Optionally provide a curl handle to modify
     *
     * @return resource Returns a prepared cURL handle
     * @throws puzzle_exception_AdapterException when an option cannot be applied
     */
    public function __invoke(
        puzzle_adapter_TransactionInterface $transaction,
        puzzle_message_MessageFactoryInterface $messageFactory,
        $handle = null
    ) {
        $request = $transaction->getRequest();
        $mediator = new puzzle_adapter_curl_RequestMediator($transaction, $messageFactory);
        $this->_closure_handleOptions = $this->getDefaultOptions($request, $mediator);
        $this->applyMethod($request);
        $this->applyTransferOptions($request, $mediator);
        $this->applyHeaders($request);
        unset($this->_closure_handleOptions['_headers']);

        // Add adapter options from the request's configuration options
        $config = $request->getConfig();
        if ($config = $config['curl']) {
            $this->_closure_handleOptions = $this->applyCustomCurlOptions($config);
        }

        if (!$handle) {
            $handle = curl_init();
        }

        if (self::$_TEST_MODE && function_exists('puzzle_test_adapter_curl_curl_setopt_array')) {

            puzzle_test_adapter_curl_curl_setopt_array($handle, $this->_closure_handleOptions);

        } else {

            curl_setopt_array($handle, $this->_closure_handleOptions);
        }

        return $handle;
    }

    protected function getDefaultOptions(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator
    ) {
        $url = $request->getUrl();

        // Strip fragment from URL. See:
        // https://github.com/guzzle/guzzle/issues/453
        if (($pos = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $pos);
        }

        $config = $request->getConfig();
        $options = array(
            CURLOPT_URL            => $url,
            CURLOPT_CONNECTTIMEOUT => $config['connect_timeout'] ? $config['connect_timeout'] : 150,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER         => false,
            CURLOPT_WRITEFUNCTION  => array($mediator, 'writeResponseBody'),
            CURLOPT_HEADERFUNCTION => array($mediator, 'receiveResponseHeader'),
            CURLOPT_READFUNCTION   => array($mediator, 'readRequestBody'),
            CURLOPT_HTTP_VERSION   => $request->getProtocolVersion() === '1.0'
                ? CURL_HTTP_VERSION_1_0 : CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 2,
            '_headers'             => $request->getHeaders()
        );

        if (defined('CURLOPT_PROTOCOLS')) {
            // Allow only HTTP and HTTPS protocols
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        // cURL sometimes adds a content-type by default. Prevent this.
        if (!$request->hasHeader('Content-Type')) {
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type:';
        }

        return $options;
    }

    private function applyMethod(puzzle_message_RequestInterface $request)
    {
        $method = $request->getMethod();
        if ($method == 'HEAD') {
            $this->_closure_handleOptions[CURLOPT_NOBODY] = true;
            unset($this->_closure_handleOptions[CURLOPT_WRITEFUNCTION], $this->_closure_handleOptions[CURLOPT_READFUNCTION]);
        } else {
            $this->_closure_handleOptions[CURLOPT_CUSTOMREQUEST] = $method;
            if (!$request->getBody()) {
                unset($this->_closure_handleOptions[CURLOPT_READFUNCTION]);
            } else {
                $this->applyBody($request, $this->_closure_handleOptions);
            }
        }
    }

    private function applyBody(puzzle_message_RequestInterface $request)
    {
        if ($request->hasHeader('Content-Length')) {
            $size = (int) $request->getHeader('Content-Length');
        } else {
            $size = null;
        }

        $request->getBody()->seek(0);

        // You can send the body as a string using curl's CURLOPT_POSTFIELDS
        $config = $request->getConfig();
        if (($size !== null && $size < 32768) ||
            isset($config['curl']['body_as_string'])
        ) {
            $this->_closure_handleOptions[CURLOPT_POSTFIELDS] = $request->getBody()->getContents();
            // Don't duplicate the Content-Length header
            $this->removeHeader('Content-Length', $this->_closure_handleOptions);
            $this->removeHeader('Transfer-Encoding', $this->_closure_handleOptions);
        } else {
            $this->_closure_handleOptions[CURLOPT_UPLOAD] = true;
            // Let cURL handle setting the Content-Length header
            if ($size !== null) {
                $this->_closure_handleOptions[CURLOPT_INFILESIZE] = $size;
                $this->removeHeader('Content-Length', $this->_closure_handleOptions);
            }
        }

        // If the Expect header is not present, prevent curl from adding it
        if (!$request->hasHeader('Expect')) {
            $this->_closure_handleOptions[CURLOPT_HTTPHEADER][] = 'Expect:';
        }
    }

    private function applyHeaders(puzzle_message_RequestInterface $request)
    {
        foreach ($this->_closure_handleOptions['_headers'] as $name => $values) {
            $this->_closure_handleOptions[CURLOPT_HTTPHEADER][] = $name . ': ' . implode(', ', $values);
        }

        // Remove the Expect header if one was not set
        if (!$request->hasHeader('Accept')) {
            $this->_closure_handleOptions[CURLOPT_HTTPHEADER][] = 'Accept:';
        }
    }

    private function applyTransferOptions(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator
    ) {
        static $methods;
        if (!$methods) {
            $methods = array_flip(get_class_methods(__CLASS__));
        }

        foreach ($request->getConfig()->toArray() as $key => $value) {
            $method = "add_{$key}";
            if (isset($methods[$method])) {
                $this->{$method}($request, $mediator, $value);
            }
        }
    }

    private function add_debug(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        if ($value) {
            $this->_closure_handleOptions[CURLOPT_STDERR] = is_resource($value) ? $value : STDOUT;
            $this->_closure_handleOptions[CURLOPT_VERBOSE] = true;
        }
    }

    private function add_proxy(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        if (!is_array($value)) {
            $this->_closure_handleOptions[CURLOPT_PROXY] = $value;
        } else {
            $scheme = $request->getScheme();
            if (isset($value[$scheme])) {
                $this->_closure_handleOptions[CURLOPT_PROXY] = $value[$scheme];
            }
        }
    }

    private function add_timeout(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        $this->_closure_handleOptions[CURLOPT_TIMEOUT_MS] = $value * 1000;
    }

    private function add_connect_timeout(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        $this->_closure_handleOptions[CURLOPT_CONNECTTIMEOUT_MS] = $value * 1000;
    }

    private function add_verify(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        if ($value === false) {
            unset($this->_closure_handleOptions[CURLOPT_CAINFO]);
            $this->_closure_handleOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            $this->_closure_handleOptions[CURLOPT_SSL_VERIFYPEER] = false;
        } elseif ($value === true || is_string($value)) {
            $this->_closure_handleOptions[CURLOPT_SSL_VERIFYHOST] = 2;
            $this->_closure_handleOptions[CURLOPT_SSL_VERIFYPEER] = true;
            if ($value !== true) {
                if (!file_exists($value)) {
                    throw new puzzle_exception_AdapterException('SSL certificate authority file'
                        . " not found: {$value}");
                }
                $this->_closure_handleOptions[CURLOPT_CAINFO] = $value;
            }
        }
    }

    private function add_cert(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        if (!file_exists($value)) {
            throw new puzzle_exception_AdapterException("SSL certificate not found: {$value}");
        }

        $this->_closure_handleOptions[CURLOPT_SSLCERT] = $value;
    }

    private function add_ssl_key(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        if (is_array($value)) {
            $this->_closure_handleOptions[CURLOPT_SSLKEYPASSWD] = $value[1];
            $value = $value[0];
        }

        if (!file_exists($value)) {
            throw new puzzle_exception_AdapterException("SSL private key not found: {$value}");
        }

        $this->_closure_handleOptions[CURLOPT_SSLKEY] = $value;
    }

    private function add_stream(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        if ($value === false) {
            return;
        }

        throw new puzzle_exception_AdapterException('cURL adapters do not support the "stream"'
            . ' request option. This error is typically encountered when trying'
            . ' to send requests with the "stream" option set to true in '
            . ' parallel. You will either need to send these one at a time or'
            . ' implement a custom ParallelAdapterInterface that supports'
            . ' sending these types of requests in parallel. This error can'
            . ' also occur if the StreamAdapter is not available on your'
            . ' system (e.g., allow_url_fopen is disabled in your php.ini).');
    }

    private function add_save_to(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        $mediator->setResponseBody(is_string($value)
            ? new puzzle_stream_LazyOpenStream($value, 'w')
            : puzzle_stream_Stream::factory($value));
    }

    private function add_decode_content(
        puzzle_message_RequestInterface $request,
        puzzle_adapter_curl_RequestMediator $mediator,
        $value
    ) {
        if (!$request->hasHeader('Accept-Encoding')) {
            $this->_closure_handleOptions[CURLOPT_ENCODING] = '';
            // Don't let curl send the header over the wire
            $this->_closure_handleOptions[CURLOPT_HTTPHEADER][] = 'Accept-Encoding:';
        } else {
            $this->_closure_handleOptions[CURLOPT_ENCODING] = $request->getHeader('Accept-Encoding');
        }
    }

    /**
     * Takes an array of curl options specified in the 'curl' option of a
     * request's configuration array and maps them to CURLOPT_* options.
     *
     * This method is only called when a  request has a 'curl' config setting.
     * Array key strings that start with CURL that have a matching constant
     * value will be automatically converted to the matching constant.
     *
     * @param array $config  Configuration array of custom curl option
     *
     * @return array Returns a new array of curl options
     */
    private function applyCustomCurlOptions(array $config)
    {
        unset($config['body_as_string']);
        $curlOptions = array();

        // Map curl constant strings to defined values
        foreach ($config as $key => $value) {
            if (defined($key) && substr($key, 0, 4) === 'CURL') {
                $key = constant($key);
            }
            $curlOptions[$key] = $value;
        }

        return $curlOptions + $this->_closure_handleOptions;
    }

    /**
     * Remove a header from the options array
     *
     * @param string $name    Case-insensitive header to remove
     * @param array  $options Array of options to modify
     */
    private function removeHeader($name, array &$options)
    {
        foreach (array_keys($options['_headers']) as $key) {
            if (!strcasecmp($key, $name)) {
                unset($options['_headers'][$key]);
                return;
            }
        }
    }

    /**
     * Hack to enable PHP 5.2 unit tests. Do not use in production!
     */
    public static function __enableTestMode()
    {
        self::$_TEST_MODE = true;
    }
}

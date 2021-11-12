<?php

/**
 * HTTP adapter that uses PHP's HTTP stream wrapper.
 *
 * When using the puzzle_adapter_StreamAdapter, custom stream context options can be specified
 * using the **stream_context** option in a request's **config** option. The
 * structure of the "stream_context" option is an associative array where each
 * key is a transport name and each option is an associative array of options.
 */
class puzzle_adapter_StreamAdapter implements puzzle_adapter_AdapterInterface
{
    /** @var puzzle_message_MessageFactoryInterface */
    private $messageFactory;

    private $_closure_add_debug_request;
    private $_closure_add_debug_value;
    private static $_closure_add_debug_map;
    private static $_closure_add_debug_args;

    private $_closure_createStreamContext_options;
    private $_closure_createStreamContext_params;

    private $_closure_createStreamResource_url;
    private $_closure_createStreamResource_context;
    private $_closure_createStreamResource_http_response_header;

    /**
     * @param puzzle_message_MessageFactoryInterface $messageFactory
     */
    public function __construct(puzzle_message_MessageFactoryInterface $messageFactory)
    {
        $this->messageFactory = $messageFactory;
    }

    public function send(puzzle_adapter_TransactionInterface $transaction)
    {
        // HTTP/1.1 streams using the PHP stream wrapper require a
        // Connection: close header. Setting here so that it is added before
        // emitting the request.before_send event.
        $request = $transaction->getRequest();
        if ($request->getProtocolVersion() == '1.1' &&
            !$request->hasHeader('Connection')
        ) {
            $transaction->getRequest()->setHeader('Connection', 'close');
        }

        puzzle_event_RequestEvents::emitBefore($transaction);
        if (!$transaction->getResponse()) {
            $this->createResponse($transaction);
            puzzle_event_RequestEvents::emitComplete($transaction);
        }

        return $transaction->getResponse();
    }

    private function createResponse(puzzle_adapter_TransactionInterface $transaction)
    {
        $request = $transaction->getRequest();
        $stream = $this->createStream($request, $http_response_header);
        $http_response_header = $this->_closure_createStreamResource_http_response_header;
        $this->createResponseObject(
            $request,
            $http_response_header,
            $transaction,
            new puzzle_stream_Stream($stream)
        );
    }

    private function createResponseObject(
        puzzle_message_RequestInterface $request,
        array $headers,
        puzzle_adapter_TransactionInterface $transaction,
        puzzle_stream_StreamInterface $stream
    ) {
        $parts = explode(' ', array_shift($headers), 3);
        $options = array('protocol_version' => substr($parts[0], -3));

        if (isset($parts[2])) {
            $options['reason_phrase'] = $parts[2];
        }

        $response = $this->messageFactory->createResponse(
            $parts[1],
            $this->headersFromLines($headers),
            null,
            $options
        );

        // Automatically decode responses when instructed.
        if ($request->getConfig()->get('decode_content')) {
            switch ($response->getHeader('Content-Encoding')) {
                case 'gzip':
                case 'deflate':
                    $stream = new puzzle_stream_InflateStream($stream);
                    break;
            }
        }

        // Drain the stream immediately if 'stream' was not enabled.
        $config = $request->getConfig();
        if (!$config['stream']) {
            $stream = $this->getSaveToBody($request, $stream);
        }

        $response->setBody($stream);
        $transaction->setResponse($response);
        puzzle_event_RequestEvents::emitHeaders($transaction);

        return $response;
    }

    /**
     * Drain the stream into the destination stream
     */
    private function getSaveToBody(
        puzzle_message_RequestInterface $request,
        puzzle_stream_StreamInterface $stream
    ) {
        $config = $request->getConfig();
        if ($saveTo = $config['save_to']) {
            // Stream the response into the destination stream
            $saveTo = is_string($saveTo)
                ? new puzzle_stream_Stream(puzzle_stream_Utils::open($saveTo, 'r+'))
                : puzzle_stream_Stream::factory($saveTo);
        } else {
            // Stream into the default temp stream
            $saveTo = puzzle_stream_Stream::factory();
        }

        puzzle_stream_Utils::copyToStream($stream, $saveTo);
        $saveTo->seek(0);
        $stream->close();

        return $saveTo;
    }

    private function headersFromLines(array $lines)
    {
        $responseHeaders = array();

        foreach ($lines as $line) {
            $headerParts = explode(':', $line, 2);
            $responseHeaders[$headerParts[0]][] = isset($headerParts[1])
                ? trim($headerParts[1])
                : '';
        }

        return $responseHeaders;
    }

    /**
     * Create a resource and check to ensure it was created successfully
     *
     * @param callable                               $callback Callable that returns stream resource
     * @param puzzle_message_RequestInterface $request  Request used when throwing exceptions
     * @param array                                  $options  Options used when throwing exceptions
     *
     * @return resource
     * @throws puzzle_exception_RequestException on error
     */
    private function createResource($callback, puzzle_message_RequestInterface $request, $options)
    {
        // Turn off error reporting while we try to initiate the request
        $level = error_reporting(0);
        $resource = call_user_func($callback);
        error_reporting($level);

        // If the resource could not be created, then grab the last error and
        // throw an exception.
        if (!is_resource($resource)) {
            $message = 'Error creating resource. [url] ' . $request->getUrl() . ' ';
            if (isset($options['http']['proxy'])) {
                $message .= "[proxy] {$options['http']['proxy']} ";
            }
            foreach ((array) error_get_last() as $key => $value) {
                $message .= "[{$key}] {$value} ";
            }
            throw new puzzle_exception_RequestException(trim($message), $request);
        }

        return $resource;
    }

    /**
     * Create the stream for the request with the context options.
     *
     * @param puzzle_message_RequestInterface $request              Request being sent
     * @param mixed            $http_response_header Populated by stream wrapper
     *
     * @return resource
     */
    private function createStream(
        puzzle_message_RequestInterface $request,
        &$http_response_header
    ) {
        static $methods;
        if (!$methods) {
            $methods = array_flip(get_class_methods(__CLASS__));
        }

        $params = array();
        $options = $this->getDefaultOptions($request);
        foreach ($request->getConfig()->toArray() as $key => $value) {
            $method = "add_{$key}";
            if (isset($methods[$method])) {
                $this->{$method}($request, $options, $value, $params);
            }
        }

        $this->applyCustomOptions($request, $options);
        $context = $this->createStreamContext($request, $options, $params);

        return $this->createStreamResource(
            $request,
            $options,
            $context,
            $http_response_header
        );
    }

    private function getDefaultOptions(puzzle_message_RequestInterface $request)
    {
        $headers = puzzle_message_AbstractMessage::getHeadersAsString($request);

        $context = array(
            'http' => array(
                'method'           => $request->getMethod(),
                'header'           => trim($headers),
                'protocol_version' => $request->getProtocolVersion(),
                'ignore_errors'    => true,
                'follow_location'  => 0
            )
        );

        if ($body = $request->getBody()) {
            $context['http']['content'] = (string) $body;
            // Prevent the HTTP adapter from adding a Content-Type header.
            if (!$request->hasHeader('Content-Type')) {
                $context['http']['header'] .= "\r\nContent-Type:";
            }
        }

        return $context;
    }

    private function add_proxy(puzzle_message_RequestInterface $request, &$options, $value, &$params)
    {
        if (!is_array($value)) {
            $options['http']['proxy'] = $value;
        } else {
            $scheme = $request->getScheme();
            if (isset($value[$scheme])) {
                $options['http']['proxy'] = $value[$scheme];
            }
        }
    }

    private function add_timeout(puzzle_message_RequestInterface $request, &$options, $value, &$params)
    {
        $options['http']['timeout'] = $value;
    }

    private function add_verify(puzzle_message_RequestInterface $request, &$options, $value, &$params)
    {
        if ($value === true || is_string($value)) {
            $options['http']['verify_peer'] = true;
            if ($value !== true) {
                if (!file_exists($value)) {
                    throw new RuntimeException("SSL certificate authority file not found: {$value}");
                }
                $options['http']['allow_self_signed'] = true;
                $options['http']['cafile'] = $value;
            }
        } elseif ($value === false) {
            $options['http']['verify_peer'] = false;
        }
    }

    private function add_cert(puzzle_message_RequestInterface $request, &$options, $value, &$params)
    {
        if (is_array($value)) {
            $options['http']['passphrase'] = $value[1];
            $value = $value[0];
        }

        if (!file_exists($value)) {
            throw new RuntimeException("SSL certificate not found: {$value}");
        }

        $options['http']['local_cert'] = $value;
    }

    private function add_debug(puzzle_message_RequestInterface $request, &$options, $value, &$params)
    {
        self::$_closure_add_debug_map = array(
            STREAM_NOTIFY_CONNECT       => 'CONNECT',
            STREAM_NOTIFY_AUTH_REQUIRED => 'AUTH_REQUIRED',
            STREAM_NOTIFY_AUTH_RESULT   => 'AUTH_RESULT',
            STREAM_NOTIFY_MIME_TYPE_IS  => 'MIME_TYPE_IS',
            STREAM_NOTIFY_FILE_SIZE_IS  => 'FILE_SIZE_IS',
            STREAM_NOTIFY_REDIRECTED    => 'REDIRECTED',
            STREAM_NOTIFY_PROGRESS      => 'PROGRESS',
            STREAM_NOTIFY_FAILURE       => 'FAILURE',
            STREAM_NOTIFY_COMPLETED     => 'COMPLETED',
            STREAM_NOTIFY_RESOLVE       => 'RESOLVE'
        );

        self::$_closure_add_debug_args = array('severity', 'message', 'message_code',
            'bytes_transferred', 'bytes_max');

        if (!is_resource($value)) {
            $value = fopen('php://output', 'w');
        }

        $this->_closure_add_debug_request = $request;
        $this->_closure_add_debug_value = $value;

        $params['notification'] = array($this, '__callback_add_debug');
    }

    public function __callback_add_debug()
    {
        $passed = func_get_args();
        $code = array_shift($passed);
        fprintf($this->_closure_add_debug_value, '<%s> [%s] ', $this->_closure_add_debug_request->getUrl(), self::$_closure_add_debug_map[$code]);
        foreach (array_filter($passed) as $i => $v) {
            fwrite($this->_closure_add_debug_value, self::$_closure_add_debug_args[$i] . ': "' . $v . '" ');
        }
        fwrite($this->_closure_add_debug_value, "\n");
    }

    private function applyCustomOptions(
        puzzle_message_RequestInterface $request,
        array &$options
    ) {
        // Overwrite any generated options with custom options
        $config = $request->getConfig();
        if ($custom = $config['stream_context']) {
            if (!is_array($custom)) {
                throw new puzzle_exception_AdapterException('stream_context must be an array');
            }
            $options = __puzzle_array_replace_recursive($options, $custom);
        }
    }

    private function createStreamContext(
        puzzle_message_RequestInterface $request,
        array $options,
        array $params
    ) {
        $this->_closure_createStreamContext_options = $options;
        $this->_closure_createStreamContext_params  = $params;
        return $this->createResource(array($this, '__callback_createStreamContext'), $request, $options);
    }

    public function __callback_createStreamContext()
    {
        $stream = stream_context_create($this->_closure_createStreamContext_options);
        stream_context_set_params($stream, $this->_closure_createStreamContext_params);
        return $stream;
    }

    private function createStreamResource(
        puzzle_message_RequestInterface $request,
        array $options,
        $context,
        &$http_response_header
    ) {
        $this->_closure_createStreamResource_url = $request->getUrl();
        $this->_closure_createStreamResource_context = $context;

        return $this->createResource(array($this, '__callback_createStreamResource'), $request, $options);
    }

    public function __callback_createStreamResource()
    {
        if (false === strpos($this->_closure_createStreamResource_url, 'http')) {
            trigger_error("URL is invalid: {$this->_closure_createStreamResource_url}", E_USER_WARNING);
            return null;
        }
        $f = fopen($this->_closure_createStreamResource_url, 'r', null, $this->_closure_createStreamResource_context);

        $this->_closure_createStreamResource_http_response_header = $http_response_header;

        return $f;
    }
}

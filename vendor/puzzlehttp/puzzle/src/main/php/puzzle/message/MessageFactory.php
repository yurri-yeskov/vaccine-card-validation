<?php

/**
 * Default HTTP request factory used to create Request and Response objects.
 */
class puzzle_message_MessageFactory extends puzzle_AbstractListenerAttacher implements puzzle_message_MessageFactoryInterface
{
    private $errorPlugin;

    /** @var puzzle_subscriber_Redirect */
    private $redirectPlugin;

    /** @var array */
    protected static $classMethods = array();

    public function __construct()
    {
        $this->errorPlugin = new puzzle_subscriber_HttpError();
        $this->redirectPlugin = new puzzle_subscriber_Redirect();
    }

    public function createResponse(
        $statusCode,
        array $headers = array(),
        $body = null,
        array $options = array()
    ) {
        if (null !== $body) {
            $body = puzzle_stream_Stream::factory($body);
        }

        return new puzzle_message_Response($statusCode, $headers, $body, $options);
    }

    public function createRequest($method, $url, array $options = array())
    {
        // Handle the request protocol version option that needs to be
        // specified in the request constructor.
        if (isset($options['version'])) {
            $options['config']['protocol_version'] = $options['version'];
            unset($options['version']);
        }

        $request = new puzzle_message_Request($method, $url, array(), null,
            isset($options['config']) ? $options['config'] : array());

        unset($options['config']);

        // Use a POST body by default
        if ($method == 'POST' &&
            !isset($options['body']) &&
            !isset($options['json'])
        ) {
            $options['body'] = array();
        }

        if ($options) {
            $this->applyOptions($request, $options);
        }

        return $request;
    }

    /**
     * Create a request or response object from an HTTP message string
     *
     * @param string $message Message to parse
     *
     * @return puzzle_message_RequestInterface|puzzle_message_ResponseInterface
     * @throws InvalidArgumentException if unable to parse a message
     */
    public function fromMessage($message)
    {
        static $parser;
        if (!$parser) {
            $parser = new puzzle_message_MessageParser();
        }

        // Parse a response
        if (strtoupper(substr($message, 0, 4)) == 'HTTP') {
            $data = $parser->parseResponse($message);
            return $this->createResponse(
                $data['code'],
                $data['headers'],
                $data['body'] === '' ? null : $data['body'],
                $data
            );
        }

        // Parse a request
        if (!($data = ($parser->parseRequest($message)))) {
            throw new InvalidArgumentException('Unable to parse request');
        }

        return $this->createRequest(
            $data['method'],
            puzzle_Url::buildUrl($data['request_url']),
            array(
                'headers' => $data['headers'],
                'body' => $data['body'] === '' ? null : $data['body'],
                'config' => array(
                    'protocol_version' => $data['protocol_version']
                )
            )
        );
    }

    /**
     * Apply POST fields and files to a request to attempt to give an accurate
     * representation.
     *
     * @param puzzle_message_RequestInterface $request Request to update
     * @param array            $body    Body to apply
     */
    protected function addPostData(puzzle_message_RequestInterface $request, array $body)
    {
        static $fields = array('string' => true, 'array' => true, 'NULL' => true,
            'boolean' => true, 'double' => true, 'integer' => true);

        $post = new puzzle_post_PostBody();
        foreach ($body as $key => $value) {
            if (isset($fields[gettype($value)])) {
                $post->setField($key, $value);
            } elseif ($value instanceof puzzle_post_PostFileInterface) {
                $post->addFile($value);
            } else {
                $post->addFile(new puzzle_post_PostFile($key, $value));
            }
        }

        if ($request->getHeader('Content-Type') == 'multipart/form-data') {
            $post->forceMultipartUpload(true);
        }

        $request->setBody($post);
    }

    protected function applyOptions(
        puzzle_message_RequestInterface $request,
        array $options = array()
    ) {
        // Values specified in the config map are passed to request options
        static $configMap = array('connect_timeout' => 1, 'timeout' => 1,
            'verify' => 1, 'ssl_key' => 1, 'cert' => 1, 'proxy' => 1,
            'debug' => 1, 'save_to' => 1, 'stream' => 1, 'expect' => 1);

        // Take the class of the instance, not the parent
        $selfClass = get_class($this);

        // Check if we already took it's class methods and had them saved
        if (!isset(self::$classMethods[$selfClass])) {
            self::$classMethods[$selfClass] = array_flip(get_class_methods($this));
        }

        // Take class methods of this particular instance
        $methods = self::$classMethods[$selfClass];

        // Iterate over each key value pair and attempt to apply a config using
        // double dispatch.
        $config = $request->getConfig();
        foreach ($options as $key => $value) {
            $method = "add_{$key}";
            if (isset($methods[$method])) {
                $this->{$method}($request, $value);
            } elseif (isset($configMap[$key])) {
                $config[$key] = $value;
            } else {
                throw new InvalidArgumentException("No method is configured "
                    . "to handle the {$key} config key");
            }
        }
    }

    private function add_body(puzzle_message_RequestInterface $request, $value)
    {
        if ($value !== null) {
            if (is_array($value)) {
                $this->addPostData($request, $value);
            } else {
                $request->setBody(puzzle_stream_Stream::factory($value));
            }
        }
    }

    private function add_allow_redirects(puzzle_message_RequestInterface $request, $value)
    {
        static $defaultRedirect = array(
            'max'     => 5,
            'strict'  => false,
            'referer' => false
        );

        if ($value === false) {
            return;
        }

        if ($value === true) {
            $value = $defaultRedirect;
        } elseif (!isset($value['max'])) {
            throw new InvalidArgumentException('allow_redirects must be '
                . 'true, false, or an array that contains the \'max\' key');
        } else {
            // Merge the default settings with the provided settings
            $value += $defaultRedirect;
        }

        $config = $request->getConfig();
        $config['redirect'] = $value;
        $request->getEmitter()->attach($this->redirectPlugin);
    }

    private function add_exceptions(puzzle_message_RequestInterface $request, $value)
    {
        if ($value === true) {
            $request->getEmitter()->attach($this->errorPlugin);
        }
    }

    private function add_auth(puzzle_message_RequestInterface $request, $value)
    {
        if (!$value) {
            return;
        } elseif (is_array($value)) {
            $authType = isset($value[2]) ? strtolower($value[2]) : 'basic';
        } else {
            $authType = strtolower($value);
        }

        $request->getConfig()->set('auth', $value);

        if ($authType == 'basic') {
            $request->setHeader(
                'Authorization',
                'Basic ' . base64_encode("$value[0]:$value[1]")
            );
        } elseif ($authType == 'digest') {
            // Currently only implemented by the cURL adapter.
            // @todo: Need an event listener solution that does not rely on cURL
            $config = $request->getConfig();
            $config->setPath('curl/' . CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
            $config->setPath('curl/' . CURLOPT_USERPWD, "$value[0]:$value[1]");
        }
    }

    private function add_query(puzzle_message_RequestInterface $request, $value)
    {
        if ($value instanceof puzzle_Query) {
            $original = $request->getQuery();
            // Do not overwrite existing query string variables by overwriting
            // the object with the query string data passed in the URL
            $request->setQuery($value->overwriteWith($original->toArray()));
        } elseif (is_array($value)) {
            // Do not overwrite existing query string variables
            $query = $request->getQuery();
            foreach ($value as $k => $v) {
                if (!isset($query[$k])) {
                    $query[$k] = $v;
                }
            }
        } else {
            throw new InvalidArgumentException('query value must be an array '
                . 'or puzzle_Query object');
        }
    }

    private function add_headers(puzzle_message_RequestInterface $request, $value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('header value must be an array');
        }

        // Do not overwrite existing headers
        foreach ($value as $k => $v) {
            if (!$request->hasHeader($k)) {
                $request->setHeader($k, $v);
            }
        }
    }

    private function add_cookies(puzzle_message_RequestInterface $request, $value)
    {
        if ($value === true) {
            static $cookie = null;
            if (!$cookie) {
                $cookie = new puzzle_subscriber_Cookie ();
            }
            $request->getEmitter()->attach($cookie);
        } elseif (is_array($value)) {
            $request->getEmitter()->attach(
                new puzzle_subscriber_Cookie (puzzle_cookie_CookieJar::fromArray($value, $request->getHost()))
            );
        } elseif ($value instanceof puzzle_cookie_CookieJarInterface) {
            $request->getEmitter()->attach(new puzzle_subscriber_Cookie ($value));
        } elseif ($value !== false) {
            throw new InvalidArgumentException('cookies must be an array, '
                . 'true, or a puzzle_cookie_CookieJarInterface object');
        }
    }

    private function add_events(puzzle_message_RequestInterface $request, $value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('events value must be an array');
        }

        $this->attachListeners($request, $this->prepareListeners($value,
            array('before', 'complete', 'error', 'headers')
        ));
    }

    private function add_subscribers(puzzle_message_RequestInterface $request, $value)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('subscribers must be an array');
        }

        $emitter = $request->getEmitter();
        foreach ($value as $subscribers) {
            $emitter->attach($subscribers);
        }
    }

    private function add_json(puzzle_message_RequestInterface $request, $value)
    {
        if (!$request->hasHeader('Content-Type')) {
            $request->setHeader('Content-Type', 'application/json');
        }

        $request->setBody(puzzle_stream_Stream::factory(json_encode($value)));
    }

    private function add_decode_content(puzzle_message_RequestInterface $request, $value)
    {
        if ($value === false) {
            return;
        }

        if ($value !== true) {
            $request->setHeader('Accept-Encoding', $value);
        }

        $config = $request->getConfig();
        $config['decode_content'] = true;
    }
}

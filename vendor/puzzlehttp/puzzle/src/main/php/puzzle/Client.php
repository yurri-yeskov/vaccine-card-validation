<?php

/**
 * HTTP client
 */
class puzzle_Client implements puzzle_ClientInterface
{
    /** @var puzzle_event_EmitterInterface */
    private $emitter;

    public function getEmitter()
    {
        if (!$this->emitter) {
            $this->emitter = new puzzle_event_Emitter();
        }

        return $this->emitter;
    }

    const DEFAULT_CONCURRENCY = 25;

    /** @var puzzle_message_MessageFactoryInterface Request factory used by the client */
    private $messageFactory;

    /** @var puzzle_adapter_AdapterInterface */
    private $adapter;

    /** @var puzzle_adapter_ParallelAdapterInterface */
    private $parallelAdapter;

    /** @var puzzle_Url Base URL of the client */
    private $baseUrl;

    /** @var array Default request options */
    private $defaults;

    /**
     * Clients accept an array of constructor parameters.
     *
     * Here's an example of creating a client using an URI template for the
     * client's base_url and an array of default request options to apply
     * to each request:
     *
     *     $client = new puzzle_Client([
     *         'base_url' => [
     *              'http://www.foo.com/{version}/',
     *              ['version' => '123']
     *          ],
     *         'defaults' => [
     *             'timeout'         => 10,
     *             'allow_redirects' => false,
     *             'proxy'           => '192.168.16.1:10'
     *         ]
     *     ]);
     *
     * @param array $config Client configuration settings
     *     - base_url: Base URL of the client that is merged into relative URLs.
     *       Can be a string or an array that contains a URI template followed
     *       by an associative array of expansion variables to inject into the
     *       URI template.
     *     - adapter: Adapter used to transfer requests
     *     - parallel_adapter: Adapter used to transfer requests in parallel
     *     - message_factory: Factory used to create request and response object
     *     - defaults: Default request options to apply to each request
     *     - emitter: Event emitter used for request events
     */
    public function __construct(array $config = array())
    {
        $this->configureBaseUrl($config);
        $this->configureDefaults($config);
        $this->configureAdapter($config);
        if (isset($config['emitter'])) {
            $this->emitter = $config['emitter'];
        }
    }

    /**
     * Get the default User-Agent string to use with Guzzle
     *
     * @return string
     */
    public static function getDefaultUserAgent()
    {
        static $defaultAgent = '';
        if (!$defaultAgent) {
            $defaultAgent = 'puzzle/' . self::VERSION;
            if (extension_loaded('curl')) {
                $curlVersion = curl_version();
                $defaultAgent .= ' curl/' . $curlVersion['version'];
            }
            $defaultAgent .= ' PHP/' . PHP_VERSION;
        }

        return $defaultAgent;
    }

    public function __call($name, $arguments)
    {
        return puzzle_deprecation_proxy(
            $this,
            $name,
            $arguments,
            array('getEventDispatcher' => 'getEmitter')
        );
    }

    public function getDefaultOption($keyOrPath = null)
    {
        return $keyOrPath === null
            ? $this->defaults
            : puzzle_get_path($this->defaults, $keyOrPath);
    }

    public function setDefaultOption($keyOrPath, $value)
    {
        puzzle_set_path($this->defaults, $keyOrPath, $value);
    }

    public function getBaseUrl()
    {
        return (string) $this->baseUrl;
    }

    public function createRequest($method, $url = null, array $options = array())
    {
        $headers = $this->mergeDefaults($options);
        // Use a clone of the client's emitter
        $options['config']['emitter'] = clone $this->getEmitter();

        $request = $this->messageFactory->createRequest(
            $method,
            $url ? (string) $this->buildUrl($url) : (string) $this->baseUrl,
            $options
        );

        // Merge in default headers
        if ($headers) {
            foreach ($headers as $key => $value) {
                if (!$request->hasHeader($key)) {
                    $request->setHeader($key, $value);
                }
            }
        }

        return $request;
    }

    public function get($url = null, $options = array())
    {
        return $this->send($this->createRequest('GET', $url, $options));
    }

    public function head($url = null, array $options = array())
    {
        return $this->send($this->createRequest('HEAD', $url, $options));
    }

    public function delete($url = null, array $options = array())
    {
        return $this->send($this->createRequest('DELETE', $url, $options));
    }

    public function put($url = null, array $options = array())
    {
        return $this->send($this->createRequest('PUT', $url, $options));
    }

    public function patch($url = null, array $options = array())
    {
        return $this->send($this->createRequest('PATCH', $url, $options));
    }

    public function post($url = null, array $options = array())
    {
        return $this->send($this->createRequest('POST', $url, $options));
    }

    public function options($url = null, array $options = array())
    {
        return $this->send($this->createRequest('OPTIONS', $url, $options));
    }

    public function send(puzzle_message_RequestInterface $request)
    {
        $transaction = new puzzle_adapter_Transaction($this, $request);
        try {
            if ($response = $this->adapter->send($transaction)) {
                return $response;
            }
            throw new LogicException('No response was associated with the transaction');
        } catch (puzzle_exception_RequestException $e) {
            throw $e;
        } catch (Exception $e) {
            // Wrap exceptions in a puzzle_exception_RequestException to adhere to the interface
            throw new puzzle_exception_RequestException($e->getMessage(), $request, null, $e);
        }
    }

    public function sendAll($requests, array $options = array())
    {
        if (!($requests instanceof puzzle_adapter_TransactionIterator)) {
            $requests = new puzzle_adapter_TransactionIterator($requests, $this, $options);
        }

        $this->parallelAdapter->sendAll(
            $requests,
            isset($options['parallel'])
                ? $options['parallel']
                : self::DEFAULT_CONCURRENCY
        );
    }

    /**
     * Get an array of default options to apply to the client
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        $settings = array(
            'allow_redirects' => true,
            'exceptions'      => true,
            'decode_content'  => true,
            'verify'          => dirname(__FILE__) . '/cacert.pem'
        );

        // Use the bundled cacert if it is a regular file, or set to true if
        // using a phar file (because curL and the stream wrapper can't read
        // cacerts from the phar stream wrapper). Favor the ini setting over
        // the system's cacert.
        if (substr(__FILE__, 0, 7) == 'phar://') {
            $settings['verify'] = ini_get('openssl.cafile') ? ini_get('openssl.cafile') : true;
        }

        // Use the standard Linux HTTP_PROXY and HTTPS_PROXY if set
        if (isset($_SERVER['HTTP_PROXY'])) {
            $settings['proxy']['http'] = $_SERVER['HTTP_PROXY'];
        }

        if (isset($_SERVER['HTTPS_PROXY'])) {
            $settings['proxy']['https'] = $_SERVER['HTTPS_PROXY'];
        }

        return $settings;
    }

    /**
     * Expand a URI template and inherit from the base URL if it's relative
     *
     * @param string|array $url URL or URI template to expand
     *
     * @return string
     */
    private function buildUrl($url)
    {
        if (!is_array($url)) {
            if (strpos($url, '://')) {
                return (string) $url;
            }
            return (string) $this->baseUrl->combine($url);
        } elseif (strpos($url[0], '://')) {
            return puzzle_uri_template($url[0], $url[1]);
        }

        return (string) $this->baseUrl->combine(
            puzzle_uri_template($url[0], $url[1])
        );
    }

    /**
     * Get a default parallel adapter to use based on the environment
     *
     * @return puzzle_adapter_ParallelAdapterInterface
     */
    private function getDefaultParallelAdapter()
    {
        return extension_loaded('curl')
            ? new puzzle_adapter_curl_MultiAdapter($this->messageFactory)
            : new puzzle_adapter_FakeParallelAdapter($this->adapter);
    }

    /**
     * Create a default adapter to use based on the environment
     * @throws RuntimeException
     */
    private function getDefaultAdapter()
    {
        if (extension_loaded('curl')) {
            $this->parallelAdapter = new puzzle_adapter_curl_MultiAdapter($this->messageFactory);
            $this->adapter = function_exists('curl_reset')
                ? new puzzle_adapter_curl_CurlAdapter($this->messageFactory)
                : $this->parallelAdapter;
            if (ini_get('allow_url_fopen')) {
                $this->adapter = new puzzle_adapter_StreamingProxyAdapter(
                    $this->adapter,
                    new puzzle_adapter_StreamAdapter($this->messageFactory)
                );
            }
        } elseif (ini_get('allow_url_fopen')) {
            $this->adapter = new puzzle_adapter_StreamAdapter($this->messageFactory);
        } else {
            throw new RuntimeException('Guzzle requires cURL, the '
                . 'allow_url_fopen ini setting, or a custom HTTP adapter.');
        }
    }

    private function configureBaseUrl(&$config)
    {
        if (!isset($config['base_url'])) {
            $this->baseUrl = new puzzle_Url('', '');
        } elseif (is_array($config['base_url'])) {
            $this->baseUrl = puzzle_Url::fromString(
                puzzle_uri_template(
                    $config['base_url'][0],
                    $config['base_url'][1]
                )
            );
            $config['base_url'] = (string) $this->baseUrl;
        } else {
            $this->baseUrl = puzzle_Url::fromString($config['base_url']);
        }
    }

    private function configureDefaults($config)
    {
        if (!isset($config['defaults'])) {
            $this->defaults = $this->getDefaultOptions();
        } else {
            $this->defaults = __puzzle_array_replace(
                $this->getDefaultOptions(),
                $config['defaults']
            );
        }

        // Add the default user-agent header
        if (!isset($this->defaults['headers'])) {
            $this->defaults['headers'] = array(
                'User-Agent' => self::getDefaultUserAgent()
            );
        } else {

            $h = array_change_key_case($this->defaults['headers']);
            if (!isset($h['user-agent'])) {

                // Add the User-Agent header if one was not already set
                $this->defaults['headers']['User-Agent'] = self::getDefaultUserAgent();
            }
        }
    }

    private function configureAdapter(&$config)
    {
        if (isset($config['message_factory'])) {
            $this->messageFactory = $config['message_factory'];
        } else {
            $this->messageFactory = new puzzle_message_MessageFactory();
        }
        if (isset($config['adapter'])) {
            $this->adapter = $config['adapter'];
        } else {
            $this->getDefaultAdapter();
        }
        // If no parallel adapter was explicitly provided and one was not
        // defaulted when creating the default adapter, then create one now.
        if (isset($config['parallel_adapter'])) {
            $this->parallelAdapter = $config['parallel_adapter'];
        } elseif (!$this->parallelAdapter) {
            $this->parallelAdapter = $this->getDefaultParallelAdapter();
        }
    }

    /**
     * Merges default options into the array passed by reference and returns
     * an array of headers that need to be merged in after the request is
     * created.
     *
     * @param array $options Options to modify by reference
     *
     * @return array|null
     */
    private function mergeDefaults(&$options)
    {
        // Merging optimization for when no headers are present
        if (!isset($options['headers'])
            || !isset($this->defaults['headers'])) {
            $options = __puzzle_array_replace_recursive($this->defaults, $options);
            return null;
        }

        $defaults = $this->defaults;
        unset($defaults['headers']);
        $options = __puzzle_array_replace_recursive($defaults, $options);

        return $this->defaults['headers'];
    }
}

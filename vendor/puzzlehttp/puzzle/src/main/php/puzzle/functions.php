<?php

if (!defined('GUZZLE_FUNCTIONS_VERSION')) {

    define('GUZZLE_FUNCTIONS_VERSION', puzzle_ClientInterface::VERSION);

    /**
     * Send a custom request
     *
     * @param string $method  HTTP request method
     * @param string $url     URL of the request
     * @param array  $options Options to use with the request.
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_request($method, $url, array $options = array())
    {
        static $client;
        if (!$client) {
            $client = new puzzle_Client();
        }

        return $client->send($client->createRequest($method, $url, $options));
    }

    /**
     * Send a GET request
     *
     * @param string $url     URL of the request
     * @param array  $options Array of request options
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_get($url, array $options = array())
    {
        return puzzle_request('GET', $url, $options);
    }

    /**
     * Send a HEAD request
     *
     * @param string $url     URL of the request
     * @param array  $options Array of request options
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_head($url, array $options = array())
    {
        return puzzle_request('HEAD', $url, $options);
    }

    /**
     * Send a DELETE request
     *
     * @param string $url     URL of the request
     * @param array  $options Array of request options
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_delete($url, array $options = array())
    {
        return puzzle_request('DELETE', $url, $options);
    }

    /**
     * Send a POST request
     *
     * @param string $url     URL of the request
     * @param array  $options Array of request options
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_post($url, array $options = array())
    {
        return puzzle_request('POST', $url, $options);
    }

    /**
     * Send a PUT request
     *
     * @param string $url     URL of the request
     * @param array  $options Array of request options
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_put($url, array $options = array())
    {
        return puzzle_request('PUT', $url, $options);
    }

    /**
     * Send a PATCH request
     *
     * @param string $url     URL of the request
     * @param array  $options Array of request options
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_patch($url, array $options = array())
    {
        return puzzle_request('PATCH', $url, $options);
    }

    /**
     * Send an OPTIONS request
     *
     * @param string $url     URL of the request
     * @param array  $options Array of request options
     *
     * @return puzzle_message_ResponseInterface
     */
    function puzzle_options($url, array $options = array())
    {
        return puzzle_request('OPTIONS', $url, $options);
    }

    /**
     * @var $__closure_puzzle_batch_hash SplObjectStorage
     */
    global $__closure_puzzle_batch_hash;
    function __callback_puzzle_batch($e)
    {
        /**
         * @var $__closure_puzzle_batch_hash SplObjectStorage
         */
        global $__closure_puzzle_batch_hash;
        $__closure_puzzle_batch_hash->offsetSet($e->getRequest(), $e);
    }

    function __callback_puzzle_batch_convertEvents($e)
    {
        global $__closure_puzzle_batch_hash;
        $__closure_puzzle_batch_hash->offsetSet($e->getRequest(), $e);
    }

    /**
     * Convenience method for sending multiple requests in parallel and
     * retrieving a hash map of requests to response objects or
     * RequestException objects.
     *
     * Note: This method keeps every request and response in memory, and as
     * such is NOT recommended when sending a large number or an indeterminable
     * number of requests in parallel.
     *
     * @param puzzle_ClientInterface $client   Client used to send the requests
     * @param array|Iterator $requests Requests to send in parallel
     * @param array           $options  Passes through the options available in
     *                                  {@see puzzle_ClientInterface::sendAll()}
     *
     * @return SplObjectStorage Requests are the key and each value is a
     *     {@see puzzle_message_ResponseInterface} if the request succeeded
     *     or a {@see puzzle_exception_RequestException} if it failed.
     * @throws InvalidArgumentException if the event format is incorrect.
     */
    function puzzle_batch(puzzle_ClientInterface $client, $requests, array $options = array())
    {
        global $__closure_puzzle_batch_hash;
        $__closure_puzzle_batch_hash = new puzzle_SplObjectStorage();
        foreach ($requests as $request) {
            $__closure_puzzle_batch_hash->offsetSet($request);
        }

        // Merge the necessary complete and error events to the event listeners
        // so that as each request succeeds or fails, it is added to the result
        // hash.
        $options = puzzle_event_RequestEvents::convertEventArray(
            $options,
            array('complete', 'error'),
            array(
                'priority' => puzzle_event_RequestEvents::EARLY,
                'once' => true,
                'fn' => '__callback_puzzle_batch_convertEvents'
            )
        );

        // Send the requests in parallel and aggregate the results.
        $client->sendAll($requests, $options);

        // Update the received value for any of the intercepted requests.
        foreach ($__closure_puzzle_batch_hash as $request) {
            $storedEvent = $__closure_puzzle_batch_hash->offsetGet($request);
            if ($storedEvent instanceof puzzle_event_CompleteEvent) {
                $__closure_puzzle_batch_hash->offsetSet($request, $storedEvent->getResponse());
            } elseif ($storedEvent instanceof puzzle_event_ErrorEvent) {
                $__closure_puzzle_batch_hash->offsetSet($request, $storedEvent->getException());
            }
        }

        return $__closure_puzzle_batch_hash;
    }

    /**
     * Gets a value from an array using a path syntax to retrieve nested data.
     *
     * This method does not allow for keys that contain "/". You must traverse
     * the array manually or using something more advanced like JMESPath to
     * work with keys that contain "/".
     *
     *     // Get the bar key of a set of nested arrays.
     *     // This is equivalent to $collection['foo']['baz']['bar'] but won't
     *     // throw warnings for missing keys.
     *     puzzle_get_path($data, 'foo/baz/bar');
     *
     * @param array  $data Data to retrieve values from
     * @param string $path Path to traverse and retrieve a value from
     *
     * @return mixed|null
     */
    function puzzle_get_path($data, $path)
    {
        $path = explode('/', $path);

        while (null !== ($part = array_shift($path))) {
            if (!is_array($data) || !isset($data[$part])) {
                return null;
            }
            $data = $data[$part];
        }

        return $data;
    }

    /**
     * Set a value in a nested array key. Keys will be created as needed to set
     * the value.
     *
     * This function does not support keys that contain "/" or "[]" characters
     * because these are special tokens used when traversing the data structure.
     * A value may be prepended to an existing array by using "[]" as the final
     * key of a path.
     *
     *     puzzle_get_path($data, 'foo/baz'); // null
     *     puzzle_set_path($data, 'foo/baz/[]', 'a');
     *     puzzle_set_path($data, 'foo/baz/[]', 'b');
     *     puzzle_get_path($data, 'foo/baz');
     *     // Returns ['a', 'b']
     *
     * @param array  $data  Data to modify by reference
     * @param string $path  Path to set
     * @param mixed  $value Value to set at the key
     *
     * @throws RuntimeException when trying to setPath using a nested path
     *     that travels through a scalar value.
     */
    function puzzle_set_path(&$data, $path, $value)
    {
        $current =& $data;
        $queue = explode('/', $path);
        while (null !== ($key = array_shift($queue))) {
            if (!is_array($current)) {
                throw new RuntimeException("Trying to setPath {$path}, but "
                    . "{$key} is set and is not an array");
            } elseif (!$queue) {
                if ($key == '[]') {
                    $current[] = $value;
                } else {
                    $current[$key] = $value;
                }
            } elseif (isset($current[$key])) {
                $current =& $current[$key];
            } else {
                $current[$key] = array();
                $current =& $current[$key];
            }
        }
    }

    /**
     * Expands a URI template
     *
     * @param string $template  URI template
     * @param array  $variables Template variables
     *
     * @return string
     */
    function puzzle_uri_template($template, array $variables)
    {
        if (function_exists('uri_template')) {
            return uri_template($template, $variables);
        }

        static $uriTemplate;
        if (!$uriTemplate) {
            $uriTemplate = new puzzle_UriTemplate();
        }

        return $uriTemplate->expand($template, $variables);
    }

    /**
     * Wrapper for JSON decode that implements error detection with helpful
     * error messages.
     *
     * @param string $json    JSON data to parse
     * @param bool $assoc     When true, returned objects will be converted
     *                        into associative arrays.
     * @param int    $depth   User specified recursion depth.
     * @param int    $options Bitmask of JSON decode options.
     *
     * @return mixed
     * @throws InvalidArgumentException if the JSON cannot be parsed.
     * @link http://www.php.net/manual/en/function.json-decode.php
     */
    function puzzle_json_decode($json, $assoc = false, $depth = 512, $options = 0)
    {
        static $jsonErrors = array(
            JSON_ERROR_DEPTH => 'JSON_ERROR_DEPTH - Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'JSON_ERROR_STATE_MISMATCH - Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'JSON_ERROR_CTRL_CHAR - Unexpected control character found',
            JSON_ERROR_SYNTAX => 'JSON_ERROR_SYNTAX - Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'JSON_ERROR_UTF8 - Malformed UTF-8 characters, possibly incorrectly encoded'
        );

        if (version_compare(PHP_VERSION, '5.4') >= 0) {

            $data = json_decode($json, $assoc, $depth, $options);

        } elseif (version_compare(PHP_VERSION, '5.3') >= 0) {

            $data = json_decode($json, $assoc, $depth);

        } else {

            $data = json_decode($json, $assoc);
        }

        if (version_compare(PHP_VERSION, '5.3') >= 0) {

            if (JSON_ERROR_NONE !== json_last_error()) {
                $last = json_last_error();
                throw new InvalidArgumentException(
                    'Unable to parse JSON data: '
                    . (isset($jsonErrors[$last])
                        ? $jsonErrors[$last]
                        : 'Unknown error')
                );
            }

        } else {

            if ("$json" !== '' && $data === null) {

                throw new InvalidArgumentException('Unable to parse JSON data: Unknown error');
            }
        }

        return $data;
    }

    /**
     * @internal
     */
    function puzzle_deprecation_proxy($object, $name, $arguments, $map)
    {
        if (!isset($map[$name])) {
            throw new BadMethodCallException('Unknown method, ' . $name);
        }

        $message = sprintf('%s is deprecated and will be removed in a future '
            . 'version. Update your code to use the equivalent %s method '
            . 'instead to avoid breaking changes when this shim is removed.',
            get_class($object) . '::' . $name . '()',
            get_class($object) . '::' . $map[$name] . '()'
        );

        if (version_compare(PHP_VERSION, '5.3') >= 0) {
            trigger_error($message, E_USER_DEPRECATED);
        } else {
            trigger_error($message, E_USER_NOTICE);
        }

        return call_user_func_array(array($object, $map[$name]), $arguments);
    }

    function __puzzle_array_replace(array $first, array $second)
    {
        if (!function_exists('array_replace'))
        {
            function array_replace( array &$array, array &$array1 )
            {
                $args = func_get_args();
                $count = func_num_args();

                for ($i = 0; $i < $count; ++$i) {
                    if (is_array($args[$i])) {
                        foreach ($args[$i] as $key => $val) {
                            $array[$key] = $val;
                        }
                    }
                    else {
                        trigger_error(
                            __FUNCTION__ . '(): Argument #' . ($i+1) . ' is not an array',
                            E_USER_WARNING
                        );
                        return NULL;
                    }
                }

                return $array;
            }
        }

        return array_replace($first, $second);
    }

    function __puzzle_array_replace_recursive(array $first, array $second)
    {
        if (!function_exists('__puzzle_recurse')) {
            function __puzzle_recurse($array, $array1) {
                foreach ($array1 as $key => $value) {
                    // create new key in $array, if it is empty or not an array
                    if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key]))) {
                        $array[$key] = array();
                    }

                    // overwrite the value in the base array
                    if (is_array($value)) {
                        $value = __puzzle_recurse($array[$key], $value);
                    }
                    $array[$key] = $value;
                }
                return $array;
            }
        }

        if (!function_exists('array_replace_recursive')) {

            function array_replace_recursive($array, $array1)
            {
                // handle the arguments, merge one by one
                $args = func_get_args();
                $array = $args[0];
                if (!is_array($array)) {
                    return $array;
                }
                for ($i = 1; $i < count($args); $i++) {
                    if (is_array($args[$i])) {
                        $array = __puzzle_recurse($array, $args[$i]);
                    }
                }
                return $array;
            }
        }

        return array_replace_recursive($first, $second);
    }
}
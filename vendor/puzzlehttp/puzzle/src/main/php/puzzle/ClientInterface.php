<?php

/**
 * Client interface for sending HTTP requests
 */
interface puzzle_ClientInterface extends puzzle_event_HasEmitterInterface
{
    const VERSION = '4.2.2';

    /**
     * Create and return a new {@see puzzle_message_RequestInterface} object.
     *
     * Use an absolute path to override the base path of the client, or a
     * relative path to append to the base path of the client. The URL can
     * contain the query string as well. Use an array to provide a URL
     * template and additional variables to use in the URL template expansion.
     *
     * @param string                  $method  HTTP method
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                   $options Array of request options to apply.
     *
     * @return puzzle_message_RequestInterface
     */
    function createRequest($method, $url = null, array $options = array());

    /**
     * Send a GET request
     *
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                          $options Array of request options to apply.
     *
     * @return puzzle_message_ResponseInterface
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function get($url = null, $options = array());

    /**
     * Send a HEAD request
     *
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                   $options Array of request options to apply.
     *
     * @return puzzle_message_ResponseInterface
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function head($url = null, array $options = array());

    /**
     * Send a DELETE request
     *
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                   $options Array of request options to apply.
     *
     * @return puzzle_message_ResponseInterface
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function delete($url = null, array $options = array());

    /**
     * Send a PUT request
     *
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                   $options Array of request options to apply.
     *
     * @return puzzle_message_ResponseInterface
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function put($url = null, array $options = array());

    /**
     * Send a PATCH request
     *
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                   $options Array of request options to apply.
     *
     * @return puzzle_message_ResponseInterface
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function patch($url = null, array $options = array());

    /**
     * Send a POST request
     *
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                   $options Array of request options to apply.
     *
     * @return puzzle_message_ResponseInterface
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function post($url = null, array $options = array());

    /**
     * Send an OPTIONS request
     *
     * @param string|array|puzzle_Url $url     URL or URI template
     * @param array                   $options Array of request options to apply.
     *
     * @return puzzle_message_ResponseInterface
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function options($url = null, array $options = array());

    /**
     * Sends a single request
     *
     * @param puzzle_message_RequestInterface $request Request to send
     *
     * @return puzzle_message_ResponseInterface
     * @throws LogicException When the adapter does not populate a response
     * @throws puzzle_exception_RequestException When an error is encountered
     */
    function send(puzzle_message_RequestInterface $request);

    /**
     * Sends multiple requests in parallel.
     *
     * Exceptions are not thrown for failed requests. Callers are expected to
     * register an "error" option to handle request errors OR directly register
     * an event handler for the "error" event of a request's
     * event emitter.
     *
     * The option values for 'before', 'after', and 'error' can be a callable,
     * an associative array containing event data, or an array of event data
     * arrays. Event data arrays contain the following keys:
     *
     * - fn: callable to invoke that receives the event
     * - priority: Optional event priority (defaults to 0)
     * - once: Set to true so that the event is removed after it is triggered
     *
     * @param array|Iterator $requests Requests to send in parallel
     * @param array           $options  Associative array of options
     *     - parallel: (int) Maximum number of requests to send in parallel
     *     - before: (callable|array) Receives a puzzle_event_BeforeEvent
     *     - after: (callable|array) Receives a puzzle_event_CompleteEvent
     *     - error: (callable|array) Receives a puzzle_event_ErrorEvent
     *
     * @throws puzzle_exception_AdapterException When an error occurs in the HTTP adapter.
     */
    function sendAll($requests, array $options = array());

    /**
     * Get default request options of the client.
     *
     * @param string|null $keyOrPath The Path to a particular default request
     *     option to retrieve or pass null to retrieve all default request
     *     options. The syntax uses "/" to denote a path through nested PHP
     *     arrays. For example, "headers/content-type".
     *
     * @return mixed
     */
    function getDefaultOption($keyOrPath = null);

    /**
     * Set a default request option on the client so that any request created
     * by the client will use the provided default value unless overridden
     * explicitly when creating a request.
     *
     * @param string|null $keyOrPath The Path to a particular configuration
     *     value to set. The syntax uses a path notation that allows you to
     *     specify nested configuration values (e.g., 'headers/content-type').
     * @param mixed $value Default request option value to set
     */
    function setDefaultOption($keyOrPath, $value);

    /**
     * Get the base URL of the client.
     *
     * @return string Returns the base URL if present
     */
    function getBaseUrl();
}

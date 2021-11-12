<?php

/**
 * HTTP Request exception
 */
class puzzle_exception_RequestException extends puzzle_exception_TransferException
{
    /** @var bool */
    private $emittedErrorEvent = false;

    /** @var puzzle_message_RequestInterface */
    private $request;

    /** @var puzzle_message_ResponseInterface */
    private $response;

    /** @var bool */
    private $throwImmediately = false;

    public function __construct(
        $message = '',
        puzzle_message_RequestInterface $request,
        puzzle_message_ResponseInterface $response = null,
        Exception $previous = null
    ) {
        $code = $response ? $response->getStatusCode() : 0;
        if (version_compare(PHP_VERSION, '5.3') < 0) {

            parent::__construct($message, $code);

        } else {

            parent::__construct($message, $code, $previous);
        }
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Factory method to create a new exception with a normalized error message
     *
     * @param puzzle_message_RequestInterface  $request  Request
     * @param puzzle_message_ResponseInterface $response Response received
     * @param Exception        $previous Previous exception
     *
     * @return self
     */
    public static function create(
        puzzle_message_RequestInterface $request,
        puzzle_message_ResponseInterface $response = null,
        Exception $previous = null
    ) {
        if (!$response) {
            return new self('Error completing request', $request, null, $previous);
        }

        $statusCode = $response->getStatusCode();
        $level = $statusCode[0];
        if ($level == '4') {
            $label = 'Client error response';
            $className = 'puzzle_exception_ClientException';
        } elseif ($level == '5') {
            $label = 'Server error response';
            $className = 'puzzle_exception_ServerException';
        } else {
            $label = 'Unsuccessful response';
            $className = __CLASS__;
        }

        $message = $label . ' [url] ' . $request->getUrl()
            . ' [status code] ' . $response->getStatusCode()
            . ' [reason phrase] ' . $response->getReasonPhrase();

        return new $className($message, $request, $response, $previous);
    }

    /**
     * Get the request that caused the exception
     *
     * @return puzzle_message_RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get the associated response
     *
     * @return puzzle_message_ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Check if a response was received
     *
     * @return bool
     */
    public function hasResponse()
    {
        return $this->response !== null;
    }

    /**
     * Check or set if the exception was emitted in an error event.
     *
     * This value is used in the puzzle_event_RequestEvents::emitBefore() method to check
     * to see if an exception has already been emitted in an error event.
     *
     * @param bool|null Set to true to set the exception as having emitted an
     *     error. Leave null to retrieve the current setting.
     *
     * @return null|bool
     * @throws InvalidArgumentException if you attempt to set the value to false
     */
    public function emittedError($value = null)
    {
        if ($value === null) {
            return $this->emittedErrorEvent;
        } elseif ($value === true) {
            $this->emittedErrorEvent = true;
        } else {
            throw new InvalidArgumentException('You cannot set the emitted '
                . 'error value to false.');
        }
    }

    /**
     * Sets whether or not parallel adapters SHOULD throw the exception
     * immediately rather than handling errors through asynchronous error
     * handling.
     *
     * @param bool $throwImmediately
     *
     */
    public function setThrowImmediately($throwImmediately)
    {
        $this->throwImmediately = $throwImmediately;
    }

    /**
     * Gets the setting specified by setThrowImmediately().
     *
     * @return bool
     */
    public function getThrowImmediately()
    {
        return $this->throwImmediately;
    }
}

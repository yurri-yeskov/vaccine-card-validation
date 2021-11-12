<?php

/**
 * Exception when a client is unable to parse the response body as XML or JSON
 */
class puzzle_exception_ParseException extends puzzle_exception_TransferException
{
    /** @var puzzle_message_ResponseInterface */
    private $response;

    public function __construct(
        $message = '',
        puzzle_message_ResponseInterface $response = null,
        Exception $previous = null
    ) {
        if (version_compare(PHP_VERSION, '5.3') >= 0) {

            parent::__construct($message, 0, $previous);

        } else {

            parent::__construct($message, 0);
        }
        $this->response = $response;
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
}

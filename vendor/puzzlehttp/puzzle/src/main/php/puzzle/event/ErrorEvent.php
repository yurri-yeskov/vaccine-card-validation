<?php

/**
 * Event object emitted after a request has been sent and an error was
 * encountered.
 *
 * You may intercept the exception and inject a response into the event to
 * rescue the request.
 */
class puzzle_event_ErrorEvent extends puzzle_event_AbstractTransferEvent
{
    private $exception;

    /**
     * @param puzzle_adapter_TransactionInterface $transaction   Transaction that contains the request
     * @param puzzle_exception_RequestException   $e             Exception encountered
     * @param array                                      $transferStats Array of transfer statistics
     */
    public function __construct(
        puzzle_adapter_TransactionInterface $transaction,
        puzzle_exception_RequestException $e,
        $transferStats = array()
    ) {
        parent::__construct($transaction, $transferStats);
        $this->exception = $e;
    }

    /**
     * Intercept the exception and inject a response
     *
     * @param puzzle_message_ResponseInterface $response Response to set
     */
    public function intercept(puzzle_message_ResponseInterface $response)
    {
        $this->stopPropagation();
        $this->getTransaction()->setResponse($response);
        $this->exception->setThrowImmediately(false);
        puzzle_event_RequestEvents::emitComplete($this->getTransaction());
    }

    /**
     * Get the exception that was encountered
     *
     * @return puzzle_exception_RequestException
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * Get the response the was received (if any)
     *
     * @return puzzle_message_ResponseInterface|null
     */
    public function getResponse()
    {
        return $this->getException()->getResponse();
    }

    /**
     * Request that a ParallelAdapterInterface throw the associated exception
     * if the exception is unhandled.
     *
     * If the error event was not emitted from a ParallelAdapterInterface, then
     * the effect of this method is nil.
     *
     * @param bool $throwImmediately Whether or not to throw immediately
     */
    public function throwImmediately($throwImmediately)
    {
        $this->exception->setThrowImmediately($throwImmediately);
    }
}

<?php

/**
 * Event object emitted after the response headers of a request have been
 * received.
 *
 * You may intercept the exception and inject a response into the event to
 * rescue the request.
 */
class puzzle_event_HeadersEvent extends puzzle_event_AbstractRequestEvent
{
    /**
     * @param puzzle_adapter_TransactionInterface $transaction Transaction that contains the
     *     request and response.
     * @throws RuntimeException
     */
    public function __construct(puzzle_adapter_TransactionInterface $transaction)
    {
        parent::__construct($transaction);
        if (!$transaction->getResponse()) {
            throw new RuntimeException('A response must be present');
        }
    }

    /**
     * Get the response the was received
     *
     * @return puzzle_message_ResponseInterface
     */
    public function getResponse()
    {
        return $this->getTransaction()->getResponse();
    }
}

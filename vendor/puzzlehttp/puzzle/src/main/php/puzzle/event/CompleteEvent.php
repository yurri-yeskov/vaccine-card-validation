<?php

/**
 * Event object emitted after a request has been completed.
 *
 * You may change the Response associated with the request using the
 * intercept() method of the event.
 */
class puzzle_event_CompleteEvent extends puzzle_event_AbstractTransferEvent
{
    /**
     * Intercept the request and associate a response
     *
     * @param puzzle_message_ResponseInterface $response Response to set
     */
    public function intercept(puzzle_message_ResponseInterface $response)
    {
        $this->stopPropagation();
        $this->getTransaction()->setResponse($response);
    }

    /**
     * Get the response of the request
     *
     * @return puzzle_message_ResponseInterface
     */
    public function getResponse()
    {
        return $this->getTransaction()->getResponse();
    }
}

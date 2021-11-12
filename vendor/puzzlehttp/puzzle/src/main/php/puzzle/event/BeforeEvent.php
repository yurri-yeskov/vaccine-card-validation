<?php

/**
 * Event object emitted before a request is sent.
 *
 * You may change the Response associated with the request using the
 * intercept() method of the event.
 */
class puzzle_event_BeforeEvent extends puzzle_event_AbstractRequestEvent
{
    /**
     * Intercept the request and associate a response
     *
     * @param puzzle_message_ResponseInterface $response Response to set
     */
    public function intercept(puzzle_message_ResponseInterface $response)
    {
        $this->getTransaction()->setResponse($response);
        $this->stopPropagation();
        puzzle_event_RequestEvents::emitComplete($this->getTransaction());
    }
}

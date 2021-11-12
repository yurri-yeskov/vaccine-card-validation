<?php

/**
 * Throws exceptions when a 4xx or 5xx response is received
 */
class puzzle_subscriber_HttpError implements puzzle_event_SubscriberInterface
{
    public function getEvents()
    {
        return array('complete' => array('onComplete', puzzle_event_RequestEvents::VERIFY_RESPONSE));
    }

    /**
     * Throw a puzzle_exception_RequestException on an HTTP protocol error
     *
     * @param puzzle_event_CompleteEvent $event Emitted event
     * @throws puzzle_exception_RequestException
     */
    public function onComplete(puzzle_event_CompleteEvent $event)
    {
        $code = (string) $event->getResponse()->getStatusCode();
        // Throw an exception for an unsuccessful response
        if ($code[0] === '4' || $code[0] === '5') {
            throw puzzle_exception_RequestException::create($event->getRequest(), $event->getResponse());
        }
    }
}

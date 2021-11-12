<?php

/**
 * Maintains a list of requests and responses sent using a request or client
 */
class puzzle_subscriber_History implements puzzle_event_SubscriberInterface, IteratorAggregate, Countable
{
    /** @var int The maximum number of requests to maintain in the history */
    private $limit;

    /** @var array Requests and responses that have passed through the plugin */
    private $transactions = array();

    public function __construct($limit = 10)
    {
        $this->limit = $limit;
    }

    public function getEvents()
    {
        return array(
            'complete' => array('onComplete', puzzle_event_RequestEvents::EARLY),
            'error'    => array('onError', puzzle_event_RequestEvents::EARLY),
        );
    }

    /**
     * Convert to a string that contains all request and response headers
     *
     * @return string
     */
    public function __toString()
    {
        $lines = array();
        foreach ($this->transactions as $entry) {
            $response = isset($entry['response']) ? $entry['response'] : '';
            $lines[] = '> ' . trim($entry['request']) . "\n\n< " . trim($response) . "\n";
        }

        return implode("\n", $lines);
    }

    public function onComplete(puzzle_event_CompleteEvent $event)
    {
        $this->add($event->getRequest(), $event->getResponse());
    }

    public function onError(puzzle_event_ErrorEvent $event)
    {
        // Only track when no response is present, meaning this didn't ever
        // emit a complete event
        if (!$event->getResponse()) {
            $this->add($event->getRequest());
        }
    }

    /**
     * Returns an Iterator that yields associative array values where each
     * associative array contains a 'request' and 'response' key.
     *
     * @return Iterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->transactions);
    }

    /**
     * Get all of the requests sent through the plugin
     *
     * @return puzzle_message_RequestInterface[]
     */
    public function getRequests()
    {
        return array_map(array($this, '__callback_getRequests'), $this->transactions);
    }

    public function __callback_getRequests($t)
    {
        return $t['request'];
    }

    /**
     * Get the number of requests in the history
     *
     * @return int
     */
    public function count()
    {
        return count($this->transactions);
    }

    /**
     * Get the last request sent
     *
     * @return puzzle_message_RequestInterface
     */
    public function getLastRequest()
    {
        $end = end($this->transactions);
        return $end['request'];
    }

    /**
     * Get the last response in the history
     *
     * @return puzzle_message_ResponseInterface|null
     */
    public function getLastResponse()
    {
        $end = end($this->transactions);
        return $end['response'];
    }

    /**
     * Clears the history
     */
    public function clear()
    {
        $this->transactions = array();
    }

    /**
     * Add a request to the history
     *
     * @param puzzle_message_RequestInterface  $request  Request to add
     * @param puzzle_message_ResponseInterface $response Response of the request
     */
    private function add(
        puzzle_message_RequestInterface $request,
        puzzle_message_ResponseInterface $response = null
    ) {
        $this->transactions[] = array('request' => $request, 'response' => $response);
        if (count($this->transactions) > $this->limit) {
            array_shift($this->transactions);
        }
    }
}

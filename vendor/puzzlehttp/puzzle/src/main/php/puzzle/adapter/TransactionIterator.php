<?php

/**
 * Converts a sequence of request objects into a transaction.
 * @internal
 */
class puzzle_adapter_TransactionIterator extends puzzle_AbstractListenerAttacher implements Iterator
{
    /** @var Iterator */
    private $source;

    /** @var puzzle_ClientInterface */
    private $client;

    /** @var array Listeners to attach to each request */
    private $eventListeners = array();

    public function __construct(
        $source,
        puzzle_ClientInterface $client,
        array $options
    ) {
        $this->client = $client;
        $this->eventListeners = $this->prepareListeners(
            $options,
            array('before', 'complete', 'error')
        );
        if ($source instanceof Iterator) {
            $this->source = $source;
        } elseif (is_array($source)) {
            $this->source = new ArrayIterator($source);
        } else {
            throw new InvalidArgumentException('Expected an Iterator or array');
        }
    }

    public function current()
    {
        $request = $this->source->current();

        if (!$request instanceof puzzle_message_RequestInterface) {
            throw new RuntimeException('All must implement puzzle_message_RequestInterface');
        }

        $this->attachListeners($request, $this->eventListeners);

        return new puzzle_adapter_Transaction($this->client, $request);
    }

    public function next()
    {
        $this->source->next();
    }

    public function key()
    {
        return $this->source->key();
    }

    public function valid()
    {
        return $this->source->valid();
    }

    public function rewind()
    {
        if (!is_a($this->source, 'Generator')) {
            $this->source->rewind();
        }
    }
}

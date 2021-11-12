<?php

abstract class puzzle_event_AbstractRequestEvent extends puzzle_event_AbstractEvent
{
    /** @var puzzle_adapter_TransactionInterface */
    private $transaction;

    /**
     * @param puzzle_adapter_TransactionInterface $transaction
     */
    public function __construct(puzzle_adapter_TransactionInterface $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Get the client associated with the event
     *
     * @return puzzle_ClientInterface
     */
    public function getClient()
    {
        return $this->transaction->getClient();
    }

    /**
     * Get the request object
     *
     * @return puzzle_message_RequestInterface
     */
    public function getRequest()
    {
        return $this->transaction->getRequest();
    }

    /**
     * @return puzzle_adapter_TransactionInterface
     */
    protected function getTransaction()
    {
        return $this->transaction;
    }


    /**
     * Hack for PHP 5.2. Do not use outside of testing!
     */
    public function __getTransaction()
    {
        return $this->getTransaction();
    }
}

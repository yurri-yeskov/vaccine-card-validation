<?php

/**
 * Provides context for a Curl transaction, including active handles,
 * pending transactions, and whether or not this is a batch or single
 * transaction.
 */
class puzzle_adapter_curl_BatchContext
{
    /** @var resource Curl multi resource */
    private $multi;

    /** @var SplObjectStorage Map of transactions to curl resources */
    private $handles;

    /** @var Iterator Yields pending transactions */
    private $pending;

    /** @var bool Whether or not to throw transactions */
    private $throwsExceptions;

    /**
     * @param resource $multiHandle      Initialized curl_multi resource
     * @param bool     $throwsExceptions Whether or not exceptions are thrown
     * @param Iterator $pending          Iterator yielding pending transactions
     */
    public function __construct(
        $multiHandle,
        $throwsExceptions,
        Iterator $pending = null
    ) {
        $this->multi = $multiHandle;
        $this->handles = new puzzle_SplObjectStorage();
        $this->throwsExceptions = $throwsExceptions;
        $this->pending = $pending;
    }

    /**
     * Closes all of the requests associated with the underlying multi handle.
     */
    public function removeAll()
    {
        foreach ($this->handles as $transaction) {
            $ch = $this->handles->offsetGet($transaction);
            curl_multi_remove_handle($this->multi, $ch);
            curl_close($ch);
            $this->handles->detach($transaction);
        }
    }

    /**
     * Find a transaction for a given curl handle
     *
     * @param resource $handle Curl handle
     *
     * @return puzzle_adapter_TransactionInterface
     * @throws puzzle_exception_AdapterException if a transaction is not found
     */
    public function findTransaction($handle)
    {
        $this->handles->rewind();
        while ($this->handles->valid()) {
            $transaction = $this->handles->current();
            if ($this->handles->getInfo() === $handle) {
                return $transaction;
            }
            $this->handles->next();
        }

        throw new puzzle_exception_AdapterException('No curl handle was found');
    }

    /**
     * Returns true if there are any active requests.
     *
     * @return bool
     */
    public function isActive()
    {
        return count($this->handles) > 0;
    }

    /**
     * Returns true if there are any remaining pending transactions
     *
     * @return bool
     */
    public function hasPending()
    {
        return $this->pending && $this->pending->valid();
    }

    /**
     * Pop the next transaction from the transaction queue
     *
     * @return puzzle_adapter_TransactionInterface|null
     */
    public function nextPending()
    {
        if (!$this->hasPending()) {
            return null;
        }

        $current = $this->pending->current();
        $this->pending->next();

        return $current;
    }

    /**
     * Checks if the batch is to throw exceptions on error
     *
     * @return bool
     */
    public function throwsExceptions()
    {
        return $this->throwsExceptions;
    }

    /**
     * Get the curl_multi handle
     *
     * @return resource
     */
    public function getMultiHandle()
    {
        return $this->multi;
    }

    /**
     * Add a transaction to the multi handle
     *
     * @param puzzle_adapter_TransactionInterface $transaction Transaction to add
     * @param resource                                   $handle      Resource to use with the handle
     *
     * @throws puzzle_exception_AdapterException If the handle is already registered
     */
    public function addTransaction(puzzle_adapter_TransactionInterface $transaction, $handle)
    {
        if ($this->handles->contains($transaction)) {
            throw new puzzle_exception_AdapterException('Transaction already registered');
        }

        $code = curl_multi_add_handle($this->multi, $handle);
        if ($code != CURLM_OK) {
            puzzle_adapter_curl_MultiAdapter::throwMultiError($code);
        }

        $this->handles->attach($transaction, $handle);
    }

    /**
     * Remove a transaction and associated handle from the context
     *
     * @param puzzle_adapter_TransactionInterface $transaction Transaction to remove
     *
     * @return array Returns the curl_getinfo array
     * @throws puzzle_exception_AdapterException if the transaction is not found
     */
    public function removeTransaction(puzzle_adapter_TransactionInterface $transaction)
    {
        if (!$this->handles->contains($transaction)) {
            throw new puzzle_exception_AdapterException('Transaction not registered');
        }

        $handle = $this->handles->offsetGet($transaction);
        $this->handles->detach($transaction);
        $info = curl_getinfo($handle);
        $code = curl_multi_remove_handle($this->multi, $handle);
        curl_close($handle);

        if ($code !== CURLM_OK) {
            puzzle_adapter_curl_MultiAdapter::throwMultiError($code);
        }

        return $info;
    }
}

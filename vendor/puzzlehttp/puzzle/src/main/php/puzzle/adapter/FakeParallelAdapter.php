<?php

/**
 * Decorates a regular puzzle_adapter_AdapterInterface object and creates a
 * puzzle_adapter_ParallelAdapterInterface object that sends multiple HTTP requests serially.
 */
class puzzle_adapter_FakeParallelAdapter implements puzzle_adapter_ParallelAdapterInterface
{
    /** @var puzzle_adapter_AdapterInterface */
    private $adapter;

    /**
     * @param puzzle_adapter_AdapterInterface $adapter Adapter used to send requests
     */
    public function __construct(puzzle_adapter_AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function sendAll(Iterator $transactions, $parallel)
    {
        foreach ($transactions as $transaction) {
            try {
                $this->adapter->send($transaction);
            } catch (puzzle_exception_RequestException $e) {
                if ($e->getThrowImmediately()) {
                    throw $e;
                }
            }
        }
    }
}

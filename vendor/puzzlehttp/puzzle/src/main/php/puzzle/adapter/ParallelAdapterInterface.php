<?php

/**
 * Adapter interface used to transfer multiple HTTP requests in parallel.
 *
 * Parallel adapters follow the same rules as puzzle_adapter_AdapterInterface except that
 * puzzle_exception_RequestExceptions are never thrown in a parallel transfer and parallel
 * adapters do not return responses.
 */
interface puzzle_adapter_ParallelAdapterInterface
{
    /**
     * Transfers multiple HTTP requests in parallel.
     *
     * puzzle_exception_RequestExceptions MUST not be thrown from a parallel transfer.
     *
     * @param Iterator $transactions Iterator that yields puzzle_adapter_TransactionInterface
     * @param int      $parallel     Max number of requests to send in parallel
     */
    function sendAll(Iterator $transactions, $parallel);
}

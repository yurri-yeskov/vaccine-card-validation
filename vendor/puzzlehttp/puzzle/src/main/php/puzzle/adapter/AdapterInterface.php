<?php

/**
 * Adapter interface used to transfer HTTP requests.
 *
 * @link http://docs.guzzlephp.org/en/guzzle4/adapters.html for a full
 *     explanation of adapters and their responsibilities.
 */
interface puzzle_adapter_AdapterInterface
{
    /**
     * Transfers an HTTP request and populates a response
     *
     * @param puzzle_adapter_TransactionInterface $transaction Transaction abject to populate
     *
     * @return puzzle_message_ResponseInterface
     */
    function send(puzzle_adapter_TransactionInterface $transaction);
}

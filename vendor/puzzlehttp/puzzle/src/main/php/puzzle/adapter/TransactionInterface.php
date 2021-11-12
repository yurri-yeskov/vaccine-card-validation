<?php

/**
 * Represents a transactions that consists of a request, response, and client
 */
interface puzzle_adapter_TransactionInterface
{
    /**
     * @return puzzle_message_RequestInterface
     */
    function getRequest();

    /**
     * @return puzzle_message_ResponseInterface|null
     */
    function getResponse();

    /**
     * Set a response on the transaction
     *
     * @param puzzle_message_ResponseInterface $response Response to set
     */
    function setResponse(puzzle_message_ResponseInterface $response);

    /**
     * @return puzzle_ClientInterface
     */
    function getClient();
}

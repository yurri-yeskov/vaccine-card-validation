<?php

class puzzle_adapter_Transaction implements puzzle_adapter_TransactionInterface
{
    /** @var puzzle_ClientInterface */
    private $client;
    /** @var puzzle_message_RequestInterface */
    private $request;
    /** @var puzzle_message_ResponseInterface */
    private $response;

    /**
     * @param puzzle_ClientInterface  $client  Client that is used to send the requests
     * @param puzzle_message_RequestInterface $request
     */
    public function __construct(
        puzzle_ClientInterface $client,
        puzzle_message_RequestInterface $request
    ) {
        $this->client = $client;
        $this->request = $request;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse(puzzle_message_ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function getClient()
    {
        return $this->client;
    }
}

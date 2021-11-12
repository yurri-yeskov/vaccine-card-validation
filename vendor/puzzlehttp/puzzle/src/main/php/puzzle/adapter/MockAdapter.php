<?php

/**
 * Adapter that can be used to associate mock responses with a transaction
 * while still emulating the event workflow of real adapters.
 */
class puzzle_adapter_MockAdapter implements puzzle_adapter_AdapterInterface
{
    private $response;

    /**
     * @param puzzle_message_ResponseInterface|callable $response Response to serve or function
     *     to invoke that handles a transaction
     */
    public function __construct($response = null)
    {
        $this->setResponse($response);
    }

    /**
     * Set the response that will be served by the adapter
     *
     * @param puzzle_message_ResponseInterface|callable $response Response to serve or
     *     function to invoke that handles a transaction
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function send(puzzle_adapter_TransactionInterface $transaction)
    {
        puzzle_event_RequestEvents::emitBefore($transaction);
        if (!$transaction->getResponse()) {

            // Read the request body if it is present
            if ($transaction->getRequest()->getBody()) {
                $transaction->getRequest()->getBody()->__toString();
            }

            $response = is_callable($this->response)
                ? call_user_func($this->response, $transaction)
                : $this->response;
            if (!$response instanceof puzzle_message_ResponseInterface) {
                throw new RuntimeException('Invalid mocked response');
            }

            $transaction->setResponse($response);
            puzzle_event_RequestEvents::emitHeaders($transaction);
            puzzle_event_RequestEvents::emitComplete($transaction);
        }

        return $transaction->getResponse();
    }
}

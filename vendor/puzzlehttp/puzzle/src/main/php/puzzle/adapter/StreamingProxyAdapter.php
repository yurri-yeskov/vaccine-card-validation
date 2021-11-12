<?php

/**
 * Sends streaming requests to a streaming compatible adapter while sending all
 * other requests to a default adapter.
 *
 * This, for example, could be useful for taking advantage of the performance
 * benefits of the puzzle_adapter_curl_CurlAdapter while still supporting true streaming through
 * the puzzle_adapter_StreamAdapter.
 */
class puzzle_adapter_StreamingProxyAdapter implements puzzle_adapter_AdapterInterface
{
    private $defaultAdapter;
    private $streamingAdapter;

    /**
     * @param puzzle_adapter_AdapterInterface $defaultAdapter   Adapter used for non-streaming responses
     * @param puzzle_adapter_AdapterInterface $streamingAdapter Adapter used for streaming responses
     */
    public function __construct(
        puzzle_adapter_AdapterInterface $defaultAdapter,
        puzzle_adapter_AdapterInterface $streamingAdapter
    ) {
        $this->defaultAdapter = $defaultAdapter;
        $this->streamingAdapter = $streamingAdapter;
    }

    public function send(puzzle_adapter_TransactionInterface $transaction)
    {
        $config = $transaction->getRequest()->getConfig();
        return $config['stream']
            ? $this->streamingAdapter->send($transaction)
            : $this->defaultAdapter->send($transaction);
    }
}

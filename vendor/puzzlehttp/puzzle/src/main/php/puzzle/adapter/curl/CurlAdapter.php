<?php

/**
 * HTTP adapter that uses cURL easy handles as a transport layer.
 *
 * Requires PHP 5.5+
 *
 * When using the puzzle_adapter_curl_CurlAdapter, custom curl options can be specified as an
 * associative array of curl option constants mapping to values in the
 * **curl** key of a request's configuration options.
 */
class puzzle_adapter_curl_CurlAdapter implements puzzle_adapter_AdapterInterface
{
    /** @var puzzle_adapter_curl_CurlFactory */
    private $curlFactory;

    /** @var puzzle_message_MessageFactoryInterface */
    private $messageFactory;

    /** @var array Array of curl easy handles */
    private $handles = array();

    /** @var array Array of owned curl easy handles */
    private $ownedHandles = array();

    /** @var int Total number of idle handles to keep in cache */
    private $maxHandles;

    /**
     * Accepts an associative array of options:
     *
     * - handle_factory: Optional callable factory used to create cURL handles.
     *   The callable is invoked with the following arguments:
     *   puzzle_adapter_TransactionInterface, puzzle_message_MessageFactoryInterface, and an optional cURL
     *   handle to modify. The factory method must then return a cURL resource.
     * - max_handles: Maximum number of idle handles (defaults to 5).
     *
     * @param puzzle_message_MessageFactoryInterface $messageFactory
     * @param array                                         $options Array of options to use with the adapter
     */
    public function __construct(
        puzzle_message_MessageFactoryInterface $messageFactory,
        array $options = array()
    ) {
        $this->handles = $this->ownedHandles = array();
        $this->messageFactory = $messageFactory;
        $this->curlFactory = isset($options['handle_factory'])
            ? $options['handle_factory']
            : new puzzle_adapter_curl_CurlFactory();
        $this->maxHandles = isset($options['max_handles'])
            ? $options['max_handles']
            : 5;
    }

    public function __destruct()
    {
        foreach ($this->handles as $handle) {
            if (is_resource($handle)) {
                curl_close($handle);
            }
        }
    }

    public function send(puzzle_adapter_TransactionInterface $transaction)
    {
        puzzle_event_RequestEvents::emitBefore($transaction);
        if ($response = $transaction->getResponse()) {
            return $response;
        }

        $factory = $this->curlFactory;
        $handle = $factory->__invoke(
            $transaction,
            $this->messageFactory,
            $this->checkoutEasyHandle()
        );

        curl_exec($handle);
        $info = curl_getinfo($handle);
        $info['curl_result'] = curl_errno($handle);

        if ($info['curl_result']) {
            $this->handleError($transaction, $info, $handle);
        } else {
            $this->releaseEasyHandle($handle);
            puzzle_event_RequestEvents::emitComplete($transaction, $info);
        }

        return $transaction->getResponse();
    }

    private function handleError(
        puzzle_adapter_TransactionInterface $transaction,
        $info,
        $handle
    ) {
        $error = curl_error($handle);
        $this->releaseEasyHandle($handle);
        puzzle_event_RequestEvents::emitError(
            $transaction,
            new puzzle_exception_AdapterException("cURL error {$info['curl_result']}: {$error}"),
            $info
        );
    }

    private function checkoutEasyHandle()
    {
        // Find an unused handle in the cache
        if (false !== ($key = array_search(false, $this->ownedHandles, true))) {
            $this->ownedHandles[$key] = true;
            return $this->handles[$key];
        }

        // Add a new handle
        $handle = curl_init();
        $id = (int) $handle;
        $this->handles[$id] = $handle;
        $this->ownedHandles[$id] = true;

        return $handle;
    }

    private function releaseEasyHandle($handle)
    {
        $id = (int) $handle;
        if (count($this->ownedHandles) > $this->maxHandles) {
            curl_close($this->handles[$id]);
            unset($this->handles[$id], $this->ownedHandles[$id]);
        } else {
            // curl_reset doesn't clear these out for some reason
            curl_setopt_array($handle, array(
                CURLOPT_HEADERFUNCTION   => null,
                CURLOPT_WRITEFUNCTION    => null,
                CURLOPT_READFUNCTION     => null,
                CURLOPT_PROGRESSFUNCTION => null
            ));
            curl_reset($handle);
            $this->ownedHandles[$id] = false;
        }
    }
}

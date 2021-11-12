<?php

/**
 * The Server class is used to control a scripted webserver using node.js that
 * will respond to HTTP requests with queued responses.
 *
 * Queued responses will be served to requests using a FIFO order.  All requests
 * received by the server are stored on the node.js server and can be retrieved
 * by calling {@see puzzle_test_Server::received()}.
 *
 * Mock responses that don't require data to be transmitted over HTTP a great
 * for testing.  Mock response, however, cannot test the actual sending of an
 * HTTP request using cURL.  This test server allows the simulation of any
 * number of HTTP request response transactions to test the actual sending of
 * requests over the wire without having to leave an internal network.
 */
class puzzle_test_Server
{
    const REQUEST_DELIMITER = "\n----[request]\n";

    /** @var puzzle_Client */
    private static $client;

    public static $started;
    public static $url = 'http://127.0.0.1:8125/';
    public static $port = 8125;

    /**
     * @var puzzle_message_MessageFactory
     */
    private static $_closure_receivedFactory;

    /**
     * Flush the received requests from the server
     * @throws RuntimeException
     */
    public static function flush()
    {
        self::start();

        return self::$client->delete('guzzle-server/requests');
    }

    /**
     * Queue an array of responses or a single response on the server.
     *
     * Any currently queued responses will be overwritten.  Subsequent requests
     * on the server will return queued responses in FIFO order.
     *
     * @param array|puzzle_message_ResponseInterface $responses A single or array of Responses
     *                                           to queue.
     * @throws Exception
     */
    public static function enqueue($responses)
    {
        static $factory;
        if (!$factory) {
            $factory = new puzzle_message_MessageFactory();
        }

        self::start();

        $data = array();
        foreach ((array) $responses as $response) {

            // Create the response object from a string
            if (is_string($response)) {
                $response = $factory->fromMessage($response);
            } elseif (!($response instanceof puzzle_message_ResponseInterface)) {
                throw new Exception('Responses must be strings or Responses');
            }

            $headers = array_map('puzzle_test_Server::__callback_enqueue', $response->getHeaders());

            $data[] = array(
                'statusCode'   => $response->getStatusCode(),
                'reasonPhrase' => $response->getReasonPhrase(),
                'headers'      => $headers,
                'body'         => base64_encode((string) $response->getBody())
            );
        }

        self::getClient()->put('guzzle-server/responses', array(
            'body' => json_encode($data)
        ));
    }

    /**
     * Get all of the received requests
     *
     * @param bool $hydrate Set to TRUE to turn the messages into
     *      actual {@see puzzle_message_RequestInterface} objects.  If $hydrate is FALSE,
     *      requests will be returned as strings.
     *
     * @return array
     * @throws RuntimeException
     */
    public static function received($hydrate = false)
    {
        if (!self::$started) {
            return array();
        }

        $response = self::getClient()->get('guzzle-server/requests');
        $data = array_filter(explode(self::REQUEST_DELIMITER, (string) $response->getBody()));
        if ($hydrate) {
            self::$_closure_receivedFactory = new puzzle_message_MessageFactory();
            $data = array_map('puzzle_test_Server::__callback_received', $data);
        }

        return $data;
    }

    /**
     * Stop running the node.js server
     */
    public static function stop()
    {
        if (self::$started) {
            self::getClient()->delete('guzzle-server');
        }

        self::$started = false;
    }

    public static function wait($maxTries = 5)
    {
        $tries = 0;
        while (!self::isListening() && ++$tries < $maxTries) {
            usleep(100000);
        }

        if (!self::isListening()) {
            throw new RuntimeException('Unable to contact node.js server');
        }
    }

    private static function start()
    {
        if (self::$started){
            return;
        }

        if (!self::isListening()) {
            exec('node ' . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'server.js '
                . self::$port . ' >> /tmp/server.log 2>&1 &');
            self::wait();
        }

        self::$started = true;
    }

    private static function isListening()
    {
        try {
            self::getClient()->get('guzzle-server/perf', array(
                'connect_timeout' => 5,
                'timeout'         => 5
            ));
            return true;
        } catch (Exception $e) {
            print $e->getMessage();
            return false;
        }
    }

    private static function getClient()
    {
        if (!self::$client) {
            self::$client = new puzzle_Client(array('base_url' => self::$url));
        }

        return self::$client;
    }

    public static function __callback_received($message)
    {
        return self::$_closure_receivedFactory->fromMessage($message);
    }

    public static function __callback_enqueue($h)
    {
        return implode(' ,', $h);
    }
}

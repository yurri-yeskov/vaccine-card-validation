<?php

require_once dirname(__FILE__) . '/AbstractCurl.php';

/**
 * @covers puzzle_adapter_curl_CurlAdapter
 */
class puzzle_test_adapter_curl_CurlAdapterTest extends puzzle_test_adapter_curl_AbstractCurl
{
    private $_closure_testCanInterceptBeforeSending_response;

    private $_closure_testHandlesCurlErrors_r;

    private $_closure_testReleasesAdditionalEasyHandles_client;

    protected function setUp()
    {
        if (!function_exists('curl_reset')) {
            $this->markTestSkipped('curl_reset() is not available');
        }
    }

    protected function getAdapter($factory = null, $options = array())
    {
        return new puzzle_adapter_curl_CurlAdapter($factory ? $factory : new puzzle_message_MessageFactory(), $options);
    }

    public function testCanSetMaxHandles()
    {
        $a = new puzzle_adapter_curl_CurlAdapter(new puzzle_message_MessageFactory(), array('max_handles' => 10));
        $this->assertEquals(10, $this->readAttribute($a, 'maxHandles'));
    }

    public function testCanInterceptBeforeSending()
    {
        $client = new puzzle_Client();
        $request = new puzzle_message_Request('GET', 'http://httpbin.org/get');
        $this->_closure_testCanInterceptBeforeSending_response = new puzzle_message_Response(200);
        $request->getEmitter()->on(
            'before',
            array($this, '__callback_testCanInterceptBeforeSending')
        );
        $transaction = new puzzle_adapter_Transaction($client, $request);
        $f = 'does_not_work';
        $a = new puzzle_adapter_curl_CurlAdapter(new puzzle_message_MessageFactory(), array('handle_factory' => $f));
        $a->send($transaction);
        $this->assertSame($this->_closure_testCanInterceptBeforeSending_response, $transaction->getResponse());
    }

    public function __callback_testCanInterceptBeforeSending(puzzle_event_BeforeEvent $e)
    {
        $e->intercept($this->_closure_testCanInterceptBeforeSending_response);
    }

    /**
     * @expectedException puzzle_exception_RequestException
     * @expectedExceptionMessage cURL error
     */
    public function testThrowsCurlErrors()
    {
        $client = new puzzle_Client();
        $request = $client->createRequest('GET', 'http://localhost:123', array(
            'connect_timeout' => 0.001,
            'timeout' => 0.001,
        ));
        $transaction = new puzzle_adapter_Transaction($client, $request);
        $a = new puzzle_adapter_curl_CurlAdapter(new puzzle_message_MessageFactory());
        $a->send($transaction);
    }

    public function testHandlesCurlErrors()
    {
        $client = new puzzle_Client();
        $request = $client->createRequest('GET', 'http://localhost:123', array(
            'connect_timeout' => 0.001,
            'timeout' => 0.001,
        ));
        $this->_closure_testHandlesCurlErrors_r = new puzzle_message_Response(200);
        $request->getEmitter()->on('error', array($this, '__callback_testHandlesCurlErrors'));
        $transaction = new puzzle_adapter_Transaction($client, $request);
        $a = new puzzle_adapter_curl_CurlAdapter(new puzzle_message_MessageFactory());
        $a->send($transaction);
        $this->assertSame($this->_closure_testHandlesCurlErrors_r, $transaction->getResponse());
    }

    public function testDoesNotSaveToWhenFailed()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array(
            "HTTP/1.1 500 Internal Server Error\r\nContent-Length: 0\r\n\r\n"
        ));

        $tmp = tempnam('/tmp', 'test_save_to');
        unlink($tmp);
        $a = new puzzle_adapter_curl_CurlAdapter(new puzzle_message_MessageFactory());
        $client = new puzzle_Client(array('base_url' => puzzle_test_Server::$url, 'adapter' => $a));
        try {
            $client->get('/', array('save_to' => $tmp));
        } catch (puzzle_exception_ServerException $e) {
            $this->assertFileNotExists($tmp);
        }
    }

    public function __callback_testHandlesCurlErrors(puzzle_event_ErrorEvent $e)
    {
        $e->intercept($this->_closure_testHandlesCurlErrors_r);
    }

    public function testReleasesAdditionalEasyHandles()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array(
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"
        ));
        $a = new puzzle_adapter_curl_CurlAdapter(new puzzle_message_MessageFactory(), array('max_handles' => 2));
        $this->_closure_testReleasesAdditionalEasyHandles_client = new puzzle_Client(array('base_url' => puzzle_test_Server::$url, 'adapter' => $a));
        $request = $this->_closure_testReleasesAdditionalEasyHandles_client->createRequest('GET', '/', array(
            'events' => array(
                'headers' => array($this, '__callback_testReleasesAdditionalEasyHandles_1')
            )
        ));
        $transaction = new puzzle_adapter_Transaction($this->_closure_testReleasesAdditionalEasyHandles_client, $request);
        $a->send($transaction);
        $this->assertCount(2, $this->readAttribute($a, 'handles'));
    }

    public function __callback_testReleasesAdditionalEasyHandles_1(puzzle_event_HeadersEvent $e)
    {
        $this->_closure_testReleasesAdditionalEasyHandles_client->get('/', array(
            'events' => array(
                'headers' => array($this, '__callback_testReleasesAdditionalEasyHandles_2')
            )
        ));
    }

    public function __callback_testReleasesAdditionalEasyHandles_2(puzzle_event_HeadersEvent $e)
    {
        $e->getClient()->get('/');
    }
}

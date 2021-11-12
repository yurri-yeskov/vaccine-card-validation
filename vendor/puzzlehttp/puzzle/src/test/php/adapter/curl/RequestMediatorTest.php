<?php

/**
 * @covers puzzle_adapter_curl_RequestMediator
 */
class puzzle_test_adapter_curl_RequestMediatorTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testSetsResponseBodyForDownload_ee;

    private $_closure_testEmitspuzzle_event_HeadersEventForHeadRequest_ee;

    public function testSetsResponseBodyForDownload()
    {
        $body = puzzle_stream_Stream::factory();
        $request = new puzzle_message_Request('GET', 'http://httbin.org');
        $this->_closure_testSetsResponseBodyForDownload_ee = null;
        $request->getEmitter()->on(
            'headers',
            array($this, '__callback_testSetsResponseBodyForDownload')
        );
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $m = new puzzle_adapter_curl_RequestMediator($t, new puzzle_message_MessageFactory());
        $m->setResponseBody($body);
        $this->assertEquals(18, $m->receiveResponseHeader(null, "HTTP/1.1 202 FOO\r\n"));
        $this->assertEquals(10, $m->receiveResponseHeader(null, "Foo: Bar\r\n"));
        $this->assertEquals(11, $m->receiveResponseHeader(null, "Baz : Bam\r\n"));
        $this->assertEquals(19, $m->receiveResponseHeader(null, "Content-Length: 3\r\n"));
        $this->assertEquals(2, $m->receiveResponseHeader(null, "\r\n"));
        $this->assertNotNull($this->_closure_testSetsResponseBodyForDownload_ee);
        $this->assertEquals(202, $t->getResponse()->getStatusCode());
        $this->assertEquals('FOO', $t->getResponse()->getReasonPhrase());
        $this->assertEquals('Bar', $t->getResponse()->getHeader('Foo'));
        $this->assertEquals('Bam', $t->getResponse()->getHeader('Baz'));
        $m->writeResponseBody(null, 'foo');
        $this->assertEquals('foo', (string) $body);
        $this->assertEquals('3', $t->getResponse()->getHeader('Content-Length'));
    }

    public function __callback_testSetsResponseBodyForDownload(puzzle_event_HeadersEvent $e)
    {
        $this->_closure_testSetsResponseBodyForDownload_ee = $e;
    }

    public function testSendsToNewBodyWhenNot2xxResponse()
    {
        $body = puzzle_stream_Stream::factory();
        $request = new puzzle_message_Request('GET', 'http://httbin.org');
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $m = new puzzle_adapter_curl_RequestMediator($t, new puzzle_message_MessageFactory());
        $m->setResponseBody($body);
        $this->assertEquals(27, $m->receiveResponseHeader(null, "HTTP/1.1 304 Not Modified\r\n"));
        $this->assertEquals(2, $m->receiveResponseHeader(null, "\r\n"));
        $this->assertEquals(304, $t->getResponse()->getStatusCode());
        $m->writeResponseBody(null, 'foo');
        $this->assertEquals('', (string) $body);
        $this->assertEquals('foo', (string) $t->getResponse()->getBody());
    }

    public function testUsesDefaultBodyIfNoneSet()
    {
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', 'http://httbin.org'));
        $t->setResponse(new puzzle_message_Response(200));
        $m = new puzzle_adapter_curl_RequestMediator($t, new puzzle_message_MessageFactory());
        $this->assertEquals(3, $m->writeResponseBody(null, 'foo'));
        $this->assertEquals('foo', (string) $t->getResponse()->getBody());
    }

    public function testCanUseResponseBody()
    {
        $body = puzzle_stream_Stream::factory();
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', 'http://httbin.org'));
        $t->setResponse(new puzzle_message_Response(200, array(), $body));
        $m = new puzzle_adapter_curl_RequestMediator($t, new puzzle_message_MessageFactory());
        $this->assertEquals(3, $m->writeResponseBody(null, 'foo'));
        $this->assertEquals('foo', (string) $body);
    }

    public function testHandlesTransactionWithNoResponseWhenWritingBody()
    {
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', 'http://httbin.org'));
        $m = new puzzle_adapter_curl_RequestMediator($t, new puzzle_message_MessageFactory());
        $this->assertEquals(0, $m->writeResponseBody(null, 'test'));
    }

    public function testReadsFromRequestBody()
    {
        $body = puzzle_stream_Stream::factory('foo');
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('PUT', 'http://httbin.org', array(), $body));
        $m = new puzzle_adapter_curl_RequestMediator($t, new puzzle_message_MessageFactory());
        $this->assertEquals('foo', $m->readRequestBody(null, null, 3));
    }

    public function testEmitspuzzle_event_HeadersEventForHeadRequest()
    {
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK"));
        $this->_closure_testEmitspuzzle_event_HeadersEventForHeadRequest_ee = null;
        $client = new puzzle_Client(array('adapter' => new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory())));
        $client->head(puzzle_test_Server::$url, array(
            'events' => array(
                'headers' => array($this, '__callback_testEmitspuzzle_event_HeadersEventForHeadRequest')
            )
        ));
        $this->assertInstanceOf('puzzle_event_HeadersEvent', $this->_closure_testEmitspuzzle_event_HeadersEventForHeadRequest_ee);
    }

    public function __callback_testEmitspuzzle_event_HeadersEventForHeadRequest(puzzle_event_HeadersEvent $e)
    {
        $this->_closure_testEmitspuzzle_event_HeadersEventForHeadRequest_ee = $e;
    }
}

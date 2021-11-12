<?php

/**
 * @covers puzzle_adapter_MockAdapter
 */
class puzzle_test_adapter_MockAdapterTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testMocksWithCallable_response;

    private $_closure_testHandlesErrors_c;

    private $_closure_testEmitsHeadersEvent_called;

    public function testYieldsMockResponse()
    {
        $response = new puzzle_message_Response(200);
        $m = new puzzle_adapter_MockAdapter();
        $m->setResponse($response);
        $this->assertSame($response, $m->send(new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', 'http://httbin.org'))));
    }

    public function testMocksWithCallable()
    {
        $this->_closure_testMocksWithCallable_response = new puzzle_message_Response(200);
        $r = array($this, '__callback_testMocksWithCallable');
        $m = new puzzle_adapter_MockAdapter($r);
        $this->assertSame($this->_closure_testMocksWithCallable_response, $m->send(new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', 'http://httbin.org'))));
    }

    public function __callback_testMocksWithCallable(puzzle_adapter_TransactionInterface $trans)
    {
        return $this->_closure_testMocksWithCallable_response;
    }

    /**
     * @expectedException RuntimeException
     */
    public function testValidatesResponses()
    {
        $m = new puzzle_adapter_MockAdapter();
        $m->setResponse('foo');
        $m->send(new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', 'http://httbin.org')));
    }

    public function testHandlesErrors()
    {
        $m = new puzzle_adapter_MockAdapter();
        $m->setResponse(new puzzle_message_Response(404));
        $request = new puzzle_message_Request('GET', 'http://httbin.org');
        $this->_closure_testHandlesErrors_c = false;
        $request->getEmitter()->once('complete', array($this, '__callback_testHandlesErrors_1'));
        $request->getEmitter()->on('error', array($this, '__callback_testHandlesErrors_2'));
        $r = $m->send(new puzzle_adapter_Transaction(new puzzle_Client(), $request));
        $this->assertTrue($this->_closure_testHandlesErrors_c);
        $this->assertEquals(201, $r->getStatusCode());
    }

    public function __callback_testHandlesErrors_1(puzzle_event_CompleteEvent $e)
    {
        $this->_closure_testHandlesErrors_c = true;
        throw new puzzle_exception_RequestException('foo', $e->getRequest());
    }

    public function __callback_testHandlesErrors_2(puzzle_event_ErrorEvent $e)
    {
        $e->intercept(new puzzle_message_Response(201));
    }

    /**
     * @expectedException puzzle_exception_RequestException
     */
    public function testThrowsUnhandledErrors()
    {
        $m = new puzzle_adapter_MockAdapter();
        $m->setResponse(new puzzle_message_Response(404));
        $request = new puzzle_message_Request('GET', 'http://httbin.org');
        $request->getEmitter()->once('complete', array($this, '__callback_testThrowsUnhandledErrors'));
        $m->send(new puzzle_adapter_Transaction(new puzzle_Client(), $request));
    }

    public function __callback_testThrowsUnhandledErrors(puzzle_event_CompleteEvent $e)
    {
        throw new puzzle_exception_RequestException('foo', $e->getRequest());
    }

    public function testReadsRequestBody()
    {
        $response = new puzzle_message_Response(200);
        $m = new puzzle_adapter_MockAdapter($response);
        $m->setResponse($response);
        $body = puzzle_stream_Stream::factory('foo');
        $request = new puzzle_message_Request('PUT', 'http://httpbin.org/put', array(), $body);
        $this->assertSame($response, $m->send(new puzzle_adapter_Transaction(new puzzle_Client(), $request)));
        $this->assertEquals(3, $body->tell());
    }

    public function testEmitsHeadersEvent()
    {
        $m = new puzzle_adapter_MockAdapter(new puzzle_message_Response(404));
        $request = new puzzle_message_Request('GET', 'http://httbin.org');
        $this->_closure_testEmitsHeadersEvent_called = false;
        $request->getEmitter()->once('headers', array($this, '__callback_testEmitsHeadersEvent'));
        $m->send(new puzzle_adapter_Transaction(new puzzle_Client(), $request));
        $this->assertTrue($this->_closure_testEmitsHeadersEvent_called);
    }

    public function __callback_testEmitsHeadersEvent()
    {
        $this->_closure_testEmitsHeadersEvent_called = true;
    }
}

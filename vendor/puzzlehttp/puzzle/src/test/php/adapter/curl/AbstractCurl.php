<?php

abstract class puzzle_test_adapter_curl_AbstractCurl extends PHPUnit_Framework_TestCase
{
    abstract protected function getAdapter($factory = null, $options = array());

    private $_closure_testDispatchesAfterSendEvent_ev;

    public function testSendsRequest()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nFoo: bar\r\nContent-Length: 0\r\n\r\n");
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', puzzle_test_Server::$url));
        $a = $this->getAdapter();
        $response = $a->send($t);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('bar', $response->getHeader('Foo'));
    }

    /**
     * @expectedException puzzle_exception_RequestException
     */
    public function testCatchesErrorWhenPreparing()
    {
        $r = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $f = $this->getMockBuilder('puzzle_adapter_curl_CurlFactory')
            ->setMethods(array('__invoke'))
            ->getMock();
        $f->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new puzzle_exception_RequestException('foo', $r)));

        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $r);
        $a = $this->getAdapter(null, array('handle_factory' => $f));
        $a->send($t);
    }

    public function testDispatchesAfterSendEvent()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 201 OK\r\nContent-Length: 0\r\n\r\n");
        $r = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $r);
        $a = $this->getAdapter();
        $this->_closure_testDispatchesAfterSendEvent_ev = null;
        $r->getEmitter()->on('complete', array($this, '__callback_testDispatchesAfterSendEvent'));
        $response = $a->send($t);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('bar', $response->getHeader('Foo'));
    }

    public function __callback_testDispatchesAfterSendEvent(puzzle_event_CompleteEvent $e)
    {
        $this->_closure_testDispatchesAfterSendEvent_ev = $e;
        $e->intercept(new puzzle_message_Response(200, array('Foo' => 'bar')));
    }

    public function testDispatchesErrorEventAndRecovers()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 201 OK\r\nContent-Length: 0\r\n\r\n");
        $r = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $r);
        $a = $this->getAdapter();
        $r->getEmitter()->once('complete', array($this, '__callback_testDispatchesErrorEventAndRecovers_1'));
        $r->getEmitter()->on('error', array($this, '__callback_testDispatchesErrorEventAndRecovers_2'));
        $response = $a->send($t);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('bar', $response->getHeader('Foo'));
    }

    public function __callback_testDispatchesErrorEventAndRecovers_1(puzzle_event_CompleteEvent $e)
    {
        throw new puzzle_exception_RequestException('Foo', $e->getRequest());
    }

    public function __callback_testDispatchesErrorEventAndRecovers_2(puzzle_event_ErrorEvent $e)
    {
        $e->intercept(new puzzle_message_Response(200, array('Foo' => 'bar')));
    }

    public function testStripsFragmentFromHost()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\n\r\nContent-Length: 0\r\n\r\n");
        // This will fail if the removal of the #fragment is not performed
        $url = puzzle_Url::fromString(puzzle_test_Server::$url)->setPath(null)->setFragment('foo');
        $client = new puzzle_Client();
        $client->get($url);
    }
}

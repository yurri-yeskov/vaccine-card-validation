<?php

require_once dirname(__FILE__) . '/AbstractCurl.php';

/**
 * @covers puzzle_adapter_curl_MultiAdapter
 */
class puzzle_test_adapter_curl_MultiAdapterTest extends puzzle_test_adapter_curl_AbstractCurl
{
    private $_closure_testCreatesAndReleasesHandlesWhenNeeded_a;
    private $_closure_testCreatesAndReleasesHandlesWhenNeeded_c;
    private $_closure_testCreatesAndReleasesHandlesWhenNeeded_ef;
    private $_closure_testCreatesAndReleasesHandlesWhenNeeded_r;

    private $_closure_testEnsuresResponseWasSetForGet_er;
    private $_closure_testEnsuresResponseWasSetForGet_response;

    private $_closure_runConnectionTest_er;
    private $_closure_runConnectionTest_obj;
    private $_closure_runConnectionTest_called;

    protected function getAdapter($factory = null, $options = array())
    {
        return new puzzle_adapter_curl_MultiAdapter($factory ? $factory : new puzzle_message_MessageFactory(), $options);
    }

    public function testSendsSingleRequest()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nFoo: bar\r\nContent-Length: 0\r\n\r\n");
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', puzzle_test_Server::$url));
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        $response = $a->send($t);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('bar', $response->getHeader('Foo'));
    }

    public function testCanSetSelectTimeout()
    {
        $current = isset($_SERVER[puzzle_adapter_curl_MultiAdapter::ENV_SELECT_TIMEOUT])
            ? $_SERVER[puzzle_adapter_curl_MultiAdapter::ENV_SELECT_TIMEOUT]: null;
        unset($_SERVER[puzzle_adapter_curl_MultiAdapter::ENV_SELECT_TIMEOUT]);
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        $this->assertEquals(1, $this->readAttribute($a, 'selectTimeout'));
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory(), array('select_timeout' => 10));
        $this->assertEquals(10, $this->readAttribute($a, 'selectTimeout'));
        $_SERVER[puzzle_adapter_curl_MultiAdapter::ENV_SELECT_TIMEOUT] = 2;
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        $this->assertEquals(2, $this->readAttribute($a, 'selectTimeout'));
        $_SERVER[puzzle_adapter_curl_MultiAdapter::ENV_SELECT_TIMEOUT] = $current;
    }

    /**
     * @expectedException puzzle_exception_AdapterException
     * @expectedExceptionMessage cURL error -2:
     */
    public function testChecksCurlMultiResult()
    {
        puzzle_adapter_curl_MultiAdapter::throwMultiError(-2);
    }

    public function testChecksForCurlException()
    {
        $mh = curl_multi_init();
        $request = new puzzle_message_Request('GET', 'http://httbin.org');
        $transaction = $this->getMockBuilder('puzzle_adapter_Transaction')
            ->setMethods(array('getRequest'))
            ->disableOriginalConstructor()
            ->getMock();
        $transaction->expects($this->exactly(2))
            ->method('getRequest')
            ->will($this->returnValue($request));
        $context = $this->getMockBuilder('puzzle_adapter_curl_BatchContext')
            ->setMethods(array('throwsExceptions'))
            ->setConstructorArgs(array($mh, true))
            ->getMock();
        $context->expects($this->once())
            ->method('throwsExceptions')
            ->will($this->returnValue(true));
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        $r = new ReflectionMethod($a, '__isCurlException');
        try {
            $r->invoke($a, $transaction, array('result' => -10), $context, array());
            curl_multi_close($mh);
            $this->fail('Did not throw');
        } catch (puzzle_exception_RequestException $e) {
            curl_multi_close($mh);
            $this->assertSame($request, $e->getRequest());
            $this->assertContains('[curl] (#-10) ', $e->getMessage());
            $this->assertContains($request->getUrl(), $e->getMessage());
        }
    }

    public function testSendsParallelRequestsFromQueue()
    {
        $c = new puzzle_Client();
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array(
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"
        ));
        $transactions = array(
            new puzzle_adapter_Transaction($c, new puzzle_message_Request('GET', puzzle_test_Server::$url)),
            new puzzle_adapter_Transaction($c, new puzzle_message_Request('PUT', puzzle_test_Server::$url)),
            new puzzle_adapter_Transaction($c, new puzzle_message_Request('HEAD', puzzle_test_Server::$url)),
            new puzzle_adapter_Transaction($c, new puzzle_message_Request('GET', puzzle_test_Server::$url))
        );
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        $a->sendAll(new ArrayIterator($transactions), 2);
        foreach ($transactions as $t) {
            $response = $t->getResponse();
            $this->assertNotNull($response);
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function testCreatesAndReleasesHandlesWhenNeeded()
    {
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c = new puzzle_Client(array(
            'adapter'  => $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_a,
            'base_url' => puzzle_test_Server::$url
        ));

        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array(
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
        ));

        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_ef = array($this, '__callback_testCreatesAndReleasesHandlesWhenNeeded_3');

        $request1 = $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c->createRequest('GET', '/');
        $request1->getEmitter()->on('headers', array($this, '__callback_testCreatesAndReleasesHandlesWhenNeeded'));

        $request1->getEmitter()->on('error', $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_ef);

        $transactions = array(
            new puzzle_adapter_Transaction($this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c, $request1),
            new puzzle_adapter_Transaction($this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c, $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c->createRequest('PUT')),
            new puzzle_adapter_Transaction($this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c, $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c->createRequest('HEAD'))
        );

        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_a->sendAll(new ArrayIterator($transactions), 2);

        foreach ($transactions as $index => $t) {
            $response = $t->getResponse();
            $this->assertInstanceOf(
                'puzzle_message_ResponseInterface',
                $response,
                'Transaction at index ' . $index . ' did not populate response'
            );
            $this->assertEquals(200, $response->getStatusCode());
        }
    }

    public function __callback_testCreatesAndReleasesHandlesWhenNeeded_3(puzzle_event_ErrorEvent $e)
    {
        throw $e->getException();
    }

    public function __callback_testCreatesAndReleasesHandlesWhenNeeded()
    {
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_a->send(new puzzle_adapter_Transaction($this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c, $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c->createRequest('GET', '/', array(
            'events' => array(
                'headers' => array($this, '__callback_testCreatesAndReleasesHandlesWhenNeeded_1'),
                'error' => array('fn' => $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_ef, 'priority' => 9999)
            )
        ))));
    }

    public function __callback_testCreatesAndReleasesHandlesWhenNeeded_1()
    {
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_r = $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c->createRequest('GET', '/', array(
            'events' => array('error' => array('fn' => $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_ef, 'priority' => 9999))
        ));
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_r->getEmitter()->once('headers', array($this, '__callback_testCreatesAndReleasesHandlesWhenNeeded_2'));
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_a->send(new puzzle_adapter_Transaction($this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c, $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_r));
        // Now, reuse an existing handle
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_a->send(new puzzle_adapter_Transaction($this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c, $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_r));
    }

    public function __callback_testCreatesAndReleasesHandlesWhenNeeded_2()
    {
        $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_a->send(new puzzle_adapter_Transaction($this->_closure_testCreatesAndReleasesHandlesWhenNeeded_c, $this->_closure_testCreatesAndReleasesHandlesWhenNeeded_r));
    }

    public function testThrowsAndReleasesWhenErrorDuringCompleteEvent()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 500 Internal Server Error\r\nContent-Length: 0\r\n\r\n");
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $request->getEmitter()->on('complete', array($this, '__callback_testThrowsAndReleasesWhenErrorDuringCompleteEvent'));
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        try {
            $a->send($t);
            $this->fail('Did not throw');
        } catch (puzzle_exception_RequestException $e) {
            $this->assertSame($request, $e->getRequest());
        }
    }

    public function __callback_testThrowsAndReleasesWhenErrorDuringCompleteEvent(puzzle_event_CompleteEvent $e)
    {
        throw new puzzle_exception_RequestException('foo', $e->getRequest());
    }

    public function testEnsuresResponseWasSetForGet()
    {
        $client = new puzzle_Client();
        $request = $client->createRequest('GET', puzzle_test_Server::$url);
        $this->_closure_testEnsuresResponseWasSetForGet_response = new puzzle_message_Response(200, array());
        $this->_closure_testEnsuresResponseWasSetForGet_er = null;

        $request->getEmitter()->on(
            'error',
            array($this, '__callback_testEnsuresResponseWasSetForGet_1')
        );

        $transaction = $this->getMockBuilder('puzzle_adapter_Transaction')
            ->setMethods(array('getResponse', 'setResponse'))
            ->setConstructorArgs(array($client, $request))
            ->getMock();
        $transaction->expects($this->any())->method('setResponse');
        $transaction->expects($this->any())
            ->method('getResponse')
            ->will($this->returnCallback(array($this, '__callback_testEnsuresResponseWasSetForGet_2')));

        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"));
        $a->sendAll(new ArrayIterator(array($transaction)), 10);
        $this->assertNotNull($this->_closure_testEnsuresResponseWasSetForGet_er);

        $this->assertContains(
            'No response was received',
            $this->_closure_testEnsuresResponseWasSetForGet_er->getException()->getMessage()
        );
    }

    public function __callback_testEnsuresResponseWasSetForGet_1(puzzle_event_ErrorEvent $e)
    {
        $this->_closure_testEnsuresResponseWasSetForGet_er = $e;
    }

    public function __callback_testEnsuresResponseWasSetForGet_2()
    {
        $backTrace = debug_backtrace();
        $caller = $backTrace[6]['function'];
        return $caller == 'addHandle' ||
        $caller == 'validateResponseWasSet'
            ? null
            : $this->_closure_testEnsuresResponseWasSetForGet_response;
    }

    private function runConnectionTest(
        $queue,
        $stream,
        $msg,
        $statusCode = null
    ) {
        $this->_closure_runConnectionTest_obj = new stdClass();
        $this->_closure_runConnectionTest_er = null;
        $client = new puzzle_Client();
        $request = $client->createRequest('PUT', puzzle_test_Server::$url, array(
            'body' => $stream
        ));

        $request->getEmitter()->on(
            'error',
            array($this, '__callback_runConnectionTest_1')
        );

        $transaction = $this->getMockBuilder('puzzle_adapter_Transaction')
            ->setMethods(array('getResponse', 'setResponse'))
            ->setConstructorArgs(array($client, $request))
            ->getMock();

        $transaction->expects($this->any())
            ->method('setResponse')
            ->will($this->returnCallback(array($this, '__callback_runConnectionTest_2')));

        $this->_closure_runConnectionTest_called = 0;
        $transaction->expects($this->any())
            ->method('getResponse')
            ->will($this->returnCallback(array($this, '__callback_runConnectionTest_3')));

        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue($queue);
        $a->sendAll(new ArrayIterator(array($transaction)), 10);

        if ($msg) {
            $this->assertNotNull($this->_closure_runConnectionTest_er);
            $this->assertContains($msg, $this->_closure_runConnectionTest_er->getException()->getMessage());
        } else {
            $this->assertEquals(
                $statusCode,
                $transaction->getResponse()->getStatusCode()
            );
        }
    }

    public function __callback_runConnectionTest_3()
    {
        $debugBackTrace = debug_backtrace();
        $caller = $debugBackTrace[6]['function'];
        if ($caller == 'addHandle') {
            return null;
        } elseif ($caller == 'validateResponseWasSet') {
            return ++$this->_closure_runConnectionTest_called == 2 ? $this->_closure_runConnectionTest_obj->res : null;
        } else {
            return $this->_closure_runConnectionTest_obj->res;
        }
    }

    public function __callback_runConnectionTest_2($r)
    {
        $this->_closure_runConnectionTest_obj->res = $r;
    }

    public function __callback_runConnectionTest_1(puzzle_event_ErrorEvent $e)
    {
        $this->_closure_runConnectionTest_er = $e;
    }

    public function testThrowsWhenTheBodyCannotBeRewound()
    {
        $this->runConnectionTest(
            array("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"),
            new puzzle_stream_NoSeekStream(puzzle_stream_Stream::factory('foo')),
            'attempting to rewind the request body failed'
        );
    }

    public function testRetriesRewindableStreamsWhenClosedConnectionErrors()
    {
        $this->runConnectionTest(
            array(
                "HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n",
                "HTTP/1.1 201 OK\r\nContent-Length: 0\r\n\r\n",
            ),
            puzzle_stream_Stream::factory('foo'),
            false,
            201
        );
    }

    public function testThrowsImmediatelyWhenInstructed()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 501\r\nContent-Length: 0\r\n\r\n"));
        $c = new puzzle_Client(array('base_url' => puzzle_test_Server::$url));
        $request = $c->createRequest('GET', '/');
        $request->getEmitter()->on('error', array($this, '__callback_testThrowsImmediatelyWhenInstructed'));
        $transactions = array(new puzzle_adapter_Transaction($c, $request));
        $a = new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory());
        try {
            $a->sendAll(new ArrayIterator($transactions), 1);
            $this->fail('Did not throw');
        } catch (puzzle_exception_RequestException $e) {
            $this->assertSame($request, $e->getRequest());
        }
    }

    public function __callback_testThrowsImmediatelyWhenInstructed(puzzle_event_ErrorEvent $e)
    {
        $e->throwImmediately(true);
    }
}

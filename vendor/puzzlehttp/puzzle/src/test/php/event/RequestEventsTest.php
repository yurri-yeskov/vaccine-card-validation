<?php

/**
 * @covers puzzle_event_RequestEvents
 */
class puzzle_test_event_RequestEventsTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testEmitsAfterSendEvent_res;

    private $_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex;
    private $_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex2;

    private $_closure_testBeforeSendEmitsErrorEvent_request;
    private $_closure_testBeforeSendEmitsErrorEvent_client;
    private $_closure_testBeforeSendEmitsErrorEvent_beforeCalled;
    private $_closure_testBeforeSendEmitsErrorEvent_ex;
    private $_closure_testBeforeSendEmitsErrorEvent_errCalled;
    private $_closure_testBeforeSendEmitsErrorEvent_response;

    private $_closure_testThrowsUnInterceptedErrors_ex;
    private $_closure_testThrowsUnInterceptedErrors_errCalled;

    private $_closure_testDoesNotEmitErrorEventTwice_r;

    private $_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_ex;
    private $_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_called;

    public function testEmitsAfterSendEvent()
    {
        $this->_closure_testEmitsAfterSendEvent_res = null;
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/'));
        $t->setResponse(new puzzle_message_Response(200));
        $t->getRequest()->getEmitter()->on('complete', array($this, '__callback_testEmitsAfterSendEvent'));
        puzzle_event_RequestEvents::emitComplete($t);
        $this->assertSame($this->_closure_testEmitsAfterSendEvent_res->getClient(), $t->getClient());
        $this->assertSame($this->_closure_testEmitsAfterSendEvent_res->getRequest(), $t->getRequest());
        $this->assertEquals('/', $t->getResponse()->getEffectiveUrl());
    }

    public function __callback_testEmitsAfterSendEvent($e)
    {
        $this->_closure_testEmitsAfterSendEvent_res = $e;
    }

    public function testEmitsAfterSendEventAndEmitsErrorIfNeeded()
    {
        $this->_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex2 = $res = null;
        $request = new puzzle_message_Request('GET', '/');
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $t->setResponse(new puzzle_message_Response(200));
        $this->_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex = new puzzle_exception_RequestException('foo', $request);
        $t->getRequest()->getEmitter()->on('complete', array($this, '__callback_testEmitsAfterSendEventAndEmitsErrorIfNeeded_1'));
        $t->getRequest()->getEmitter()->on('error', array($this, '__callback_testEmitsAfterSendEventAndEmitsErrorIfNeeded_2'));
        puzzle_event_RequestEvents::emitComplete($t);
        $this->assertSame($this->_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex, $this->_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex2);
    }

    public function __callback_testEmitsAfterSendEventAndEmitsErrorIfNeeded_1($e)
    {
        $this->_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex->e = $e;
        throw $this->_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex;
    }

    public function __callback_testEmitsAfterSendEventAndEmitsErrorIfNeeded_2($e)
    {
        $this->_closure_testEmitsAfterSendEventAndEmitsErrorIfNeeded_ex2 = $e->getException();
        $e->stopPropagation();
    }

    public function testBeforeSendEmitsErrorEvent()
    {
        $this->_closure_testBeforeSendEmitsErrorEvent_ex = new Exception('Foo');
        $this->_closure_testBeforeSendEmitsErrorEvent_client = new puzzle_Client();
        $this->_closure_testBeforeSendEmitsErrorEvent_request = new puzzle_message_Request('GET', '/');
        $this->_closure_testBeforeSendEmitsErrorEvent_response = new puzzle_message_Response(200);
        $t = new puzzle_adapter_Transaction($this->_closure_testBeforeSendEmitsErrorEvent_client, $this->_closure_testBeforeSendEmitsErrorEvent_request);
        $this->_closure_testBeforeSendEmitsErrorEvent_beforeCalled = $this->_closure_testBeforeSendEmitsErrorEvent_errCalled = 0;

        $this->_closure_testBeforeSendEmitsErrorEvent_request->getEmitter()->on(
            'before',
            array($this, '__callback_testBeforeSendEmitsErrorEvent_1')
        );

        $this->_closure_testBeforeSendEmitsErrorEvent_request->getEmitter()->on(
            'error',
            array($this, '__callback_testBeforeSendEmitsErrorEvent_2')
        );

        puzzle_event_RequestEvents::emitBefore($t);
        $this->assertEquals(1, $this->_closure_testBeforeSendEmitsErrorEvent_beforeCalled);
        $this->assertEquals(1, $this->_closure_testBeforeSendEmitsErrorEvent_errCalled);
        $this->assertSame($this->_closure_testBeforeSendEmitsErrorEvent_response, $t->getResponse());
    }

    public function __callback_testBeforeSendEmitsErrorEvent_1(puzzle_event_BeforeEvent $e)
    {
        $this->assertSame($this->_closure_testBeforeSendEmitsErrorEvent_request, $e->getRequest());
        $this->assertSame($this->_closure_testBeforeSendEmitsErrorEvent_client, $e->getClient());
        $this->_closure_testBeforeSendEmitsErrorEvent_beforeCalled++;
        throw $this->_closure_testBeforeSendEmitsErrorEvent_ex;
    }

    public function __callback_testBeforeSendEmitsErrorEvent_2(puzzle_event_ErrorEvent $e)
    {
        $this->_closure_testBeforeSendEmitsErrorEvent_errCalled++;
        $this->assertInstanceOf('puzzle_exception_RequestException', $e->getException());
        if (version_compare(PHP_VERSION, '5.3') >= 0) {

            $this->assertSame($this->_closure_testBeforeSendEmitsErrorEvent_ex, $e->getException()->getPrevious());
        }
        $e->intercept($this->_closure_testBeforeSendEmitsErrorEvent_response);
    }

    public function testThrowsUnInterceptedErrors()
    {
        $this->_closure_testThrowsUnInterceptedErrors_ex = new Exception('Foo');
        $client = new puzzle_Client();
        $request = new puzzle_message_Request('GET', '/');
        $t = new puzzle_adapter_Transaction($client, $request);
        $this->_closure_testThrowsUnInterceptedErrors_errCalled = 0;

        $request->getEmitter()->on('before', array($this, '__callback_testThrowsUnInterceptedErrors'));
        $request->getEmitter()->on('error', array($this, '__callback_testThrowsUnInterceptedErrors_2'));

        try {
            puzzle_event_RequestEvents::emitBefore($t);
            $this->fail('Did not throw');
        } catch (puzzle_exception_RequestException $e) {
            $this->assertEquals(1, $this->_closure_testThrowsUnInterceptedErrors_errCalled);
        }
    }

    public function __callback_testThrowsUnInterceptedErrors_1(puzzle_event_BeforeEvent $e)
    {
        throw $this->_closure_testThrowsUnInterceptedErrors_ex;
    }

    public function __callback_testThrowsUnInterceptedErrors_2(puzzle_event_ErrorEvent $e)
    {
        $this->_closure_testThrowsUnInterceptedErrors_errCalled++;
    }

    public function testDoesNotEmitErrorEventTwice()
    {
        $client = new puzzle_Client();
        $mock = new puzzle_subscriber_Mock(array(new puzzle_message_Response(500)));
        $client->getEmitter()->attach($mock);

        $this->_closure_testDoesNotEmitErrorEventTwice_r = array();
        $client->getEmitter()->on('error', array($this, '__callback_testDoesNotEmitErrorEventTwice'));

        try {
            $client->get('http://foo.com');
            $this->fail('Did not throw');
        } catch (puzzle_exception_RequestException $e) {
            $this->assertCount(1, $this->_closure_testDoesNotEmitErrorEventTwice_r);
        }
    }

    public function __callback_testDoesNotEmitErrorEventTwice(puzzle_event_ErrorEvent $event)
    {
        $this->_closure_testDoesNotEmitErrorEventTwice_r[] = $event->getRequest();
    }

    /**
     * Note: Longest test name ever.
     */
    public function testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent()
    {
        $request = new puzzle_message_Request('GET', '/');
        $this->_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_ex = new puzzle_exception_RequestException('foo', $request);

        $client = new puzzle_Client();
        $client->getEmitter()->on('before', array($this, '__callback_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_1'));
        $this->_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_called = false;
        $client->getEmitter()->on('error', array($this, '__callback_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_2'));

        try {
            $client->get('http://foo.com');
            $this->fail('Did not throw');
        } catch (puzzle_exception_RequestException $e) {
            $this->assertTrue($this->_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_called);
        }
    }

    public function __callback_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_1(puzzle_event_BeforeEvent $event)
    {
        throw $this->_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_ex;
    }

    public function __callback_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_2(puzzle_event_ErrorEvent $event)
    {
        $this->_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_called = true;
        $this->assertSame($this->_closure_testEmitsErrorEventForRequestExceptionsThrownDuringBeforeThatHaveNotEmittedAnErrorEvent_ex, $event->getException());
    }

    public function prepareEventProvider()
    {
        $cb = array($this, '__callback_empty');

        return array(
            array(array(), array('complete'), $cb, array('complete' => array($cb))),
            array(
                array('complete' => $cb),
                array('complete'),
                $cb,
                array('complete' => array($cb, $cb))
            ),
            array(
                array('prepare' => array()),
                array('error', 'foo'),
                $cb,
                array(
                    'prepare' => array(),
                    'error'   => array($cb),
                    'foo'     => array($cb)
                )
            ),
            array(
                array('prepare' => array()),
                array('prepare'),
                $cb,
                array(
                    'prepare' => array($cb)
                )
            ),
            array(
                array('prepare' => array('fn' => $cb)),
                array('prepare'), $cb,
                array(
                    'prepare' => array(
                        array('fn' => $cb),
                        $cb
                    )
                )
            ),
        );
    }

    public function __callback_empty()
    {

    }

    /**
     * @dataProvider prepareEventProvider
     */
    public function testConvertsEventArrays(
        array $in,
        array $events,
        $add,
        array $out
    ) {
        $result = puzzle_event_RequestEvents::convertEventArray($in, $events, $add);
        $this->assertEquals($out, $result);
    }
}

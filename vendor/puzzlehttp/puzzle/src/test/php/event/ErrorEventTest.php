<?php

/**
 * @covers puzzle_event_ErrorEvent
 */
class puzzle_test_event_ErrorEventTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testInterceptsWithEvent_res;

    public function testInterceptsWithEvent()
    {
        $client = new puzzle_Client();
        $request = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(404);
        $transaction = new puzzle_adapter_Transaction($client, $request);
        $except = new puzzle_exception_RequestException('foo', $request, $response);
        $event = new puzzle_event_ErrorEvent($transaction, $except);

        $event->throwImmediately(true);
        $this->assertTrue($except->getThrowImmediately());
        $event->throwImmediately(false);
        $this->assertFalse($except->getThrowImmediately());

        $this->assertSame($except, $event->getException());
        $this->assertSame($response, $event->getResponse());
        $this->assertSame($request, $event->getRequest());

        $this->_closure_testInterceptsWithEvent_res = null;
        $request->getEmitter()->on('complete', array($this, '__callback_testInterceptsWithEvent'));

        $good = new puzzle_message_Response(200);
        $event->intercept($good);
        $this->assertTrue($event->isPropagationStopped());
        $this->assertSame($this->_closure_testInterceptsWithEvent_res->getClient(), $event->getClient());
        $this->assertSame($good, $this->_closure_testInterceptsWithEvent_res->getResponse());
    }

    public function __callback_testInterceptsWithEvent($e)
    {
        $this->_closure_testInterceptsWithEvent_res = $e;
    }
}

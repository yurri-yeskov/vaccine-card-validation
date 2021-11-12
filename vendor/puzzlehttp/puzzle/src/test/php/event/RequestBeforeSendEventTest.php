<?php

/**
 * @covers puzzle_event_BeforeEvent
 */
class puzzle_test_event_BeforeEventTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testInterceptsWithEvent_res;

    public function testInterceptsWithEvent()
    {
        $response = new puzzle_message_Response(200);
        $this->_closure_testInterceptsWithEvent_res = null;
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/'));
        $t->getRequest()->getEmitter()->on('complete', array($this, '__callback_testInterceptsWithEvent'));
        $e = new puzzle_event_BeforeEvent($t);
        $e->intercept($response);
        $this->assertTrue($e->isPropagationStopped());
        $this->assertSame($this->_closure_testInterceptsWithEvent_res->getClient(), $e->getClient());
    }

    public function __callback_testInterceptsWithEvent($e)
    {
        $this->_closure_testInterceptsWithEvent_res = $e;
    }
}

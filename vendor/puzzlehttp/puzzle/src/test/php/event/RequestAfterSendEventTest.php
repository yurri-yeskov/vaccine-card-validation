<?php

/**
 * @covers puzzle_event_CompleteEvent
 */
class puzzle_test_event_CompleteEventTest extends PHPUnit_Framework_TestCase
{
    public function testHasValues()
    {
        $c = new puzzle_Client();
        $r = new puzzle_message_Request('GET', '/');
        $res = new puzzle_message_Response(200);
        $t = new puzzle_adapter_Transaction($c, $r);
        $e = new puzzle_event_CompleteEvent($t);
        $e->intercept($res);
        $this->assertTrue($e->isPropagationStopped());
        $this->assertSame($res, $e->getResponse());
    }
}

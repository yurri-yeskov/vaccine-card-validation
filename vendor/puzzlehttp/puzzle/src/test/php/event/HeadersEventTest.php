<?php

/**
 * @covers puzzle_event_HeadersEvent
 */
class puzzle_test_event_HeadersEventTest extends PHPUnit_Framework_TestCase
{
    public function testHasValues()
    {
        $c = new puzzle_Client();
        $r = new puzzle_message_Request('GET', '/');
        $t = new puzzle_adapter_Transaction($c, $r);
        $response = new puzzle_message_Response(200);
        $t->setResponse($response);
        $e = new puzzle_event_HeadersEvent($t);
        $this->assertSame($c, $e->getClient());
        $this->assertSame($r, $e->getRequest());
        $this->assertSame($response, $e->getResponse());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testEnsuresResponseIsSet()
    {
        $c = new puzzle_Client();
        $r = new puzzle_message_Request('GET', '/');
        $t = new puzzle_adapter_Transaction($c, $r);
        new puzzle_event_HeadersEvent($t);
    }
}

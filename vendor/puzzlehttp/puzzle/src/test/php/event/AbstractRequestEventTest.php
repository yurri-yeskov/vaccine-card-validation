<?php

/**
 * @covers puzzle_event_AbstractRequestEvent
 */
class puzzle_test_event_AbstractRequestEventTest extends PHPUnit_Framework_TestCase
{
    public function testHasTransactionMethods()
    {
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/'));
        $e = $this->getMockBuilder('puzzle_event_AbstractRequestEvent')
            ->setConstructorArgs(array($t))
            ->getMockForAbstractClass();
        $this->assertSame($t->getClient(), $e->getClient());
        $this->assertSame($t->getRequest(), $e->getRequest());
    }

    public function testHasTransaction()
    {
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/'));
        $e = $this->getMockBuilder('puzzle_event_AbstractRequestEvent')
            ->setConstructorArgs(array($t))
            ->getMockForAbstractClass();
        $r = new ReflectionMethod($e, '__getTransaction');
        $this->assertSame($t, $r->invoke($e));
    }
}

<?php

/**
 * @covers puzzle_event_AbstractTransferEvent
 */
class puzzle_test_event_AbstractTransferEventTest extends PHPUnit_Framework_TestCase
{
    public function testHasStats()
    {
        $s = array('foo' => 'bar');
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/'));
        $e = $this->getMockBuilder('puzzle_event_AbstractTransferEvent')
            ->setConstructorArgs(array($t, $s))
            ->getMockForAbstractClass();
        $this->assertNull($e->getTransferInfo('baz'));
        $this->assertEquals('bar', $e->getTransferInfo('foo'));
        $this->assertEquals($s, $e->getTransferInfo());
    }
}

<?php

class puzzle_test_event_AbstractEventTest extends PHPUnit_Framework_TestCase
{
    public function testStopsPropagation()
    {
        $e = $this->getMockBuilder('puzzle_event_AbstractEvent')
            ->getMockForAbstractClass();
        $this->assertFalse($e->isPropagationStopped());
        $e->stopPropagation();
        $this->assertTrue($e->isPropagationStopped());
    }
}

<?php

class puzzle_test_stream_exception_SeekExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testHasStream()
    {
        $s = puzzle_stream_Stream::factory('foo');
        $e = new puzzle_stream_exception_SeekException($s, 10);
        $this->assertSame($s, $e->getStream());
        $this->assertContains('10', $e->getMessage());
    }
}

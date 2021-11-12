<?php

/**
 * @covers puzzle_stream_NoSeekStream
 */
class puzzle_test_stream_NoSeekStreamTest extends PHPUnit_Framework_TestCase
{
    public function testCannotSeek()
    {
        $s = $this->getMockBuilder('puzzle_stream_StreamInterface')
            ->setMethods(array('isSeekable', 'seek'))
            ->getMockForAbstractClass();
        $s->expects($this->never())->method('seek');
        $s->expects($this->never())->method('isSeekable');
        $wrapped = new puzzle_stream_NoSeekStream($s);
        $this->assertFalse($wrapped->isSeekable());
        $this->assertFalse($wrapped->seek(2));
    }

    public function testHandlesClose()
    {
        $s = puzzle_stream_Stream::factory('foo');
        $wrapped = new puzzle_stream_NoSeekStream($s);
        $wrapped->close();
        $this->assertFalse($wrapped->write('foo'));
    }
}

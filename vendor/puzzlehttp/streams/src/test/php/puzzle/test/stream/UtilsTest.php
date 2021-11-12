<?php

class puzzle_test_stream_UtilsTest extends PHPUnit_Framework_TestCase
{
    public function testCopiesToString()
    {
        $s = puzzle_stream_Stream::factory('foobaz');
        $this->assertEquals('foobaz', puzzle_stream_Utils::copyToString($s));
        $s->seek(0);
        $this->assertEquals('foo', puzzle_stream_Utils::copyToString($s, 3));
        $this->assertEquals('baz', puzzle_stream_Utils::copyToString($s, 3));
        $this->assertEquals('', puzzle_stream_Utils::copyToString($s));
    }

    public function testCopiesToStringStopsWhenReadFails()
    {
        $s1 = puzzle_stream_Stream::factory('foobaz');
        $s1 = puzzle_stream_FnStream::decorate($s1, array('read' => array($this, '__callback_returnEmpty')));
        $result = puzzle_stream_Utils::copyToString($s1);
        $this->assertEquals('', $result);
    }

    public function testCopiesToStringStopsWhenReadFailsWithMaxLen()
    {
        $s1 = puzzle_stream_Stream::factory('foobaz');
        $s1 = puzzle_stream_FnStream::decorate($s1, array('read' => array($this, '__callback_returnEmpty')));
        $result = puzzle_stream_Utils::copyToString($s1, 10);
        $this->assertEquals('', $result);
    }

    public function testCopiesToStream()
    {
        $s1 = puzzle_stream_Stream::factory('foobaz');
        $s2 = puzzle_stream_Stream::factory('');
        puzzle_stream_Utils::copyToStream($s1, $s2);
        $this->assertEquals('foobaz', (string) $s2);
        $s2 = puzzle_stream_Stream::factory('');
        $s1->seek(0);
        puzzle_stream_Utils::copyToStream($s1, $s2, 3);
        $this->assertEquals('foo', (string) $s2);
        puzzle_stream_Utils::copyToStream($s1, $s2, 3);
        $this->assertEquals('foobaz', (string) $s2);
    }

    public function testStopsCopyToStreamWhenWriteFails()
    {
        $s1 = puzzle_stream_Stream::factory('foobaz');
        $s2 = puzzle_stream_Stream::factory('');
        $s2 = puzzle_stream_FnStream::decorate($s2, array('write' => array($this, '__callback_return0')));
        puzzle_stream_Utils::copyToStream($s1, $s2);
        $this->assertEquals('', (string) $s2);
    }

    public function testStopsCopyToSteamWhenWriteFailsWithMaxLen()
    {
        $s1 = puzzle_stream_Stream::factory('foobaz');
        $s2 = puzzle_stream_Stream::factory('');
        $s2 = puzzle_stream_FnStream::decorate($s2, array('write' => array($this, '__callback_return0')));
        puzzle_stream_Utils::copyToStream($s1, $s2, 10);
        $this->assertEquals('', (string) $s2);
    }

    public function testStopsCopyToSteamWhenReadFailsWithMaxLen()
    {
        $s1 = puzzle_stream_Stream::factory('foobaz');
        $s1 = puzzle_stream_FnStream::decorate($s1, array('read' => array($this, '__callback_returnEmpty')));
        $s2 = puzzle_stream_Stream::factory('');
        puzzle_stream_Utils::copyToStream($s1, $s2, 10);
        $this->assertEquals('', (string) $s2);
    }

    public function testReadsLines()
    {
        $s = puzzle_stream_Stream::factory("foo\nbaz\nbar");
        $this->assertEquals("foo\n", puzzle_stream_Utils::readline($s));
        $this->assertEquals("baz\n", puzzle_stream_Utils::readline($s));
        $this->assertEquals("bar", puzzle_stream_Utils::readline($s));
    }

    public function testReadsLinesUpToMaxLength()
    {
        $s = puzzle_stream_Stream::factory("12345\n");
        $this->assertEquals("123", puzzle_stream_Utils::readline($s, 4));
        $this->assertEquals("45\n", puzzle_stream_Utils::readline($s));
    }

    public function testReadsLineUntilFalseReturnedFromRead()
    {
        $s = $this->getMockBuilder('puzzle_stream_Stream')
            ->setMethods(array('read', 'eof'))
            ->disableOriginalConstructor()
            ->getMock();
        $s->expects($this->exactly(2))
            ->method('read')
            ->will($this->returnCallback(array($this, '__callback_testReadsLineUntilFalseReturnedFromRead')));
        $s->expects($this->exactly(2))
            ->method('eof')
            ->will($this->returnValue(false));
        $this->assertEquals("h", puzzle_stream_Utils::readline($s));
    }

    public function testCalculatesHash()
    {
        $s = puzzle_stream_Stream::factory('foobazbar');
        $this->assertEquals(md5('foobazbar'), puzzle_stream_Utils::hash($s, 'md5'));
    }

    /**
     * @expectedException puzzle_stream_exception_SeekException
     */
    public function testCalculatesHashThrowsWhenSeekFails()
    {
        $s = new puzzle_stream_NoSeekStream(puzzle_stream_Stream::factory('foobazbar'));
        $s->read(2);
        puzzle_stream_Utils::hash($s, 'md5');
    }

    public function testCalculatesHashSeeksToOriginalPosition()
    {
        $s = puzzle_stream_Stream::factory('foobazbar');
        $s->seek(4);
        $this->assertEquals(md5('foobazbar'), puzzle_stream_Utils::hash($s, 'md5'));
        $this->assertEquals(4, $s->tell());
    }

    public function testOpensFilesSuccessfully()
    {
        $r = puzzle_stream_Utils::open(__FILE__, 'r');
        $this->assertInternalType('resource', $r);
        fclose($r);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Unable to open /path/to/does/not/exist using mode r
     */
    public function testThrowsExceptionNotWarning()
    {
        puzzle_stream_Utils::open('/path/to/does/not/exist', 'r');
    }

    public function testProxiesToFactory()
    {
        $this->assertEquals('foo', (string) puzzle_stream_Utils::create('foo'));
    }

    public function __callback_returnEmpty()
    {
        return '';
    }

    public function __callback_return0()
    {
        return 0;
    }

    public function __callback_testReadsLineUntilFalseReturnedFromRead()
    {
        static $c = false;
        if ($c) {
            return false;
        }
        $c = true;
        return 'h';
    }
}

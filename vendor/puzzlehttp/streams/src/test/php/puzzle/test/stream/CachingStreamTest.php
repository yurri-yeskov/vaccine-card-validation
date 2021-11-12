<?php

/**
 * @covers puzzle_stream_CachingStream
 */
class puzzle_test_stream_CachingStreamTest extends PHPUnit_Framework_TestCase
{
    /** @var puzzle_stream_CachingStream */
    protected $body;

    /** @var puzzle_stream_Stream */
    protected $decorated;

    public function setUp()
    {
        $this->decorated = puzzle_stream_Stream::factory('testing');
        $this->body = new puzzle_stream_CachingStream($this->decorated);
    }

    public function tearDown()
    {
        $this->decorated->close();
        $this->body->close();
    }

    public function testUsesRemoteSizeIfPossible()
    {
        $body = puzzle_stream_Stream::factory('test');
        $caching = new puzzle_stream_CachingStream($body);
        $this->assertEquals(4, $caching->getSize());
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage Cannot seek to byte 10
     */
    public function testCannotSeekPastWhatHasBeenRead()
    {
        $this->body->seek(10);
    }

    public function testCannotUseSeekEnd()
    {
        $this->assertFalse($this->body->seek(2, SEEK_END));
    }

    public function testRewindUsesSeek()
    {
        $a = puzzle_stream_Stream::factory('foo');
        $d = $this->getMockBuilder('puzzle_stream_CachingStream')
            ->setMethods(array('seek'))
            ->setConstructorArgs(array($a))
            ->getMock();
        $d->expects($this->once())
            ->method('seek')
            ->with(0)
            ->will($this->returnValue(true));
        $d->seek(0);
    }

    public function testCanSeekToReadBytes()
    {
        $this->assertEquals('te', $this->body->read(2));
        $this->body->seek(0);
        $this->assertEquals('test', $this->body->read(4));
        $this->assertEquals(4, $this->body->tell());
        $this->body->seek(2);
        $this->assertEquals(2, $this->body->tell());
        $this->body->seek(2, SEEK_CUR);
        $this->assertEquals(4, $this->body->tell());
        $this->assertEquals('ing', $this->body->read(3));
    }

    public function testWritesToBufferStream()
    {
        $this->body->read(2);
        $this->body->write('hi');
        $this->body->seek(0);
        $this->assertEquals('tehiing', (string) $this->body);
    }

    public function testSkipsOverwrittenBytes()
    {
        $decorated = puzzle_stream_Stream::factory(
            implode("\n", array_map(array($this, '__callback_testSkipsOverwrittenBytes'), range(0, 25))),
            true
        );

        $body = new puzzle_stream_CachingStream($decorated);

        $this->assertEquals("0000\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals("0001\n", puzzle_stream_Utils::readline($body));
        // Write over part of the body yet to be read, so skip some bytes
        $this->assertEquals(5, $body->write("TEST\n"));
        $this->assertEquals(5, $this->readAttribute($body, 'skipReadBytes'));
        // Read, which skips bytes, then reads
        $this->assertEquals("0003\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals(0, $this->readAttribute($body, 'skipReadBytes'));
        $this->assertEquals("0004\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals("0005\n", puzzle_stream_Utils::readline($body));

        // Overwrite part of the cached body (so don't skip any bytes)
        $body->seek(5);
        $this->assertEquals(5, $body->write("ABCD\n"));
        $this->assertEquals(0, $this->readAttribute($body, 'skipReadBytes'));
        $this->assertEquals("TEST\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals("0003\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals("0004\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals("0005\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals("0006\n", puzzle_stream_Utils::readline($body));
        $this->assertEquals(5, $body->write("1234\n"));
        $this->assertEquals(5, $this->readAttribute($body, 'skipReadBytes'));

        // Seek to 0 and ensure the overwritten bit is replaced
        $body->seek(0);
        $this->assertEquals("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", $body->read(50));

        // Ensure that casting it to a string does not include the bit that was overwritten
        $this->assertContains("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", (string) $body);
    }

    public function testClosesBothStreams()
    {
        $s = fopen('php://temp', 'r');
        $a = puzzle_stream_Stream::factory($s);
        $d = new puzzle_stream_CachingStream($a);
        $d->close();
        $this->assertFalse(is_resource($s));
    }

    public function __callback_testSkipsOverwrittenBytes($n)
    {
        return str_pad($n, 4, '0', STR_PAD_LEFT);
    }
}

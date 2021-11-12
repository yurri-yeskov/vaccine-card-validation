<?php

/**
 * @covers puzzle_stream_Stream
 */
class puzzle_test_stream_StreamTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorThrowsExceptionOnInvalidArgument()
    {
        new puzzle_stream_Stream(true);
    }

    public function testConstructorInitializesProperties()
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, 'data');
        $stream = new puzzle_stream_Stream($handle);
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertEquals('php://temp', $stream->getMetadata('uri'));
        $this->assertInternalType('array', $stream->getMetadata());
        $this->assertEquals(4, $stream->getSize());
        $this->assertFalse($stream->eof());
        $stream->close();
    }

    public function testStreamClosesHandleOnDestruct()
    {
        $handle = fopen('php://temp', 'r');
        $stream = new puzzle_stream_Stream($handle);
        unset($stream);
        $this->assertFalse(is_resource($handle));
    }

    public function testConvertsToString()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new puzzle_stream_Stream($handle);
        $this->assertEquals('data', (string) $stream);
        $this->assertEquals('data', (string) $stream);
        $stream->close();
    }

    public function testGetsContents()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new puzzle_stream_Stream($handle);
        $this->assertEquals('', $stream->getContents());
        $stream->seek(0);
        $this->assertEquals('data', $stream->getContents());
        $this->assertEquals('', $stream->getContents());
        $stream->seek(0);
        $this->assertEquals('da', $stream->getContents(2));
        $stream->close();
    }

    public function testChecksEof()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new puzzle_stream_Stream($handle);
        $this->assertFalse($stream->eof());
        $stream->read(4);
        $this->assertTrue($stream->eof());
        $stream->close();
    }

    public function testAllowsSettingManualSize()
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new puzzle_stream_Stream($handle);
        $stream->setSize(10);
        $this->assertEquals(10, $stream->getSize());
        $stream->close();
    }

    public function testGetSize()
    {
        $size = filesize(__FILE__);
        $handle = fopen(__FILE__, 'r');
        $stream = new puzzle_stream_Stream($handle);
        $this->assertEquals($size, $stream->getSize());
        // Load from cache
        $this->assertEquals($size, $stream->getSize());
        $stream->close();
    }

    public function testEnsuresSizeIsConsistent()
    {
        $h = fopen('php://temp', 'w+');
        $this->assertEquals(3, fwrite($h, 'foo'));
        $stream = new puzzle_stream_Stream($h);
        $this->assertEquals(3, $stream->getSize());
        $this->assertEquals(4, $stream->write('test'));
        $this->assertEquals(7, $stream->getSize());
        $this->assertEquals(7, $stream->getSize());
        $stream->close();
    }

    public function testProvidesStreamPosition()
    {
        $handle = fopen('php://temp', 'w+');
        $stream = new puzzle_stream_Stream($handle);
        $this->assertEquals(0, $stream->tell());
        $stream->write('foo');
        $this->assertEquals(3, $stream->tell());
        $stream->seek(1);
        $this->assertEquals(1, $stream->tell());
        $this->assertSame(ftell($handle), $stream->tell());
        $stream->close();
    }

    public function testKeepsPositionOfResource()
    {
        $h = fopen(__FILE__, 'r');
        fseek($h, 10);
        $stream = puzzle_stream_Stream::factory($h);
        $this->assertEquals(10, $stream->tell());
        $stream->close();
    }

    public function testCanDetachStream()
    {
        $r = fopen('php://temp', 'w+');
        $stream = new puzzle_stream_Stream($r);
        $this->assertTrue($stream->isReadable());
        $this->assertSame($r, $stream->detach());
        $this->assertNull($stream->detach());
        $this->assertFalse($stream->isReadable());
        $this->assertSame('', $stream->read(10));
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->write('foo'));
        $this->assertFalse($stream->isSeekable());
        $this->assertFalse($stream->seek(10));
        $this->assertFalse($stream->tell());
        $this->assertFalse($stream->eof());
        $this->assertNull($stream->getSize());
        $this->assertSame('', (string) $stream);
        $this->assertSame('', $stream->getContents());
        $stream->close();
    }

    public function testCloseClearProperties()
    {
        $handle = fopen('php://temp', 'r+');
        $stream = new puzzle_stream_Stream($handle);
        $stream->close();

        $this->assertEmpty($stream->getMetadata());
        $this->assertFalse($stream->isSeekable());
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertNull($stream->getSize());
    }

    public function testCreatesWithFactory()
    {
        $stream = puzzle_stream_Stream::factory('foo');
        $this->assertInstanceOf('puzzle_stream_Stream', $stream);
        $this->assertEquals('foo', $stream->getContents());
        $stream->close();
    }

    public function testFlushes()
    {
        $stream = puzzle_stream_Stream::factory('foo');
        $this->assertTrue($stream->flush());
        $stream->close();
    }

    public function testFactoryCreatesFromEmptyString()
    {
        $s = puzzle_stream_Stream::factory();
        $this->assertInstanceOf('puzzle_stream_Stream', $s);
    }

    public function testFactoryCreatesFromResource()
    {
        $r = fopen(__FILE__, 'r');
        $s = puzzle_stream_Stream::factory($r);
        $this->assertInstanceOf('puzzle_stream_Stream', $s);
        $this->assertSame(file_get_contents(__FILE__), (string) $s);
    }

    public function testFactoryCreatesFromObjectWithToString()
    {
        $r = new puzzle_test_stream_HasToString();
        $s = puzzle_stream_Stream::factory($r);
        $this->assertInstanceOf('puzzle_stream_Stream', $s);
        $this->assertEquals('foo', (string) $s);
    }

    public function testCreatePassesThrough()
    {
        $s = puzzle_stream_Stream::factory('foo');
        $this->assertSame($s, puzzle_stream_Stream::factory($s));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testThrowsExceptionForUnknown()
    {
        puzzle_stream_Stream::factory(new stdClass());
    }
}

class puzzle_test_stream_HasToString
{
    public function __toString() {
        return 'foo';
    }
}

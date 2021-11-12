<?php

/**
 * @covers puzzle_stream_LimitStream
 */
class puzzle_test_stream_LimitStreamTest extends PHPUnit_Framework_TestCase
{
    /** @var puzzle_stream_LimitStream */
    protected $body;

    /** @var puzzle_stream_Stream */
    protected $decorated;

    public function setUp()
    {
        $this->decorated = puzzle_stream_Stream::factory(fopen(__FILE__, 'r'));
        $this->body = new puzzle_stream_LimitStream($this->decorated, 10, 3);
    }

    public function testReturnsSubset()
    {
        $body = new puzzle_stream_LimitStream(puzzle_stream_Stream::factory('foo'), -1, 1);
        $this->assertEquals('oo', (string) $body);
        $this->assertTrue($body->eof());
        $body->seek(0);
        $this->assertFalse($body->eof());
        $this->assertEquals('oo', $body->read(100));
        $this->assertTrue($body->eof());
    }

    public function testReturnsSubsetWhenCastToString()
    {
        $body = puzzle_stream_Stream::factory('foo_baz_bar');
        $limited = new puzzle_stream_LimitStream($body, 3, 4);
        $this->assertEquals('baz', (string) $limited);
    }

    public function testReturnsSubsetOfEmptyBodyWhenCastToString()
    {
        $body = puzzle_stream_Stream::factory('');
        $limited = new puzzle_stream_LimitStream($body, 0, 10);
        $this->assertEquals('', (string) $limited);
    }

    public function testSeeksWhenConstructed()
    {
        $this->assertEquals(0, $this->body->tell());
        $this->assertEquals(3, $this->decorated->tell());
    }

    public function testAllowsBoundedSeek()
    {
        $this->body->seek(100);
        $this->assertEquals(13, $this->decorated->tell());
        $this->body->seek(0);
        $this->assertEquals(0, $this->body->tell());
        $this->assertEquals(3, $this->decorated->tell());
        $this->assertEquals(false, $this->body->seek(1000, SEEK_END));
    }

    public function testReadsOnlySubsetOfData()
    {
        $data = $this->body->read(100);
        $this->assertEquals(10, strlen($data));
        $this->assertFalse($this->body->read(1000));

        $this->body->setOffset(10);
        $newData = $this->body->read(100);
        $this->assertEquals(10, strlen($newData));
        $this->assertNotSame($data, $newData);
    }

    /**
     * @expectedException puzzle_stream_exception_SeekException
     * @expectedExceptionMessage Could not seek the stream to position 2
     */
    public function testThrowsWhenCurrentGreaterThanOffsetSeek()
    {
        $a = puzzle_stream_Stream::factory('foo_bar');
        $b = new puzzle_stream_NoSeekStream($a);
        $c = new puzzle_stream_LimitStream($b);
        $a->getContents();
        $c->setOffset(2);
    }

    public function testClaimsConsumedWhenReadLimitIsReached()
    {
        $this->assertFalse($this->body->eof());
        $this->body->read(1000);
        $this->assertTrue($this->body->eof());
    }

    public function testContentLengthIsBounded()
    {
        $this->assertEquals(10, $this->body->getSize());
    }

    public function testGetContentsIsBasedOnSubset()
    {
        $body = new puzzle_stream_LimitStream(puzzle_stream_Stream::factory('foobazbar'), 3, 3);
        $this->assertEquals('baz', $body->getContents());
    }

    public function testReturnsNullIfSizeCannotBeDetermined()
    {
        $a = new puzzle_stream_FnStream(array(
            'getSize' => array($this, '__callback_returnNull'),
            'tell'    => array($this, '__callback_return0'),
        ));
        $b = new puzzle_stream_LimitStream($a);
        $this->assertNull($b->getSize());
    }

    public function testLengthLessOffsetWhenNoLimitSize()
    {
        $a = puzzle_stream_Stream::factory('foo_bar');
        $b = new puzzle_stream_LimitStream($a, -1, 4);
        $this->assertEquals(3, $b->getSize());
    }

    public function __callback_returnNull()
    {
        return null;
    }

    public function __callback_return0()
    {
        return 0;
    }
}

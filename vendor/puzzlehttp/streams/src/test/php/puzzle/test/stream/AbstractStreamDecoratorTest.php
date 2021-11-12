<?php

class puzzle_test_stream_Str extends puzzle_stream_AbstractStreamDecorator implements puzzle_stream_StreamInterface
{

}

/**
 * @covers puzzle_stream_AbstractStreamDecorator
 */
class puzzle_test_stream_AbstractStreamDecoratorTest extends PHPUnit_Framework_TestCase
{
    private $a;
    private $b;
    private $c;

    private $_msg;

    public function setUp()
    {
        $this->c = fopen('php://temp', 'r+');
        fwrite($this->c, 'foo');
        fseek($this->c, 0);
        $this->a = puzzle_stream_Stream::factory($this->c);
        $this->b = new puzzle_test_stream_Str($this->a);
    }

    public function testCatchesExceptionsWhenCastingToString()
    {
        $s = $this->getMockBuilder('puzzle_stream_StreamInterface')
            ->setMethods(array('read'))
            ->getMockForAbstractClass();
        $s->expects($this->once())
            ->method('read')
            ->will($this->throwException(new Exception('foo')));
        $this->_msg = '';
        set_error_handler(array($this, '__callback_testCatchesExceptionsWhenCastingToString'));
        echo new puzzle_test_stream_Str($s);
        restore_error_handler();
        $this->assertContains('foo', $this->_msg);
    }

    public function testToString()
    {
        $this->assertEquals('foo', (string) $this->b);
    }

    public function testHasSize()
    {
        $this->assertEquals(3, $this->b->getSize());
        $this->assertSame($this->b, $this->b->setSize(2));
        $this->assertEquals(2, $this->b->getSize());
    }

    public function testReads()
    {
        $this->assertEquals('foo', $this->b->read(10));
    }

    public function testCheckMethods()
    {
        $this->assertEquals($this->a->isReadable(), $this->b->isReadable());
        $this->assertEquals($this->a->isWritable(), $this->b->isWritable());
        $this->assertEquals($this->a->isSeekable(), $this->b->isSeekable());
    }

    public function testSeeksAndTells()
    {
        $this->assertTrue($this->b->seek(1));
        $this->assertEquals(1, $this->a->tell());
        $this->assertEquals(1, $this->b->tell());
        $this->assertTrue($this->b->seek(0));
        $this->assertEquals(0, $this->a->tell());
        $this->assertEquals(0, $this->b->tell());
        $this->assertTrue($this->b->seek(0, SEEK_END));
        $this->assertEquals(3, $this->a->tell());
        $this->assertEquals(3, $this->b->tell());
    }

    public function testGetsContents()
    {
        $this->assertEquals('foo', $this->b->getContents());
        $this->assertEquals('', $this->b->getContents());
        $this->b->seek(1);
        $this->assertEquals('o', $this->b->getContents(1));
        $this->assertEquals('', $this->b->getContents(0));
    }

    public function testCloses()
    {
        $this->b->close();
        $this->assertFalse(is_resource($this->c));
    }

    public function testDetaches()
    {
        $this->b->detach();
        $this->assertFalse($this->b->isReadable());
    }

    public function testWrapsMetadata()
    {
        $this->assertSame($this->b->getMetadata(), $this->a->getMetadata());
        $this->assertSame($this->b->getMetadata('uri'), $this->a->getMetadata('uri'));
    }

    public function testWrapsWrites()
    {
        $this->b->seek(0, SEEK_END);
        $this->b->write('foo');
        $this->assertEquals('foofoo', (string) $this->a);
    }

    public function __callback_testCatchesExceptionsWhenCastingToString($errNo, $str)
    {
        $this->_msg = $str;
    }

    public function testWrapsFlush()
    {
        $this->b->flush();
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testThrowsWithInvalidGetter()
    {
        $this->b->foo;
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testThrowsWhenGetterNotImplemented()
    {
        $s = new puzzle_test_stream_BadStream();
        $s->stream;
    }
}

class puzzle_test_stream_BadStream extends puzzle_stream_AbstractStreamDecorator
{
    public function __construct() {}
}

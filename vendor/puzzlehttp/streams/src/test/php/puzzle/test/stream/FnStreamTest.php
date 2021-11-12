<?php

/**
 * @covers puzzle_stream_FnStream
 */
class puzzle_test_stream_FnStreamTest extends PHPUnit_Framework_TestCase
{
    private $_closure_var_testCanCloseOnDestruct_called;

    private $_closure_var_testDecoratesWithCustomizations_called;
    private $_closure_var_testDecoratesWithCustomizations_a;

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage seek() is not implemented in the puzzle_stream_FnStream
     */
    public function testThrowsWhenNotImplemented()
    {
        $stream = new puzzle_stream_FnStream(array());
        $stream->seek(1);
    }

    public function testProxiesToFunction()
    {
        $s = new puzzle_stream_FnStream(array(
            'read' => array($this, '__callback_testProxiesToFunction')
        ));

        $this->assertEquals('foo', $s->read(3));
    }

    public function __callback_testProxiesToFunction($len)
    {
        $this->assertEquals(3, $len);
        return 'foo';
    }

    public function testCanCloseOnDestruct()
    {
        $this->_closure_var_testCanCloseOnDestruct_called = false;
        $s = new puzzle_stream_FnStream(array(
            'close' => array($this, '__callback_testCanCloseOnDestruct')
        ));
        unset($s);
        $this->assertTrue($this->_closure_var_testCanCloseOnDestruct_called);
    }

    public function __callback_testCanCloseOnDestruct()
    {
        $this->_closure_var_testCanCloseOnDestruct_called = true;
    }


    public function testDoesNotRequireClose()
    {
        $s = new puzzle_stream_FnStream(array());
        unset($s);
    }

    public function testDecoratesStream()
    {
        $a = puzzle_stream_Stream::factory('foo');
        $b = puzzle_stream_FnStream::decorate($a, array());
        $this->assertEquals(3, $b->getSize());
        $this->assertEquals($b->isWritable(), true);
        $this->assertEquals($b->isReadable(), true);
        $this->assertEquals($b->isSeekable(), true);
        $this->assertEquals($b->read(3), 'foo');
        $this->assertEquals($b->tell(), 3);
        $this->assertEquals($a->tell(), 3);
        $this->assertEquals($b->eof(), true);
        $this->assertEquals($a->eof(), true);
        $b->seek(0);
        $this->assertEquals('foo', (string) $b);
        $b->seek(0);
        $this->assertEquals('foo', $b->getContents());
        $this->assertEquals($a->getMetadata(), $b->getMetadata());
        $b->seek(0, SEEK_END);
        $b->write('bar');
        $this->assertEquals('foobar', (string) $b);
        $b->flush();
        $this->assertInternalType('resource', $b->detach());
        $b->close();
    }

    public function testDecoratesWithCustomizations()
    {
        $this->_closure_var_testDecoratesWithCustomizations_called = false;
        $this->_closure_var_testDecoratesWithCustomizations_a      = puzzle_stream_Stream::factory('foo');
        $b = puzzle_stream_FnStream::decorate($this->_closure_var_testDecoratesWithCustomizations_a, array(
            'read' => array($this, '__callback_testDecoratesWithCustomizations')
        ));
        $this->assertEquals('foo', $b->read(3));
        $this->assertTrue($this->_closure_var_testDecoratesWithCustomizations_called);
    }

    public function __callback_testDecoratesWithCustomizations($len)
    {
        $this->_closure_var_testDecoratesWithCustomizations_called = true;

        return $this->_closure_var_testDecoratesWithCustomizations_a->read($len);
    }
}

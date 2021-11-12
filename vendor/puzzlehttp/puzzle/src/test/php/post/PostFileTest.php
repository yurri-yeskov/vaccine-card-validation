<?php

/**
 * @covers puzzle_post_PostFile
 */
class puzzle_test_post_PostFileTest extends PHPUnit_Framework_TestCase
{
    public function testCreatesFromString()
    {
        $p = new puzzle_post_PostFile('foo', 'hi', '/path/to/test.php');
        $this->assertInstanceOf('puzzle_post_PostFileInterface', $p);
        $this->assertEquals('hi', $p->getContent());
        $this->assertEquals('foo', $p->getName());
        $this->assertEquals('/path/to/test.php', $p->getFilename());
        $headers = $p->getHeaders();
        $this->assertEquals(
            'form-data; name="foo"; filename="test.php"',
            $headers['Content-Disposition']
        );
    }

    public function testGetsFilenameFromMetadata()
    {
        $p = new puzzle_post_PostFile('foo', fopen(__FILE__, 'r'));
        $this->assertEquals(__FILE__, $p->getFilename());
    }

    public function testDefaultsToNameWhenNoFilenameExists()
    {
        $p = new puzzle_post_PostFile('foo', 'bar');
        $this->assertEquals('foo', $p->getFilename());
    }

    public function testCreatesFromMultipartFormData()
    {
        $mp = $this->getMockBuilder('puzzle_post_MultipartBody')
            ->setMethods(array('getBoundary'))
            ->disableOriginalConstructor()
            ->getMock();
        $mp->expects($this->once())
            ->method('getBoundary')
            ->will($this->returnValue('baz'));

        $p = new puzzle_post_PostFile('foo', $mp);
        $headers = $p->getHeaders();
        $this->assertEquals(
            'form-data; name="foo"',
            $headers['Content-Disposition']
        );
        $this->assertEquals(
            'multipart/form-data; boundary=baz',
            $headers['Content-Type']
        );
    }

    public function testCanAddHeaders()
    {
        $p = new puzzle_post_PostFile('foo', puzzle_stream_Stream::factory('hi'), 'test.php', array(
            'X-Foo' => '123',
            'Content-Disposition' => 'bar'
        ));
        $headers = $p->getHeaders();
        $this->assertEquals('bar', $headers['Content-Disposition']);
        $this->assertEquals('123', $headers['X-Foo']);
    }
}

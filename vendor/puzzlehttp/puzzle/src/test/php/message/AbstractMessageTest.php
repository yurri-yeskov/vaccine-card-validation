<?php

/**
 * @covers puzzle_message_AbstractMessage
 */
class puzzle_test_message_AbstractMessageTest extends PHPUnit_Framework_TestCase
{
    public function testHasProtocolVersion()
    {
        $m = new puzzle_message_Request('GET', '/');
        $this->assertEquals(1.1, $m->getProtocolVersion());
    }

    public function testHasHeaders()
    {
        $m = new puzzle_message_Request('GET', 'http://foo.com');
        $this->assertFalse($m->hasHeader('foo'));
        $m->addHeader('foo', 'bar');
        $this->assertTrue($m->hasHeader('foo'));
    }

    public function testInitializesMessageWithProtocolVersionOption()
    {
        $m = new puzzle_message_Request('GET', '/', array(), null, array(
            'protocol_version' => '10'
        ));
        $this->assertEquals(10, $m->getProtocolVersion());
    }

    public function testHasBody()
    {
        $m = new puzzle_message_Request('GET', 'http://foo.com');
        $this->assertNull($m->getBody());
        $s = puzzle_stream_Stream::factory('test');
        $m->setBody($s);
        $this->assertSame($s, $m->getBody());
        $this->assertFalse($m->hasHeader('Content-Length'));
    }

    public function testCanRemoveBodyBySettingToNullAndRemovesCommonBodyHeaders()
    {
        $m = new puzzle_message_Request('GET', 'http://foo.com');
        $m->setBody(puzzle_stream_Stream::factory('foo'));
        $m->setHeader('Content-Length', 3)->setHeader('Transfer-Encoding', 'chunked');
        $m->setBody(null);
        $this->assertNull($m->getBody());
        $this->assertFalse($m->hasHeader('Content-Length'));
        $this->assertFalse($m->hasHeader('Transfer-Encoding'));
    }

    public function testCastsToString()
    {
        $m = new puzzle_message_Request('GET', 'http://foo.com');
        $m->setHeader('foo', 'bar');
        $m->setBody(puzzle_stream_Stream::factory('baz'));
        $this->assertEquals("GET / HTTP/1.1\r\nHost: foo.com\r\nfoo: bar\r\n\r\nbaz", (string) $m);
    }

    public function parseParamsProvider()
    {
        $res1 = array(
            array(
                '<http:/.../front.jpeg>',
                'rel' => 'front',
                'type' => 'image/jpeg',
            ),
            array(
                '<http://.../back.jpeg>',
                'rel' => 'back',
                'type' => 'image/jpeg',
            ),
        );

        return array(
            array(
                '<http:/.../front.jpeg>; rel="front"; type="image/jpeg", <http://.../back.jpeg>; rel=back; type="image/jpeg"',
                $res1
            ),
            array(
                '<http:/.../front.jpeg>; rel="front"; type="image/jpeg",<http://.../back.jpeg>; rel=back; type="image/jpeg"',
                $res1
            ),
            array(
                'foo="baz"; bar=123, boo, test="123", foobar="foo;bar"',
                array(
                    array('foo' => 'baz', 'bar' => '123'),
                    array('boo'),
                    array('test' => '123'),
                    array('foobar' => 'foo;bar')
                )
            ),
            array(
                '<http://.../side.jpeg?test=1>; rel="side"; type="image/jpeg",<http://.../side.jpeg?test=2>; rel=side; type="image/jpeg"',
                array(
                    array('<http://.../side.jpeg?test=1>', 'rel' => 'side', 'type' => 'image/jpeg'),
                    array('<http://.../side.jpeg?test=2>', 'rel' => 'side', 'type' => 'image/jpeg')
                )
            ),
            array(
                '',
                array()
            )
        );
    }

    /**
     * @dataProvider parseParamsProvider
     */
    public function testParseParams($header, $result)
    {
        $request = new puzzle_message_Request('GET', '/', array('foo' => $header));
        $this->assertEquals($result, puzzle_message_Request::parseHeader($request, 'foo'));
    }

    public function testAddsHeadersWhenNotPresent()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->addHeader('foo', 'bar');
        $this->assertInternalType('string', $h->getHeader('foo'));
        $this->assertEquals('bar', $h->getHeader('foo'));
    }

    public function testAddsHeadersWhenPresentSameCase()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->addHeader('foo', 'bar')->addHeader('foo', 'baz');
        $this->assertEquals('bar, baz', $h->getHeader('foo'));
        $this->assertEquals(array('bar', 'baz'), $h->getHeader('foo', true));
    }

    public function testAddsMultipleHeaders()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->addHeaders(array(
            'foo' => ' bar',
            'baz' => array(' bam ', 'boo')
        ));
        $this->assertEquals(array(
            'foo' => array('bar'),
            'baz' => array('bam', 'boo'),
            'Host' => array('foo.com')
        ), $h->getHeaders());
    }

    public function testAddsHeadersWhenPresentDifferentCase()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->addHeader('Foo', 'bar')->addHeader('fOO', 'baz');
        $this->assertEquals('bar, baz', $h->getHeader('foo'));
    }

    public function testAddsHeadersWithArray()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->addHeader('Foo', array('bar', 'baz'));
        $this->assertEquals('bar, baz', $h->getHeader('foo'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testThrowsExceptionWhenInvalidValueProvidedToAddHeader()
    {
        $request = new puzzle_message_Request('GET', 'http://foo.com');
        $request->addHeader('foo', false);
    }

    public function testGetHeadersReturnsAnArrayOfOverTheWireHeaderValues()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->addHeader('foo', 'bar');
        $h->addHeader('Foo', 'baz');
        $h->addHeader('boO', 'test');
        $result = $h->getHeaders();
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('Foo', $result);
        $this->assertArrayNotHasKey('foo', $result);
        $this->assertArrayHasKey('boO', $result);
        $this->assertEquals(array('bar', 'baz'), $result['Foo']);
        $this->assertEquals(array('test'), $result['boO']);
    }

    public function testSetHeaderOverwritesExistingValues()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->setHeader('foo', 'bar');
        $this->assertEquals('bar', $h->getHeader('foo'));
        $h->setHeader('Foo', 'baz');
        $this->assertEquals('baz', $h->getHeader('foo'));
        $this->assertArrayHasKey('Foo', $h->getHeaders());
    }

    public function testSetHeaderOverwritesExistingValuesUsingHeaderArray()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->setHeader('foo', array('bar'));
        $this->assertEquals('bar', $h->getHeader('foo'));
    }

    public function testSetHeaderOverwritesExistingValuesUsingArray()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->setHeader('foo', array('bar'));
        $this->assertEquals('bar', $h->getHeader('foo'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testThrowsExceptionWhenInvalidValueProvidedToSetHeader()
    {
        $request = new puzzle_message_Request('GET', 'http://foo.com');
        $request->setHeader('foo', false);
    }

    public function testSetHeadersOverwritesAllHeaders()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->setHeader('foo', 'bar');
        $h->setHeaders(array('foo' => 'a', 'boo' => 'b'));
        $this->assertEquals(array('foo' => array('a'), 'boo' => array('b')), $h->getHeaders());
    }

    public function testChecksIfCaseInsensitiveHeaderIsPresent()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->setHeader('foo', 'bar');
        $this->assertTrue($h->hasHeader('foo'));
        $this->assertTrue($h->hasHeader('Foo'));
        $h->setHeader('fOo', 'bar');
        $this->assertTrue($h->hasHeader('Foo'));
    }

    public function testRemovesHeaders()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->setHeader('foo', 'bar');
        $h->removeHeader('foo');
        $this->assertFalse($h->hasHeader('foo'));
        $h->setHeader('Foo', 'bar');
        $h->removeHeader('FOO');
        $this->assertFalse($h->hasHeader('foo'));
    }

    public function testReturnsCorrectTypeWhenMissing()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $this->assertInternalType('string', $h->getHeader('foo'));
        $this->assertInternalType('array', $h->getHeader('foo', true));
    }

    public function testSetsIntegersAndFloatsAsHeaders()
    {
        $h = new puzzle_message_Request('GET', 'http://foo.com');
        $h->setHeader('foo', 10);
        $h->setHeader('bar', 10.5);
        $h->addHeader('foo', 10);
        $h->addHeader('bar', 10.5);
        $this->assertSame('10, 10', $h->getHeader('foo'));
        $this->assertSame('10.5, 10.5', $h->getHeader('bar'));
    }

    public function testGetsResponseStartLine()
    {
        $m = new puzzle_message_Response(200);
        $this->assertEquals('HTTP/1.1 200 OK', puzzle_message_Response::getStartLine($m));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testThrowsWhenMessageIsUnknown()
    {
        $m = $this->getMockBuilder('puzzle_message_AbstractMessage')
            ->getMockForAbstractClass();
        puzzle_message_AbstractMessage::getStartLine($m);
    }
}

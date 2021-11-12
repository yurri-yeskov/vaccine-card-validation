<?php

/**
 * @covers puzzle_subscriber_Prepare
 */
class puzzle_test_subscriber_PrepareTest extends PHPUnit_Framework_TestCase
{
    public function testIgnoresRequestsWithNoBody()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertFalse($t->getRequest()->hasHeader('Expect'));
    }

    public function testAppliesPostBody()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $p = $this->getMockBuilder('puzzle_post_PostBodyInterface')
            ->setMethods(array('applyRequestHeaders'))
            ->getMockForAbstractClass();
        $p->expects($this->once())
            ->method('applyRequestHeaders');
        $t->getRequest()->setBody($p);
        $s->onBefore(new puzzle_event_BeforeEvent($t));
    }

    public function testAddsExpectHeaderWithTrue()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->getConfig()->set('expect', true);
        $t->getRequest()->setBody(puzzle_stream_Stream::factory('foo'));
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertEquals('100-Continue', $t->getRequest()->getHeader('Expect'));
    }

    public function testAddsExpectHeaderBySize()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->getConfig()->set('expect', 2);
        $t->getRequest()->setBody(puzzle_stream_Stream::factory('foo'));
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertTrue($t->getRequest()->hasHeader('Expect'));
    }

    public function testDoesNotModifyExpectHeaderIfPresent()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->setHeader('Expect', 'foo');
        $t->getRequest()->setBody(puzzle_stream_Stream::factory('foo'));
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertEquals('foo', $t->getRequest()->getHeader('Expect'));
    }

    public function testDoesAddExpectHeaderWhenSetToFalse()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->getConfig()->set('expect', false);
        $t->getRequest()->setBody(puzzle_stream_Stream::factory('foo'));
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertFalse($t->getRequest()->hasHeader('Expect'));
    }

    public function testDoesNotAddExpectHeaderBySize()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->getConfig()->set('expect', 10);
        $t->getRequest()->setBody(puzzle_stream_Stream::factory('foo'));
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertFalse($t->getRequest()->hasHeader('Expect'));
    }

    public function testAddsExpectHeaderForNonSeekable()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->setBody(new puzzle_stream_NoSeekStream(puzzle_stream_Stream::factory('foo')));
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertTrue($t->getRequest()->hasHeader('Expect'));
    }

    public function testRemovesContentLengthWhenSendingWithChunked()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->setBody(puzzle_stream_Stream::factory('foo'));
        $t->getRequest()->setHeader('Transfer-Encoding', 'chunked');
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertFalse($t->getRequest()->hasHeader('Content-Length'));
    }

    public function testUsesProvidedContentLengthAndRemovesXferEncoding()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->setBody(puzzle_stream_Stream::factory('foo'));
        $t->getRequest()->setHeader('Content-Length', '3');
        $t->getRequest()->setHeader('Transfer-Encoding', 'chunked');
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertEquals(3, $t->getRequest()->getHeader('Content-Length'));
        $this->assertFalse($t->getRequest()->hasHeader('Transfer-Encoding'));
    }

    public function testSetsContentTypeIfPossibleFromStream()
    {
        $body = $this->getMockBody();
        $sub = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->setBody($body);
        $sub->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertEquals(
            'image/jpeg',
            $t->getRequest()->getHeader('Content-Type')
        );
        $this->assertEquals(4, $t->getRequest()->getHeader('Content-Length'));
    }

    public function testDoesNotOverwriteExistingContentType()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->setBody($this->getMockBody());
        $t->getRequest()->setHeader('Content-Type', 'foo/baz');
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertEquals(
            'foo/baz',
            $t->getRequest()->getHeader('Content-Type')
        );
    }

    public function testSetsContentLengthIfPossible()
    {
        $s = new puzzle_subscriber_Prepare();
        $t = $this->getTrans();
        $t->getRequest()->setBody($this->getMockBody());
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertEquals(4, $t->getRequest()->getHeader('Content-Length'));
    }

    public function testSetsTransferEncodingChunkedIfNeeded()
    {
        $r = new puzzle_message_Request('PUT', '/');
        $s = $this->getMockBuilder('puzzle_stream_StreamInterface')
            ->setMethods(array('getSize'))
            ->getMockForAbstractClass();
        $s->expects($this->exactly(2))
            ->method('getSize')
            ->will($this->returnValue(null));
        $r->setBody($s);
        $t = $this->getTrans($r);
        $s = new puzzle_subscriber_Prepare();
        $s->onBefore(new puzzle_event_BeforeEvent($t));
        $this->assertEquals('chunked', $r->getHeader('Transfer-Encoding'));
    }

    private function getTrans($request = null)
    {
        return new puzzle_adapter_Transaction(
            new puzzle_Client(),
            $request ? $request : new puzzle_message_Request('PUT', '/')
        );
    }

    /**
     * @return puzzle_stream_StreamInterface
     */
    private function getMockBody()
    {
        $s = $this->getMockBuilder('puzzle_stream_MetadataStreamInterface')
            ->setMethods(array('getMetadata', 'getSize'))
            ->getMockForAbstractClass();
        $s->expects($this->any())
            ->method('getMetadata')
            ->with('uri')
            ->will($this->returnValue('/foo/baz/bar.jpg'));
        $s->expects($this->exactly(2))
            ->method('getSize')
            ->will($this->returnValue(4));

        return $s;
    }
}

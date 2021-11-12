<?php

/**
 * @covers puzzle_post_PostBody
 */
class puzzle_test_post_PostBodyTest extends PHPUnit_Framework_TestCase
{
    public function testWrapsBasicStreamFunctionality()
    {
        $b = new puzzle_post_PostBody();
        $this->assertTrue($b->isSeekable());
        $this->assertTrue($b->isReadable());
        $this->assertFalse($b->isWritable());
        $this->assertFalse($b->write('foo'));
    }

    public function testApplyingWithNothingDoesNothing()
    {
        $b = new puzzle_post_PostBody();
        $m = new puzzle_message_Request('POST', '/');
        $b->applyRequestHeaders($m);
        $this->assertFalse($m->hasHeader('Content-Length'));
        $this->assertFalse($m->hasHeader('Content-Type'));
    }

    public function testCanForceMultipartUploadsWhenApplying()
    {
        $b = new puzzle_post_PostBody();
        $b->forceMultipartUpload(true);
        $m = new puzzle_message_Request('POST', '/');
        $b->applyRequestHeaders($m);
        $this->assertContains(
            'multipart/form-data',
            $m->getHeader('Content-Type')
        );
    }

    public function testApplyingWithFilesAddsMultipartUpload()
    {
        $b = new puzzle_post_PostBody();
        $p = new puzzle_post_PostFile('foo', fopen(__FILE__, 'r'));
        $b->addFile($p);
        $this->assertEquals(array($p), $b->getFiles());
        $this->assertNull($b->getFile('missing'));
        $this->assertSame($p, $b->getFile('foo'));
        $m = new puzzle_message_Request('POST', '/');
        $b->applyRequestHeaders($m);
        $this->assertContains(
            'multipart/form-data',
            $m->getHeader('Content-Type')
        );
        $this->assertTrue($m->hasHeader('Content-Length'));
    }

    public function testApplyingWithFieldsAddsMultipartUpload()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('foo', 'bar');
        $this->assertEquals(array('foo' => 'bar'), $b->getFields());
        $m = new puzzle_message_Request('POST', '/');
        $b->applyRequestHeaders($m);
        $this->assertContains(
            'application/x-www-form',
            $m->getHeader('Content-Type')
        );
        $this->assertTrue($m->hasHeader('Content-Length'));
    }

    public function testMultipartWithNestedFields()
    {
      $b = new puzzle_post_PostBody();
      $b->setField('foo', array('bar' => 'baz'));
      $b->forceMultipartUpload(true);
      $this->assertEquals(array('foo' => array('bar' => 'baz')), $b->getFields());
      $m = new puzzle_message_Request('POST', '/');
      $b->applyRequestHeaders($m);
      $this->assertContains(
          'multipart/form-data',
          $m->getHeader('Content-Type')
      );
      $this->assertTrue($m->hasHeader('Content-Length'));
      $contents = $b->getContents();
      $this->assertContains('name="foo[bar]"', $contents);
      $this->assertNotContains('name="foo"', $contents);
    }

    public function testCountProvidesFieldsAndFiles()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('foo', 'bar');
        $b->addFile(new puzzle_post_PostFile('foo', fopen(__FILE__, 'r')));
        $this->assertEquals(2, count($b));
        $b->clearFiles();
        $b->removeField('foo');
        $this->assertEquals(0, count($b));
        $this->assertEquals(array(), $b->getFiles());
        $this->assertEquals(array(), $b->getFields());
    }

    public function testHasFields()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('foo', 'bar');
        $b->setField('baz', '123');
        $this->assertEquals('bar', $b->getField('foo'));
        $this->assertEquals('123', $b->getField('baz'));
        $this->assertNull($b->getField('ahh'));
        $this->assertTrue($b->hasField('foo'));
        $this->assertFalse($b->hasField('test'));
        $b->replaceFields(array('abc' => '123'));
        $this->assertFalse($b->hasField('foo'));
        $this->assertTrue($b->hasField('abc'));
    }

    public function testConvertsFieldsToQueryStyleBody()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('foo', 'bar');
        $b->setField('baz', '123');
        $this->assertEquals('foo=bar&baz=123', $b);
        $this->assertEquals(15, $b->getSize());
        $b->seek(0);
        $this->assertEquals('foo=bar&baz=123', $b->getContents());
        $b->seek(0);
        $this->assertEquals('foo=bar&baz=123', $b->read(1000));
        $this->assertEquals(15, $b->tell());
        $this->assertTrue($b->eof());
    }

    public function testCanSpecifyQueryAggregator()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('foo', array('baz', 'bar'));
        $this->assertEquals('foo%5B0%5D=baz&foo%5B1%5D=bar', (string) $b);
        $b = new puzzle_post_PostBody();
        $b->setField('foo', array('baz', 'bar'));
        $agg = puzzle_Query::duplicateAggregator();
        $b->setAggregator($agg);
        $this->assertEquals('foo=baz&foo=bar', (string) $b);
    }

    public function testDetachesAndCloses()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('foo', 'bar');
        $b->detach();
        $this->assertTrue($b->close());
        $this->assertEquals('', $b->read(10));
    }

    public function testCreatesMultipartUploadWithMultiFields()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('testing', array('baz', 'bar'));
        $b->setField('other', 'hi');
        $b->setField('third', 'there');
        $b->addFile(new puzzle_post_PostFile('foo', fopen(__FILE__, 'r')));
        $s = (string) $b;
        $this->assertContains(file_get_contents(__FILE__), $s);
        $this->assertContains('testing=bar', $s);
        $this->assertContains(
            'Content-Disposition: form-data; name="third"',
            $s
        );
        $this->assertContains(
            'Content-Disposition: form-data; name="other"',
            $s
        );
    }

    public function testMultipartWithBase64Fields()
    {
      $b = new puzzle_post_PostBody();
      $b->setField('foo64', '/xA2JhWEqPcgyLRDdir9WSRi/khpb2Lh3ooqv+5VYoc=');
      $b->forceMultipartUpload(true);
      $this->assertEquals(
          array('foo64' => '/xA2JhWEqPcgyLRDdir9WSRi/khpb2Lh3ooqv+5VYoc='),
          $b->getFields()
      );
      $m = new puzzle_message_Request('POST', '/');
      $b->applyRequestHeaders($m);
      $this->assertContains(
          'multipart/form-data',
          $m->getHeader('Content-Type')
      );
      $this->assertTrue($m->hasHeader('Content-Length'));
      $contents = $b->getContents();
      $this->assertContains('name="foo64"', $contents);
      $this->assertContains(
          '/xA2JhWEqPcgyLRDdir9WSRi/khpb2Lh3ooqv+5VYoc=',
          $contents
      );
    }

    public function testMultipartWithAmpersandInValue()
    {
        $b = new puzzle_post_PostBody();
        $b->setField('a', 'b&c=d');
        $b->forceMultipartUpload(true);
        $this->assertEquals(array('a' => 'b&c=d'), $b->getFields());
        $m = new puzzle_message_Request('POST', '/');
        $b->applyRequestHeaders($m);
        $this->assertContains(
            'multipart/form-data',
            $m->getHeader('Content-Type')
        );
        $this->assertTrue($m->hasHeader('Content-Length'));
        $contents = $b->getContents();
        $this->assertContains('name="a"', $contents);
        $this->assertContains('b&c=d', $contents);
    }
}

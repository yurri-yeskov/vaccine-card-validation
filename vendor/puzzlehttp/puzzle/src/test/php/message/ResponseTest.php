<?php

/**
 * @covers puzzle_message_Response
 */
class puzzle_test_message_ResponseTest extends PHPUnit_Framework_TestCase
{
    public function testCanProvideCustomStatusCodeAndReasonPhrase()
    {
        $response = new puzzle_message_Response(999, array(), null, array('reason_phrase' => 'hi!'));
        $this->assertEquals(999, $response->getStatusCode());
        $this->assertEquals('hi!', $response->getReasonPhrase());
    }

    public function testConvertsToString()
    {
        $response = new puzzle_message_Response(200);
        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n", (string) $response);
        // Add another header
        $response = new puzzle_message_Response(200, array('X-Test' => 'Guzzle'));
        $this->assertEquals("HTTP/1.1 200 OK\r\nX-Test: Guzzle\r\n\r\n", (string) $response);
        $response = new puzzle_message_Response(200, array('Content-Length' => 4), puzzle_stream_Stream::factory('test'));
        $this->assertEquals("HTTP/1.1 200 OK\r\nContent-Length: 4\r\n\r\ntest", (string) $response);
    }

    public function testConvertsToStringAndSeeksToByteZero()
    {
        $response = new puzzle_message_Response(200);
        $s = puzzle_stream_Stream::factory('foo');
        $s->read(1);
        $response->setBody($s);
        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\nfoo", (string) $response);
    }

    public function testParsesJsonResponses()
    {
        $json = '{"foo": "bar"}';
        $response = new puzzle_message_Response(200, array(), puzzle_stream_Stream::factory($json));
        $this->assertEquals(array('foo' => 'bar'), $response->json());
        $this->assertEquals(json_decode($json), $response->json(array('object' => true)));

        $response = new puzzle_message_Response(200);
        $this->assertEquals(null, $response->json());
    }

    public function testThrowsExceptionWhenFailsToParseJsonResponse()
    {
        if (version_compare(PHP_VERSION, '5.3') >= 0) {

            $this->setExpectedException('puzzle_exception_ParseException', 'Unable to parse JSON data: JSON_ERROR_SYNTAX - Syntax error, malformed JSON');

        } else {

            $this->setExpectedException('puzzle_exception_ParseException', 'Unable to parse JSON data: Unknown error');
        }

        $response = new puzzle_message_Response(200, array(), puzzle_stream_Stream::factory('{"foo": "'));
        $response->json();
    }

    public function testParsesXmlResponses()
    {
        $response = new puzzle_message_Response(200, array(), puzzle_stream_Stream::factory('<abc><foo>bar</foo></abc>'));
        $this->assertEquals('bar', (string) $response->xml()->foo);
        // Always return a SimpleXMLElement from the xml method
        $response = new puzzle_message_Response(200);
        $this->assertEmpty((string) $response->xml()->foo);
    }

    /**
     * @expectedException puzzle_exception_ParseException
     * @expectedExceptionMessage Unable to parse response body into XML: String could not be parsed as XML
     */
    public function testThrowsExceptionWhenFailsToParseXmlResponse()
    {
        $response = new puzzle_message_Response(200, array(), puzzle_stream_Stream::factory('<abc'));
        $response->xml();
    }

    public function testHasEffectiveUrl()
    {
        $r = new puzzle_message_Response(200);
        $this->assertNull($r->getEffectiveUrl());
        $r->setEffectiveUrl('http://www.test.com');
        $this->assertEquals('http://www.test.com', $r->getEffectiveUrl());
    }

    public function testPreventsComplexExternalEntities()
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE scan[<!ENTITY test SYSTEM "php://filter/read=convert.base64-encode/resource=ResponseTest.php">]><scan>&test;</scan>';
        $response = new puzzle_message_Response(200, array(), puzzle_stream_Stream::factory($xml));

        $oldCwd = getcwd();
        chdir(dirname(__FILE__));
        try {
            $xml = $response->xml();
            chdir($oldCwd);
            $this->markTestIncomplete('Did not throw the expected exception! XML resolved as: ' . $xml->asXML());
        } catch (Exception $e) {
            chdir($oldCwd);
        }
    }
}

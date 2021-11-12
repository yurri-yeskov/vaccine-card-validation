<?php

/**
 * @covers puzzle_subscriber_Mock
 */
class puzzle_test_subscriber_MockTest extends PHPUnit_Framework_TestCase
{
    public function testDescribesSubscribedEvents()
    {
        $mock = new puzzle_subscriber_Mock();
        $this->assertInternalType('array', $mock->getEvents());
    }

    public function testIsCountable()
    {
        $plugin = new puzzle_subscriber_Mock();
        $factory = new puzzle_message_MessageFactory();
        $plugin->addResponse($factory->fromMessage("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"));
        $this->assertEquals(1, count($plugin));
    }

    public function testCanClearQueue()
    {
        $plugin = new puzzle_subscriber_Mock();
        $factory = new puzzle_message_MessageFactory();
        $plugin->addResponse($factory->fromMessage("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"));
        $plugin->clearQueue();
        $this->assertEquals(0, count($plugin));
    }

    public function testRetrievesResponsesFromFiles()
    {
        $tmp = tempnam('/tmp', 'tfile');
        file_put_contents($tmp, "HTTP/1.1 201 OK\r\nContent-Length: 0\r\n\r\n");
        $plugin = new puzzle_subscriber_Mock();
        $plugin->addResponse($tmp);
        unlink($tmp);
        $this->assertEquals(1, count($plugin));
        $q = $this->readAttribute($plugin, 'queue');
        $this->assertEquals(201, $q[0]->getStatusCode());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testThrowsExceptionWhenInvalidResponse()
    {
        $mock = new puzzle_subscriber_Mock();
        $mock->addResponse(false);
    }

    public function testAddsMockResponseToRequestFromClient()
    {
        $response = new puzzle_message_Response(200);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/'));
        $m = new puzzle_subscriber_Mock(array($response));
        $ev = new puzzle_event_BeforeEvent($t);
        $m->onBefore($ev);
        $this->assertSame($response, $t->getResponse());
    }

    /**
     * @expectedException OutOfBoundsException
     */
    public function testUpdateThrowsExceptionWhenEmpty()
    {
        $p = new puzzle_subscriber_Mock();
        $ev = new puzzle_event_BeforeEvent(new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/')));
        $p->onBefore($ev);
    }

    public function testReadsBodiesFromMockedRequests()
    {
        $m = new puzzle_subscriber_Mock(array(new puzzle_message_Response(200)));
        $client = new puzzle_Client(array('base_url' => 'http://test.com'));
        $client->getEmitter()->attach($m);
        $body = puzzle_stream_Stream::factory('foo');
        $client->put('/', array('body' => $body));
        $this->assertEquals(3, $body->tell());
    }

    public function testCanMockBadRequestExceptions()
    {
        $client = new puzzle_Client(array('base_url' => 'http://test.com'));
        $request = $client->createRequest('GET', '/');
        $ex = new puzzle_exception_RequestException('foo', $request);
        $mock = new puzzle_subscriber_Mock(array($ex));
        $this->assertCount(1, $mock);
        $request->getEmitter()->attach($mock);

        try {
            $client->send($request);
            $this->fail('Did not dequeue an exception');
        } catch (puzzle_exception_RequestException $e) {
            $this->assertSame($e, $ex);
            $this->assertSame($request, $ex->getRequest());
        }
    }
}

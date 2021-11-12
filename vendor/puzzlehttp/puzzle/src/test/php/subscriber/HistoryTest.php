<?php

/**
 * @covers puzzle_subscriber_History
 */
class puzzle_test_subscriber_HistoryTest extends PHPUnit_Framework_TestCase
{
    public function testAddsForErrorEvent()
    {
        $request = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(400);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $t->setResponse($response);
        $e = new puzzle_exception_RequestException('foo', $request, $response);
        $ev = new puzzle_event_ErrorEvent($t, $e);
        $h = new puzzle_subscriber_History(2);
        $h->onError($ev);
        // Only tracks when no response is present
        $this->assertEquals(array(), $h->getRequests());
    }

    public function testLogsConnectionErrors()
    {
        $request = new puzzle_message_Request('GET', '/');
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $e = new puzzle_exception_RequestException('foo', $request);
        $ev = new puzzle_event_ErrorEvent($t, $e);
        $h = new puzzle_subscriber_History();
        $h->onError($ev);
         $this->assertEquals(array($request), $h->getRequests());
     }

    public function testMaintainsLimitValue()
    {
        $request = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(200);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $t->setResponse($response);
        $ev = new puzzle_event_CompleteEvent($t);
        $h = new puzzle_subscriber_History(2);
        $h->onComplete($ev);
        $h->onComplete($ev);
        $h->onComplete($ev);
        $this->assertEquals(2, count($h));
        $this->assertSame($request, $h->getLastRequest());
        $this->assertSame($response, $h->getLastResponse());
        foreach ($h as $trans) {
            $this->assertInstanceOf('puzzle_message_RequestInterface', $trans['request']);
            $this->assertInstanceOf('puzzle_message_ResponseInterface', $trans['response']);
        }
        return $h;
    }

    /**
     * @depends testMaintainsLimitValue
     */
    public function testClearsHistory($h)
    {
        $this->assertEquals(2, count($h));
        $h->clear();
        $this->assertEquals(0, count($h));
    }

    public function testCanCastToString()
    {
        $client = new puzzle_Client(array('base_url' => 'http://localhost/'));
        $h = new puzzle_subscriber_History();
        $client->getEmitter()->attach($h);

        $mock = new puzzle_subscriber_Mock(array(
            new puzzle_message_Response(301, array('Location' => '/redirect1', 'Content-Length' => 0)),
            new puzzle_message_Response(307, array('Location' => '/redirect2', 'Content-Length' => 0)),
            new puzzle_message_Response(200, array('Content-Length' => '2'), puzzle_stream_Stream::factory('HI'))
        ));

        $client->getEmitter()->attach($mock);
        $request = $client->createRequest('GET', '/');
        $client->send($request);
        $this->assertEquals(3, count($h));

        $h = str_replace("\r", '', $h);
        $this->assertContains("> GET / HTTP/1.1\nHost: localhost\nUser-Agent:", $h);
        $this->assertContains("< HTTP/1.1 301 Moved Permanently\nLocation: /redirect1", $h);
        $this->assertContains("< HTTP/1.1 307 Temporary Redirect\nLocation: /redirect2", $h);
        $this->assertContains("< HTTP/1.1 200 OK\nContent-Length: 2\n\nHI", $h);
    }
}

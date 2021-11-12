<?php

/**
 * @covers puzzle_subscriber_Cookie
 */
class puzzle_test_cookie_CookieTest extends PHPUnit_Framework_TestCase
{
    public function testExtractsAndStoresCookies()
    {
        $request = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(200);
        $mock = $this->getMockBuilder('puzzle_cookie_CookieJar')
            ->setMethods(array('extractCookies'))
            ->getMock();

        $mock->expects($this->exactly(1))
            ->method('extractCookies')
            ->with($request, $response);

        $plugin = new puzzle_subscriber_Cookie ($mock);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $t->setResponse($response);
        $plugin->onComplete(new puzzle_event_CompleteEvent($t));
    }

    public function testProvidesCookieJar()
    {
        $jar = new puzzle_cookie_CookieJar();
        $plugin = new puzzle_subscriber_Cookie ($jar);
        $this->assertSame($jar, $plugin->getCookieJar());
    }

    public function testCookiesAreExtractedFromRedirectResponses()
    {
        $jar = new puzzle_cookie_CookieJar();
        $cookie = new puzzle_subscriber_Cookie ($jar);
        $history = new puzzle_subscriber_History();
        $mock = new puzzle_subscriber_Mock(array(
            "HTTP/1.1 302 Moved Temporarily\r\n" .
            "Set-Cookie: test=583551; Domain=www.foo.com; Expires=Wednesday, 23-Mar-2050 19:49:45 GMT; Path=/\r\n" .
            "Location: /redirect\r\n\r\n",
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 0\r\n\r\n",
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 0\r\n\r\n"
        ));
        $client = new puzzle_Client(array('base_url' => 'http://www.foo.com'));
        $client->getEmitter()->attach($cookie);
        $client->getEmitter()->attach($mock);
        $client->getEmitter()->attach($history);

        $client->get();
        $request = $client->createRequest('GET', '/');
        $client->send($request);

        $this->assertEquals('test=583551', $request->getHeader('Cookie'));
        $requests = $history->getRequests();
        // Confirm subsequent requests have the cookie.
        $this->assertEquals('test=583551', $requests[2]->getHeader('Cookie'));
        // Confirm the redirected request has the cookie.
        $this->assertEquals('test=583551', $requests[1]->getHeader('Cookie'));
    }
}

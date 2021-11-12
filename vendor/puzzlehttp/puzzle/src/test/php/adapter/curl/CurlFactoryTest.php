<?php

// Override curl_setopt_array() to get the last set curl options
function puzzle_test_adapter_curl_curl_setopt_array($handle, array $options)
{
    if (array_values($options) != array(null, null, null, null)) {
        $_SERVER['last_curl'] = $options;
    }
    curl_setopt_array($handle, $options);
}


/**
 * @covers puzzle_adapter_curl_CurlFactory
 */
class puzzle_test_adapter_curl_CurlFactoryTest extends PHPUnit_Framework_TestCase
{
    /** @var puzzle_test_Server */
    static $server;

    public static function setUpBeforeClass()
    {
        puzzle_adapter_curl_CurlFactory::__enableTestMode();
        unset($_SERVER['last_curl']);
    }

    public static function tearDownAfterClass()
    {
        unset($_SERVER['last_curl']);
    }

    public function testCreatesCurlHandle()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nFoo: Bar\r\n Baz:  bam\r\nContent-Length: 2\r\n\r\nhi"));
        $request = new puzzle_message_Request(
            'PUT',
            puzzle_test_Server::$url . 'haha',
            array('Hi' => ' 123'),
            puzzle_stream_Stream::factory('testing')
        );
        $stream = puzzle_stream_Stream::factory();
        $request->getConfig()->set('save_to', $stream);
        $request->getConfig()->set('verify', true);
        $this->emit($request);

        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        $this->assertInternalType('resource', $h);
        curl_exec($h);
        $response = $t->getResponse();
        $this->assertInstanceOf('puzzle_message_ResponseInterface', $response);
        $this->assertEquals('hi', $response->getBody());
        $this->assertEquals('Bar', $response->getHeader('Foo'));
        $this->assertEquals('bam', $response->getHeader('Baz'));
        curl_close($h);

        $sent = puzzle_test_Server::received(true);
        $sent = $sent[0];
        $this->assertEquals('PUT', $sent->getMethod());
        $this->assertEquals('/haha', $sent->getPath());
        $this->assertEquals('123', $sent->getHeader('Hi'));
        $this->assertEquals('7', $sent->getHeader('Content-Length'));
        $this->assertEquals('testing', $sent->getBody());
        $this->assertEquals('1.1', $sent->getProtocolVersion());
        $this->assertEquals('hi', (string) $stream);

        $this->assertEquals(true, $_SERVER['last_curl'][CURLOPT_SSL_VERIFYPEER]);
        $this->assertEquals(2, $_SERVER['last_curl'][CURLOPT_SSL_VERIFYHOST]);
    }

    public function testSendsHeadRequests()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\n"));
        $request = new puzzle_message_Request('HEAD', puzzle_test_Server::$url);
        $this->emit($request);

        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        $response = $t->getResponse();
        $this->assertEquals('2', $response->getHeader('Content-Length'));
        $this->assertEquals('', $response->getBody());

        $sent = puzzle_test_Server::received(true);
        $sent = $sent[0];
        $this->assertEquals('HEAD', $sent->getMethod());
        $this->assertEquals('/', $sent->getPath());
    }

    public function testSendsPostRequestWithNoBody()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"));
        $request = new puzzle_message_Request('POST', puzzle_test_Server::$url);
        $this->emit($request);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertEquals('POST', $sent->getMethod());
        $this->assertEquals('', $sent->getBody());
    }

    public function testSendsChunkedRequests()
    {
        $stream = $this->getMockBuilder('puzzle_stream_Stream')
            ->setConstructorArgs(array(fopen('php://temp', 'r+')))
            ->setMethods(array('getSize'))
            ->getMock();
        $stream->expects($this->any())
            ->method('getSize')
            ->will($this->returnValue(null));
        $stream->write('foo');
        $stream->seek(0);

        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"));
        $request = new puzzle_message_Request('PUT', puzzle_test_Server::$url, array(), $stream);
        $this->emit($request);
        $this->assertNull($request->getBody()->getSize());
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        $sent = puzzle_test_Server::received(false);
        $sent = $sent[0];
        $this->assertContains('PUT / HTTP/1.1', $sent);
        $this->assertContains('transfer-encoding: chunked', strtolower($sent));
        $this->assertContains("\r\n\r\nfoo", $sent);
    }

    public function testDecodesGzippedResponses()
    {
        puzzle_test_Server::flush();
        $content = gzencode('test');
        $message = "HTTP/1.1 200 OK\r\n"
            . "Content-Encoding: gzip\r\n"
            . "Content-Length: " . strlen($content) . "\r\n\r\n"
            . $content;
        puzzle_test_Server::enqueue($message);
        $client = new puzzle_Client();
        $request = $client->createRequest('GET', puzzle_test_Server::$url);
        $this->emit($request);
        $t = new puzzle_adapter_Transaction($client, $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertSame('', $sent->getHeader('Accept-Encoding'));
        $this->assertEquals('test', (string) $t->getResponse()->getBody());
    }

    public function testDecodesWithCustomAcceptHeader()
    {
        puzzle_test_Server::flush();
        $content = gzencode('test');
        $message = "HTTP/1.1 200 OK\r\n"
            . "Content-Encoding: gzip\r\n"
            . "Content-Length: " . strlen($content) . "\r\n\r\n"
            . $content;
        puzzle_test_Server::enqueue($message);
        $client = new puzzle_Client();
        $request = $client->createRequest('GET', puzzle_test_Server::$url, array(
            'decode_content' => 'gzip'
        ));
        $this->emit($request);
        $t = new puzzle_adapter_Transaction($client, $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertSame('gzip', $sent->getHeader('Accept-Encoding'));
        $this->assertEquals('test', (string) $t->getResponse()->getBody());
    }

    public function testDoesNotForceDecode()
    {
        puzzle_test_Server::flush();
        $content = gzencode('test');
        $message = "HTTP/1.1 200 OK\r\n"
            . "Content-Encoding: gzip\r\n"
            . "Content-Length: " . strlen($content) . "\r\n\r\n"
            . $content;
        puzzle_test_Server::enqueue($message);
        $client = new puzzle_Client();
        $request = $client->createRequest('GET', puzzle_test_Server::$url, array(
            'headers'        => array('Accept-Encoding' => 'gzip'),
            'decode_content' => false
    ));
        $this->emit($request);
        $t = new puzzle_adapter_Transaction($client, $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertSame('gzip', $sent->getHeader('Accept-Encoding'));
        $this->assertSame($content, (string) $t->getResponse()->getBody());
    }

    public function testAddsDebugInfoToBuffer()
    {
        $r = tmpfile();
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nfoo"));
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $request->getConfig()->set('debug', $r);
        $this->emit($request);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        rewind($r);
        $this->assertNotEmpty(stream_get_contents($r));
    }

    public function testAddsProxyOptions()
    {
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $this->emit($request);
        $request->getConfig()->set('proxy', '123');
        $request->getConfig()->set('connect_timeout', 1);
        $request->getConfig()->set('timeout', 2);
        $request->getConfig()->set('cert', __FILE__);
        $request->getConfig()->set('ssl_key', array(__FILE__, '123'));
        $request->getConfig()->set('verify', false);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        curl_close($f->__invoke($t, new puzzle_message_MessageFactory()));
        $this->assertEquals('123', $_SERVER['last_curl'][CURLOPT_PROXY]);
        $this->assertEquals(1000, $_SERVER['last_curl'][CURLOPT_CONNECTTIMEOUT_MS]);
        $this->assertEquals(2000, $_SERVER['last_curl'][CURLOPT_TIMEOUT_MS]);
        $this->assertEquals(__FILE__, $_SERVER['last_curl'][CURLOPT_SSLCERT]);
        $this->assertEquals(__FILE__, $_SERVER['last_curl'][CURLOPT_SSLKEY]);
        $this->assertEquals('123', $_SERVER['last_curl'][CURLOPT_SSLKEYPASSWD]);
        $this->assertEquals(0, $_SERVER['last_curl'][CURLOPT_SSL_VERIFYHOST]);
        $this->assertEquals(false, $_SERVER['last_curl'][CURLOPT_SSL_VERIFYPEER]);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testEnsuresCertExists()
    {
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $this->emit($request);
        $request->getConfig()->set('cert', __FILE__ . 'ewfwef');
        $f = new puzzle_adapter_curl_CurlFactory();
        $f->__invoke(new puzzle_adapter_Transaction(new puzzle_Client(), $request), new puzzle_message_MessageFactory());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testEnsuresKeyExists()
    {
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $this->emit($request);
        $request->getConfig()->set('ssl_key', __FILE__ . 'ewfwef');
        $f = new puzzle_adapter_curl_CurlFactory();
        $f->__invoke(new puzzle_adapter_Transaction(new puzzle_Client(), $request), new puzzle_message_MessageFactory());
    }

    /**
     * @expectedException RuntimeException
     */
    public function testEnsuresCacertExists()
    {
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $this->emit($request);
        $request->getConfig()->set('verify', __FILE__ . 'ewfwef');
        $f = new puzzle_adapter_curl_CurlFactory();
        $f->__invoke(new puzzle_adapter_Transaction(new puzzle_Client(), $request), new puzzle_message_MessageFactory());
    }

    public function testClientUsesSslByDefault()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 3\r\n\r\nfoo"));
        $f = new puzzle_adapter_curl_CurlFactory();
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_curl_MultiAdapter(new puzzle_message_MessageFactory(), array('handle_factory' => $f))
        ));
        $client->get();
        $this->assertEquals(2, $_SERVER['last_curl'][CURLOPT_SSL_VERIFYHOST]);
        $this->assertEquals(true, $_SERVER['last_curl'][CURLOPT_SSL_VERIFYPEER]);
        $this->assertFileExists($_SERVER['last_curl'][CURLOPT_CAINFO]);
    }

    public function testConvertsConstantNameKeysToValues()
    {
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url);
        $request->getConfig()->set('curl', array('CURLOPT_USERAGENT' => 'foo'));
        $this->emit($request);
        $f = new puzzle_adapter_curl_CurlFactory();
        curl_close($f->__invoke(new puzzle_adapter_Transaction(new puzzle_Client(), $request), new puzzle_message_MessageFactory()));
        $this->assertEquals('foo', $_SERVER['last_curl'][CURLOPT_USERAGENT]);
    }

    public function testStripsFragment()
    {
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url . '#foo');
        $this->emit($request);
        $f = new puzzle_adapter_curl_CurlFactory();
        curl_close($f->__invoke(new puzzle_adapter_Transaction(new puzzle_Client(), $request), new puzzle_message_MessageFactory()));
        $this->assertEquals(puzzle_test_Server::$url, $_SERVER['last_curl'][CURLOPT_URL]);
    }

    public function testDoesNotSendSizeTwice()
    {
        $request = new puzzle_message_Request('PUT', puzzle_test_Server::$url, array(), puzzle_stream_Stream::factory(str_repeat('a', 32769)));
        $this->emit($request);
        $f = new puzzle_adapter_curl_CurlFactory();
        curl_close($f->__invoke(new puzzle_adapter_Transaction(new puzzle_Client(), $request), new puzzle_message_MessageFactory()));
        $this->assertEquals(32769, $_SERVER['last_curl'][CURLOPT_INFILESIZE]);
        $this->assertNotContains('Content-Length', implode(' ', $_SERVER['last_curl'][CURLOPT_HTTPHEADER]));
    }


    public function testCanSendPayloadWithGet()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\n\r\n"));
        $request = new puzzle_message_Request(
            'GET',
            puzzle_test_Server::$url,
            array(),
            puzzle_stream_Stream::factory('foo')
        );
        $this->emit($request);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();
        $h = $f->__invoke($t, new puzzle_message_MessageFactory());
        curl_exec($h);
        curl_close($h);
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertEquals('foo', (string) $sent->getBody());
        $this->assertEquals(3, (string) $sent->getHeader('Content-Length'));
    }

    private function emit(puzzle_message_RequestInterface $request)
    {
        $event = new puzzle_event_BeforeEvent(new puzzle_adapter_Transaction(new puzzle_Client(), $request));
        $request->getEmitter()->emit('before', $event);
    }

    public function testDoesNotAlwaysAddContentType()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n"));
        $client = new puzzle_Client();
        $client->put(puzzle_test_Server::$url . '/foo', array('body' => 'foo'));
        $rx = puzzle_test_Server::received(true);
        $request = $rx[0];
        $this->assertEquals('', $request->getHeader('Content-Type'));
    }

    /**
     * @expectedException puzzle_exception_AdapterException
     */
    public function testThrowsForStreamOption()
    {
        $request = new puzzle_message_Request('GET', puzzle_test_Server::$url . 'haha');
        $request->getConfig()->set('stream', true);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $f = new puzzle_adapter_curl_CurlFactory();

        $f->__invoke($t, new puzzle_message_MessageFactory());
    }
}


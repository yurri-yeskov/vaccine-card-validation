<?php

/**
 * @covers puzzle_adapter_StreamAdapter
 */
class puzzle_test_adapter_StreamAdapterTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testCanHandleExceptionsUsingEvents_mockResponse;

    private $_closure_testEmitsAfterSendEvent_ee;

    public function testReturnsResponseForSuccessfulRequest()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(
            "HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 2\r\n\r\nhi"
        );
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $response = $client->get('/', array('headers' => array('Foo' => 'Bar')));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('Bar', $response->getHeader('Foo'));
        $this->assertEquals('2', $response->getHeader('Content-Length'));
        $this->assertEquals('hi', $response->getBody());
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertEquals('GET', $sent->getMethod());
        $this->assertEquals('/', $sent->getResource());
        $this->assertEquals('127.0.0.1:8125', $sent->getHeader('host'));
        $this->assertEquals('Bar', $sent->getHeader('foo'));
        $this->assertTrue($sent->hasHeader('user-agent'));
    }

    /**
     * @expectedException puzzle_exception_RequestException
     * @expectedExceptionMessage Error creating resource. [url] http://localhost:123 [proxy] tcp://localhost:1234
     */
    public function testThrowsExceptionsCaughtDuringTransfer()
    {
        puzzle_test_Server::flush();
        $client = new puzzle_Client(array(
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory()),
        ));
        $client->get('http://localhost:123', array(
            'timeout' => 0.01,
            'proxy'   => 'tcp://localhost:1234'
        ));
    }

    /**
     * @expectedException puzzle_exception_RequestException
     * @expectedExceptionMessage URL is invalid: ftp://localhost:123
     */
    public function testEnsuresTheHttpProtocol()
    {
        puzzle_test_Server::flush();
        $client = new puzzle_Client(array(
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory()),
        ));
        $client->get('ftp://localhost:123');
    }

    public function testCanHandleExceptionsUsingEvents()
    {
        puzzle_test_Server::flush();
        $client = new puzzle_Client(array(
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $request = $client->createRequest('GET', puzzle_test_Server::$url);
        $this->_closure_testCanHandleExceptionsUsingEvents_mockResponse = new puzzle_message_Response(200);
        $request->getEmitter()->on(
            'error',
            array($this, '__callback_testCanHandleExceptionsUsingEvents')
        );
        $this->assertSame($this->_closure_testCanHandleExceptionsUsingEvents_mockResponse, $client->send($request));
    }

    public function __callback_testCanHandleExceptionsUsingEvents(puzzle_event_ErrorEvent $e)
    {
        $e->intercept($this->_closure_testCanHandleExceptionsUsingEvents_mockResponse);
    }

    public function testEmitsAfterSendEvent()
    {
        $this->_closure_testEmitsAfterSendEvent_ee = null;
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(
            "HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 8\r\n\r\nhi there"
        );
        $client = new puzzle_Client(array('adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())));
        $request = $client->createRequest('GET', puzzle_test_Server::$url);
        $request->getEmitter()->on('complete', array($this, '__callback_testEmitsAfterSendEvent'));
        $client->send($request);
        $this->assertInstanceOf('puzzle_event_CompleteEvent', $this->_closure_testEmitsAfterSendEvent_ee);
        $this->assertSame($request, $this->_closure_testEmitsAfterSendEvent_ee->getRequest());
        $this->assertEquals(200, $this->_closure_testEmitsAfterSendEvent_ee->getResponse()->getStatusCode());
    }

    public function __callback_testEmitsAfterSendEvent($e)
    {
        $this->_closure_testEmitsAfterSendEvent_ee = $e;
    }

    public function testStreamAttributeKeepsStreamOpen()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(
            "HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 8\r\n\r\nhi there"
        );
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $response = $client->put('/foo', array(
            'headers' => array('Foo' => 'Bar'),
            'body' => 'test',
            'stream' => true
        ));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('8', $response->getHeader('Content-Length'));
        $body = $response->getBody();
        if (defined('HHVM_VERSION')) {
            $this->markTestIncomplete('HHVM has not implemented this?');
        }
        $meta = $body->getMetadata();
        $this->assertEquals('http', $meta['wrapper_type']);
        $this->assertEquals(puzzle_test_Server::$url . 'foo', $meta['uri']);
        $this->assertEquals('hi', $body->read(2));
        $body->close();

        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertEquals('PUT', $sent->getMethod());
        $this->assertEquals('/foo', $sent->getResource());
        $this->assertEquals('127.0.0.1:8125', $sent->getHeader('host'));
        $this->assertEquals('Bar', $sent->getHeader('foo'));
        $this->assertTrue($sent->hasHeader('user-agent'));
    }

    public function testDrainsResponseIntoTempStream()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 8\r\n\r\nhi there");
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $response = $client->get('/');
        $body = $response->getBody();
        $meta = $body->getMetadata();
        $this->assertEquals('php://temp', $meta['uri']);
        $this->assertEquals('hi', $body->read(2));
        $body->close();
    }

    public function testDrainsResponseIntoSaveToBody()
    {
        $r = fopen('php://temp', 'r+');
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 8\r\n\r\nhi there");
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $response = $client->get('/', array('save_to' => $r));
        $body = $response->getBody();
        $meta = $body->getMetadata();
        $this->assertEquals('php://temp', $meta['uri']);
        $this->assertEquals('hi', $body->read(2));
        $this->assertEquals(' there', stream_get_contents($r));
        $body->close();
    }

    public function testDrainsResponseIntoSaveToBodyAtPath()
    {
        $tmpfname = tempnam('/tmp', 'save_to_path');
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 8\r\n\r\nhi there");
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $response = $client->get('/', array('save_to' => $tmpfname));
        $body = $response->getBody();
        $meta = $body->getMetadata();
        $this->assertEquals($tmpfname, $meta['uri']);
        $this->assertEquals('hi', $body->read(2));
        $body->close();
        unlink($tmpfname);
    }

    public function testAutomaticallyDecompressGzip()
    {
        puzzle_test_Server::flush();
        $content = gzencode('test');
        $message = "HTTP/1.1 200 OK\r\n"
            . "Foo: Bar\r\n"
            . "Content-Encoding: gzip\r\n"
            . "Content-Length: " . strlen($content) . "\r\n\r\n"
            . $content;
        puzzle_test_Server::enqueue($message);
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $response = $client->get('/', array('stream' => true));
        $body = $response->getBody();
        $meta = $body->getMetadata();
        $this->assertEquals('guzzle://stream', $meta['uri']);
        $this->assertEquals('test', (string) $body);
    }

    public function testDoesNotForceDecode()
    {
        puzzle_test_Server::flush();
        $content = gzencode('test');
        $message = "HTTP/1.1 200 OK\r\n"
            . "Foo: Bar\r\n"
            . "Content-Encoding: gzip\r\n"
            . "Content-Length: " . strlen($content) . "\r\n\r\n"
            . $content;
        puzzle_test_Server::enqueue($message);
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $response = $client->get('/', array(
            'decode_content' => false,
            'stream'         => true
        ));
        $body = $response->getBody();
        $this->assertSame($content, (string) $body);
    }

    protected function getStreamFromBody(puzzle_stream_Stream $body)
    {
        if (version_compare(PHP_VERSION, '5.3') < 0) {

            $this->markTestSkipped('PHP 5.2');
            return null;
        }
        $r = new ReflectionProperty($body, 'stream');
        $r->setAccessible(true);

        return $r->getValue($body);
    }

    protected function getSendResult(array $opts)
    {
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 8\r\n\r\nhi there");
        $client = new puzzle_Client(array('adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())));

        return $client->get(puzzle_test_Server::$url, $opts);
    }

    public function testAddsProxy()
    {
        $body = $this->getSendResult(array('stream' => true, 'proxy' => '127.0.0.1:8125'))->getBody();
        $opts = stream_context_get_options($this->getStreamFromBody($body));
        $this->assertEquals('127.0.0.1:8125', $opts['http']['proxy']);
    }

    public function testAddsTimeout()
    {
        $body = $this->getSendResult(array('stream' => true, 'timeout' => 200))->getBody();
        $opts = stream_context_get_options($this->getStreamFromBody($body));
        $this->assertEquals(200, $opts['http']['timeout']);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage SSL certificate authority file not found: /does/not/exist
     */
    public function testVerifiesVerifyIsValidIfPath()
    {
        $client = new puzzle_Client(array(
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory()),
            'base_url' => puzzle_test_Server::$url,
            'defaults' => array('verify' => '/does/not/exist')
        ));
        $client->get('/');
    }

    public function testVerifyCanBeDisabled()
    {
        puzzle_test_Server::enqueue("HTTP/1.1 200\r\nContent-Length: 0\r\n\r\n");
        $client = new puzzle_Client(array(
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory()),
            'base_url' => puzzle_test_Server::$url,
            'defaults' => array('verify' => false)
        ));
        $client->get('/');
    }

    public function testVerifyCanBeSetToPath()
    {
        $path = dirname(__FILE__) . '/../../../main/php/puzzle/cacert.pem';
        $this->assertFileExists($path);
        $body = $this->getSendResult(array('stream' => true, 'verify' => $path))->getBody();
        $opts = stream_context_get_options($this->getStreamFromBody($body));
        $this->assertEquals(true, $opts['http']['verify_peer']);
        $this->assertEquals($path, $opts['http']['cafile']);
        $this->assertTrue(file_exists($opts['http']['cafile']));
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage SSL certificate not found: /does/not/exist
     */
    public function testVerifiesCertIfValidPath()
    {
        $client = new puzzle_Client(array(
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory()),
            'base_url' => puzzle_test_Server::$url,
            'defaults' => array('cert' => '/does/not/exist')
        ));
        $client->get('/');
    }

    public function testCanSetPasswordWhenSettingCert()
    {
        $path = dirname(__FILE__) . '/../../../main/php/puzzle/cacert.pem';
        $body = $this->getSendResult(array('stream' => true, 'cert' => array($path, 'foo')))->getBody();
        $opts = stream_context_get_options($this->getStreamFromBody($body));
        $this->assertEquals($path, $opts['http']['local_cert']);
        $this->assertEquals('foo', $opts['http']['passphrase']);
    }

    public function testDebugAttributeWritesStreamInfoToTempBufferByDefault()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM has not implemented this?');
            return;
        }

        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nFoo: Bar\r\nContent-Length: 8\r\n\r\nhi there");
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        ob_start();
        $client->get('/', array('debug' => true));
        $contents = ob_get_clean();
        $this->assertContains('<http://127.0.0.1:8125/> [CONNECT]', $contents);
        $this->assertContains('<http://127.0.0.1:8125/> [FILE_SIZE_IS]', $contents);
        $this->assertContains('<http://127.0.0.1:8125/> [PROGRESS]', $contents);
    }

    public function testDebugAttributeWritesStreamInfoToBuffer()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('HHVM has not implemented this?');
            return;
        }

        $buffer = fopen('php://temp', 'r+');
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nContent-Length: 8\r\nContent-Type: text/plain\r\n\r\nhi there");
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $client->get('/', array('debug' => $buffer));
        fseek($buffer, 0);
        $contents = stream_get_contents($buffer);
        $this->assertContains('<http://127.0.0.1:8125/> [CONNECT]', $contents);
        $this->assertContains('<http://127.0.0.1:8125/> [FILE_SIZE_IS] message: "Content-Length: 8"', $contents);
        $this->assertContains('<http://127.0.0.1:8125/> [PROGRESS] bytes_max: "8"', $contents);
        $this->assertContains('<http://127.0.0.1:8125/> [MIME_TYPE_IS] message: "text/plain"', $contents);
    }

    public function testAddsProxyByProtocol()
    {
        $url = str_replace('http', 'tcp', puzzle_test_Server::$url);
        $body = $this->getSendResult(array('stream' => true, 'proxy' => array('http' => $url)))->getBody();
        $opts = stream_context_get_options($this->getStreamFromBody($body));
        $this->assertEquals($url, $opts['http']['proxy']);
    }

    public function testPerformsShallowMergeOfCustomContextOptions()
    {
        $body = $this->getSendResult(array(
            'stream' => true,
            'config' => array(
                'stream_context' => array(
                    'http' => array(
                        'request_fulluri' => true,
                        'method' => 'HEAD'
                    ),
                    'socket' => array(
                        'bindto' => '127.0.0.1:0'
                    ),
                    'ssl' => array(
                        'verify_peer' => false
                    )
                )
            )
        ))->getBody();

        $opts = stream_context_get_options($this->getStreamFromBody($body));
        $this->assertEquals('HEAD', $opts['http']['method']);
        $this->assertTrue($opts['http']['request_fulluri']);
        $this->assertFalse($opts['ssl']['verify_peer']);
        $this->assertEquals('127.0.0.1:0', $opts['socket']['bindto']);
    }

    /**
     * @expectedException puzzle_exception_RequestException
     * @expectedExceptionMessage stream_context must be an array
     */
    public function testEnsuresThatStreamContextIsAnArray()
    {
        $this->getSendResult(array(
            'stream' => true,
            'config' => array('stream_context' => 'foo')
        ));
    }

    /**
     * @ticket https://github.com/guzzle/guzzle/issues/725
     */
    public function testHandlesMultipleHeadersOfSameName()
    {
        if (version_compare(PHP_VERSION, '5.3') < 0) {

            $this->markTestSkipped('PHP 5.2');
            return;
        }

        $a = new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory());
        $ref = new ReflectionMethod($a, 'headersFromLines');
        $ref->setAccessible(true);
        $this->assertEquals(array(
            'foo' => array('bar', 'bam'),
            'abc' => array('123')
        ), $ref->invoke($a, array(
            'foo: bar',
            'foo: bam',
            'abc: 123'
        )));
    }

    public function testDoesNotAddContentTypeByDefault()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");
        $client = new puzzle_Client(array(
            'base_url' => puzzle_test_Server::$url,
            'adapter' => new puzzle_adapter_StreamAdapter(new puzzle_message_MessageFactory())
        ));
        $client->put('/', array('body' => 'foo'));
        $requests = puzzle_test_Server::received(true);
        $this->assertEquals('', $requests[0]->getHeader('Content-Type'));
        $this->assertEquals(3, $requests[0]->getHeader('Content-Length'));
    }
}

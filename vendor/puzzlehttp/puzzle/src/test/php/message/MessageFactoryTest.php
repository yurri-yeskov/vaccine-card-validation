<?php

/**
 * @covers puzzle_message_MessageFactory
 */
class puzzle_message_MessageFactoryTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testCanAddEvents_foo;
    private $_closure_testCanAddEventsWithPriority_foo;
    private $_closure_testCanAddEventsOnce_foo;

    public function testCreatesResponses()
    {
        $f = new puzzle_message_MessageFactory();
        $response = $f->createResponse(200, array('foo' => 'bar'), 'test', array(
            'protocol_version' => 1.0
        ));
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(array('foo' => array('bar')), $response->getHeaders());
        $this->assertEquals('test', $response->getBody());
        $this->assertEquals(1.0, $response->getProtocolVersion());
    }

    public function testCreatesRequestFromMessage()
    {
        $f = new puzzle_message_MessageFactory();
        $req = $f->fromMessage("GET / HTTP/1.1\r\nBaz: foo\r\n\r\n");
        $this->assertEquals('GET', $req->getMethod());
        $this->assertEquals('/', $req->getPath());
        $this->assertEquals('foo', $req->getHeader('Baz'));
        $this->assertNull($req->getBody());
    }

    public function testCreatesRequestFromMessageWithBody()
    {
        $factory = new puzzle_message_MessageFactory();
        $req = $factory->fromMessage("GET / HTTP/1.1\r\nBaz: foo\r\n\r\ntest");
        $this->assertEquals('test', $req->getBody());
    }

    public function testCreatesRequestWithPostBody()
    {
        $factory = new puzzle_message_MessageFactory();
        $req = $factory->createRequest('GET', 'http://www.foo.com', array('body' => array('abc' => '123')));
        $this->assertEquals('abc=123', $req->getBody());
    }

    public function testCreatesRequestWithPostBodyScalars()
    {
        $factory = new puzzle_message_MessageFactory();
        $req = $factory->createRequest(
            'GET',
            'http://www.foo.com',
            array('body' => array(
                'abc' => true,
                '123' => false,
                'foo' => null,
                'baz' => 10,
                'bam' => 1.5,
                'boo' => array(1))
            )
        );
        $this->assertEquals(
            'abc=1&123=&foo&baz=10&bam=1.5&boo%5B0%5D=1',
            (string) $req->getBody()
        );
    }

    public function testCreatesRequestWithPostBodyAndPostFiles()
    {
        $pf = fopen(__FILE__, 'r');
        $pfi = new puzzle_post_PostFile('ghi', 'abc', __FILE__);
        $factory = new puzzle_message_MessageFactory();
        $req = $factory->createRequest('GET', 'http://www.foo.com', array(
            'body' => array(
                'abc' => '123',
                'def' => $pf,
                'ghi' => $pfi
            )
        ));
        $this->assertInstanceOf('puzzle_post_PostBody', $req->getBody());
        $s = (string) $req;
        $this->assertContains('testCreatesRequestWithPostBodyAndPostFiles', $s);
        $this->assertContains('multipart/form-data', $s);
        $this->assertTrue(in_array($pfi, $req->getBody()->getFiles(), true));
    }

    public function testCreatesResponseFromMessage()
    {
        $factory = new puzzle_message_MessageFactory();
        $response = $factory->fromMessage("HTTP/1.1 200 OK\r\nContent-Length: 4\r\n\r\ntest");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('4', $response->getHeader('Content-Length'));
        $this->assertEquals('test', $response->getBody(true));
    }

    public function testCanCreateHeadResponses()
    {
        $factory = new puzzle_message_MessageFactory();
        $response = $factory->fromMessage("HTTP/1.1 200 OK\r\nContent-Length: 4\r\n\r\n");
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals(null, $response->getBody());
        $this->assertEquals('4', $response->getHeader('Content-Length'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testFactoryRequiresMessageForRequest()
    {
        $factory = new puzzle_message_MessageFactory();
        $factory->fromMessage('');
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage foo
     */
    public function testValidatesOptionsAreImplemented()
    {
        $factory = new puzzle_message_MessageFactory();
        $factory->createRequest('GET', 'http://test.com', array('foo' => 'bar'));
    }

    public function testOptionsAddsRequestOptions()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest(
            'GET', 'http://test.com', array('config' => array('baz' => 'bar'))
        );
        $this->assertEquals('bar', $request->getConfig()->get('baz'));
    }

    public function testCanDisableRedirects()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('allow_redirects' => false));
        $this->assertEmpty($request->getEmitter()->listeners('complete'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesRedirects()
    {
        $factory = new puzzle_message_MessageFactory();
        $factory->createRequest('GET', '/', array('allow_redirects' => array()));
    }

    public function testCanEnableStrictRedirectsAndSpecifyMax()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array(
            'allow_redirects' => array('max' => 10, 'strict' => true)
        ));
        $config = $request->getConfig();
        $this->assertTrue($config['redirect']['strict']);
        $this->assertEquals(10, $config['redirect']['max']);
    }

    public function testCanAddCookiesFromHash()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://www.test.com/', array(
            'cookies' => array('Foo' => 'Bar')
        ));
        $cookies = null;
        foreach ($request->getEmitter()->listeners('before') as $l) {
            if ($l[0] instanceof puzzle_subscriber_Cookie ) {
                $cookies = $l[0];
                break;
            }
        }
        if (!$cookies) {
            $this->fail('Did not add cookie listener');
        } else {
            $this->assertCount(1, $cookies->getCookieJar());
        }
    }

    public function testAddsCookieUsingTrue()
    {
        $factory = new puzzle_message_MessageFactory();
        $request1 = $factory->createRequest('GET', '/', array('cookies' => true));
        $request2 = $factory->createRequest('GET', '/', array('cookies' => true));
        $listeners = array($this, '__callback_testAddsCookieUsingTrue_1');
        $this->assertSame(call_user_func($listeners, $request1), call_user_func($listeners, $request2));
    }

    public function __callback_testAddsCookieUsingTrue_1($r)
    {
        return array_filter($r->getEmitter()->listeners('before'), array($this, '__callback_testAddsCookieUsingTrue_2'));
    }

    public function __callback_testAddsCookieUsingTrue_2($l)
    {
        return $l[0] instanceof puzzle_subscriber_Cookie;
    }

    public function testAddsCookieFromCookieJar()
    {
        $jar = new puzzle_cookie_CookieJar();
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('cookies' => $jar));
        foreach ($request->getEmitter()->listeners('before') as $l) {
            if ($l[0] instanceof puzzle_subscriber_Cookie ) {
                $this->assertSame($jar, $l[0]->getCookieJar());
            }
        }
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesCookies()
    {
        $factory = new puzzle_message_MessageFactory();
        $factory->createRequest('GET', '/', array('cookies' => 'baz'));
    }

    public function testCanAddQuery()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://foo.com', array(
            'query' => array('Foo' => 'Bar')
        ));
        $this->assertEquals('Bar', $request->getQuery()->get('Foo'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesQuery()
    {
        $factory = new puzzle_message_MessageFactory();
        $factory->createRequest('GET', 'http://foo.com', array(
            'query' => 'foo'
        ));
    }

    public function testCanSetDefaultQuery()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://foo.com?test=abc', array(
            'query' => array('Foo' => 'Bar', 'test' => 'def')
        ));
        $this->assertEquals('Bar', $request->getQuery()->get('Foo'));
        $this->assertEquals('abc', $request->getQuery()->get('test'));
    }

    public function testCanSetDefaultQueryWithObject()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://foo.com?test=abc', array(
            'query' => new puzzle_Query(array('Foo' => 'Bar', 'test' => 'def'))
        ));
        $this->assertEquals('Bar', $request->getQuery()->get('Foo'));
        $this->assertEquals('abc', $request->getQuery()->get('test'));
    }

    public function testCanAddBasicAuth()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://foo.com', array(
            'auth' => array('michael', 'test')
        ));
        $this->assertTrue($request->hasHeader('Authorization'));
    }

    public function testCanAddDigestAuth()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://foo.com', array(
            'auth' => array('michael', 'test', 'digest')
        ));
        $this->assertEquals('michael:test', $request->getConfig()->getPath('curl/' . CURLOPT_USERPWD));
        $this->assertEquals(CURLAUTH_DIGEST, $request->getConfig()->getPath('curl/' . CURLOPT_HTTPAUTH));
    }

    public function testCanDisableAuth()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://foo.com', array(
            'auth' => false
        ));
        $this->assertFalse($request->hasHeader('Authorization'));
    }

    public function testCanSetCustomAuth()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://foo.com', array(
            'auth' => 'foo'
        ));
        $config = $request->getConfig();
        $this->assertEquals('foo', $config['auth']);
    }

    public function testCanAddEvents()
    {
        $this->_closure_testCanAddEvents_foo = null;
        $client = new puzzle_Client();
        $client->getEmitter()->attach(new puzzle_subscriber_Mock(array(new puzzle_message_Response(200))));
        $client->get('http://test.com', array(
            'events' => array(
                'before' => array($this, '__callback_testCanAddEvents')
            )
        ));
        $this->assertTrue($this->_closure_testCanAddEvents_foo);
    }

    public function __callback_testCanAddEvents()
    {
        $this->_closure_testCanAddEvents_foo = true;
    }

    public function testCanAddEventsWithPriority()
    {
        $this->_closure_testCanAddEventsWithPriority_foo = null;
        $client = new puzzle_Client();
        $client->getEmitter()->attach(new puzzle_subscriber_Mock(array(new puzzle_message_Response(200))));
        $request = $client->createRequest('GET', 'http://test.com', array(
            'events' => array(
                'before' => array(
                    'fn' => array($this, '__callback_testCanAddEventsWithPriority'),
                    'priority' => 123
                )
            )
        ));
        $client->send($request);
        $this->assertTrue($this->_closure_testCanAddEventsWithPriority_foo);
        $l = $this->readAttribute($request->getEmitter(), 'listeners');
        $this->assertArrayHasKey(123, $l['before']);
    }

    public function __callback_testCanAddEventsWithPriority()
    {
        $this->_closure_testCanAddEventsWithPriority_foo = true;
    }

    public function testCanAddEventsOnce()
    {
        $this->_closure_testCanAddEventsOnce_foo = 0;
        $client = new puzzle_Client();
        $client->getEmitter()->attach(new puzzle_subscriber_Mock(array(
            new puzzle_message_Response(200),
            new puzzle_message_Response(200),
        )));
        $fn = array($this, '__callback_testCanAddEventsOnce');
        $request = $client->createRequest('GET', 'http://test.com', array(
            'events' => array('before' => array('fn' => $fn, 'once' => true))
        ));
        $client->send($request);
        $this->assertEquals(1, $this->_closure_testCanAddEventsOnce_foo);
        $client->send($request);
        $this->assertEquals(1, $this->_closure_testCanAddEventsOnce_foo);
    }

    public function __callback_testCanAddEventsOnce()
    {
        ++$this->_closure_testCanAddEventsOnce_foo;
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesEventContainsFn()
    {
        $client = new puzzle_Client(array('base_url' => 'http://test.com'));
        $client->createRequest('GET', '/', array('events' => array('before' => array('foo' => 'bar'))));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesEventIsArray()
    {
        $client = new puzzle_Client(array('base_url' => 'http://test.com'));
        $client->createRequest('GET', '/', array('events' => array('before' => '123')));
    }

    public function testCanAddSubscribers()
    {
        $mock = new puzzle_subscriber_Mock(array(new puzzle_message_Response(200)));
        $client = new puzzle_Client();
        $client->getEmitter()->attach($mock);
        $request = $client->get('http://test.com', array('subscribers' => array($mock)));
    }

    public function testCanDisableExceptions()
    {
        $client = new puzzle_Client();
        $this->assertEquals(500, $client->get('http://test.com', array(
            'subscribers' => array(new puzzle_subscriber_Mock(array(new puzzle_message_Response(500)))),
            'exceptions' => false
        ))->getStatusCode());
    }

    public function testCanChangeSaveToLocation()
    {
        $saveTo = puzzle_stream_Stream::factory();
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('save_to' => $saveTo));
        $this->assertSame($saveTo, $request->getConfig()->get('save_to'));
    }

    public function testCanSetProxy()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('proxy' => '192.168.16.121'));
        $this->assertEquals('192.168.16.121', $request->getConfig()->get('proxy'));
    }

    public function testCanSetHeadersOption()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('headers' => array('Foo' => 'Bar')));
        $this->assertEquals('Bar', (string) $request->getHeader('Foo'));
    }

    public function testCanSetHeaders()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array(
            'headers' => array('Foo' => array('Baz', 'Bar'), 'Test' => '123')
        ));
        $this->assertEquals('Baz, Bar', $request->getHeader('Foo'));
        $this->assertEquals('123', $request->getHeader('Test'));
    }

    public function testCanSetTimeoutOption()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('timeout' => 1.5));
        $this->assertEquals(1.5, $request->getConfig()->get('timeout'));
    }

    public function testCanSetConnectTimeoutOption()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('connect_timeout' => 1.5));
        $this->assertEquals(1.5, $request->getConfig()->get('connect_timeout'));
    }

    public function testCanSetDebug()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('debug' => true));
        $this->assertTrue($request->getConfig()->get('debug'));
    }

    public function testCanSetVerifyToOff()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('verify' => false));
        $this->assertFalse($request->getConfig()->get('verify'));
    }

    public function testCanSetVerifyToOn()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('verify' => true));
        $this->assertTrue($request->getConfig()->get('verify'));
    }

    public function testCanSetVerifyToPath()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('verify' => '/foo.pem'));
        $this->assertEquals('/foo.pem', $request->getConfig()->get('verify'));
    }

    public function inputValidation()
    {
        return array_map(array($this, '__callback_inputValidation'), array(
            'headers', 'events', 'subscribers', 'params'
        ));
    }

    public function __callback_inputValidation($option)
    {
        return array($option);
    }

    /**
     * @dataProvider inputValidation
     * @expectedException InvalidArgumentException
     */
    public function testValidatesInput($option)
    {
        $factory = new puzzle_message_MessageFactory();
        $factory->createRequest('GET', '/', array($option => 'foo'));
    }

    public function testCanAddSslKey()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('ssl_key' => '/foo.pem'));
        $this->assertEquals('/foo.pem', $request->getConfig()->get('ssl_key'));
    }

    public function testCanAddSslKeyPassword()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('ssl_key' => array('/foo.pem', 'bar')));
        $this->assertEquals(array('/foo.pem', 'bar'), $request->getConfig()->get('ssl_key'));
    }

    public function testCanAddSslCert()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('cert' => '/foo.pem'));
        $this->assertEquals('/foo.pem', $request->getConfig()->get('cert'));
    }

    public function testCanAddSslCertPassword()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', '/', array('cert' => array('/foo.pem', 'bar')));
        $this->assertEquals(array('/foo.pem', 'bar'), $request->getConfig()->get('cert'));
    }

    public function testCreatesBodyWithoutZeroString()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('PUT', 'http://test.com', array('body' => '0'));
        $this->assertSame('0', (string) $request->getBody());
    }

    public function testCanSetProtocolVersion()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('GET', 'http://test.com', array('version' => 1.0));
        $this->assertEquals(1.0, $request->getProtocolVersion());
    }

    public function testCanAddJsonData()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('PUT', 'http://f.com', array(
            'json' => array('foo' => 'bar')
        ));
        $this->assertEquals(
            'application/json',
            $request->getHeader('Content-Type')
        );
        $this->assertEquals('{"foo":"bar"}', (string) $request->getBody());
    }

    public function testCanAddJsonDataToAPostRequest()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('POST', 'http://f.com', array(
            'json' => array('foo' => 'bar')
        ));
        $this->assertEquals(
            'application/json',
            $request->getHeader('Content-Type')
        );
        $this->assertEquals('{"foo":"bar"}', (string) $request->getBody());
    }

    public function testCanAddJsonDataAndNotOverwriteContentType()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('PUT', 'http://f.com', array(
            'headers' => array('Content-Type' => 'foo'),
            'json' => null
        ));
        $this->assertEquals('foo', $request->getHeader('Content-Type'));
        $this->assertEquals('null', (string) $request->getBody());
    }

    public function testCanUseCustomSubclassesWithMethods()
    {
        $factory = new ExtendedFactory();
        $factory->createRequest('PUT', 'http://f.com', array(
            'headers' => array('Content-Type' => 'foo'),
            'foo' => 'bar'
        ));
        try {
            $f = new puzzle_message_MessageFactory;
            $f->createRequest('PUT', 'http://f.com', array(
                'headers' => array('Content-Type' => 'foo'),
                'foo' => 'bar'
            ));
        } catch (InvalidArgumentException $e) {
            $this->assertContains('foo config', $e->getMessage());
        }
    }

    /**
     * @ticket https://github.com/guzzle/guzzle/issues/706
     */
    public function testDoesNotApplyPostBodyRightAway()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('POST', 'http://f.cn', array(
            'body' => array('foo' => array('bar', 'baz'))
        ));
        $this->assertEquals('', $request->getHeader('Content-Type'));
        $this->assertEquals('', $request->getHeader('Content-Length'));
        $request->getBody()->setAggregator(puzzle_Query::duplicateAggregator());
        $request->getBody()->applyRequestHeaders($request);
        $this->assertEquals('foo=bar&foo=baz', $request->getBody());
    }

    public function testCanForceMultipartUploadWithContentType()
    {
        $client = new puzzle_Client();
        $client->getEmitter()->attach(new puzzle_subscriber_Mock(array(new puzzle_message_Response(200))));
        $history = new puzzle_subscriber_History();
        $client->getEmitter()->attach($history);
        $client->post('http://foo.com', array(
            'headers' => array('Content-Type' => 'multipart/form-data'),
            'body' => array('foo' => 'bar')
        ));
        $this->assertContains(
            'multipart/form-data; boundary=',
            $history->getLastRequest()->getHeader('Content-Type')
        );
        $this->assertContains(
            "Content-Disposition: form-data; name=\"foo\"\r\n\r\nbar",
            (string) $history->getLastRequest()->getBody()
        );
    }

    public function testDecodeDoesNotForceAcceptHeader()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('POST', 'http://f.cn', array(
            'decode_content' => true
        ));
        $this->assertEquals('', $request->getHeader('Accept-Encoding'));
        $this->assertTrue($request->getConfig()->get('decode_content'));
    }

    public function testDecodeCanAddAcceptHeader()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('POST', 'http://f.cn', array(
            'decode_content' => 'gzip'
        ));
        $this->assertEquals('gzip', $request->getHeader('Accept-Encoding'));
        $this->assertTrue($request->getConfig()->get('decode_content'));
    }

    public function testCanDisableDecoding()
    {
        $factory = new puzzle_message_MessageFactory();
        $request = $factory->createRequest('POST', 'http://f.cn', array(
            'decode_content' => false
        ));
        $this->assertEquals('', $request->getHeader('Accept-Encoding'));
        $this->assertNull($request->getConfig()->get('decode_content'));
    }
}

class ExtendedFactory extends puzzle_message_MessageFactory
{
    protected function add_foo() {}
}

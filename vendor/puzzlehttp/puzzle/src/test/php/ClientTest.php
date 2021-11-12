<?php

/**
 * @covers puzzle_Client
 */
class puzzle_ClientTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testClientMergesDefaultOptionsWithRequestOptions_o;

    private $_closure_testSendingRequestCanBeIntercepted_response;

    private $_closure_testCanSetCustomParallelAdapter_called;

    public function testProvidesDefaultUserAgent()
    {
        $this->assertEquals(1, preg_match('#^puzzle/.+ curl/.+ PHP/.+$#', puzzle_Client::getDefaultUserAgent()));
    }

    public function testUsesDefaultDefaultOptions()
    {
        $client = new puzzle_Client();
        $this->assertTrue($client->getDefaultOption('allow_redirects'));
        $this->assertTrue($client->getDefaultOption('exceptions'));
        $this->assertContains('cacert.pem', $client->getDefaultOption('verify'));
    }

    public function testUsesProvidedDefaultOptions()
    {
        $client = new puzzle_Client(array(
            'defaults' => array(
                'allow_redirects' => false,
                'query' => array('foo' => 'bar')
            )
        ));
        $this->assertFalse($client->getDefaultOption('allow_redirects'));
        $this->assertTrue($client->getDefaultOption('exceptions'));
        $this->assertContains('cacert.pem', $client->getDefaultOption('verify'));
        $this->assertEquals(array('foo' => 'bar'), $client->getDefaultOption('query'));
    }

    public function testCanSpecifyBaseUrl()
    {
        $client = (new puzzle_Client());
        $this->assertSame('', $client->getBaseUrl());
        $client = (new puzzle_Client(array(
            'base_url' => 'http://foo'
        )));
        $this->assertEquals('http://foo', $client->getBaseUrl());
    }

    public function testCanSpecifyBaseUrlUriTemplate()
    {
        $client = new puzzle_Client(array('base_url' => array('http://foo.com/{var}/', array('var' => 'baz'))));
        $this->assertEquals('http://foo.com/baz/', $client->getBaseUrl());
    }

    public function testClientUsesDefaultAdapterWhenNoneIsSet()
    {
        $client = new puzzle_Client();
        if (!extension_loaded('curl')) {
            $adapter = 'puzzle_adapter_StreamAdapter';
        } elseif (ini_get('allow_url_fopen')) {
            $adapter = 'puzzle_adapter_StreamingProxyAdapter';
        } else {
            $adapter = 'puzzle_adapter_curl_CurlAdapter';
        }
        $this->assertInstanceOf($adapter, $this->readAttribute($client, 'adapter'));
    }

    public function testCanSpecifyAdapter()
    {
        try {
            $adapter = $this->getMockBuilder('puzzle_adapter_AdapterInterface')
                ->setMethods(array('send'))
                ->getMockForAbstractClass();
            $adapter->expects($this->once())
                ->method('send')
                ->will($this->throwException(new Exception('Foo')));
            $client = new puzzle_Client(array('adapter' => $adapter));
            $client->get('http://httpbin.org');
        } catch (Exception $e) {

            $this->assertTrue($e instanceof puzzle_exception_RequestException && $e->getMessage() === 'Foo', get_class($e));
            return;
        }

        $this->fail('Should have thrown Exception');
    }

    public function testCanSpecifyMessageFactory()
    {
        try {
            $factory = $this->getMockBuilder('puzzle_message_MessageFactoryInterface')
                ->setMethods(array('createRequest'))
                ->getMockForAbstractClass();
            $factory->expects($this->once())
                ->method('createRequest')
                ->will($this->throwException(new Exception('Foo')));
            $client = new puzzle_Client(array('message_factory' => $factory));
            $client->get();
        } catch (Exception $e) {

            $this->assertTrue(get_class($e) === 'Exception' && $e->getMessage() === 'Foo');
            return;
        }

        $this->fail('Should have thrown Exception');
    }

    public function testCanSpecifyEmitter()
    {
        $emitter = $this->getMockBuilder('puzzle_event_EmitterInterface')
            ->setMethods(array('listeners'))
            ->getMockForAbstractClass();
        $emitter->expects($this->once())
            ->method('listeners')
            ->will($this->returnValue('foo'));

        $client = new puzzle_Client(array('emitter' => $emitter));
        $this->assertEquals('foo', $client->getEmitter()->listeners());
    }

    public function testAddsDefaultUserAgentHeaderWithDefaultOptions()
    {
        $client = new puzzle_Client(array('defaults' => array('allow_redirects' => false)));
        $this->assertFalse($client->getDefaultOption('allow_redirects'));
        $this->assertEquals(
            array('User-Agent' => puzzle_Client::getDefaultUserAgent()),
            $client->getDefaultOption('headers')
        );
    }

    public function testAddsDefaultUserAgentHeaderWithoutDefaultOptions()
    {
        $client = new puzzle_Client();
        $this->assertEquals(
            array('User-Agent' => puzzle_Client::getDefaultUserAgent()),
            $client->getDefaultOption('headers')
        );
    }

    private function getRequestClient()
    {
        $client = $this->getMockBuilder('puzzle_Client')
            ->setMethods(array('send'))
            ->getMock();
        $client->expects($this->once())
            ->method('send')
            ->will($this->returnArgument(0));

        return $client;
    }

    public function requestMethodProvider()
    {
        return array(
            array('GET', false),
            array('HEAD', false),
            array('DELETE', false),
            array('OPTIONS', false),
            array('POST', 'foo'),
            array('PUT', 'foo'),
            array('PATCH', 'foo')
        );
    }

    /**
     * @dataProvider requestMethodProvider
     */
    public function testClientProvidesMethodShortcut($method, $body)
    {
        $client = $this->getRequestClient();
        if ($body) {
            $request = $client->{$method}('http://foo.com', array(
                'headers' => array('X-Baz' => 'Bar'),
                'body' => $body,
                'query' => array('a' => 'b')
            ));
        } else {
            $request = $client->{$method}('http://foo.com', array(
                'headers' => array('X-Baz' => 'Bar'),
                'query' => array('a' => 'b')
            ));
        }
        $this->assertEquals($method, $request->getMethod());
        $this->assertEquals('Bar', $request->getHeader('X-Baz'));
        $this->assertEquals('a=b', $request->getQuery());
        if ($body) {
            $this->assertEquals($body, $request->getBody());
        }
    }

    public function testClientMergesDefaultOptionsWithRequestOptions()
    {
        $f = $this->getMockBuilder('puzzle_message_MessageFactoryInterface')
            ->setMethods(array('createRequest'))
            ->getMockForAbstractClass();

        $this->_closure_testClientMergesDefaultOptionsWithRequestOptions_o = null;
        // Intercept the creation
        $f->expects($this->once())
            ->method('createRequest')
            ->will($this->returnCallback(array($this, '__callback_testClientMergesDefaultOptionsWithRequestOptions')));

        $client = new puzzle_Client(array(
            'message_factory' => $f,
            'defaults' => array(
                'headers' => array('Foo' => 'Bar'),
                'query' => array('baz' => 'bam'),
                'exceptions' => false
            )
        ));

        $request = $client->createRequest('GET', 'http://foo.com?a=b', array(
            'headers' => array('Hi' => 'there', '1' => 'one'),
            'allow_redirects' => false,
            'query' => array('t' => 1)
        ));

        $this->assertFalse($this->_closure_testClientMergesDefaultOptionsWithRequestOptions_o['allow_redirects']);
        $this->assertFalse($this->_closure_testClientMergesDefaultOptionsWithRequestOptions_o['exceptions']);
        $this->assertEquals('Bar', $request->getHeader('Foo'));
        $this->assertEquals('there', $request->getHeader('Hi'));
        $this->assertEquals('one', $request->getHeader('1'));
        $this->assertEquals('a=b&baz=bam&t=1', $request->getQuery());
    }

    public function testClientMergesDefaultHeadersCaseInsensitively()
    {
        $client = new puzzle_Client(array('defaults' => array('headers' => array('Foo' => 'Bar'))));
        $request = $client->createRequest('GET', 'http://foo.com?a=b', array(
            'headers' => array('foo' => 'custom', 'user-agent' => 'test')
        ));
        $this->assertEquals('test', $request->getHeader('User-Agent'));
        $this->assertEquals('custom', $request->getHeader('Foo'));
    }

    public function testUsesBaseUrlWhenNoUrlIsSet()
    {
        $client = new puzzle_Client(array('base_url' => 'http://www.foo.com/baz?bam=bar'));
        $this->assertEquals(
            'http://www.foo.com/baz?bam=bar',
            $client->createRequest('GET')->getUrl()
        );
    }

    public function testUsesBaseUrlCombinedWithProvidedUrl()
    {
        $client = new puzzle_Client(array('base_url' => 'http://www.foo.com/baz?bam=bar'));
        $this->assertEquals(
            'http://www.foo.com/bar/bam',
            $client->createRequest('GET', 'bar/bam')->getUrl()
        );
    }

    public function testUsesBaseUrlCombinedWithProvidedUrlViaUriTemplate()
    {
        $client = new puzzle_Client(array('base_url' => 'http://www.foo.com/baz?bam=bar'));
        $this->assertEquals(
            'http://www.foo.com/bar/123',
            $client->createRequest('GET', array('bar/{bam}', array('bam' => '123')))->getUrl()
        );
    }

    public function testSettingAbsoluteUrlOverridesBaseUrl()
    {
        $client = new puzzle_Client(array('base_url' => 'http://www.foo.com/baz?bam=bar'));
        $this->assertEquals(
            'http://www.foo.com/foo',
            $client->createRequest('GET', '/foo')->getUrl()
        );
    }

    public function testSettingAbsoluteUriTemplateOverridesBaseUrl()
    {
        $client = new puzzle_Client(array('base_url' => 'http://www.foo.com/baz?bam=bar'));
        $this->assertEquals(
            'http://goo.com/1',
            $client->createRequest(
                'GET',
                array('http://goo.com/{bar}', array('bar' => '1'))
            )->getUrl()
        );
    }

    public function testCanSetRelativeUrlStartingWithHttp()
    {
        $client = new puzzle_Client(array('base_url' => 'http://www.foo.com'));
        $this->assertEquals(
            'http://www.foo.com/httpfoo',
            $client->createRequest('GET', 'httpfoo')->getUrl()
        );
    }

    public function testClientSendsRequests()
    {
        $response = new puzzle_message_Response(200);
        $adapter = new puzzle_adapter_MockAdapter();
        $adapter->setResponse($response);
        $client = new puzzle_Client(array('adapter' => $adapter));
        $this->assertSame($response, $client->get('http://test.com'));
        $this->assertEquals('http://test.com', $response->getEffectiveUrl());
    }

    public function testSendingRequestCanBeIntercepted()
    {
        $response = new puzzle_message_Response(200);
        $this->_closure_testSendingRequestCanBeIntercepted_response = new puzzle_message_Response(200);
        $adapter = new puzzle_adapter_MockAdapter();
        $adapter->setResponse($response);
        $client = new puzzle_Client(array('adapter' => $adapter));
        $client->getEmitter()->on(
            'before', array($this, '__callback_testSendingRequestCanBeIntercepted')
        );
        $this->assertSame($this->_closure_testSendingRequestCanBeIntercepted_response, $client->get('http://test.com'));
        $this->assertEquals('http://test.com', $this->_closure_testSendingRequestCanBeIntercepted_response->getEffectiveUrl());
    }

    /**
     * @expectedException puzzle_exception_RequestException
     * @expectedExceptionMessage No response
     */
    public function testEnsuresResponseIsPresentAfterSending()
    {
        $adapter = $this->getMockBuilder('puzzle_adapter_MockAdapter')
            ->setMethods(array('send'))
            ->getMock();
        $adapter->expects($this->once())
            ->method('send');
        $client = new puzzle_Client(array('adapter' => $adapter));
        $client->get('http://httpbin.org');
    }

    public function testClientHandlesErrorsDuringBeforeSend()
    {
        $client = new puzzle_Client();
        $client->getEmitter()->on('before', array($this, '__callback_throwFooException'));
        $client->getEmitter()->on('error', array($this, '__callback_testClientHandlesErrorsDuringBeforeSend'));
        $this->assertEquals(200, $client->get('http://test.com')->getStatusCode());
    }

    /**
     * @expectedException puzzle_exception_RequestException
     * @expectedExceptionMessage foo
     */
    public function testClientHandlesErrorsDuringBeforeSendAndThrowsIfUnhandled()
    {
        $client = new puzzle_Client();
        $client->getEmitter()->on('before', array($this, '__callback_testClientHandlesErrorsDuringBeforeSendAndThrowsIfUnhandled'));
        $client->get('http://httpbin.org');
    }

    /**
     * @expectedException puzzle_exception_RequestException
     * @expectedExceptionMessage foo
     */
    public function testClientWrapsExceptions()
    {
        $client = new puzzle_Client();
        $client->getEmitter()->on('before', array($this, '__callback_throwFooException'));
        $client->get('http://httpbin.org');
    }

    public function testCanSetDefaultValues()
    {
        $client = new puzzle_Client(array('foo' => 'bar'));
        $client->setDefaultOption('headers/foo', 'bar');
        $this->assertNull($client->getDefaultOption('foo'));
        $this->assertEquals('bar', $client->getDefaultOption('headers/foo'));
    }

    public function testSendsAllInParallel()
    {
        $client = new puzzle_Client();
        $client->getEmitter()->attach(new puzzle_subscriber_Mock(array(
            new puzzle_message_Response(200),
            new puzzle_message_Response(201),
            new puzzle_message_Response(202),
        )));
        $history = new puzzle_subscriber_History();
        $client->getEmitter()->attach($history);

        $requests = array(
            $client->createRequest('GET', 'http://test.com'),
            $client->createRequest('POST', 'http://test.com'),
            $client->createRequest('PUT', 'http://test.com')
        );

        $client->sendAll($requests);
        $requests = array_map(array($this, '__callback_testSendsAllInParallel'), $history->getRequests());
        $this->assertContains('GET', $requests);
        $this->assertContains('POST', $requests);
        $this->assertContains('PUT', $requests);
    }

    public function testCanSetCustomParallelAdapter()
    {
        $this->_closure_testCanSetCustomParallelAdapter_called = false;
        $pa = new puzzle_adapter_FakeParallelAdapter(new puzzle_adapter_MockAdapter(array($this, '__callback_testCanSetCustomParallelAdapter')));
        $client = new puzzle_Client(array('parallel_adapter' => $pa));
        $client->sendAll(array($client->createRequest('GET', 'http://www.foo.com')));
        $this->assertTrue($this->_closure_testCanSetCustomParallelAdapter_called);
    }

    public function testCanDisableAuthPerRequest()
    {
        $client = new puzzle_Client(array('defaults' => array('auth' => 'foo')));
        $request = $client->createRequest('GET', 'http://test.com');
        $config = $request->getConfig();
        $this->assertEquals('foo', $config['auth']);
        $request = $client->createRequest('GET', 'http://test.com', array('auth' => null));
        $this->assertFalse($request->getConfig()->hasKey('auth'));
    }

    public function testHasDeprecatedGetEmitter()
    {
        try {

            $client = new puzzle_Client();
            $client->getEventDispatcher();
        } catch (Exception $e) {
            if (version_compare(PHP_VERSION, '5.3') >= 0) {
                $this->assertInstanceOf('PHPUnit_Framework_Error_Deprecated', $e);
            } else {
                $this->assertInstanceOf('PHPUnit_Framework_Error_Notice', $e);
            }
            return;
        }
        $this->fail('Should have thrown exception');
    }

    public function testUsesProxyEnvironmentVariables()
    {
        $http = isset($_SERVER['HTTP_PROXY']) ? $_SERVER['HTTP_PROXY'] : null;
        $https = isset($_SERVER['HTTPS_PROXY']) ? $_SERVER['HTTPS_PROXY'] : null;
        unset($_SERVER['HTTP_PROXY']);
        unset($_SERVER['HTTPS_PROXY']);

        $client = new puzzle_Client();
        $this->assertNull($client->getDefaultOption('proxy'));

        $_SERVER['HTTP_PROXY'] = '127.0.0.1';
        $client = new puzzle_Client();
        $this->assertEquals(
            array('http' => '127.0.0.1'),
            $client->getDefaultOption('proxy')
        );

        $_SERVER['HTTPS_PROXY'] = '127.0.0.2';
        $client = new puzzle_Client();
        $this->assertEquals(
            array('http' => '127.0.0.1', 'https' => '127.0.0.2'),
            $client->getDefaultOption('proxy')
        );

        $_SERVER['HTTP_PROXY'] = $http;
        $_SERVER['HTTPS_PROXY'] = $https;
    }

    public function __callback_testClientMergesDefaultOptionsWithRequestOptions($method, $url, array $options = array())
    {
        $this->_closure_testClientMergesDefaultOptionsWithRequestOptions_o = $options;
        $factory = new puzzle_message_MessageFactory();
        return $factory->createRequest($method, $url, $options);
    }

    public function __callback_testSendingRequestCanBeIntercepted(puzzle_event_BeforeEvent $e) {
        $e->intercept($this->_closure_testSendingRequestCanBeIntercepted_response);
    }

    public function __callback_throwFooException()
    {
        throw new Exception('foo');
    }

    public function __callback_testClientHandlesErrorsDuringBeforeSend($e)
    {
        $e->intercept(new puzzle_message_Response(200));
    }

    public function __callback_testClientHandlesErrorsDuringBeforeSendAndThrowsIfUnhandled ($e)
    {
        throw new puzzle_exception_RequestException('foo', $e->getRequest());
    }

    public function __callback_testSendsAllInParallel($r)
    {
        return $r->getMethod();
    }

    public function __callback_testCanSetCustomParallelAdapter()
    {
        $this->_closure_testCanSetCustomParallelAdapter_called = true;
        return new puzzle_message_Response(203);
    }
}

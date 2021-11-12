<?php

class puzzle_test_FunctionsTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testBatchesRequests_a;
    private $_closure_testBatchesRequests_b;
    private $_closure_testBatchesRequests_c;

    public function testExpandsTemplate()
    {
        $this->assertEquals('foo/123', puzzle_uri_template('foo/{bar}', array('bar' => '123')));
    }

    public function noBodyProvider()
    {
        return array(array('get'), array('head'), array('delete'));
    }

    /**
     * @dataProvider noBodyProvider
     */
    public function testSendsNoBody($method)
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array(new puzzle_message_Response(200)));
        call_user_func("puzzle_{$method}", puzzle_test_Server::$url, array(
            'headers' => array('foo' => 'bar'),
            'query' => array('a' => '1')
        ));
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertEquals(strtoupper($method), $sent->getMethod());
        $this->assertEquals('/?a=1', $sent->getResource());
        $this->assertEquals('bar', $sent->getHeader('foo'));
    }

    public function testSendsOptionsRequest()
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array(new puzzle_message_Response(200)));
        puzzle_options(puzzle_test_Server::$url, array('headers' => array('foo' => 'bar')));
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertEquals('OPTIONS', $sent->getMethod());
        $this->assertEquals('/', $sent->getResource());
        $this->assertEquals('bar', $sent->getHeader('foo'));
    }

    public function hasBodyProvider()
    {
        return array(array('put'), array('post'), array('patch'));
    }

    /**
     * @dataProvider hasBodyProvider
     */
    public function testSendsWithBody($method)
    {
        puzzle_test_Server::flush();
        puzzle_test_Server::enqueue(array(new puzzle_message_Response(200)));
        call_user_func("puzzle_{$method}", puzzle_test_Server::$url, array(
            'headers' => array('foo' => 'bar'),
            'body'    => 'test',
            'query'   => array('a' => '1')
        ));
        $rx = puzzle_test_Server::received(true);
        $sent = $rx[0];
        $this->assertEquals(strtoupper($method), $sent->getMethod());
        $this->assertEquals('/?a=1', $sent->getResource());
        $this->assertEquals('bar', $sent->getHeader('foo'));
        $this->assertEquals('test', $sent->getBody());
    }

    public function testManagesDeprecatedMethods()
    {
        try {

            $d = new puzzle_test_HasDeprecations();
        $d->baz();

        } catch (Exception $e) {

            $this->assertEquals('puzzle_test_HasDeprecations::baz() is deprecated and will be removed in a future version. Update your code to use the equivalent puzzle_test_HasDeprecations::foo() method instead to avoid breaking changes when this shim is removed.', $e->getMessage());

            if (version_compare(PHP_VERSION, '5.3') >= 0) {

                $this->assertInstanceOf('PHPUnit_Framework_Error_Deprecated', $e);

            } else {

                $this->assertInstanceOf('PHPUnit_Framework_Error_Notice', $e);
    }
            return;
        }

        $this->fail('Should have thrown Exception');
    }

    /**
     * @expectedException BadMethodCallException
     */
    public function testManagesDeprecatedMethodsAndHandlesMissingMethods()
    {
        $d = new puzzle_test_HasDeprecations();
        $d->doesNotExist();
    }

    public function testBatchesRequests()
    {
        $client = new puzzle_Client();
        $responses = array(
            new puzzle_message_Response(301, array('Location' => 'http://foo.com/bar')),
            new puzzle_message_Response(200),
            new puzzle_message_Response(200),
            new puzzle_message_Response(404)
        );
        $client->getEmitter()->attach(new puzzle_subscriber_Mock($responses));
        $requests = array(
            $client->createRequest('GET', 'http://foo.com/baz'),
            $client->createRequest('HEAD', 'http://httpbin.org/get'),
            $client->createRequest('PUT', 'http://httpbin.org/put'),
        );

        $this->_closure_testBatchesRequests_a = $this->_closure_testBatchesRequests_b = $this->_closure_testBatchesRequests_c = 0;
        $result = puzzle_batch($client, $requests, array(
            'before'   => array($this, '__callback_testBatchesRequests_a'),
            'complete' => array($this, '__callback_testBatchesRequests_b'),
            'error'    => array($this, '__callback_testBatchesRequests_c'),
        ));

        $this->assertEquals(4, $this->_closure_testBatchesRequests_a);
        $this->assertEquals(2, $this->_closure_testBatchesRequests_b);
        $this->assertEquals(1, $this->_closure_testBatchesRequests_c);
        $this->assertCount(3, $result);

        foreach ($result as $i => $request) {
            $this->assertSame($requests[$i], $request);
        }

        // The first result is actually the second (redirect) response.
        $this->assertSame($responses[1], $result->offsetGet($requests[0]));
        // The second result is a 1:1 request:response map
        $this->assertSame($responses[2], $result->offsetGet($requests[1]));
        // The third entry is the 404 puzzle_exception_RequestException
        $this->assertSame($responses[3], $result->offsetGet($requests[2])->getResponse());
    }

    public function __callback_testBatchesRequests_a(puzzle_event_BeforeEvent $e)
    {
        $this->_closure_testBatchesRequests_a++;
    }

    public function __callback_testBatchesRequests_b(puzzle_event_CompleteEvent $e)
    {
        $this->_closure_testBatchesRequests_b++;
    }

    public function __callback_testBatchesRequests_c(puzzle_event_ErrorEvent $e)
    {
        $this->_closure_testBatchesRequests_c++;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid event format
     */
    public function testBatchValidatesTheEventFormat()
    {
        $client = new puzzle_Client();
        $requests = array($client->createRequest('GET', 'http://foo.com/baz'));
        puzzle_batch($client, $requests, array('complete' => 'foo'));
    }

    public function testJsonDecodes()
    {
        $data = puzzle_json_decode('true');
        $this->assertTrue($data);
    }

    public function testJsonDecodesWithErrorMessages()
    {
        if (version_compare(PHP_VERSION, '5.3') >= 0) {

            $this->setExpectedException('InvalidArgumentException', 'Unable to parse JSON data: JSON_ERROR_SYNTAX - Syntax error, malformed JSON');

        } else {

            $this->setExpectedException('InvalidArgumentException', 'Unable to parse JSON data: Unknown error');
        }

        puzzle_json_decode('!narf!');
    }
}

class puzzle_test_HasDeprecations
{
    function foo()
    {
        return 'abc';
    }
    function __call($name, $arguments)
    {
        return puzzle_deprecation_proxy($this, $name, $arguments, array(
            'baz' => 'foo'
        ));
    }
}

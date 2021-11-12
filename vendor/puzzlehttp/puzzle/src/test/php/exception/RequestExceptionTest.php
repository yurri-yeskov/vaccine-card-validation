<?php

/**
 * @covers puzzle_exception_RequestException
 */
class puzzle_test_exception_RequestExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testHasRequestAndResponse()
    {
        $req = new puzzle_message_Request('GET', '/');
        $res = new puzzle_message_Response(200);
        $e = new puzzle_exception_RequestException('foo', $req, $res);
        $this->assertSame($req, $e->getRequest());
        $this->assertSame($res, $e->getResponse());
        $this->assertTrue($e->hasResponse());
        $this->assertEquals('foo', $e->getMessage());
    }

    public function testCreatesGenerateException()
    {
        $e = puzzle_exception_RequestException::create(new puzzle_message_Request('GET', '/'));
        $this->assertEquals('Error completing request', $e->getMessage());
        $this->assertInstanceOf('puzzle_exception_RequestException', $e);
    }

    public function testCreatesClientErrorResponseException()
    {
        $e = puzzle_exception_RequestException::create(new puzzle_message_Request('GET', '/'), new puzzle_message_Response(400));
        $this->assertEquals(
            'Client error response [url] / [status code] 400 [reason phrase] Bad Request',
            $e->getMessage()
        );
        $this->assertInstanceOf('puzzle_exception_ClientException', $e);
    }

    public function testCreatesServerErrorResponseException()
    {
        $e = puzzle_exception_RequestException::create(new puzzle_message_Request('GET', '/'), new puzzle_message_Response(500));
        $this->assertEquals(
            'Server error response [url] / [status code] 500 [reason phrase] Internal Server Error',
            $e->getMessage()
        );
        $this->assertInstanceOf('puzzle_exception_ServerException', $e);
    }

    public function testCreatesGenericErrorResponseException()
    {
        $e = puzzle_exception_RequestException::create(new puzzle_message_Request('GET', '/'), new puzzle_message_Response(600));
        $this->assertEquals(
            'Unsuccessful response [url] / [status code] 600 [reason phrase] ',
            $e->getMessage()
        );
        $this->assertInstanceOf('puzzle_exception_RequestException', $e);
    }

    public function testCanSetAndRetrieveErrorEmitted()
    {
        $e = puzzle_exception_RequestException::create(new puzzle_message_Request('GET', '/'), new puzzle_message_Response(600));
        $this->assertFalse($e->emittedError());
        $e->emittedError(true);
        $this->assertTrue($e->emittedError());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCannotSetEmittedErrorToFalse()
    {
        $e = puzzle_exception_RequestException::create(new puzzle_message_Request('GET', '/'), new puzzle_message_Response(600));
        $e->emittedError(true);
        $e->emittedError(false);
    }

    public function testHasStatusCodeAsExceptionCode() {
        $e = puzzle_exception_RequestException::create(new puzzle_message_Request('GET', '/'), new puzzle_message_Response(442));
        $this->assertEquals(442, $e->getCode());
    }

    public function testHasThrowState() {
        $e = puzzle_exception_RequestException::create(
            new puzzle_message_Request('GET', '/'),
            new puzzle_message_Response(442)
        );
        $this->assertFalse($e->getThrowImmediately());
        $e->setThrowImmediately(true);
        $this->assertTrue($e->getThrowImmediately());
    }
}

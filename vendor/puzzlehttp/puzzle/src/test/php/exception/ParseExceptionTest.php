<?php

/**
 * @covers puzzle_exception_ParseException
 */
class puzzle_test_exception_ParseExceptionTest extends PHPUnit_Framework_TestCase
{
    public function testHasResponse()
    {
        $res = new puzzle_message_Response(200);
        $e = new puzzle_exception_ParseException('foo', $res);
        $this->assertSame($res, $e->getResponse());
        $this->assertEquals('foo', $e->getMessage());
    }
}

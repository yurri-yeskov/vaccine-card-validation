<?php

/**
 * @covers puzzle_adapter_Transaction
 */
class puzzle_test_adapter_TransactionTest extends PHPUnit_Framework_TestCase
{
    public function testHasRequestAndClient()
    {
        $c = new puzzle_Client();
        $req = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(200);
        $t = new puzzle_adapter_Transaction($c, $req);
        $this->assertSame($c, $t->getClient());
        $this->assertSame($req, $t->getRequest());
        $this->assertNull($t->getResponse());
        $t->setResponse($response);
        $this->assertSame($response, $t->getResponse());
    }
}

<?php

class puzzle_test_adapter_TransactionIteratorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesConstructor()
    {
        new puzzle_adapter_TransactionIterator('foo', new puzzle_Client(), array());
    }

    public function testCreatesTransactions()
    {
        $client = new puzzle_Client();
        $requests = array(
            $client->createRequest('GET', 'http://test.com'),
            $client->createRequest('POST', 'http://test.com'),
            $client->createRequest('PUT', 'http://test.com'),
        );
        $t = new puzzle_adapter_TransactionIterator($requests, $client, array());
        $this->assertEquals(0, $t->key());
        $this->assertTrue($t->valid());
        $this->assertEquals('GET', $t->current()->getRequest()->getMethod());
        $t->next();
        $this->assertEquals(1, $t->key());
        $this->assertTrue($t->valid());
        $this->assertEquals('POST', $t->current()->getRequest()->getMethod());
        $t->next();
        $this->assertEquals(2, $t->key());
        $this->assertTrue($t->valid());
        $this->assertEquals('PUT', $t->current()->getRequest()->getMethod());
    }

    public function testCanForeach()
    {
        $c = new puzzle_Client();
        $requests = array(
            $c->createRequest('GET', 'http://test.com'),
            $c->createRequest('POST', 'http://test.com'),
            $c->createRequest('PUT', 'http://test.com'),
        );

        $t = new puzzle_adapter_TransactionIterator(new ArrayIterator($requests), $c, array());
        $methods = array();

        foreach ($t as $trans) {
            $this->assertInstanceOf(
                'puzzle_adapter_TransactionInterface',
                $trans
            );
            $methods[] = $trans->getRequest()->getMethod();
        }

        $this->assertEquals(array('GET', 'POST', 'PUT'), $methods);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testValidatesEachElement()
    {
        $c = new puzzle_Client();
        $requests = array('foo');
        $t = new puzzle_adapter_TransactionIterator(new ArrayIterator($requests), $c, array());
        iterator_to_array($t);
    }
}

<?php

/**
 * @covers puzzle_adapter_FakeParallelAdapter
 */
class puzzle_test_adapter_FakeParallelAdapterTest extends PHPUnit_Framework_TestCase
{
    private $_closure_sent;

    public function testSendsAllTransactions()
    {
        $client = new puzzle_Client();
        $requests = array(
            $client->createRequest('GET', 'http://httbin.org'),
            $client->createRequest('HEAD', 'http://httbin.org'),
        );

        $this->_closure_sent = array();
        $f = new puzzle_adapter_FakeParallelAdapter(new puzzle_adapter_MockAdapter(array($this, '__callback_testSendsAllTransactions')));

        $tIter = new puzzle_adapter_TransactionIterator($requests, $client, array());
        $f->sendAll($tIter, 2);
        $this->assertContains('GET', $this->_closure_sent);
        $this->assertContains('HEAD', $this->_closure_sent);
    }

    public function __callback_testSendsAllTransactions($trans)
    {
        $this->_closure_sent[] = $trans->getRequest()->getMethod();
        return new puzzle_message_Response(200);
    }

    public function testThrowsImmediatelyIfInstructed()
    {
        $client = new puzzle_Client();
        $request = $client->createRequest('GET', 'http://httbin.org');
        $request->getEmitter()->on('error', array($this, '__callback_testThrowsImmediatelyIfInstructed_1'));
        $this->_closure_sent = array();
        $f = new puzzle_adapter_FakeParallelAdapter(
            new puzzle_adapter_MockAdapter(array($this, '__callback_testThrowsImmediatelyIfInstructed_2'))
        );
        $tIter = new puzzle_adapter_TransactionIterator(array($request), $client, array());
        try {
            $f->sendAll($tIter, 1);
            $this->fail('Did not throw');
        } catch (puzzle_exception_RequestException $e) {
            $this->assertSame($request, $e->getRequest());
        }
    }

    public function __callback_testThrowsImmediatelyIfInstructed_1(puzzle_event_ErrorEvent $e)
    {
        $e->throwImmediately(true);
    }

    public function __callback_testThrowsImmediatelyIfInstructed_2($trans)
    {
        $this->_closure_sent[] = $trans->getRequest()->getMethod();
        return new puzzle_message_Response(404);
    }
}

<?php

/**
 * @covers puzzle_subscriber_HttpError
 */
class puzzle_test_subscriber_HttpErrorTest extends PHPUnit_Framework_TestCase
{
    public function testIgnoreSuccessfulRequests()
    {
        $event = $this->getEvent();
        $event->intercept(new puzzle_message_Response(200));
        $error = new puzzle_subscriber_HttpError();
        $error->onComplete($event);
    }

    /**
     * @expectedException puzzle_exception_ClientException
     */
    public function testThrowsClientExceptionOnFailure()
    {
        $event = $this->getEvent();
        $event->intercept(new puzzle_message_Response(403));
        $error = new puzzle_subscriber_HttpError();
        $error->onComplete($event);    }

    /**
     * @expectedException puzzle_exception_ServerException
     */
    public function testThrowsServerExceptionOnFailure()
    {
        $event = $this->getEvent();
        $event->intercept(new puzzle_message_Response(500));
        $error = new puzzle_subscriber_HttpError();
        $error->onComplete($event);    }

    private function getEvent()
    {
        return new puzzle_event_CompleteEvent(new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('PUT', '/')));
    }

    /**
     * @expectedException puzzle_exception_ClientException
     */
    public function testFullTransaction()
    {
        $client = new puzzle_Client();
        $client->getEmitter()->attach(new puzzle_subscriber_Mock(array(
            new puzzle_message_Response(403)
        )));
        $client->get('http://httpbin.org');
    }
}

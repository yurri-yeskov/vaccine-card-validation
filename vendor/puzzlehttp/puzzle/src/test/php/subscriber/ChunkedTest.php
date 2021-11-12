<?php

/**
 * @covers puzzle_subscriber_Chunked
 */
class puzzle_test_cookie_ChunkedTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getDataDecode
     */
    public function testDecode($incoming, $expectedOutput)
    {
        $plugin = new puzzle_subscriber_Chunked();

        $request = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(200);
        $response->setHeader('Transfer-Encoding', 'chunked');
        $response->setBody(puzzle_stream_Stream::factory($incoming));
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $t->setResponse($response);
        $ev = new puzzle_event_CompleteEvent($t);

        $plugin->onComplete($ev);

        $newBody = $ev->getResponse()->getBody();
        $this->assertEquals($expectedOutput, "$newBody");
    }

    public function getDataDecode()
    {
        $raw = $this->_decodeTestArray();
        $toReturn = array();
        foreach ($raw as $decoded => $encoded) {
            $toReturn[] = array($encoded, $decoded);
        }
        return $toReturn;
    }

    public function testNotChunked()
    {
        $plugin = new puzzle_subscriber_Chunked();

        $request = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(200);
        $response->setHeader('Transfer-Encoding', 'something');
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $t->setResponse($response);
        $ev = new puzzle_event_CompleteEvent($t);


        $plugin->onComplete($ev);
    }

    public function testNotTransferEncoded()
    {
        $plugin = new puzzle_subscriber_Chunked();

        $request = new puzzle_message_Request('GET', '/');
        $response = new puzzle_message_Response(200);
        $t = new puzzle_adapter_Transaction(new puzzle_Client(), $request);
        $t->setResponse($response);
        $ev = new puzzle_event_CompleteEvent($t);


        $plugin->onComplete($ev);
    }

    public function testEvents()
    {
        $plugin = new puzzle_subscriber_Chunked();

        $plugin->getEvents();

        $this->assertEquals(array(
            'complete' => array('onComplete', 80)
        ), $plugin->getEvents());
    }

    private function _decodeTestArray() {

        return array(
            <<<EOT
abra
cadabra
EOT
            => "02\r\nab\r\n04\r\nra\nc\r\n06\r\nadabra\r\n0\r\nnothing\n",
            <<<EOT
abra
cadabra
EOT
            => "02\r\nab\r\n04\r\nra\nc\r\n06\r\nadabra\n0\nhidden\n",
            <<<EOT
abra
cadabra
all we got

EOT
            => "02\r\nab\r\n04\r\nra\nc\r\n06\r\nadabra\r\n0c\r\n\nall we got\n",
            <<<EOT
this string is chunked encoded

EOT
            => "05\r\nthis \r\n07\r\nstring \r\n12\r\nis chunked encoded\r\n01\r\n\n\r\n00",
            <<<EOT
this string is chunked encoder

EOT
            => "005   \r\nthis \r\n     07\r\nstring \r\n12     \r\nis chunked encoder\r\n   000001     \r\n\n\r\n00"
        );
    }
}

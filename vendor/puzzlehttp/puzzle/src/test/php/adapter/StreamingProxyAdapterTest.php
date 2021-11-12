<?php

/**
 * @covers puzzle_adapter_StreamingProxyAdapter
 */
class puzzle_test_adapter_StreamingProxyAdapterTest extends PHPUnit_Framework_TestCase
{
    public function testSendsWithDefaultAdapter()
    {
        $response = new puzzle_message_Response(200);
        $mock = $this->getMockBuilder('puzzle_adapter_AdapterInterface')
            ->setMethods(array('send'))
            ->getMockForAbstractClass();
        $mock->expects($this->once())
            ->method('send')
            ->will($this->returnValue($response));
        $streaming = $this->getMockBuilder('puzzle_adapter_AdapterInterface')
            ->setMethods(array('send'))
            ->getMockForAbstractClass();
        $streaming->expects($this->never())
            ->method('send');

        $s = new puzzle_adapter_StreamingProxyAdapter($mock, $streaming);
        $this->assertSame($response, $s->send(new puzzle_adapter_Transaction(new puzzle_Client(), new puzzle_message_Request('GET', '/'))));
    }

    public function testSendsWithStreamingAdapter()
    {
        $response = new puzzle_message_Response(200);
        $mock = $this->getMockBuilder('puzzle_adapter_AdapterInterface')
            ->setMethods(array('send'))
            ->getMockForAbstractClass();
        $mock->expects($this->never())
            ->method('send');
        $streaming = $this->getMockBuilder('puzzle_adapter_AdapterInterface')
            ->setMethods(array('send'))
            ->getMockForAbstractClass();
        $streaming->expects($this->once())
            ->method('send')
            ->will($this->returnValue($response));
        $request = new puzzle_message_Request('GET', '/');
        $request->getConfig()->set('stream', true);
        $s = new puzzle_adapter_StreamingProxyAdapter($mock, $streaming);
        $this->assertSame($response, $s->send(new puzzle_adapter_Transaction(new puzzle_Client(), $request)));
    }
}

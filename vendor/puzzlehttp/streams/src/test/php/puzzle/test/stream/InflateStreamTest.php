<?php

class puzzle_test_stream_InflateStreamtest extends PHPUnit_Framework_TestCase
{
    public function testInflatesStreams()
    {
        $content = gzencode('test');
        $a = puzzle_stream_Stream::factory($content);
        $b = new puzzle_stream_InflateStream($a);
        $this->assertEquals('test', (string) $b);
    }
}

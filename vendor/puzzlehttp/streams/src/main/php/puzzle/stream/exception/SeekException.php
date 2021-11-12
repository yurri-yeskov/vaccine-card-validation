<?php

/**
 * Exception thrown when a seek fails on a stream.
 */
class puzzle_stream_exception_SeekException extends RuntimeException
{
    private $stream;

    public function __construct(puzzle_stream_StreamInterface $stream, $pos = 0, $msg = '')
    {
        $this->stream = $stream;
        $msg = $msg ? $msg : 'Could not seek the stream to position ' . $pos;
        parent::__construct($msg);
    }

    /**
     * @return puzzle_stream_StreamInterface
     */
    public function getStream()
    {
        return $this->stream;
    }
}

<?php

/**
 * Stream decorator that prevents a stream from being seeked
 */
class puzzle_stream_NoSeekStream extends puzzle_stream_AbstractStreamDecorator implements puzzle_stream_StreamInterface, puzzle_stream_MetadataStreamInterface
{
    public function seek($offset, $whence = SEEK_SET)
    {
        return false;
    }

    public function isSeekable()
    {
        return false;
    }
}

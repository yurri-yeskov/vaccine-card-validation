<?php

/**
 * Lazily reads or writes to a file that is opened only after an IO operation
 * take place on the stream.
 */
class puzzle_stream_LazyOpenStream extends puzzle_stream_AbstractStreamDecorator implements puzzle_stream_StreamInterface, puzzle_stream_MetadataStreamInterface
{
    /** @var string File to open */
    private $filename;

    /** @var string $mode */
    private $mode;

    /**
     * @param string $filename File to lazily open
     * @param string $mode     fopen mode to use when opening the stream
     */
    public function __construct($filename, $mode)
    {
        $this->filename = $filename;
        $this->mode = $mode;
    }

    /**
     * Creates the underlying stream lazily when required.
     *
     * @return puzzle_stream_StreamInterface
     */
    protected function createStream()
    {
        return puzzle_stream_Stream::factory(puzzle_stream_Utils::open($this->filename, $this->mode));
    }
}

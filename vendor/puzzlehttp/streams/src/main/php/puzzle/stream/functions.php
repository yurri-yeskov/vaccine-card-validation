<?php
/*
 * This file is deprecated and only included so that backwards compatibility
 * is maintained for downstream packages.
 *
 * Use the functions available in the puzzle_stream_Utils class instead of the the below
 * functions.
 */

if (!defined('GUZZLE_STREAMS_FUNCTIONS')) {

    define('GUZZLE_STREAMS_FUNCTIONS', true);

    /**
     * @deprecated Moved to puzzle_stream_Stream::factory
     */
    function puzzle_stream_create($resource = '', $size = null)
    {
        return puzzle_stream_Stream::factory($resource, $size);
    }

    /**
     * @deprecated Moved to puzzle_stream_Utils::copyToString
     */
    function puzzle_stream_copy_to_string(puzzle_stream_StreamInterface $stream, $maxLen = -1)
    {
        return puzzle_stream_Utils::copyToString($stream, $maxLen);
    }

    /**
     * @deprecated Moved to puzzle_stream_Utils::copyToStream
     */
    function puzzle_stream_copy_to_stream(
        puzzle_stream_StreamInterface $source,
        puzzle_stream_StreamInterface $dest,
        $maxLen = -1
    ) {
        puzzle_stream_Utils::copyToStream($source, $dest, $maxLen);
    }

    /**
     * @deprecated Moved to puzzle_stream_Utils::hash
     */
    function puzzle_stream_hash(
        puzzle_stream_StreamInterface $stream,
        $algo,
        $rawOutput = false
    ) {
        return puzzle_stream_Utils::hash($stream, $algo, $rawOutput);
    }

    /**
     * @deprecated Moced to puzzle_stream_Utils::readline
     */
    function puzzle_stream_read_line(puzzle_stream_StreamInterface $stream, $maxLength = null)
    {
        return puzzle_stream_Utils::readline($stream, $maxLength);
    }

    /**
     * @deprecated Moved to puzzle_stream_Utils::open()
     */
    function puzzle_stream_safe_open($filename, $mode)
    {
        return puzzle_stream_Utils::open($filename, $mode);
    }
}

<?php

/**
 * Describes a stream instance.
 */
interface puzzle_stream_StreamInterface
{
    /**
     * Attempts to seek to the beginning of the stream and reads all data into
     * a string until the end of the stream is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * @return string
     */
    function __toString();

    /**
     * Closes the stream and any underlying resources.
     */
    function close();

    /**
     * Separates any underlying resources from the stream.
     *
     * After the underlying resource has been detached, the stream object is in
     * an unusable state. If you wish to use a Stream object as a PHP stream
     * but keep the Stream object in a consistent state, use
     * {@see puzzle_stream_GuzzleStreamWrapper::getResource}.
     *
     * @return resource|null Returns the underlying PHP stream resource or null
     *                       if the puzzle_stream_Stream object did not utilize an underlying
     *                       stream resource.
     */
    function detach();

    /**
     * Get the size of the stream if known
     *
     * @return int|null Returns the size in bytes if known, or null if unknown
     */
    function getSize();

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int|bool Returns the position of the file pointer or false on error
     */
    function tell();

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    function eof();

    /**
     * Returns whether or not the stream is seekable
     *
     * @return bool
     */
    function isSeekable();

    /**
     * Seek to a position in the stream
     *
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *                    based on the seek offset. Valid values are identical
     *                    to the built-in PHP $whence values for `fseek()`.
     *                    SEEK_SET: Set position equal to offset bytes
     *                    SEEK_CUR: Set position to current location plus offset
     *                    SEEK_END: Set position to end-of-stream plus offset
     *
     * @return bool Returns true on success or false on failure
     * @link   http://www.php.net/manual/en/function.fseek.php
     */
    function seek($offset, $whence = SEEK_SET);

    /**
     * Returns whether or not the stream is writable
     *
     * @return bool
     */
    function isWritable();

    /**
     * Write data to the stream
     *
     * @param string $string The string that is to be written.
     *
     * @return int|bool Returns the number of bytes written to the stream on
     *                  success or false on failure.
     */
    function write($string);

    /**
     * Flush the write buffers of the stream.
     *
     * @return bool Returns true on success and false on failure
     */
    public function flush();

    /**
     * Returns whether or not the stream is readable
     *
     * @return bool
     */
    function isReadable();

    /**
     * Read data from the stream
     *
     * @param int $length Read up to $length bytes from the object and return
     *                    them. Fewer than $length bytes may be returned if
     *                    underlying stream call returns fewer bytes.
     *
     * @return string     Returns the data read from the stream.
     */
    function read($length);

    /**
     * Returns the remaining contents in a string, up to maxlength bytes.
     *
     * @param int $maxLength The maximum bytes to read. Defaults to -1 (read
     *                       all the remaining buffer).
     * @return string
     */
    function getContents($maxLength = -1);
}

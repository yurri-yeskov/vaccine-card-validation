<?php

/**
 * Post file upload interface
 */
interface puzzle_post_PostFileInterface
{
    /**
     * Get the name of the form field
     *
     * @return string
     */
    function getName();

    /**
     * Get the full path to the file
     *
     * @return string
     */
    function getFilename();

    /**
     * Get the content
     *
     * @return puzzle_stream_StreamInterface
     */
    function getContent();

    /**
     * Gets all POST file headers.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is a string.
     *
     * @return array Returns an associative array of the file's headers.
     */
    function getHeaders();
}

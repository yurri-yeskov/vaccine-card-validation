<?php

/**
 * Represents a POST body that is sent as either a multipart/form-data stream
 * or application/x-www-urlencoded stream.
 */
interface puzzle_post_PostBodyInterface extends puzzle_stream_StreamInterface, Countable
{
    /**
     * Apply headers to the request appropriate for the current state of the object
     *
     * @param puzzle_message_RequestInterface $request Request
     */
    function applyRequestHeaders(puzzle_message_RequestInterface $request);

    /**
     * Set a specific field
     *
     * @param string       $name  Name of the field to set
     * @param string|array $value Value to set
     *
     * @return $this
     */
    function setField($name, $value);

    /**
     * Set the aggregation strategy that will be used to turn multi-valued
     * fields into a string.
     *
     * The aggregation function accepts a deeply nested array of query string
     * values and returns a flattened associative array of key value pairs.
     *
     * @param callable $aggregator
     */
    function setAggregator($aggregator);

    /**
     * Set to true to force a multipart upload even if there are no files.
     *
     * @param bool $force Set to true to force multipart uploads or false to
     *     remove this flag.
     *
     * @return self
     */
    function forceMultipartUpload($force);

    /**
     * Replace all existing form fields with an array of fields
     *
     * @param array $fields Associative array of fields to set
     *
     * @return $this
     */
    function replaceFields(array $fields);

    /**
     * Get a specific field by name
     *
     * @param string $name Name of the POST field to retrieve
     *
     * @return string|null
     */
    function getField($name);

    /**
     * Remove a field by name
     *
     * @param string $name Name of the field to remove
     *
     * @return $this
     */
    function removeField($name);

    /**
     * Returns an associative array of names to values or a query string.
     *
     * @param bool $asString Set to true to retrieve the fields as a query
     *     string.
     *
     * @return array|string
     */
    function getFields($asString = false);

    /**
     * Returns true if a field is set
     *
     * @param string $name Name of the field to set
     *
     * @return bool
     */
    function hasField($name);

    /**
     * Get all of the files
     *
     * @return array Returns an array of puzzle_post_PostFileInterface objects
     */
    function getFiles();

    /**
     * Get a POST file by name.
     *
     * @param string $name Name of the POST file to retrieve
     *
     * @return puzzle_post_PostFileInterface|null
     */
    function getFile($name);

    /**
     * Add a file to the POST
     *
     * @param puzzle_post_PostFileInterface $file File to add
     *
     * @return $this
     */
    function addFile(puzzle_post_PostFileInterface $file);

    /**
     * Remove all files from the collection
     *
     * @return $this
     */
    function clearFiles();
}

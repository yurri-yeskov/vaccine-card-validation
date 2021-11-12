<?php

/**
 * Stores HTTP cookies.
 *
 * It extracts cookies from HTTP requests, and returns them in HTTP responses.
 * puzzle_cookie_CookieJarInterface instances automatically expire contained cookies when
 * necessary. Subclasses are also responsible for storing and retrieving
 * cookies from a file, database, etc.
 *
 * @link http://docs.python.org/2/library/cookielib.html Inspiration
 */
interface puzzle_cookie_CookieJarInterface extends Countable, IteratorAggregate
{
    /**
     * Add a Cookie header to a request.
     *
     * If no matching cookies are found in the cookie jar, then no Cookie
     * header is added to the request.
     *
     * @param puzzle_message_RequestInterface $request Request object to update
     */
    function addCookieHeader(puzzle_message_RequestInterface $request);

    /**
     * Extract cookies from an HTTP response and store them in the puzzle_cookie_CookieJar.
     *
     * @param puzzle_message_RequestInterface  $request  Request that was sent
     * @param puzzle_message_ResponseInterface $response Response that was received
     */
    function extractCookies(
        puzzle_message_RequestInterface $request,
        puzzle_message_ResponseInterface $response
    );

    /**
     * Sets a cookie in the cookie jar.
     *
     * @param puzzle_cookie_SetCookie $cookie Cookie to set.
     *
     * @return bool Returns true on success or false on failure
     */
    function setCookie(puzzle_cookie_SetCookie $cookie);

    /**
     * Remove cookies currently held in the cookie jar.
     *
     * Invoking this method without arguments will empty the whole cookie jar.
     * If given a $domain argument only cookies belonging to that domain will
     * be removed. If given a $domain and $path argument, cookies belonging to
     * the specified path within that domain are removed. If given all three
     * arguments, then the cookie with the specified name, path and domain is
     * removed.
     *
     * @param string $domain Clears cookies matching a domain
     * @param string $path   Clears cookies matching a domain and path
     * @param string $name   Clears cookies matching a domain, path, and name
     *
     * @return puzzle_cookie_CookieJarInterface
     */
    function clear($domain = null, $path = null, $name = null);

    /**
     * Discard all sessions cookies.
     *
     * Removes cookies that don't have an expire field or a have a discard
     * field set to true. To be called when the user agent shuts down according
     * to RFC 2965.
     */
    function clearSessionCookies();
}

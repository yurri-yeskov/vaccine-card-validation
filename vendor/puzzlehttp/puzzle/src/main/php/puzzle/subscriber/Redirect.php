<?php

/**
 * Subscriber used to implement HTTP redirects.
 *
 * **Request options**
 *
 * - redirect: Associative array containing the 'max', 'strict', and 'referer'
 *   keys.
 *
 *   - max: Maximum number of redirects allowed per-request
 *   - strict: You can use strict redirects by setting this value to ``true``.
 *     Strict redirects adhere to strict RFC compliant redirection (e.g.,
 *     redirect POST with POST) vs doing what most clients do (e.g., redirect
 *     POST request with a GET request).
 *   - referer: Set to true to automatically add the "Referer" header when a
 *     redirect request is sent.
 */
class puzzle_subscriber_Redirect implements puzzle_event_SubscriberInterface
{
    public function getEvents()
    {
        return array('complete' => array('onComplete', puzzle_event_RequestEvents::REDIRECT_RESPONSE));
    }

    /**
     * Rewind the entity body of the request if needed
     *
     * @param puzzle_message_RequestInterface $redirectRequest
     * @throws puzzle_exception_CouldNotRewindStreamException
     */
    public static function rewindEntityBody(puzzle_message_RequestInterface $redirectRequest)
    {
        // Rewind the entity body of the request if needed
        if ($redirectRequest->getBody()) {
            $body = $redirectRequest->getBody();
            // Only rewind the body if some of it has been read already, and
            // throw an exception if the rewind fails
            if ($body->tell() && !$body->seek(0)) {
                throw new puzzle_exception_CouldNotRewindStreamException(
                    'Unable to rewind the non-seekable request body after redirecting',
                    $redirectRequest
                );
            }
        }
    }

    /**
     * Called when a request receives a redirect response
     *
     * @param puzzle_event_CompleteEvent $event Event emitted
     * @throws puzzle_exception_TooManyRedirectsException
     */
    public function onComplete(puzzle_event_CompleteEvent $event)
    {
        $response = $event->getResponse();

        if (substr($response->getStatusCode(), 0, 1) != '3' ||
            !$response->hasHeader('Location')
        ) {
            return;
        }

        $redirectCount = 0;
        $redirectRequest = $event->getRequest();
        $redirectResponse = $response;
        $max = $redirectRequest->getConfig()->getPath('redirect/max') ? $redirectRequest->getConfig()->getPath('redirect/max') : 5;

        do {
            if (++$redirectCount > $max) {
                throw new puzzle_exception_TooManyRedirectsException(
                    "Will not follow more than {$redirectCount} redirects",
                    $redirectRequest
                );
            }
            $redirectRequest = $this->createRedirectRequest($redirectRequest, $redirectResponse);
            $redirectResponse = $event->getClient()->send($redirectRequest);
        } while (substr($redirectResponse->getStatusCode(), 0, 1) == '3' &&
            $redirectResponse->hasHeader('Location')
        );

        if ($redirectResponse !== $response) {
            $event->intercept($redirectResponse);
        }
    }

    /**
     * Create a redirect request for a specific request object
     *
     * Takes into account strict RFC compliant redirection (e.g. redirect POST
     * with POST) vs doing what most clients do (e.g. redirect POST with GET).
     *
     * @param puzzle_message_RequestInterface  $request
     * @param puzzle_message_ResponseInterface $response
     *
     * @return puzzle_message_RequestInterface Returns a new redirect request
     * @throws puzzle_exception_CouldNotRewindStreamException If the body cannot be rewound.
     */
    private function createRedirectRequest(
        puzzle_message_RequestInterface $request,
        puzzle_message_ResponseInterface $response
    ) {
        $config = $request->getConfig();

        // Use a GET request if this is an entity enclosing request and we are
        // not forcing RFC compliance, but rather emulating what all browsers
        // would do. Be sure to disable redirects on the clone.
        $redirectRequest = clone $request;
        $redirectRequest->getEmitter()->detach($this);
        $statusCode = $response->getStatusCode();

        if ($statusCode == 303 ||
            ($statusCode <= 302 && $request->getBody() &&
                !$config->getPath('redirect/strict'))
        ) {
            $redirectRequest->setMethod('GET');
            $redirectRequest->setBody(null);
        }

        $this->setRedirectUrl($redirectRequest, $response);
        $this->rewindEntityBody($redirectRequest);

        // Add the Referer header if it is told to do so and only
        // add the header if we are not redirecting from https to http.
        if ($config->getPath('redirect/referer') && (
            $redirectRequest->getScheme() == 'https' ||
            $redirectRequest->getScheme() == $request->getScheme()
        )) {
            $url = puzzle_Url::fromString($request->getUrl());
            $url->setUsername(null)->setPassword(null);
            $redirectRequest->setHeader('Referer', (string) $url);
        }

        return $redirectRequest;
    }

    /**
     * Set the appropriate URL on the request based on the location header
     *
     * @param puzzle_message_RequestInterface  $redirectRequest
     * @param puzzle_message_ResponseInterface $response
     */
    private function setRedirectUrl(
        puzzle_message_RequestInterface $redirectRequest,
        puzzle_message_ResponseInterface $response
    ) {
        $location = $response->getHeader('Location');
        $location = puzzle_Url::fromString($location);

        // Combine location with the original URL if it is not absolute.
        if (!$location->isAbsolute()) {
            $originalUrl = puzzle_Url::fromString($redirectRequest->getUrl());
            // Remove query string parameters and just take what is present on
            // the redirect Location header
            $originalUrl->getQuery()->clear();
            $location = $originalUrl->combine($location);
        }

        $redirectRequest->setUrl($location);
    }
}

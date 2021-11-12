<?php

/**
 * Adds, extracts, and persists cookies between HTTP requests
 */
class puzzle_subscriber_Cookie  implements puzzle_event_SubscriberInterface
{
    /** @var puzzle_cookie_CookieJarInterface */
    private $cookieJar;

    /**
     * @param puzzle_cookie_CookieJarInterface $cookieJar Cookie jar used to hold cookies
     */
    public function __construct(puzzle_cookie_CookieJarInterface $cookieJar = null)
    {
        $this->cookieJar = $cookieJar ? $cookieJar : new puzzle_cookie_CookieJar();
    }

    public function getEvents()
    {
        // Fire the cookie plugin complete event before redirecting
        return array(
            'before'   => array('onBefore'),
            'complete' => array('onComplete', puzzle_event_RequestEvents::REDIRECT_RESPONSE + 10)
        );
    }

    /**
     * Get the cookie cookieJar
     *
     * @return puzzle_cookie_CookieJarInterface
     */
    public function getCookieJar()
    {
        return $this->cookieJar;
    }

    public function onBefore(puzzle_event_BeforeEvent $event)
    {
        $this->cookieJar->addCookieHeader($event->getRequest());
    }

    public function onComplete(puzzle_event_CompleteEvent $event)
    {
        $this->cookieJar->extractCookies(
            $event->getRequest(),
            $event->getResponse()
        );
    }
}

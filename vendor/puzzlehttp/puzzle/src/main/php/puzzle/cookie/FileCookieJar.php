<?php

/**
 * Persists non-session cookies using a JSON formatted file
 */
class puzzle_cookie_FileCookieJar extends puzzle_cookie_CookieJar
{
    /** @var string filename */
    private $filename;

    /**
     * Create a new puzzle_cookie_FileCookieJar object
     *
     * @param string $cookieFile File to store the cookie data
     *
     * @throws RuntimeException if the file cannot be found or created
     */
    public function __construct($cookieFile)
    {
        $this->filename = $cookieFile;

        if (file_exists($cookieFile)) {
            $this->load($cookieFile);
        }
    }

    /**
     * Saves the file when shutting down
     */
    public function __destruct()
    {
        $this->save($this->filename);
    }

    /**
     * Saves the cookies to a file.
     *
     * @param string $filename File to save
     * @throws RuntimeException if the file cannot be found or created
     */
    public function save($filename)
    {
        $json = array();
        foreach ($this as $cookie) {
            if ($cookie->getExpires() && !$cookie->getDiscard()) {
                $json[] = $cookie->toArray();
            }
        }

        if (false === file_put_contents($filename, json_encode($json))) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException("Unable to save file {$filename}");
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Load cookies from a JSON formatted file.
     *
     * Old cookies are kept unless overwritten by newly loaded ones.
     *
     * @param string $filename Cookie file to load.
     * @throws RuntimeException if the file cannot be loaded.
     */
    public function load($filename)
    {
        $json = file_get_contents($filename);
        if (false === $json) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException("Unable to load file {$filename}");
            // @codeCoverageIgnoreEnd
        }

        $data = puzzle_json_decode($json, true);
        if (is_array($data)) {
            foreach (puzzle_json_decode($json, true) as $cookie) {
                $this->setCookie(new puzzle_cookie_SetCookie($cookie));
            }
        } elseif (strlen($data)) {
            throw new RuntimeException("Invalid cookie file: {$filename}");
        }
    }
}

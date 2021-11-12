<?php

/**
 * @covers puzzle_cookie_SessionCookieJar
 */
class puzzle_test_cooki_SessionCookieJarTest extends PHPUnit_Framework_TestCase
{
    private $sessionVar;

    public function setUp()
    {
        $this->sessionVar = 'sessionKey';

        if (!isset($_SESSION)) {
            $_SESSION = array();
        }
    }

    /**
     * @expectedException RuntimeException
     */
    public function testValidatesCookieSession()
    {
        $_SESSION[$this->sessionVar] = 'true';
        new puzzle_cookie_SessionCookieJar($this->sessionVar);
    }

    public function testLoadsFromSession()
    {
        $jar = new puzzle_cookie_SessionCookieJar($this->sessionVar);
        $this->assertEquals(array(), $jar->getIterator()->getArrayCopy());
        unset($_SESSION[$this->sessionVar]);
    }

    public function testPersistsToSession()
    {
        $jar = new puzzle_cookie_SessionCookieJar($this->sessionVar);
        $jar->setCookie(new puzzle_cookie_SetCookie(array(
            'Name'    => 'foo',
            'Value'   => 'bar',
            'Domain'  => 'foo.com',
            'Expires' => time() + 1000
        )));
        $jar->setCookie(new puzzle_cookie_SetCookie(array(
            'Name'    => 'baz',
            'Value'   => 'bar',
            'Domain'  => 'foo.com',
            'Expires' => time() + 1000
        )));
        $jar->setCookie(new puzzle_cookie_SetCookie(array(
            'Name'    => 'boo',
            'Value'   => 'bar',
            'Domain'  => 'foo.com',
        )));

        $this->assertEquals(3, count($jar));
        unset($jar);

        // Make sure it wrote to the sessionVar in $_SESSION
        $contents = $_SESSION[$this->sessionVar];
        $this->assertNotEmpty($contents);

        // Load the cookieJar from the file
        $jar = new puzzle_cookie_SessionCookieJar($this->sessionVar);

        // Weeds out temporary and session cookies
        $this->assertEquals(2, count($jar));
        unset($jar);
        unset($_SESSION[$this->sessionVar]);
    }
}

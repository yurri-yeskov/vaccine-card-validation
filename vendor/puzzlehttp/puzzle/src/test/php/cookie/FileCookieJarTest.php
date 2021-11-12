<?php

/**
 * @covers puzzle_cookie_FileCookieJar
 */
class puzzle_test_cookie_FileCookieJarTest extends PHPUnit_Framework_TestCase
{
    private $file;

    public function setUp()
    {
        $this->file = tempnam('/tmp', 'file-cookies');
    }

    /**
     * @expectedException RuntimeException
     */
    public function testValidatesCookieFile()
    {
        file_put_contents($this->file, 'true');
        new puzzle_cookie_FileCookieJar($this->file);
    }

    public function testLoadsFromFileFile()
    {
        $jar = new puzzle_cookie_FileCookieJar($this->file);
        $this->assertEquals(array(), $jar->getIterator()->getArrayCopy());
        unlink($this->file);
    }

    public function testPersistsToFileFile()
    {
        $jar = new puzzle_cookie_FileCookieJar($this->file);
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

        // Make sure it wrote to the file
        $contents = file_get_contents($this->file);
        $this->assertNotEmpty($contents);

        // Load the cookieJar from the file
        $jar = new puzzle_cookie_FileCookieJar($this->file);

        // Weeds out temporary and session cookies
        $this->assertEquals(2, count($jar));
        unset($jar);
        unlink($this->file);
    }
}

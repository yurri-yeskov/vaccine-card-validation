<?php

/**
 * @covers Mimetypes
 */
class puzzle_test_MimetypesTest extends PHPUnit_Framework_TestCase
{
    public function testGetsFromExtension()
    {
        $this->assertEquals('text/x-php', puzzle_Mimetypes::getInstance()->fromExtension('php'));
    }

    public function testGetsFromFilename()
    {
        $this->assertEquals('text/x-php', puzzle_Mimetypes::getInstance()->fromFilename(__FILE__));
    }

    public function testGetsFromCaseInsensitiveFilename()
    {
        $this->assertEquals('text/x-php', puzzle_Mimetypes::getInstance()->fromFilename(strtoupper(__FILE__)));
    }

    public function testReturnsNullWhenNoMatchFound()
    {
        $this->assertNull(puzzle_Mimetypes::getInstance()->fromExtension('foobar'));
    }
}

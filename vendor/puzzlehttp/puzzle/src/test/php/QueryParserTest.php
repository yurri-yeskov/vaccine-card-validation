<?php

class puzzle_test_QueryParserTest extends PHPUnit_Framework_TestCase
{
    public function parseQueryProvider()
    {
        return array(
            // Does not need to parse when the string is empty
            array('', array()),
            // Can parse mult-values items
            array('q=a&q=b', array('q' => array('a', 'b'))),
            // Can parse multi-valued items that use numeric indices
            array('q[0]=a&q[1]=b', array('q' => array('a', 'b'))),
            // Can parse duplicates and does not include numeric indices
            array('q[]=a&q[]=b', array('q' => array('a', 'b'))),
            // Ensures that the value of "q" is an array even though one value
            array('q[]=a', array('q' => array('a'))),
            // Does not modify "." to "_" like PHP's parse_str()
            array('q.a=a&q.b=b', array('q.a' => 'a', 'q.b' => 'b')),
            // Can decode %20 to " "
            array('q%20a=a%20b', array('q a' => 'a b')),
            // Can parse funky strings with no values by assigning each to null
            array('q&a', array('q' => null, 'a' => null)),
            // Does not strip trailing equal signs
            array('data=abc=', array('data' => 'abc=')),
            // Can store duplicates without affecting other values
            array('foo=a&foo=b&?µ=c', array('foo' => array('a', 'b'), '?µ' => 'c')),
            // Sets value to null when no "=" is present
            array('foo', array('foo' => null)),
            // Preserves "0" keys.
            array('0', array('0' => null)),
            // Sets the value to an empty string when "=" is present
            array('0=', array('0' => '')),
            // Preserves falsey keys
            array('var=0', array('var' => '0')),
            // Can deeply nest and store duplicate PHP values
            array('a[b][c]=1&a[b][c]=2', array(
                'a' => array('b' => array('c' => array('1', '2')))
            )),
            // Can parse PHP style arrays
            array('a[b]=c&a[d]=e', array('a' => array('b' => 'c', 'd' => 'e'))),
            // Ensure it doesn't leave things behind with repeated values
            // Can parse mult-values items
            array('q=a&q=b&q=c', array('q' => array('a', 'b', 'c'))),
        );
    }

    /**
     * @dataProvider parseQueryProvider
     */
    public function testParsesQueries($input, $output)
    {
        $query = puzzle_Query::fromString($input);
        $this->assertEquals($output, $query->toArray());
        // Normalize the input and output
        $query->setEncodingType(false);
        $this->assertEquals(rawurldecode($input), (string) $query);
    }

    public function testConvertsPlusSymbolsToSpacesByDefault()
    {
        $query = puzzle_Query::fromString('var=foo+bar', true);
        $this->assertEquals('foo bar', $query->get('var'));
    }

    public function testCanControlDecodingType()
    {
        $qp = new puzzle_QueryParser();
        $q = new puzzle_Query();
        $qp->parseInto($q, 'var=foo+bar', puzzle_Query::RFC3986);
        $this->assertEquals('foo+bar', $q->get('var'));
        $qp->parseInto($q, 'var=foo+bar', puzzle_Query::RFC1738);
        $this->assertEquals('foo bar', $q->get('var'));
    }
}

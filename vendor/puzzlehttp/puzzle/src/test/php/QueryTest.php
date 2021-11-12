<?php

class puzzle_test_QueryTest extends PHPUnit_Framework_TestCase
{
    public function testCanCastToString()
    {
        $q = new puzzle_Query(array('foo' => 'baz', 'bar' => 'bam boozle'));
        $this->assertEquals('foo=baz&bar=bam%20boozle', (string) $q);
    }

    public function testCanDisableUrlEncoding()
    {
        $q = new puzzle_Query(array('bar' => 'bam boozle'));
        $q->setEncodingType(false);
        $this->assertEquals('bar=bam boozle', (string) $q);
    }

    public function testCanSpecifyRfc1783UrlEncodingType()
    {
        $q = new puzzle_Query(array('bar abc' => 'bam boozle'));
        $q->setEncodingType(puzzle_Query::RFC1738);
        $this->assertEquals('bar+abc=bam+boozle', (string) $q);
    }

    public function testCanSpecifyRfc3986UrlEncodingType()
    {
        $q = new puzzle_Query(array('bar abc' => 'bam boozle', 'ሴ' => 'hi'));
        $q->setEncodingType(puzzle_Query::RFC3986);
        $this->assertEquals('bar%20abc=bam%20boozle&%E1%88%B4=hi', (string) $q);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesEncodingType()
    {
        $q = new puzzle_Query(array('bar' => 'bam boozle'));
        $q->setEncodingType('foo');
    }

    public function testAggregatesMultipleValues()
    {
        $q = new puzzle_Query(array('foo' => array('bar', 'baz')));
        $this->assertEquals('foo%5B0%5D=bar&foo%5B1%5D=baz', (string) $q);
    }

    public function testCanSetAggregator()
    {
        $q = new puzzle_Query(array('foo' => array('bar', 'baz')));
        $q->setAggregator(array($this, '__callback_testCanSetAggregator'));
        $this->assertEquals('foo=barANDbaz', (string) $q);
    }

    public function __callback_testCanSetAggregator(array $data)
    {
        return array('foo' => array('barANDbaz'));
    }

    public function testAllowsMultipleValuesPerKey()
    {
        $q = new puzzle_Query();
        $q->add('facet', 'size');
        $q->add('facet', 'width');
        $q->add('facet.field', 'foo');
        // Use the duplicate aggregator
        $q->setAggregator($q->duplicateAggregator());
        $this->assertEquals('facet=size&facet=width&facet.field=foo', (string) $q);
    }

    public function testAllowsZeroValues()
    {
        $query = new puzzle_Query(array(
            'foo' => 0,
            'baz' => '0',
            'bar' => null,
            'boo' => false
        ));
        $this->assertEquals('foo=0&baz=0&bar&boo=', (string) $query);
    }

    private $encodeData = array(
        't' => array(
            'v1' => array('a', '1'),
            'v2' => 'b',
            'v3' => array('v4' => 'c', 'v5' => 'd')
        )
    );

    public function testEncodesDuplicateAggregator()
    {
        $agg = puzzle_Query::duplicateAggregator();
        $result = call_user_func($agg, $this->encodeData);
        $this->assertEquals(array(
            't[v1]' => array('a', '1'),
            't[v2]' => array('b'),
            't[v3][v4]' => array('c'),
            't[v3][v5]' => array('d'),
        ), $result);
    }

    public function testDuplicateEncodesNoNumericIndices()
    {
        $agg = puzzle_Query::duplicateAggregator();
        $result = call_user_func($agg, $this->encodeData);
        $this->assertEquals(array(
            't[v1]' => array('a', '1'),
            't[v2]' => array('b'),
            't[v3][v4]' => array('c'),
            't[v3][v5]' => array('d'),
        ), $result);
    }

    public function testEncodesPhpAggregator()
    {
        $agg = puzzle_Query::phpAggregator();
        $result = call_user_func($agg, $this->encodeData);
        $this->assertEquals(array(
            't[v1][0]' => array('a'),
            't[v1][1]' => array('1'),
            't[v2]' => array('b'),
            't[v3][v4]' => array('c'),
            't[v3][v5]' => array('d'),
        ), $result);
    }

    public function testPhpEncodesNoNumericIndices()
    {
        $agg = puzzle_Query::phpAggregator(false);
        $result = call_user_func($agg, $this->encodeData);
        $this->assertEquals(array(
            't[v1][]' => array('a', '1'),
            't[v2]' => array('b'),
            't[v3][v4]' => array('c'),
            't[v3][v5]' => array('d'),
        ), $result);
    }

    public function testCanDisableUrlEncodingDecoding()
    {
        $q = puzzle_Query::fromString('foo=bar+baz boo%20', false);
        $this->assertEquals('bar+baz boo%20', $q['foo']);
        $this->assertEquals('foo=bar+baz boo%20', (string) $q);
    }

    public function testCanChangeUrlEncodingDecodingToRfc1738()
    {
        $q = puzzle_Query::fromString('foo=bar+baz', puzzle_Query::RFC1738);
        $this->assertEquals('bar baz', $q['foo']);
        $this->assertEquals('foo=bar+baz', (string) $q);
    }

    public function testCanChangeUrlEncodingDecodingToRfc3986()
    {
        $q = puzzle_Query::fromString('foo=bar%20baz', puzzle_Query::RFC3986);
        $this->assertEquals('bar baz', $q['foo']);
        $this->assertEquals('foo=bar%20baz', (string) $q);
    }
}

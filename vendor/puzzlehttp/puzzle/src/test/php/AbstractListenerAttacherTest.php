<?php

class ObjectWithEvents extends puzzle_AbstractListenerAttacher implements puzzle_event_HasEmitterInterface
{
    /** @var puzzle_event_EmitterInterface */
    private $emitter;

    public function getEmitter()
    {
        if (!$this->emitter) {
            $this->emitter = new puzzle_event_Emitter();
        }

        return $this->emitter;
    }

    public $listeners = array();

    public function __construct(array $args = array())
    {
        $this->listeners = $this->prepareListeners($args, array('foo', 'bar'));
        $this->attachListeners($this, $this->listeners);
    }
}

class AbstractListenerAttacherTest extends PHPUnit_Framework_TestCase
{
    private $_closureVar_testRegistersEventsWithOnce_called;

    public function testRegistersEvents()
    {
        $fn = array($this, '__empty');
        $o = new ObjectWithEvents(array(
            'foo' => $fn,
            'bar' => $fn,
        ));

        $this->assertEquals(array(
            array('name' => 'foo', 'fn' => $fn, 'priority' => 0, 'once' => false),
            array('name' => 'bar', 'fn' => $fn, 'priority' => 0, 'once' => false),
        ), $o->listeners);

        $this->assertCount(1, $o->getEmitter()->listeners('foo'));
        $this->assertCount(1, $o->getEmitter()->listeners('bar'));
    }

    public function testRegistersEventsWithPriorities()
    {
        $fn = array($this, '__empty');
        $o = new ObjectWithEvents(array(
            'foo' => array('fn' => $fn, 'priority' => 99, 'once' => true),
            'bar' => array('fn' => $fn, 'priority' => 50),
        ));

        $this->assertEquals(array(
            array('name' => 'foo', 'fn' => $fn, 'priority' => 99, 'once' => true),
            array('name' => 'bar', 'fn' => $fn, 'priority' => 50, 'once' => false),
        ), $o->listeners);
    }

    public function testRegistersMultipleEvents()
    {
        $fn = array($this, '__empty');
        $eventArray = array(array('fn' => $fn), array('fn' => $fn));
        $o = new ObjectWithEvents(array(
            'foo' => $eventArray,
            'bar' => $eventArray,
        ));

        $this->assertEquals(array(
            array('name' => 'foo', 'fn' => $fn, 'priority' => 0, 'once' => false),
            array('name' => 'foo', 'fn' => $fn, 'priority' => 0, 'once' => false),
            array('name' => 'bar', 'fn' => $fn, 'priority' => 0, 'once' => false),
            array('name' => 'bar', 'fn' => $fn, 'priority' => 0, 'once' => false),
        ), $o->listeners);

        $this->assertCount(2, $o->getEmitter()->listeners('foo'));
        $this->assertCount(2, $o->getEmitter()->listeners('bar'));
    }

    public function testRegistersEventsWithOnce()
    {
        $this->_closureVar_testRegistersEventsWithOnce_called = 0;
        $fn = array($this, '__callback_testRegistersEventsWithOnce');
        $o = new ObjectWithEvents(array('foo' => array('fn' => $fn, 'once' => true)));
        $ev = $this->getMock('puzzle_event_EventInterface');
        $o->getEmitter()->emit('foo', $ev);
        $o->getEmitter()->emit('foo', $ev);
        $this->assertEquals(1, $this->_closureVar_testRegistersEventsWithOnce_called);
    }

    public function __callback_testRegistersEventsWithOnce()
    {
        $this->_closureVar_testRegistersEventsWithOnce_called++;
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testValidatesEvents()
    {
        $o = new ObjectWithEvents(array('foo' => 'bar'));
    }
    
    public function __empty()
    {
        
    }
}

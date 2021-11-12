<?php

/**
 * @link https://github.com/symfony/symfony/blob/master/src/Symfony/Component/EventDispatcher/Tests/EventDispatcherTest.php Based on this test.
 */
class puzzle_test_event_EmitterTest extends PHPUnit_Framework_TestCase
{
    private $_closure_testDispatchForClosure_invoked;

    private $_closure_testDispatchByPriority_invoked;

    private $_closure_testCanAddFirstAndLastListeners_b;

    /* Some pseudo events */
    const preFoo = 'pre.foo';
    const postFoo = 'post.foo';
    const preBar = 'pre.bar';
    const postBar = 'post.bar';

    /** @var puzzle_event_Emitter */
    private $emitter;
    private $listener;

    protected function setUp()
    {
        $this->emitter = new puzzle_event_Emitter();
        $this->listener = new puzzle_test_event_TestEventListener();
    }

    protected function tearDown()
    {
        $this->emitter = null;
        $this->listener = null;
    }

    public function testInitialState()
    {
        $this->assertEquals(array(), $this->emitter->listeners());
    }

    public function testAddListener()
    {
        $this->emitter->on('pre.foo', array($this->listener, 'preFoo'));
        $this->emitter->on('post.foo', array($this->listener, 'postFoo'));
        $this->assertCount(1, $this->emitter->listeners(self::preFoo));
        $this->assertCount(1, $this->emitter->listeners(self::postFoo));
        $this->assertCount(2, $this->emitter->listeners());
    }

    public function testGetListenersSortsByPriority()
    {
        $listener1 = new puzzle_test_event_TestEventListener();
        $listener2 = new puzzle_test_event_TestEventListener();
        $listener3 = new puzzle_test_event_TestEventListener();
        $listener1->name = '1';
        $listener2->name = '2';
        $listener3->name = '3';

        $this->emitter->on('pre.foo', array($listener1, 'preFoo'), -10);
        $this->emitter->on('pre.foo', array($listener2, 'preFoo'), 10);
        $this->emitter->on('pre.foo', array($listener3, 'preFoo'));

        $expected = array(
            array($listener2, 'preFoo'),
            array($listener3, 'preFoo'),
            array($listener1, 'preFoo'),
        );

        $this->assertSame($expected, $this->emitter->listeners('pre.foo'));
    }

    public function testGetAllListenersSortsByPriority()
    {
        $listener1 = new puzzle_test_event_TestEventListener();
        $listener2 = new puzzle_test_event_TestEventListener();
        $listener3 = new puzzle_test_event_TestEventListener();
        $listener4 = new puzzle_test_event_TestEventListener();
        $listener5 = new puzzle_test_event_TestEventListener();
        $listener6 = new puzzle_test_event_TestEventListener();

        $this->emitter->on('pre.foo', array($listener1, 'preFoo'), -10);
        $this->emitter->on('pre.foo', array($listener2, 'preFoo'));
        $this->emitter->on('pre.foo', array($listener3, 'preFoo'), 10);
        $this->emitter->on('post.foo', array($listener4, 'preFoo'), -10);
        $this->emitter->on('post.foo', array($listener5, 'preFoo'));
        $this->emitter->on('post.foo', array($listener6, 'preFoo'), 10);

        $expected = array(
            'pre.foo'  => array(array($listener3, 'preFoo'), array($listener2, 'preFoo'), array($listener1, 'preFoo')),
            'post.foo' => array(array($listener6, 'preFoo'), array($listener5, 'preFoo'), array($listener4, 'preFoo')),
        );

        $this->assertSame($expected, $this->emitter->listeners());
    }

    public function testDispatch()
    {
        $this->emitter->on('pre.foo', array($this->listener, 'preFoo'));
        $this->emitter->on('post.foo', array($this->listener, 'postFoo'));
        $this->emitter->emit(self::preFoo, $this->getEvent());
        $this->assertTrue($this->listener->preFooInvoked);
        $this->assertFalse($this->listener->postFooInvoked);
        $this->assertInstanceOf('puzzle_event_EventInterface', $this->emitter->emit(self::preFoo, $this->getEvent()));
        $event = $this->getEvent();
        $return = $this->emitter->emit(self::preFoo, $event);
        $this->assertSame($event, $return);
    }

    public function testDispatchForClosure()
    {
        $this->_closure_testDispatchForClosure_invoked = 0;
        $listener = array($this, '__callback_testDispatchForClosure');
        $this->emitter->on('pre.foo', $listener);
        $this->emitter->on('post.foo', $listener);
        $this->emitter->emit(self::preFoo, $this->getEvent());
        $this->assertEquals(1, $this->_closure_testDispatchForClosure_invoked);
    }

    public function __callback_testDispatchForClosure()
    {
        $this->_closure_testDispatchForClosure_invoked++;
    }

    public function testStopEventPropagation()
    {
        $otherListener = new puzzle_test_event_TestEventListener();

        // postFoo() stops the propagation, so only one listener should
        // be executed
        // Manually set priority to enforce $this->listener to be called first
        $this->emitter->on('post.foo', array($this->listener, 'postFoo'), 10);
        $this->emitter->on('post.foo', array($otherListener, 'preFoo'));
        $this->emitter->emit(self::postFoo, $this->getEvent());
        $this->assertTrue($this->listener->postFooInvoked);
        $this->assertFalse($otherListener->postFooInvoked);
    }

    public function testDispatchByPriority()
    {
        $this->_closure_testDispatchByPriority_invoked = array();
        $listener1 = array($this, '__callback_testDispatchByPriority_1');
        $listener2 = array($this, '__callback_testDispatchByPriority_2');
        $listener3 = array($this, '__callback_testDispatchByPriority_3');
        $this->emitter->on('pre.foo', $listener1, -10);
        $this->emitter->on('pre.foo', $listener2);
        $this->emitter->on('pre.foo', $listener3, 10);
        $this->emitter->emit(self::preFoo, $this->getEvent());
        $this->assertEquals(array('3', '2', '1'), $this->_closure_testDispatchByPriority_invoked);
    }

    public function __callback_testDispatchByPriority_1()
    {
        $this->_closure_testDispatchByPriority_invoked[] = '1';
    }

    public function __callback_testDispatchByPriority_2()
    {
        $this->_closure_testDispatchByPriority_invoked[] = '2';
    }

    public function __callback_testDispatchByPriority_3()
    {
        $this->_closure_testDispatchByPriority_invoked[] = '3';
    }

    public function testRemoveListener()
    {
        $this->emitter->on('pre.bar', array($this->listener, 'preFoo'));
        $this->assertNotEmpty($this->emitter->listeners(self::preBar));
        $this->emitter->removeListener('pre.bar', array($this->listener, 'preFoo'));
        $this->assertEmpty($this->emitter->listeners(self::preBar));
        $this->emitter->removeListener('notExists', array($this->listener, 'preFoo'));
    }

    public function testAddSubscriber()
    {
        $eventSubscriber = new puzzle_test_event_TestEventSubscriber();
        $this->emitter->attach($eventSubscriber);
        $this->assertNotEmpty($this->emitter->listeners(self::preFoo));
        $this->assertNotEmpty($this->emitter->listeners(self::postFoo));
    }

    public function testAddSubscriberWithMultiple()
    {
        $eventSubscriber = new puzzle_test_event_TestEventSubscriberWithMultiple();
        $this->emitter->attach($eventSubscriber);
        $listeners = $this->emitter->listeners('pre.foo');
        $this->assertNotEmpty($this->emitter->listeners(self::preFoo));
        $this->assertCount(2, $listeners);
    }

    public function testAddSubscriberWithPriorities()
    {
        $eventSubscriber = new puzzle_test_event_TestEventSubscriber();
        $this->emitter->attach($eventSubscriber);

        $eventSubscriber = new puzzle_test_event_TestEventSubscriberWithPriorities();
        $this->emitter->attach($eventSubscriber);

        $listeners = $this->emitter->listeners('pre.foo');
        $this->assertNotEmpty($this->emitter->listeners(self::preFoo));
        $this->assertCount(2, $listeners);
        $this->assertInstanceOf('puzzle_test_event_TestEventSubscriberWithPriorities', $listeners[0][0]);
    }

    public function testdetach()
    {
        $eventSubscriber = new puzzle_test_event_TestEventSubscriber();
        $this->emitter->attach($eventSubscriber);
        $this->assertNotEmpty($this->emitter->listeners(self::preFoo));
        $this->assertNotEmpty($this->emitter->listeners(self::postFoo));
        $this->emitter->detach($eventSubscriber);
        $this->assertEmpty($this->emitter->listeners(self::preFoo));
        $this->assertEmpty($this->emitter->listeners(self::postFoo));
    }

    public function testdetachWithPriorities()
    {
        $eventSubscriber = new puzzle_test_event_TestEventSubscriberWithPriorities();
        $this->emitter->attach($eventSubscriber);
        $this->assertNotEmpty($this->emitter->listeners(self::preFoo));
        $this->assertNotEmpty($this->emitter->listeners(self::postFoo));
        $this->emitter->detach($eventSubscriber);
        $this->assertEmpty($this->emitter->listeners(self::preFoo));
        $this->assertEmpty($this->emitter->listeners(self::postFoo));
    }

    public function testEventReceivesEventNameAsArgument()
    {
        $listener = new puzzle_test_event_TestWithDispatcher();
        $this->emitter->on('test', array($listener, 'foo'));
        $this->assertNull($listener->name);
        $this->emitter->emit('test', $this->getEvent());
        $this->assertEquals('test', $listener->name);
    }

    /**
     * @see https://bugs.php.net/bug.php?id=62976
     *
     * This bug affects:
     *  - The PHP 5.3 branch for versions < 5.3.18
     *  - The PHP 5.4 branch for versions < 5.4.8
     *  - The PHP 5.5 branch is not affected
     */
    public function testWorkaroundForPhpBug62976()
    {
        $dispatcher = new puzzle_event_Emitter();
        $dispatcher->on('bug.62976', new puzzle_test_event_CallableClass());
        $dispatcher->removeListener('bug.62976', array($this, '__callback_emptyFunction'));
        $this->assertNotEmpty($dispatcher->listeners('bug.62976'));
    }

    public function __callback_emptyFunction()
    {

    }

    public function testRegistersEventsOnce()
    {
        $this->emitter->once('pre.foo', array($this->listener, 'preFoo'));
        $this->emitter->on('pre.foo', array($this->listener, 'preFoo'));
        $this->assertCount(2, $this->emitter->listeners(self::preFoo));
        $this->emitter->emit(self::preFoo, $this->getEvent());
        $this->assertTrue($this->listener->preFooInvoked);
        $this->assertCount(1, $this->emitter->listeners(self::preFoo));
    }

    public function testReturnsEmptyArrayForNonExistentEvent()
    {
        $this->assertEquals(array(), $this->emitter->listeners('doesnotexist'));
    }

    public function testCanAddFirstAndLastListeners()
    {
        $this->_closure_testCanAddFirstAndLastListeners_b = '';
        $this->emitter->on('foo', array($this, '__callback_testCanAddFirstAndLastListeners_a'), 'first'); // 1
        $this->emitter->on('foo', array($this, '__callback_testCanAddFirstAndLastListeners_b'), 'last');  // 0
        $this->emitter->on('foo', array($this, '__callback_testCanAddFirstAndLastListeners_c'), 'first'); // 2
        $this->emitter->on('foo', array($this, '__callback_testCanAddFirstAndLastListeners_d'), 'first'); // 3
        $this->emitter->on('foo', array($this, '__callback_testCanAddFirstAndLastListeners_e'), 'first'); // 4
        $this->emitter->on('foo', array($this, '__callback_testCanAddFirstAndLastListeners_f'));          // 0
        $this->emitter->emit('foo', $this->getEvent());
        $this->assertEquals('edcabf', $this->_closure_testCanAddFirstAndLastListeners_b);
    }

    public function __callback_testCanAddFirstAndLastListeners_a()
    {
        $this->_closure_testCanAddFirstAndLastListeners_b .= 'a';
    }

    public function __callback_testCanAddFirstAndLastListeners_b()
    {
        $this->_closure_testCanAddFirstAndLastListeners_b .= 'b';
    }

    public function __callback_testCanAddFirstAndLastListeners_c()
    {
        $this->_closure_testCanAddFirstAndLastListeners_b .= 'c';
    }

    public function __callback_testCanAddFirstAndLastListeners_d()
    {
        $this->_closure_testCanAddFirstAndLastListeners_b .= 'd';
    }

    public function __callback_testCanAddFirstAndLastListeners_e()
    {
        $this->_closure_testCanAddFirstAndLastListeners_b .= 'e';
    }

    public function __callback_testCanAddFirstAndLastListeners_f()
    {
        $this->_closure_testCanAddFirstAndLastListeners_b .= 'f';
    }

    /**
     * @return puzzle_event_EventInterface
     */
    private function getEvent()
    {
        return $this->getMockBuilder('puzzle_event_AbstractEvent')
            ->getMockForAbstractClass();
    }
}

class puzzle_test_event_CallableClass
{
    public function __invoke()
    {
    }
}

class puzzle_test_event_TestEventListener
{
    public $preFooInvoked = false;
    public $postFooInvoked = false;

    /* Listener methods */

    public function preFoo(puzzle_event_EventInterface $e)
    {
        $this->preFooInvoked = true;
    }

    public function postFoo(puzzle_event_EventInterface $e)
    {
        $this->postFooInvoked = true;

        $e->stopPropagation();
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Deprecated
     */
    public function testHasDeprecatedAddListener()
    {
        $emitter = new puzzle_event_Emitter();
        $emitter->addListener('foo', array($this, '__callback_emptyFunction'));
    }

    /**
     * @expectedException PHPUnit_Framework_Error_Deprecated
     */
    public function testHasDeprecatedAddSubscriber()
    {
        $emitter = new puzzle_event_Emitter();
        $emitter->addSubscriber('foo', new puzzle_test_event_TestEventSubscriber());
    }
}

class puzzle_test_event_TestWithDispatcher
{
    public $name;

    public function foo(puzzle_event_EventInterface $e, $name)
    {
        $this->name = $name;
    }
}

class puzzle_test_event_TestEventSubscriber extends puzzle_test_event_TestEventListener implements puzzle_event_SubscriberInterface
{
    public function getEvents()
    {
        return array(
            'pre.foo' => array('preFoo'),
            'post.foo' => array('postFoo')
        );
    }
}

class puzzle_test_event_TestEventSubscriberWithPriorities extends puzzle_test_event_TestEventListener implements puzzle_event_SubscriberInterface
{
    public function getEvents()
    {
        return array(
            'pre.foo' => array('preFoo', 10),
            'post.foo' => array('postFoo')
        );
    }
}

class puzzle_test_event_TestEventSubscriberWithMultiple extends puzzle_test_event_TestEventListener implements puzzle_event_SubscriberInterface
{
    public function getEvents()
    {
        return array('pre.foo' => array(array('preFoo', 10), array('preFoo', 20)));
    }
}
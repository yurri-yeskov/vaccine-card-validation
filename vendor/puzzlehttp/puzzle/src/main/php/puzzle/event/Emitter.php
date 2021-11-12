<?php

/**
 * Guzzle event emitter.
 *
 * Some of this class is based on the Symfony EventDispatcher component, which
 * ships with the following license:
 *
 *     This file is part of the Symfony package.
 *
 *     (c) Fabien Potencier <fabien@symfony.com>
 *
 *     For the full copyright and license information, please view the LICENSE
 *     file that was distributed with this source code.
 *
 * @link https://github.com/symfony/symfony/tree/master/src/Symfony/Component/EventDispatcher
 */
class puzzle_event_Emitter implements puzzle_event_EmitterInterface
{
    /** @var array */
    private $listeners = array();

    /** @var array */
    private $sorted = array();

    public function on($eventName, $listener, $priority = 0)
    {
        $this->_on($eventName, $listener, $priority);
    }

    private function _on($eventName, $listener, $priority, $once = false)
    {
        if ($priority === 'first') {
            $priority = isset($this->listeners[$eventName])
                ? max(array_keys($this->listeners[$eventName])) + 1
                : 1;
        } elseif ($priority === 'last') {
            $priority = isset($this->listeners[$eventName])
                ? min(array_keys($this->listeners[$eventName])) - 1
                : -1;
        }

        if ($once) {

            $listener = array('___once___', $listener);
        }

        $this->listeners[$eventName][$priority][] = $listener;
        unset($this->sorted[$eventName]);
    }

    public function once($eventName, $listener, $priority = 0)
    {
        $this->_on($eventName, $listener, $priority, true);
    }

    public function removeListener($eventName, $listener)
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $priority => $listeners) {
            if (false !== ($key = array_search($listener, $listeners, true))) {
                unset(
                    $this->listeners[$eventName][$priority][$key],
                    $this->sorted[$eventName]
                );
            }
        }
    }

    public function listeners($eventName = null)
    {
        // Return all events in a sorted priority order
        if ($eventName === null) {
            foreach (array_keys($this->listeners) as $eventName) {
                if (!isset($this->sorted[$eventName])) {
                    $this->listeners($eventName);
                }
            }
            return $this->sorted;
        }

        // Return the listeners for a specific event, sorted in priority order
        if (!isset($this->sorted[$eventName])) {
            if (!isset($this->listeners[$eventName])) {
                return array();
            } else {
                krsort($this->listeners[$eventName]);
                $this->sorted[$eventName] = call_user_func_array(
                    'array_merge',
                    $this->listeners[$eventName]
                );
            }
        }

        return $this->sorted[$eventName];
    }

    public function emit($eventName, puzzle_event_EventInterface $event)
    {
        if (isset($this->listeners[$eventName])) {
            foreach ($this->listeners($eventName) as $listener) {
                if (is_array($listener) && isset($listener[0]) && $listener[0] === '___once___') {
                    $this->removeListener($eventName, $listener);
                    $listener = $listener[1];
                }
                call_user_func_array($listener, array($event, $eventName));

                if ($event->isPropagationStopped()) {
                    break;
                }
            }
        }

        return $event;
    }

    public function attach(puzzle_event_SubscriberInterface $subscriber)
    {
        foreach ($subscriber->getEvents() as $eventName => $listeners) {
            if (is_array($listeners[0])) {
                foreach ($listeners as $listener) {
                    $this->on(
                        $eventName,
                        array($subscriber, $listener[0]),
                        isset($listener[1]) ? $listener[1] : 0
                    );
                }
            } else {
                $this->on(
                    $eventName,
                    array($subscriber, $listeners[0]),
                    isset($listeners[1]) ? $listeners[1] : 0
                );
            }
        }
    }

    public function detach(puzzle_event_SubscriberInterface $subscriber)
    {
        foreach ($subscriber->getEvents() as $eventName => $listener) {
            $this->removeListener($eventName, array($subscriber, $listener[0]));
        }
    }

    public function __call($name, $arguments)
    {
        return puzzle_deprecation_proxy(
            $this,
            $name,
            $arguments,
            array(
                'addSubscriber'    => 'attach',
                'removeSubscriber' => 'detach',
                'addListener'      => 'on',
                'dispatch'         => 'emit'
            )
        );
    }
}

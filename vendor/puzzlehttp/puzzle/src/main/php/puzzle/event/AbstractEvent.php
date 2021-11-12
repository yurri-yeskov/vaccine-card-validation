<?php

/**
 * Basic event class that can be extended.
 */
abstract class puzzle_event_AbstractEvent implements puzzle_event_EventInterface
{
    private $propagationStopped = false;

    public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }

    public function stopPropagation()
    {
        $this->propagationStopped = true;
    }
}

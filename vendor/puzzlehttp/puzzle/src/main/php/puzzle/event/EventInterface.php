<?php

/**
 * Base event interface used when dispatching events to listeners using an
 * event emitter.
 */
interface puzzle_event_EventInterface
{
    /**
     * Returns whether or not stopPropagation was called on the event.
     *
     * @return bool
     * @see puzzle_event_Event::stopPropagation
     */
    function isPropagationStopped();

    /**
     * Stops the propagation of the event, preventing subsequent listeners
     * registered to the same event from being invoked.
     */
    function stopPropagation();
}

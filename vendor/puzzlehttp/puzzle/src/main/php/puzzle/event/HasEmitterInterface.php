<?php

/**
 * Holds an event emitter
 */
interface puzzle_event_HasEmitterInterface
{
    /**
     * Get the event emitter of the object
     *
     * @return puzzle_event_EmitterInterface
     */
    function getEmitter();
}

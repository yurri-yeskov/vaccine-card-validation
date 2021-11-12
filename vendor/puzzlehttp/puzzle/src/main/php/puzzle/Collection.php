<?php

/**
 * Key value pair collection object
 */
class puzzle_Collection extends puzzle_AbstractHasData implements
    ArrayAccess,
    IteratorAggregate,
    Countable,
    puzzle_ToArrayInterface
{
    /**
     * @param array $data Associative array of data to set
     */
    public function __construct(array $data = array())
    {
        $this->data = $data;
    }

    /**
     * Create a new collection from an array, validate the keys, and add default
     * values where missing
     *
     * @param array $config   Configuration values to apply.
     * @param array $defaults Default parameters
     * @param array $required Required parameter names
     *
     * @return puzzle_Collection
     * @throws InvalidArgumentException if a parameter is missing
     */
    public static function fromConfig(
        array $config = array(),
        array $defaults = array(),
        array $required = array()
    ) {
        $data = $config + $defaults;

        if ($missing = array_diff($required, array_keys($data))) {
            throw new InvalidArgumentException(
                'Config is missing the following keys: ' .
                implode(', ', $missing));
        }

        return new self($data);
    }

    /**
     * Removes all key value pairs
     *
     * @return puzzle_Collection
     */
    public function clear()
    {
        $this->data = array();

        return $this;
    }

    /**
     * Get a specific key value.
     *
     * @param string $key Key to retrieve.
     *
     * @return mixed|null Value of the key or NULL
     */
    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * Set a key value pair
     *
     * @param string $key   Key to set
     * @param mixed  $value Value to set
     *
     * @return puzzle_Collection Returns a reference to the object
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Add a value to a key.  If a key of the same name has already been added,
     * the key value will be converted into an array and the new value will be
     * pushed to the end of the array.
     *
     * @param string $key   Key to add
     * @param mixed  $value Value to add to the key
     *
     * @return puzzle_Collection Returns a reference to the object.
     */
    public function add($key, $value)
    {
        if (!array_key_exists($key, $this->data)) {
            $this->data[$key] = $value;
        } elseif (is_array($this->data[$key])) {
            $this->data[$key][] = $value;
        } else {
            $this->data[$key] = array($this->data[$key], $value);
        }

        return $this;
    }

    /**
     * Remove a specific key value pair
     *
     * @param string $key A key to remove
     *
     * @return puzzle_Collection
     */
    public function remove($key)
    {
        unset($this->data[$key]);

        return $this;
    }

    /**
     * Get all keys in the collection
     *
     * @return array
     */
    public function getKeys()
    {
        return array_keys($this->data);
    }

    /**
     * Returns whether or not the specified key is present.
     *
     * @param string $key The key for which to check the existence.
     *
     * @return bool
     */
    public function hasKey($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Checks if any keys contains a certain value
     *
     * @param string $value Value to search for
     *
     * @return mixed Returns the key if the value was found FALSE if the value
     *     was not found.
     */
    public function hasValue($value)
    {
        return array_search($value, $this->data, true);
    }

    /**
     * Replace the data of the object with the value of an array
     *
     * @param array $data Associative array of data
     *
     * @return puzzle_Collection Returns a reference to the object
     */
    public function replace(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Add and merge in a puzzle_Collection or array of key value pair data.
     *
     * @param puzzle_Collection|array $data Associative array of key value pair data
     *
     * @return puzzle_Collection Returns a reference to the object.
     */
    public function merge($data)
    {
        foreach ($data as $key => $value) {
            $this->add($key, $value);
        }

        return $this;
    }

    /**
     * Over write key value pairs in this collection with all of the data from
     * an array or collection.
     *
     * @param array|Traversable $data Values to override over this config
     *
     * @return puzzle_Collection
     */
    public function overwriteWith($data)
    {
        if (is_array($data)) {
            $this->data = $data + $this->data;
        } elseif ($data instanceof puzzle_Collection) {
            $this->data = $data->toArray() + $this->data;
        } else {
            foreach ($data as $key => $value) {
                $this->data[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Returns a puzzle_Collection containing all the elements of the collection after
     * applying the callback function to each one.
     *
     * The callable should accept three arguments:
     * - (string) $key
     * - (string) $value
     * - (array) $context
     *
     * The callable must return a the altered or unaltered value.
     *
     * @param callable $closure Map function to apply
     * @param array    $context Context to pass to the callable
     *
     * @return puzzle_Collection
     */
    public function map($closure, array $context = array())
    {
        $collection = new self();
        foreach ($this as $key => $value) {
            $collection[$key] = call_user_func_array($closure, array($key, $value, $context));
        }

        return $collection;
    }

    /**
     * Iterates over each key value pair in the collection passing them to the
     * callable. If the callable returns true, the current value from input is
     * returned into the result puzzle_Collection.
     *
     * The callable must accept two arguments:
     * - (string) $key
     * - (string) $value
     *
     * @param callable $closure Evaluation function
     *
     * @return puzzle_Collection
     */
    public function filter($closure)
    {
        $collection = new self();
        foreach ($this->data as $key => $value) {
            if (call_user_func_array($closure, array($key, $value))) {
                $collection[$key] = $value;
            }
        }

        return $collection;
    }
}

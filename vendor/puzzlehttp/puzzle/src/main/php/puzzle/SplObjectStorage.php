<?php

class puzzle_SplObjectStorage extends SplObjectStorage
{
    private $_data = array();

    public function attach($object, $data = null)
    {
        parent::attach($object);
        $this->offsetSet($object, $data);
    }

    public function offsetSet($object, $data = null)
    {
        if (!$this->contains($object)) {

            parent::attach($object);
        }

        if ($data !== null) {

            $this->_data[spl_object_hash($object)] = $data;
        }
    }

    public function offsetGet($object)
    {
        $hash = spl_object_hash($object);

        if (!isset($this->_data[$hash])) {

            return null;
        }

        return $this->_data[$hash];
    }

    public function getInfo()
    {
        return $this->offsetGet($this->current());
    }
}
<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Arrays;

/**
 * Abstract Array Object
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Arrays
 * @since 1.1
 */
abstract class AbstractArrayObject implements \ArrayAccess, \Countable, \Serializable, \IteratorAggregate
{
    /**
     * Serializes the array
     *
     * @return string
     * @api
     */
    abstract public function serialize();

    /**
     * Unserializes a serialized array
     *
     * @param string Serialized array
     * @api
     */
    abstract public function unserialize($serialized);

    /**
     * Provides an iterator for the array
     *
     * @return ArrayIterator
     * @api
     */
    abstract public function getIterator();

    /**
     * Provides the count of the array
     *
     * @return int
     * @api
     */
    abstract public function count();

    /**
     * Sets an array value at the provided offset
     *
     * @param mixed $offset The offset at which to set the value
     * @param mixed $value The value to set
     * @api
     */
    abstract public function offsetSet($offset, $value);

    /**
     * Tests if the provided offset exists in the array
     *
     * @param mixed $offset The offset to test
     * @return bool
     * @api
     */
    abstract public function offsetExists($offset);

    /**
     * Clears the value and removes the specified offset in the array
     *
     * @param mixed $offset The offest to remove
     * @api
     */
    abstract public function offsetUnset($offset);

    /**
     * Retrieves the value at the specified offset
     *
     * @param mixed $offset The offset at which to retrieve the value
     * @return mixed
     * @api
     */
    abstract public function offsetGet($offset);

    /**
     * Magic for setting a value
     *
     * @api
     */
    public function __set($offset, $value)
    {
        $this->offsetSet($offset, $value);
    }

    /**
     * Magic for getting a value
     *
     * @api
     */
    public function __get($offset)
    {
        return $this->offsetGet($offset);
    }

    /**
     * Magic for testing a value
     *
     * @api
     */
    public function __isset($offset)
    {
        return $this->offsetExists($offset);
    }

    /**
     * Magic for unsetting a value
     *
     * @api
     */
    public function __unset($offset)
    {
        $this->offsetUnset($offset);
    }
}

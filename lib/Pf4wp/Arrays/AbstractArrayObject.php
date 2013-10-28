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

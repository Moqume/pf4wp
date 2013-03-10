<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Storage;

/**
 * Class providing a Cached Array Object
 *
 * The cached array may persist across multiple PHP sessions and/or instances,
 * depending on the WordPress cache provider, but is not guaranteed.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Storage
 * @since 1.0.16
 * @api
 */
class CachedArrayObject implements \ArrayAccess, \Countable, \Serializable, \IteratorAggregate
{
    const PERSIST_TEST_KEY = 'pf4wp_persist_test';

    /** Key and Group under which to store the cache
     * @internal
     */
    private $key;
    private $wp_key;
    private $int_key;
    private $group;

    /** The maximum age of a cached entry
     * @internal
     */
    private $max_age = 0;

    /** Simple local storage to work with the cache
     * @internal
     */
    private $storage = array();

    /** The maximum and current age of the local storage
     * @internal
     */
    private $max_storage_age = 0;
    private $storage_time = 0;

    /** Set if the local storage and cache are out of sync
     * @internal
     */
    private $is_dirty = false;

    /** Set if the cache can persist
     * @see isPersistent()
     * @internal
     */
    private $can_persist = false;

    /**
     * Constructor
     *
     * Note that increasing the maximum age of the local storage will increase
     * performance, but will also reduce cache coherence.
     *
     * @param string $key The storage key
     * @param string $group The storage group ('pf4wp' by default)
     * @param int $max_age Maximum age, in seconds, to keep the cache valid
     * @param int $max_storage_age Maximum age, in milliseconds, to keep the local working storage valid (1ms min, 100ms default)
     * @api
     */
    public function __construct($key, $group = 'pf4wp', $max_age = 0, $max_storage_age = 100)
    {
        global $blog_id;

        $this->key   = $key;
        $this->group = $group;

        // Helper to create the wp_key and int_key variables
        $this->generateKeys();

        $this->setMaxAge($max_age);
        $this->setMaxStorageAge($max_storage_age);

        // Generic persistence tests
        if (defined('PF4WP_APC') && PF4WP_APC === true) {
            $this->can_persist = (apc_fetch(self::PERSIST_TEST_KEY) === true);

            if (!$this->can_persist)
                apc_store(self::PERSIST_TEST_KEY, true);
        } else {
            $this->can_persist = (wp_cache_get(self::PERSIST_TEST_KEY) === true);

            if (!$this->can_persist)
                wp_cache_set(self::PERSIST_TEST_KEY, true);
        }

        // Ensure we're working with the correct cache on a MultiSite
        add_action('switch_blog', array($this, '_onSwitchBlog'), 10, 0);
    }

    /**
     * Destructor
     *
     * Ensures a cache is flushed before destruction, regardless of its state
     * @internal
     */
    public function __destruct()
    {
        $this->flushCache(true);
    }

    /**
     * Event called on a blog switch (MultiSite)
     *
     * Note: Scope is public because it needs to be accessible by WordPress' action handler
     *
     * @internal
     */
    public function _onSwitchBlog($new_blog_id, $previous_blog_id)
    {
        // Flush anything set under the previous blog, regardless of its state
        $this->flushCache(true);

        // Update the keys with new blog ID
        $this->generateKeys();

        // Fill storage with cache of new blog (forced)
        $this->fetchCache(true);
    }

    /**
     * Creates the keys under which cached data is stored
     *
     * @internal
     */
    private function generateKeys()
    {
        global $blog_id;

        $this->wp_key  = sprintf("%s_%d", $this->key, $blog_id);
        $this->int_key = sprintf("pf4wp.%s.cao.%s.%d", md5($this->group), $this->key, $blog_id);
    }


    /**
     * Set the maximum age of the cache
     *
     * @param int $max_age Maximum age
     * @api
     */
    public function setMaxAge($max_age)
    {
        $this->max_age = (int)$max_age;
    }

    /**
     * Get the maximum allowed age of the cache, in seconds
     *
     * @return int
     * @api
     */
    public function getMaxAge()
    {
        return $this->max_age;
    }

    /**
     * Set the maximum age of the local working storage
     *
     * @param int $max_age Maximum age in milliseconds (1ms minimum)
     * @api
     */
    public function setMaxStorageAge($max_age)
    {
        if ((int)$max_age <= 0)
            $max_age = 1;

        $this->max_storage_age = (float)($max_age / 1000);
    }

    /**
     * Get the maximum allowed age of the local working storage, in milliseconds
     *
     * @return int
     * @api
     */
    public function getMaxStorageAge()
    {
        return (int)($this->max_storage_age * 1000);
    }

    /**
     * Returns if the cache can persists the array across sessions
     *
     * @return bool
     * @api
     */
    public function isPersistent()
    {
        return $this->can_persist;
    }

    /**
     * Exports CachedArray to an array
     *
     * @return array
     * @api
     */
    public function getArrayCopy()
    {
        $this->fetchCache();

        return $this->storage;
    }

    /**
     * Exchange the array for another one
     *
     * @input array The new array to exchange with the current array.
     * @return Returns the old array
     * @api
     */
    public function exchangeArray($input)
    {
        $this->fetchCache();

        $old           = $this->storage;
        $this->storage = (array)$input;

        $this->setCache();

        return $old;
    }

    /**
     * Fetches the data from the cache
     *
     * Use $storage to manipulate
     *
     * @param bool $force Force fetching of the cache, without age checking
     * @internal
     */
    private function fetchCache($force = false)
    {
        $time = microtime(true);

        // If the local storage is outdated...
        if (($time - $this->storage_time) > $this->max_storage_age || $force === true) {
            // Ensure a dirty cache is flushed
            $this->flushCache();

            if (defined('PF4WP_APC') && PF4WP_APC === true) {
                // Use internal method
                $cache = apc_fetch($this->int_key);

                if (extension_loaded('zlib') && $cache)
                    $cache = @unserialize(gzinflate($cache));
            } else {
                // Use WordPress' method (Memcache, W3 Total Cache, etc.)
                $cache = wp_cache_get($this->wp_key, $this->group);
            }

            $this->storage = (is_array($cache)) ? $cache : array();

            $this->storage_time = $time; // Invalidate
        }
    }

    /**
     * Saves the data to the cache
     *
     * @param bool $force Force setting the cache, without age checking
     * @internal
     */
    private function setCache($force = false)
    {
        $this->is_dirty = true; // Mark cache as dirty

        $time = microtime(true); // Remember the time

        // If it is time to sync the local storage with cache, or forced...
        if (($time - $this->storage_time) > $this->max_storage_age || $force === true) {
            // Write changes to cache
            if (defined('PF4WP_APC') && PF4WP_APC === true) {
                $data = $this->storage;

                // Compress the data, if possible (increases chance of storing large data at cost of a few ms)
                if (extension_loaded('zlib'))
                    $data = gzdeflate(serialize($data), 6);

                $success = apc_store($this->int_key, $data, $this->max_age);
            } else {
                $success = wp_cache_set($this->wp_key, $this->storage, $this->group, $this->max_age);
            }

            if ($success === true) {
                $this->is_dirty = false;

                $this->storage_time = $time; // Invalidate
            } // else write to cache failed
        }
    }

    /**
     * Flushes any unsaved (dirty) data to the cache
     *
     * @param bool $force Force the flushing of the cache, regardless of its state
     * @api
     */
    public function flushCache($force = false)
    {
        if ($this->is_dirty || $force === true)
            $this->setCache(true); // force it
    }

    /**
     * Serializes the array
     *
     * @return string
     * @api
     */
    public function serialize() {
        $this->fetchCache();

        return serialize($this->storage);
    }

    /**
     * Unserializes a serialized array
     *
     * @param string Serialized array
     * @api
     */
    public function unserialize($serialized)
    {
        $this->storage = unserialize($serialized);

        $this->setCache();
    }

    /**
     * Provides an iterator for the array
     *
     * @return ArrayIterator
     * @api
     */
    public function getIterator() {
        $this->fetchCache();

        return new \ArrayIterator($this->storage);
    }

    /**
     * Provides the count of the array
     *
     * @return int
     * @api
     */
    public function count()
    {
        $this->fetchCache();

        return count($this->storage);
    }

    /**
     * Sets an array value at the provided offset
     *
     * @param mixed $offset The offset at which to set the value
     * @param mixed $value The value to set
     * @api
     */
    public function offsetSet($offset, $value)
    {
        $this->fetchCache();

        if (is_null($offset)) {
            $this->storage[] = $value;
        } else {
            $this->storage[$offset] = $value;
        }

        $this->setCache();
    }

    /**
     * Tests if the provided offset exists in the array
     *
     * @param mixed $offset The offset to test
     * @return bool
     * @api
     */
    public function offsetExists($offset)
    {
        $this->fetchCache();

        return isset($this->storage[$offset]);
    }

    /**
     * Clears the value and removes the specified offset in the array
     *
     * @param mixed $offset The offest to remove
     * @api
     */
    public function offsetUnset($offset)
    {
        $this->fetchCache();

        try {
            unset($this->storage[$offset]);
        } catch (\Exception $e) {}

        $this->setCache();
    }

    /**
     * Retrieves the value at the specified offset
     *
     * @param mixed $offset The offset at which to retrieve the value
     * @return mixed
     * @api
     */
    public function offsetGet($offset)
    {
        $this->fetchCache();

        return isset($this->storage[$offset]) ? $this->storage[$offset] : null;
    }

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

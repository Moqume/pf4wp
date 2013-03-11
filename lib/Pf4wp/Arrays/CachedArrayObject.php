<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Arrays;

use Pf4wp\Arrays\AbstractArrayObject;

/**
 * Class providing a Cached Array Object
 *
 * The cached array may persist across multiple PHP sessions and/or instances,
 * depending on the WordPress cache provider, but is not guaranteed.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Arrays
 * @since 1.0.16
 * @api
 */
class CachedArrayObject extends AbstractArrayObject
{
    const PERSIST_TEST_KEY = 'pf4wp.persist_test.cao';

    /** Key and Group under which to store the cache
     * @internal
     */
    protected $key;
    protected $group;
    protected $cache_key;

    /** The maximum age of a cached entry
     * @internal
     */
    protected $max_age = 0;

    /** Simple local storage to work with the cache
     * @internal
     */
    protected $storage = array();

    /** The maximum and current age of the local storage
     * @internal
     */
    protected $max_storage_age = 0;
    protected $storage_time = 0;

    /** Set if the local storage and cache are out of sync
     * @internal
     */
    protected $is_dirty = false;

    /** Set if the cache can persist
     * @see isPersistent()
     * @internal
     */
    protected $can_persist = false;

    /**
     * Constructor
     *
     * Note that increasing the maximum age of the local storage will increase
     * performance, particularly write performance, but will also reduce cache coherence.
     *
     * @param string $key The storage key
     * @param string $group The storage group ('pf4wp' by default)
     * @param int $max_age Maximum age, in seconds, to keep the cache valid
     * @param int $max_storage_age Maximum age, in milliseconds, to keep the local working storage valid (1ms min, 100ms default)
     * @api
     */
    public function __construct($key, $group = 'pf4wp', $max_age = 0, $max_storage_age = 100)
    {
        global $wp_object_cache;
        global $blog_id;

        $this->key   = $key;
        $this->group = $group;

        // Helper to create the cache key
        $this->generateCacheKey();

        $this->setMaxAge($max_age);
        $this->setMaxStorageAge($max_storage_age);

        // Generic persistence tests
        if (defined('PF4WP_APC') && PF4WP_APC === true) {
            $this->can_persist = (apc_fetch(self::PERSIST_TEST_KEY) == self::PERSIST_TEST_KEY);

            if (!$this->can_persist)
                apc_store(self::PERSIST_TEST_KEY, self::PERSIST_TEST_KEY);
        } else {
            $this->can_persist = (wp_cache_get(self::PERSIST_TEST_KEY, 'site-transient') == self::PERSIST_TEST_KEY);

            if (!$this->can_persist)
                wp_cache_set(self::PERSIST_TEST_KEY, self::PERSIST_TEST_KEY, 'site-transient');
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
        $this->generateCacheKey();

        // Fill storage with cache of new blog (forced)
        $this->fetchCache(true);
    }

    /**
     * Creates the keys under which cached data is stored
     *
     * @internal
     */
    protected function generateCacheKey()
    {
        global $blog_id;

        $this->cache_key = sprintf("pf4wp.%s.cao", md5($this->group . $this->key . $blog_id));
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
    protected function fetchCache($force = false)
    {
        // If the local storage is outdated...
        if ($force === true || (microtime(true) - $this->storage_time) > $this->max_storage_age) {
            // Ensure a dirty cache is flushed
            $this->flushCache();

            if (defined('PF4WP_APC') && PF4WP_APC === true) {
                // Use internal method
                $cache = apc_fetch($this->cache_key);

                if (extension_loaded('zlib') && $cache)
                    $cache = @gzinflate($cache);
            } else {
                // Use WordPress' method (Memcache, W3 Total Cache, etc.)
                $cache = wp_cache_get($this->cache_key, 'transient');
            }

            // Unserialize cache, if valid
            if ($cache)
                $cache = @unserialize($cache);

            // Set local storage if the unserialized cache is in fact an array
            $this->storage = (is_array($cache)) ? $cache : array();

            $this->storage_time = microtime(true); // Invalidate
        }
    }

    /**
     * Saves the data to the cache
     *
     * @param bool $force Force setting the cache, without age checking
     * @internal
     */
    protected function setCache($force = false)
    {
        global $wp_object_cache;

        $this->is_dirty = true; // Mark cache as dirty

        // If it is time to sync the local storage with cache, or forced...
        if ($force === true || (microtime(true) - $this->storage_time) > $this->max_storage_age) {
            $data = serialize($this->storage);

            if (defined('PF4WP_APC') && PF4WP_APC === true) {
                // Compress the data, if possible (increases chance of storing large data at cost of a few ms)
                if (extension_loaded('zlib'))
                    $data = gzdeflate($data, 6);

                $success = apc_store($this->cache_key, $data, $this->max_age);
            } else {
                // Note: W3TC Object Cache fails at __destruct, so we need to check the wp_object_cache first
                $success = (isset($wp_object_cache)) ? wp_cache_set($this->cache_key, $data, 'transient', $this->max_age) : false;
            }

            if ($success === true) {
                $this->is_dirty = false;

                $this->storage_time = microtime(true); // Invalidate
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
        /* Because function calling inside loops is very expensive, we perform time checks
         * here. This is the most expensive function (looped reads are generally handled by
         * the ArrayIterator), and it saves about 150ms per 10,000 transactions.
         */
        $time = microtime(true);

        // Check if its time to fetch the cache, instead of calling fetchCache immediately
        if (($time - $this->storage_time) > $this->max_storage_age)
            $this->fetchCache(true);

        // Write changes to local storage (to cache is handled by fetch/setCache functions)
        if ($offset === null) {
            $this->storage[] = $value;
        } else {
            $this->storage[$offset] = $value;
        }

        // Mark cache as dirty
        $this->is_dirty = true;

        // Check if its time to set the cache (storage_time may have changed at this point)
        if (($time - $this->storage_time) > $this->max_storage_age)
            $this->setCache(true);
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
}

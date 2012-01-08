<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Options;

/**
 * Options provides an abstract class for standard option storage facilities.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Options
 * @api
 */
abstract class Options
{
    /** Name under which all options are stored
     * @internal
     */
    protected $name;
    
    /** Array containing default options
     * @internal
     */
    protected $defaults = array();
    
    /** Non-persistent working memory cache
     * @internal
     */
    private $cache = array();
       
    /**
     * Constructor
     *
     * @param string $name Name under which all options are stored
     * @param array $defaults Default options
     * @api
     */
    public function __construct($name, array $defaults = array())
    {
        $this->name = $name;
       
        $this->setDefaults($defaults);
        
        // Ensure we're working with the right options on a multisite
        add_action('switch_blog', array($this, '_invalidateCache'), 10, 0);
    }
        
    /**
     * Sets the defaults
     *
     * @param array $defaults Default options
     * @api
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
        
        $this->_invalidateCache();
    }
    
    /**
     * Invalidate the working memory cache
     *
     * Private function on a public scope, as it is also called on `switch_blog` action.
     *
     * @param string $option Specific option to invalidate
     * @internal
     */
    public function _invalidateCache($option = null)
    {
        if (is_null($option)) {
            $this->cache = array();
        } else {
            unset($this->cache[$option]);
        }
    }
    
    /**
     * Get magic for options
     *
     * This will return the option merged with the default values, or `null`
     * if the option does not exist and there is no default value (other than 
     * `null` itself).
     *
     * @param string $option Option to retrieve
     * @return mixed
     * @api
     */
    public function __get($option)
    {
        // Return cached entry, if present
        if (isset($this->cache[$option]))
            return $this->cache[$option];
            
        $options = $this->get();
        
        $result = null; // Default
        
        if (array_key_exists($option, $options))
            $result = $options[$option];

        if (isset($this->defaults[$option])) {
            if (($default = (array)$this->defaults[$option]) === $this->defaults[$option]) {
                // The default is an array, and so should the result be
                if (empty($result)) {
                    $result = array();
                } else if ((array)$result !== $result) {
                    $result = array($result);
                }
                
                // Ensure nested arrays are the same as the default
                $result = $this->array_replace_nested($default, $result);
            } else if (is_null($result)) {
                $result = $this->defaults[$option];
            }
        }
        
        // Store into cache
        $this->cache[$option] = $result;

        return $result;
    }
    
    /**
     * Set magic for options
     * 
     * Setting the value to `null` will delete the particular option
     *
     * @param string $option Option to set
     * @param mixed $value Value to assign to the option
     * @api
     */
    public function __set($option, $value)
    {
        $options = $this->get();

        if (is_null($value)) {
            unset($options[$option]);
        } else {
            $options[$option] = $value;
        }
        
        $this->_invalidateCache($option);
        
        $this->set($options);
    }
    
    /**
     * Replaces nested arrays based on a default array.
     *
     * @param array $defaults Array containing default array elements
     * @param array $set Array containing elements that need to have at least the same elements as the default array.
     * @internal
     */
    private function array_replace_nested(array $defaults, array $set)
    {
        // Initially match up defaults with set
        $result = array_replace($defaults, $set);
        
        // Iterate over defaults, to see if there are any nested arrays
        foreach ($defaults as $default_key => $default_value) {
            // Note: typecast checking is faster than using is_
            if ((array)$default_value === $default_value) { 
                if ((int)$default_key === $default_key) {
                    // If indexed (multiple entries), ensure each entry has the same default values
                    foreach ($result as $result_key => $result_value)
                        $result[$result_key] = $this->array_replace_nested($default_value, $result_value);
                } else {
                    $result[$default_key] = $this->array_replace_nested($default_value, $result[$default_key]);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Deletes all options
     *
     * @return bool Returns `true` of the options were deleted, `false` otherwise
     * @api
     */
    public function delete()
    {
        // Invalidate cache
        $this->_invalidateCache();
        
        return true;
    }    
    
    /**
     * Obtains options
     *
     * @return array Array containing options
     */
    protected abstract function get();
       
    /**
     * Saves options
     *
     * @param array $options Options to save
     * @retrn bool Returns `true` if the options were updates, `false` otherwise
     */
    protected abstract function set(array $options);
}
<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
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
 */
abstract class Options
{
    protected $name;
    protected $defaults = array();
    
    private $cache = array(); // Non-persistent working memory cache
       
    /**
     * Constructor
     *
     * @param string $name Name under which all options are stored
     * @param array $default Default options
     */
    public function __construct($name, array $defaults = array())
    {
        $this->name = $name;
       
        $this->setDefaults($defaults);
    }
    
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
        
        // Invalidate cache
        $this->cache = array();        
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
            } else if ($result == null) {
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
     */
    public function __set($option, $value)
    {
        $options = $this->get();

        if ($value == null) {
            unset($options[$option]);
        } else {
            $options[$option] = $value;
        }
                
        $this->set($options);
        
        // Invalidate cached entry
        unset($this->cache[$option]);
    }
    
    /**
     * Replaces nested arrays based on a default array.
     *
     * @param array $default Array containing default array elements
     * @param arrat $set Array containing elements that need to have at least the same
     *   elements as the default array.
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
     */
    public function delete()
    {
        // Invalidate cache
        $this->cache = array();
        
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
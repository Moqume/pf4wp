<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Options;

use Pf4wp\WordpressPlugin;

/**
 * Options provides an abstract class for standard option storage facilities.
 *
 * @author Mike Green <myatus@gmail.com>
 */
abstract class Options
{
    protected $name;
    protected $defaults = array();
       
    /**
     * Constructor
     *
     * @param string $name Name under which all options are stored
     * @param array $default Default options
     */
    public function __construct($name, array $defaults = array())
    {
        $this->name = $name;
        $this->defaults = $defaults;
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
        $options = $this->get();
        
        $result = null; // Default
        
        if (array_key_exists($option, $options))
            $result = $options[$option];

        if (isset($this->defaults[$option])) {
            if (($default = (array)$this->defaults[$option]) === $this->defaults[$option]) {
                // The default is an array, and so should the result be
                if (empty($result)) {
                    $result = array();
                } else if ((array)$result !== $result)
                    $result = array($result);
                
                // Ensure nested arrays are the same as the default
                $result = $this->array_replace_nested($default, $result);
            } else if ($result == null) {
                $result = $this->defaults[$option];
            }
        }
        
        return $result;
    }
    
    /**
     * Replaces nested arrays based on a default array.
     *
     * @param array $default Array containing default array elements
     * @param arrat $set Array containing array elements that need to have at least the same
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
                    for ($i = 0; $i < count($result); $i++)
                        $result[$i] = $this->array_replace_nested($default_value, $result[$i]);                        
                } else {
                    $result[$default_key] = $this->array_replace_nested($default_value, $result[$default_key]);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Set magic for options
     * 
     * @param string $option Option to set
     * @param mixed $value Value to assign to the option
     */
    public function __set($option, $value)
    {
        $options = $this->get();

        $options[$option] = $value;
        
        $this->set($options);
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

    /**
     * Deletes all options
     *
     * @return bool Returns `true` of the options were deleted, `false` otherwise
     */
    public abstract function delete();
}
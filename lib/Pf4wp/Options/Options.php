<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
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

    /** Non-persistent filter cache
     * @internal
     * @since 1.0.7
     */
    private $filter_cache = array();

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
        if ($option === null) {
            $this->cache        = array();
            $this->filter_cache = array();
        } else {
            unset($this->cache[$option]);
            unset($this->filter_cache[$option]);
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
            } else if ($result === null) {
                $result = $this->defaults[$option];
            }
        }

        // Strip any slashes from the result value - @since 1.0.10
        if (is_string($result))
            $result = stripslashes($result);

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

        if ($value === null) {
            unset($options[$option]);
        } else {
            $options[$option] = $value;
        }

        $this->_invalidateCache($option);

        $this->set($options);
    }

    /**
     * Isset magic for options
     *
     * @api
     * @since 1.0.10
     */
    public function __isset($option) {
        return ($this->__get($option) !== null);
    }

    /**
     * Unset magic for options
     *
     * @api
     * @since 1.0.10
     */
    public function __unset($option) {
        $this->__set($option, null);
    }

    /**
     * Returns the filtered results of one or more options using apply_filters()
     *
     * @param array|string $options The option(s) to filter
     * @param string $filter_prefix The filter prefix (Optional)
     * @return mixed Returns the filtered option(s)
     * @api
     * @since 1.0.7
     */
    public function filtered($options, $filter_prefix = '')
    {
        $result = array();
        $single = false;

        if (!is_array($options)) {
            $single  = true;
            $options = array($options);
        }

        foreach ($options as $option) {
            if (!isset($this->filter_cache[$option])) {
                // No filtered option stored in cache, so fetch first
                $this->filter_cache[$option] = apply_filters($filter_prefix . $option, $this->__get($option));
            }

            if (!$single) {
                $result[$option] = $this->filter_cache[$option];
            } else {
                $result = $this->filter_cache[$option];
                break;
            }
        }

        return $result;
    }

    /**
     * Sanitizes a value (does not set!)
     *
     * The following sanitize options are accepted:
     *
     *  'regex'         Performs a regular expression match (set $sanitize_value)
     *  'string'        Ensures the value is a string
     *  'int'           Ensures the value is an integer
     *  'bool'          Converts the value to a boolean
     *  'in_array'      Checks if the value is within an array (set $sanitize_value)
     *  'range'         Ensures the value is an integer within a range (set $sanitize_value)
     *  callback        If the value is a callback, the callback will be performed and save the result
     *
     * @param mixed $value The value to sanitize
     * @param mixed $sanitize_option The method for sanitizing
     * @param mixed $sanitize_value Optional sanitize values to pass to the sanitize method
     * @since 1.0.7
     * @api
     */
    public function sanitizeValue($value, $sanitize_option, $sanitize_value = null)
    {
        $result = null;

        switch ($sanitize_option) {
            case 'regex' :
                if (preg_match($sanitize_value, $value))
                    $result = $value;
                break;

            case 'string' :
                $result = (string)$value;
                break;

            case 'int' :
                $result = (int)$value;
                break;

            case 'bool' :
                if (strtolower($value) == 'true') {
                    $result = true;
                } else {
                    $result = !empty($value);
                }
                break;

            case 'in_array' :
                if (!is_array($sanitize_value)) {
                    if (is_string($sanitize_value)) {
                        $sanitize_value = explode(',', $sanitize_value);
                    } else {
                        $sanitize_value = array($sanitize_value);
                    }
                }

                $result = (in_array($value, $sanitize_value)) ? $value : null;
                break;

            case 'range' :
                if (!is_array($sanitize_value)) {
                    if (is_string($sanitize_value)) {
                        $sanitize_value = explode(',', $sanitize_value);
                    } else {
                        $sanitize_value = array($sanitize_value);
                    }
                }

                $count = count($sanitize_value);

                if ($count > 1) {
                    $min     = $sanitize_value[0];
                    $max     = $sanitize_value[1];
                    $default = ($count > 2) ? $sanitize_value[2] : null;
                    $value   = (int)$value;

                    if ($value >= $min && $value <= $max) {
                        $result = $value;
                    } else {
                        $result = $default;
                    }
                }
                break;

            default :
                if (is_callable($sanitize_name)) {
                    $result = $sanitize_name($value);
                }
                break;
        }

        return $result;
    }


    /**
     * Loads the options from an array, applying a sanitize check before setting
     *
     * This can be used to save form-results or similar bulk actions. It automatically
     * filters out options that start with an underscore.
     *
     * @param array $source An array with key/value pairs
     * @param array $sanitize An array containing details about sanitizing the source
     * @param bool $ignore_unsanitized If there's an option without a corresponding sanitize, ignore the value completely
     * @api
     * @since 1.0.7
     */
    public function load($source, $sanitize = array(), $ignore_unsanitized = true)
    {
        if (!is_array($source))
            return false;

        foreach ($source as $option => $value) {
            if (strpos($option, '_') === 0)
                continue;

            if (!isset($sanitize[$option])) {
                // No sanitize option was given
                if (!$ignore_unsanitized) {
                    // We were asked not to ignore unsanitized options, so set it
                    $this->__set($option, $value);
                }
            } else {
                $sanitize_value = null;

                if (is_array($sanitize[$option])) {
                    $sanitize_name  = $sanitize[$option][0];
                    $sanitize_value = $sanitize[$option][1];
                } else {
                    $sanitize_name = $sanitize[$option];
                }

                // Unset the sanitize and source
                unset($source[$option]);
                unset($sanitize[$option]);

                if ($sanitize_name !== 'ignore') {
                    $this->__set($option, $this->sanitizeValue($value, $sanitize_name, $sanitize_value));
                }
            } // isset sanitize option
        } // foreach

        // If there's something left to sanitize, the source didn't have it at all
        if (!empty($sanitize)) {
            foreach ($sanitize as $option => $sanitize_name) {
                $sanitize_value = null;

                if (is_array($sanitize_name)) {
                    $sanitize_name  = $sanitize_name[0];
                    $sanitize_value = $sanitize_name[1];
                }

                if ($sanitize_name !== 'ignore') {
                    $this->__set($option, $this->sanitizeValue(null, $sanitize_name, $sanitize_value));
                }
            }
        }
    }

    /**
     * Fetches multiple options as an array
     *
     * @param array $options Array containing the options to retrieve
     * @return array Array containing key/value pairs
     * @api
     * @since 1.0.7
     */
    public function fetch($options)
    {
        $result = array();

        if (!is_array($options))
            return $result; // Nada!

        foreach ($options as $option) {
            $result[$option] = $this->__get($option);
        }

        return $result;
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
                    foreach ($result as $result_key => $result_value) {
                        if (is_string($result_value))
                            $result_value = stripslashes($result_value); // @since 1.0.10

                        $result[$result_key] = $this->array_replace_nested($default_value, $result_value);
                    }
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

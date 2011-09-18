<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Common;

/**
 * Static class providing some common helper methods
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Common
 */
class Helpers
{
    /**
     * Converts a string into a fairly unique, short slug
     *
     * @param string $string String to convert to a slug
     * @return string Slug
     */
    public static function makeSlug($string)
    {
        return substr(base64_encode(md5($string)), 3, 6);
    }
    
    /**
     * Verifies if the callback is valid
     *
     * @param mixed $callback Callback to check
     * @param string $suffix Adds a suffix to the callback's method or function, checking against that instead (Optional)
     * @return mixed Returns the callback (including suffix, if provided), or `false` if invalid.
     */
    public static function validCallback($callback, $suffix = '')
    {
        if (empty($callback))
            return false;
            
        if (is_array($callback) && isset($callback[0]) && is_object($callback[0])) {
            $method = (empty($suffix)) ? $callback[1] : $callback[1].$suffix;
            
            if (method_exists($callback[0], $method))
                return array($callback[0], $method);
        } else if (is_string($callback)) {
            $function = (empty($suffix)) ? $callback : $callback.$suffix;        
                
            if (function_exists($function))
                return $function;
        }
        
        return false; // Invalid or unknown callback
    }
    
    /**
     * Adds an 'items per page' screen setting
     * 
     * @param string $title Title to display
     * @param int $default Default amount of items per page
     */
    public static function addItemsPerPageScreenSetting($title = '', $default = 20)
    {
        add_screen_option('per_page', 
            array(
                'label'  => (empty($title)) ? __('items per page') : $title,
                'option' => '',
            )
        );
        
    }
}
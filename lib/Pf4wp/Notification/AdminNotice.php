<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Notification;

/**
 * Class providing notifications on the WordPress Dashboard
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Notification
 */
class AdminNotice
{
    private static $notices = array();
    
    /**
     * Hide constructor. Purely static class.
     */
    protected function __construct() {}
    
    /**
     * Adds an notice to the queue
     *
     * @param string $message Message to display to the end user
     * @param bool $is_error Optional parameter to indicate the message is an error message
     * @param bool $raw Optional parameter that ignores $is_error and renders the $message as-is
     */
    public static function add($message, $is_error = false, $raw = false)
    {
        if (!is_admin())
            return;
            
        if ($raw) {
            static::$notices[] = $message;
            return;
        }

        $class = ($is_error) ? 'error' : 'updated';
        
        static::$notices[] = sprintf('<div id="message" class="%s"><p>%s</p></div>', $class, $message);
    }
    
    /**
     * Clears the notice queue
     */
    public static function clear()
    {
        if (!is_admin())
            return;
        
        self::$notices = array();
    }
    
    /**
     * Displays (and clears) the notices in the queue
     *
     * An notice is displayed on the WordPress Dahsboard to indicate the status
     * of a certain action, such as saving the plugin options for example.
     */
    public static function display()
    {
        if (!is_admin())
            return;
        
        if ( !empty(self::$notices) ) {
            foreach (self::$notices as $notice)
                echo $notice;
        }
        
        self::clear();
    }
}

 

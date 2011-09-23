<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Options;

/**
 * WordpressOptions provides a settings/options storage facility within WordPress itself.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Options
 */
class WordpressOptions extends Options
{
    protected $options = array();
    
    /**
     * Constructor
     *
     * @param string $name Name under which all options are stored
     * @param array $defaults Default options
     */
    public function __construct($name, array $defaults = array())
    {
        parent::__construct($name, $defaults);
        
        // Ensure we're working with the right options on a multisite
        add_action('switch_blog', array($this, '_invalidateOptions'), 10, 0);
    }
    
    /**
     * Invalidates the options
     *
     * Private function on a public scope, as it is also called on `switch_blog` action.
     */
    public function _invalidateOptions()
    {
        $this->options = array();
    }    
    
    /**
     * Obtains options
     *
     * @return array Array containing options
     */
    protected function get()
    {
        if (empty($this->options)) {
            $this->options = get_option($this->name);
            
            if ($this->options === false)
                $this->_invalidateOptions();
        }

        return $this->options;
    }
       
    /**
     * Saves options
     *
     * @param array $options Options to save
     * @retrn bool Returns `true` if the options were updates, `false` otherwise
     */
    protected function set(array $options)
    {
        $this->options = $options;

        return update_option($this->name, $this->options);
    }
        
    /**
     * Deletes all options
     *
     * @return bool Returns `true` of the options were deleted, `false` otherwise
     */
    public function delete()
    {
        if (!parent::delete())
            return false;
        
        $result = delete_option($this->name);
        
        if ($result !== false)
            $this->_invalidateOptions();
        
        return $result;
    }
}
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
 */
class WordpressOptions extends Options
{
    protected $options = array();
    
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
                $this->options = array();
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
        parent::delete();
        
        $result = delete_option($this->name);
        
        if ($result !== false)
            $this->options = array();
        
        return $result;
    }
}
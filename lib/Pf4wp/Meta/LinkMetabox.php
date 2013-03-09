<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Meta;

/**
 * The LinkMetabox class adds an easy to use meta box to link edit/add pages
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Meta
 * @api
 */
class LinkMetabox extends Metabox
{
    /*---------- Helpers ----------*/    
    
    /**
     * Registers the Metabox for the specified page(s)
     */
    public function register()
    {
        // Force to link Metabox
        $this->pages = array('link');

        $res = parent::register();

        if ($res) {
            add_action('add_link',  array($this, '_onSave'));
            add_action('edit_link', array($this, '_onSave'));
            add_action('delete_link', array($this, '_onDelete'));
        }
        
        return $res;
    }
    
    
    /*---------- Private events (the scope is public, due to external calling) ----------*/
       
    /**
     * Internal event called when a link is saved
     *
     * @param int $id ID of the post
     * @internal
     */
    public function _onSave($id)
    {
        if (!current_user_can('manage_links'))
            return;

        parent::_onSave($id);
    }
}
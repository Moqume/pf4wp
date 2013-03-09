<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Meta;

use Pf4wp\WordpressPlugin;

/**
 * The abstract Metabox class adds an easy to use meta box to post, page or link edit pages
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Meta
 */
abstract class Metabox
{
    /** Name as registered with WordPress
     * @internal
     */
    protected $name = '';
    
    /** Back-reference to owner object
     * @internal
     */
    protected $owner;
    
    /** Set if the Metabox has been registered with WordPress
     * @internal
     */
    protected $registered = false;    

    /**
     * The title to display on the Dashboard Widget
     * @api
     */
    protected $title = '';
    
    /**
     * Array of page(s) on which to show this Metabox
     *
     * Possible pages: 'post', 'page', 'link', or a custom post type (see http://codex.wordpress.org/Post_Types)
     * @api
     */
    protected $pages = array('post');
    
    /**
     * The part of the page where this Metabox should be shown
     *
     * Possible parts: 'normal', 'advanced' or 'side'
     * @api
     */
    protected $context = 'normal';
    
    /**
     * The priority within the context where the Metabox should show
     *
     * Possible priorities: 'high', 'core', 'default' or 'low'
     * @api
     */
    protected $priority = 'default';
       
    /**
     * Constructor
     *
     * @param WordpressPlugin $owner Owner of this Metabox
     * @param bool $auto_register Set to `true` if the meta-box can be registered immidiately during construction
     * @api
     */
    public function __construct(WordpressPlugin $owner, $auto_register = true)
    {
        if (!is_admin())
            return;
        
        $this->owner = $owner;
        $this->name = strtolower(preg_replace('/\W/', '-', get_class($this)));

        if ($auto_register === true)
            $this->register();
    }
    
    /*---------- Helpers ----------*/    
    
    /**
     * Registers the Metabox for the specified page(s)
     * @api
     */
    public function register()
    {
        if (!is_admin() || $this->registered)
            return false;    
        
        foreach ($this->pages as $page)
            add_meta_box($this->name, $this->title, array($this, '_onRender'), $page, $this->context, $this->priority);

        $this->registered = true;
        
        return true;
    }
    
    
    /*---------- Private events (the scope is public, due to external calling) ----------*/
    
    /**
     * Internal event called when ready to render the Metabox contents. 
     *
     * @param object $data Object containing details of the post
     * @internal
     */
    public function _onRender($data)
    {
        $id = 0;
        
        if (isset($data->link_id)) {
            $id = $data->link_id;
        } else if (isset($data->ID)) {
            $id = $data->ID;
        }
        
        wp_nonce_field($this->name.'-metabox', $this->name.'-nonce');
        
        $this->onRender($id, $data);
    }
    
    /**
     * Internal event called when a post or link is saved.
     *
     * @param int $id The Post ID
     * @internal
     */
    public function _onSave($id)
    {
        $this->onSave($id);
    }
    
    /**
     * Internal event called when a post or link is deleted.
     *
     * @param int $id The post ID
     * @internal
     */
    public function _onDelete($id)
    {
        $this->onDelete($id);
    }
    
    
    /*---------- Public events that are safe to override ----------*/
    
    /**
     * Event called when ready to render the Metabox contents 
     *
     * @param string $id ID of the post or link being edited
     * @param object $data Array object containing $_POST data, if any
     * @api
     */
    public function onRender($id, $data) {}
    
    /**
     * Event called when a post or link is saved
     *
     * Use functions such as get_post_meta(), update_post_meta() to modify post-related 
     * metadata - but please note that this is not compatible with links (and the reason
     * why this is subclassed for posts and links)!
     *
     * @param string $id ID of the post or link being saved
     * @api
     */
    public function onSave($id) {}
    
    /**
     * Event called when a post or link is deleted
     *
     * Use functions such as delete_post_meta() to remove post-related metadata - but 
     * please note that this is not compatible with links (and the reason
     * why this is subclassed for posts and links)!
     *
     * @param string $id ID of the post or link being deleted
     * @api
     */
    public function onDelete($id) {}    
}
<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Meta;

/**
 * The PostMetabox class adds an easy to use meta box to post or page edit pages
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Meta
 */
class PostMetabox extends Metabox
{
    /**
     * Registers the Metabox for the specified page(s)
     */
    public function register()
    {
        // Filter link Metaboxes
        $this->pages = array_diff($this->pages, array('link'));

        $res = parent::register();

        if ($res) {
            add_action('save_post', array($this, '_onSave'));
            add_action('delete_post', array($this, '_onDelete'));
        }
        
        return $res;        
    }
    
    /**
     * A helper function to set a single (unique) meta object for a post
     *
     * @param int $id Post or Link ID
     * @param string $field Field name for the meta object
     * @param mixed $data Data to associate with the meta object
     * @return bool Returns `true` if successful, `false` otherwise
     */
    public function setSinglePostMeta($id, $field, $data)
    {
        $olddata = get_post_meta($id, $field, true);
        
        if (empty($olddata)) {
            if (!empty($data)) {
                return add_post_meta($id, $field, $data, true);
            }
        } else {
            if (empty($data)) {
                return delete_post_meta($id, $field);
            } else {
                return update_post_meta($id, $field, $data, $olddata);
            }
        }
        
        return false;
    }
    
    /*---------- Private events (the scope is public, due to external calling) ----------*/
    
    /**
     * Internal event called when a post is saved
     */
    public function _onSave($id)
    {
        // Skip if autosaving
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;
            
        // We first ensure it's actually something we know how to work with
        if (!array_key_exists('post_type', $_POST) || empty($_POST['post_type']))
            return;

        // Set the type
        $type = strtolower($_POST['post_type']);

        // Verify our own nonce, return if failed
        if (!isset($_POST[$this->name.'-nonce']) || !wp_verify_nonce($_POST[$this->name.'-nonce'], $this->name.'-metabox'))
            return;

        // Verify permissions
        
        /* If it's a 'post' or 'page', we can verify it. Otherwise, it's 
           a custom type and needs manual verification in onSavePost() */
        if (in_array($type, array('page', 'post'))) {
            if (!current_user_can('edit_'.$type)) // Meta check
                return;
        }
        
        /* Enforced by this class: ensure we don't try to work with revisions */
        if ($post_id = wp_is_post_revision($id))
            $id = $post_id;            

        parent::_onSave($id);
    }

}
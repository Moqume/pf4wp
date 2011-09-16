<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Menu;

/**
 * MenuEntry provides all the details for adding and keeping track of a menu entry on 
 * the WordPress Dashboard, and renders the initial portions of a page.
 *
 * @author Mike Green <myatus@gmail.com>
 */
class MenuEntry
{
    /* Menu Types */
    const MT_CUSTOM     = -1;
    const MT_SUBMENU    = 0;
    const MT_COMMENTS   = 1;
    const MT_DASHBOARD  = 2;
    const MT_LINKS      = 3;
    const MT_TOOLS      = 4;
    const MT_MEDIA      = 5;
    const MT_SETTINGS   = 6;
    const MT_PAGES      = 7;
    const MT_PLUGINS    = 8;
    const MT_POSTS      = 9;
    const MT_THEMES     = 10;
    const MT_USERS      = 11;    
    
    private $hook = false;
    private $displayed = false;
    private $menu_properties = array(
        'parent_slug'     => '',
        'capability'      => '',
        'slug'            => '',
        'page_title'      => '',
        'title'           => '',
        'count'           => false,
        'large_icon'      => '',
        'icon'            => '',
        'before_callback' => '',
        'after_callback'  => '',
        'callback'        => '',
        'callback_args'   => false,
        'context_help'    => '',
        'type'            => self::MT_SETTINGS, // default
    );
    
    /**
     * Set to true, MT_SUBMENU will be not be rendered automatically
     */
    public $use_subheaders = false;
    
    
    /**
     * Checks if the current menu item is active (selected)
     *
     * @return bool
     */
    public function isActive()
    {
        $screen = get_current_screen(); // Introduced in WP 3.1.0
        
        if (!empty($screen))
            return ($screen->id == $this->hook);
            
        return false;
    }
    
    /**
     * Returns the hook for this menu as provided by WordPress, false if invalid.
     *
     * @return string|bool
     */
    public function getHook()
    {
        return $this->hook;
    }
    
    /**
     * Checks if the current menu item has been displayed
     *
     * @return bool
     */
    public function isDisplayed()
    {
        return $this->displayed;
    }
    
    /**
     * Get magic for menu item properties
     *
     * @param string $name Name of the property to retrieve
     * @return mixed
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->menu_properties))
            return $this->menu_properties[$name];
        
        return null;
    }
    
    /**
     * Set magic for menu item properties
     * 
     * @param string $name Name of the property to set
     * @param mixed $value Value to assign to the property
     */
    public function __set($name, $value)
    {
        if ( !$this->displayed ) {
            if (array_key_exists($name, $this->menu_properties))
                $this->menu_properties[$name] = $value;
        } else {
            throw new \Exception('Menu entry has already been added');
        }
    }
        
    /**
     * Converts a string into a fairly unique, short slug for the menu
     *
     * @param string $string String to convert to a slug
     * @return string Slug
     */
    public static function makeSlug($string)
    {
        return substr(base64_encode(md5($string)), 3, 6);
    }
    
    /**
     * Displays the menu entry on the WordPress Dashboard
     *
     * @return bool Returns true if successful, false otherwise.
     */
    public function display()
    {
        if ( empty($this->menu_properties['title']) || 
             empty($this->menu_properties['callback'] )) {
            throw new \Exception('No title or callback function specified for menu entry');
        }
               
        $title      = $this->menu_properties['title'];
        $page_title = $this->menu_properties['page_title'];
        $icon       = $this->menu_properties['icon'];
        $capability = $this->menu_properties['capability'];
        $parent     = $this->menu_properties['parent_slug'];
        $slug       = $this->menu_properties['slug'];
        
        if ( empty($slug) ) {
            $slug = self::makeSlug($title);
        }
        
        if ( empty($page_title) ) {
            $page_title = $title;
        }
        
        // Add count to the title here (prior operations use a "clean" title)
        if ($this->menu_properties['count'] !== false) {
            $title .= ' <span class="awaiting-mod"><span class="pending-count">' . $this->menu_properties['count'] . '</span>';
        }
        
        // We call our own callback first
        $callback = array($this, 'onMenuCallback');
        
        switch ($this->menu_properties['type'])
        {
            case self::MT_CUSTOM:
                if ( empty($capability) ) $capability = 'read';
                $this->hook = add_menu_page($page_title, $title, $capability, $slug, $callback, $icon);
                break;
            
            case self::MT_SUBMENU:
                if ( empty($capability) ) $capability = 'read';
                if ( !$this->use_subheaders ) {
                    $this->hook = add_submenu_page($parent, $page_title, $title, $capability, $slug, $callback);
                } else {
                    $this->hook = '';
                }
                break;

            case self::MT_COMMENTS:
                if ( empty($capability) ) $capability = 'moderate_comments';
                $this->hook = add_comments_page($page_title, $title, $capability, $slug, $callback);
                break;

            case self::MT_DASHBOARD:
                if ( empty($capability) ) $capability = 'read';
                $this->hook = add_dashboard_page($page_title, $title, $capability, $slug, $callback);
                break;
                
            case self::MT_LINKS:
                if ( empty($capability) ) $capability = 'manage_links';
                $this->hook = add_links_page($page_title, $title, $capability, $slug, $callback);
                break;
                
            case self::MT_TOOLS:
                if ( empty($capability) ) $capability = 'import';
                $this->hook = add_management_page($page_title, $title, $capability, $slug, $callback);
                break;

            case self::MT_MEDIA:
                if ( empty($capability) ) $capability = 'upload_files';
                $this->hook = add_media_page($page_title, $title, $capability, $slug, $callback);
                break;

            case self::MT_SETTINGS:
                if ( empty($capability) ) $capability = 'manage_options';
                $this->hook = add_options_page($page_title, $title, $capability, $slug, $callback);
                break;
                
            case self::MT_PAGES:
                if ( empty($capability) ) $capability = 'edit_pages';
                $this->hook = add_pages_page($page_title, $title, $capability, $slug, $callback);
                break;
                
            case self::MT_PLUGINS:
                if ( empty($capability) ) $capability = 'update_plugins';
                $this->hook = add_plugins_page($page_title, $title, $capability, $slug, $callback);
                break;
                
            case self::MT_POSTS:
                if ( empty($capability) ) $capability = 'edit_posts';
                $this->hook = add_posts_page($page_title, $title, $capability, $slug, $callback);
                break;

            case self::MT_THEMES:
                if ( empty($capability) ) $capability = 'edit_theme_options';
                $this->hook = add_theme_page($page_title, $title, $capability, $slug, $callback);
                break;

            case self::MT_USERS:
                if ( empty($capability) ) $capability = 'edit_users';
                $this->hook = add_users_page($page_title, $title, $capability, $slug, $callback);
                break;
           
            default:
                $this->hook = false;
                break;
        }
        
        $this->displayed = ($this->hook !== false);
        
        if ( $this->displayed ) {
            // Write back any changes for future reference
            $this->menu_properties['slug'] = $slug;
            $this->menu_properties['capability'] = $capability;
            $this->menu_properties['page_title'] = $page_title;
        }

        return $this->displayed;
    }


    /* Events */
    
    /**
     * Event called before the user-defined menu callback is triggered.
     *
     * It also renders portions of the page, which includes a large icon, page title
     * and depending on the before_callback a header (ie., sub menu) and on after_callback
     * a footer.
     *
     * The actual callback depends on what is returned by the menu's "before_callback". If
     * that is NULL, it will use the callback defined for this MenuEntry's "callback", 
     * otherwise the result from "before_callback" is used.
     *
     * The callback is also passed an array containing various details about the menu
     * properties, its hook and any custom defined arguments.
     */
    public function onMenuCallback()
    {
        $callback = $this->menu_properties['callback'];
        
        // Pass these details back to the callback, in case the developer wants to use them:
        $callback_args = array_intersect_key(
            $this->menu_properties, 
            array(
                'parent_slug' => '', 'capability' => '', 'slug' => '',
                'page_title' => '', 'title' => '', 'count' => false,
                'callback_args' => false,
            )
        );
        
        echo '<div class="wrap">';
        
        // Render large icon
        echo '<div class="icon32" '; // <...
        if ( empty($this->menu_properties['large_icon']) ) {
            echo 'id="icon-options-general">';
        } else {
            if ( strpos($this->menu_properties['large_icon'], '/') === false ) {
                // Use an icon by CSS ID
                echo 'id="' . $this->menu_properties['large_icon'] . '">'; // ...>
            } else {
                // Property contains a URL
                echo 'style="background: url(' . $this->menu_properties['large_icon'] . ') no-repeat scroll center center transparent">'; // ...>
            }
        }
        echo '<br /></div>';

        // Render title
        echo '<h2>'.$this->menu_properties['page_title'].'</h2>';
        
        // Perform 'before_callback' event
        if ( !empty($this->menu_properties['before_callback']) ) {
            $result = call_user_func($this->menu_properties['before_callback']);
            
            // If the result from 'before_callback' is not NULL, use the result as the actual callback (override).
            if ( !empty($result) )
                $callback = $result;
        }

        // Perform final callback
        echo '<div class="clear"></div><div>';
        call_user_func($callback, $callback_args);       
        echo '</div>';
        
        // Perform 'afer_callback' event
        if ( !empty($this->menu_properties['after_callback']) )
            call_user_func($this->menu_properties['after_callback']);        
        
        echo '</div>'; // div wrap
    }    
}
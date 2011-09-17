<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Menu;

/**
 * StandardMenu provides a single stand-alone menu entry with submenus.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Menu
 */
class StandardMenu
{
    private $active_menu;
    private $displayed = false;
    private $id = 'myatu';
    
    protected $menus = array(); // Read only public
    protected $parent = ''; // Parent menu
    protected $capability = ''; // Default, determined by the type of menu. @see MenuEntry
    
    /**
     * The default type for the menu entries
     */
    protected $type = MenuEntry::MT_CUSTOM;
    
    /**
     * The supported menu entry types for this class
     */
    protected $supported_types = array(MenuEntry::MT_CUSTOM);
    
    /**
     * Constructor
     */
    public function __construct($id = '')
    {
        if (!empty($id))
            $this->id = $id;
    }
    
        
    /**
     * Checks if the menu has already been displayed
     *
     * Note: This returns as soon as *any* of the menus have been displayed.
     *
     * @return bool `True` if the menu has been displayed, `false` otherwise
     */
    public function isDisplayed()
    {
        if ($this->displayed == false) {
            foreach ($this->menus as $menu) {
                if ( $menu instanceof MenuEntry && $menu->isDisplayed() )
                    return ($this->displayed = true);
            }
        }
        
        return $this->displayed;
    }

    /**
     * Returns all menus from internal storage
     *
     * @return array An array of MenuEntry entries
     */
    public function getMenus()
    {
        return $this->menus;
    }
    
    /**
     * Returns the active menu entry
     *
     * @return MenuEntry|bool active menu item, `false` if invalid
     */
    public function getActiveMenu()
    {
        if (!isset($this->active_menu)) {
            $this->active_menu = false;
            
            foreach ($this->menus as $menu) {
                if ($menu->isActive()) {
                    $this->active_menu = $menu;
                    break;
                }
            }
        }
        
        return $this->active_menu;
    }
    
    /**
     * Returns the slug of the parent menu entry
     * 
     * @return string
     */
    public function getParentSlug()
    {
        return $this->parent;
    }
    
    /**
     * Returns the hook of the parent menu entry
     *
     * @return string|bool WordPress provided hook, `false` if invalid.
     */
    public function getParentHook()
    {
        if (!empty($this->parent))
            return $this->menus[$this->parent]->getHook();
        
        return false;
    }
    
    /**
     * Returns the URL of the parent menu entry
     *
     * @return string
     */
    public function getParentUrl()
    {
        return menu_page_url($this->parent, false);
    }
    
    /**
     * Changes the type of the menu entries, ie, MT_PLUGINS
     *
     * This is a "menu-wide" setting, applying to all entries.
     *
     * @see \Myatu\WordPress\Plugin\Menu\MenuEntry
     * @see getType()
     * @param int $new_type The new type to set the menu entries to
     * @throws \Exception if the menu does not support the type requested, or has already been displayed
     */
    public function setType($new_type)
    {
        if ( !in_array($new_type, $this->supported_types) )
            throw new \Exception('This menu does not support the type requested.');
        
        if ( ! $this->isDisplayed() ) {
            $this->type = $new_type;
            
            foreach ($this->menus as $menu) {
                if ( $menu instanceof MenuEntry && $menu->type != MenuEntry::MT_SUBMENU )
                    $menu->type = $this->type;
            }               
        } else {
            throw new \Exception('The menu has already been displayed. Cannot change type');
        }
    }
    
    /**
     * Obtains the meny type being used
     *
     * @see \Myatu\WordPress\Plugin\Menu\MenuEntry
     * @see setType()
     * @return int Menu type
     */
    public function getType()
    {
        return $this->type;
    }
    
    /**
     * Changes the capabilities required to access the menu (security).
     *
     * This is a "menu-wide" setting, applying to all entries. The
     * capabilities are simple strings defined by WordPress. Please see
     * http://codex.wordpress.org/Roles_and_Capabilities for details.
     *
     * @see getCapability()
     * @link http://codex.wordpress.org/Roles_and_Capabilities
     * @param string $new_capability Capability as defined by WordPress
     * @throws \Exception if the menu has already been displayed.
     */
    public function setCapability($new_capability)
    {
        if ( ! $this->isDisplayed() ) {
            $this->capability = $new_capability;
            
            foreach ($this->menus as $menu) {
                if ( $menu instanceof MenuEntry )
                    $menu->capability = $this->capability;
            }
        } else {
            throw new \Exception('The menu has already been displayed. Cannot change capabilities');
        }
    }
    
    /**
     * Retrieves the capabilities required to display this menu.
     *
     * @see setCapability()
     * @return string Capability as defined by WordPress
     */
    public function getCapability()
    {
        return $this->capability;
    }
        
    /**
     * Adds a new menu item
     *
     * Note that adding a menu item does not display it yet, allowing
     * for possible customization.
     *
     * @see display()
     * @see addSubMenu()
     * @param string $title The title to give the menu entry
     * @param string|array $callback The function to call when the menu's page nees to be rendered
     * @param array|bool $callback_args Optional additional arguments to pass to the callback (Optional, none by default)
     * @param bool|int $count A count that is displayed next to the menu entry, or false for none (Optional, none by default)
     * @param string $context_help String continaing context help (Optional, none by default)
     * @param string $page_title The page title to display on a rendered page (Optional, menu entry title by default)
     * @param string $icon A small icon displayed next to the menu entry, if supported (Optional, none by default)
     * @param string $large_icon The CSS ID or URL of a large icon to display on the rendered page (Optional, CSS ID 'icon-general-option' by default)
     * @param bool $is_submenu Set to `true` if this is a submenu entry (`False` by default)
     * @throws \Exception if the specified menu is a submenu, without having added a main menu.
     */
    public function addMenu($title, $callback, $callback_args = false, $count = false, $context_help = '', $page_title = '', $icon = '', $large_icon = '', $is_submenu = false)
    {
        $menu = new MenuEntry();
        
        $menu->title         = $title;
        $menu->page_title    = $page_title;
        $menu->count         = $count;
        $menu->callback      = $callback;
        $menu->callback_args = $callback_args;
        $menu->context_help  = $context_help;
        $menu->icon          = $icon;
        $menu->large_icon    = $large_icon;
        $menu->capability    = $this->capability;
        $menu->slug          = MenuEntry::makeSlug($this->id.$menu->title);
        
        // Ensure the slug is unique in our menu (up to 99).
        if (array_key_exists($menu->slug, $this->menus)) {
            $count = 1;
            while (array_key_exists($menu->slug.$count, $this->menus) && $count < 100)
                $count++;
           
            $menu->slug .= $count;
        }
        
        if ( !$is_submenu ) {
            $this->parent = $menu->slug;
            $menu->type = $this->type;
        } else {
            if (!empty($this->parent)) {
                $menu->parent_slug = $this->parent;
                $menu->type = MenuEntry::MT_SUBMENU; // Submenus are always of this type
            } else {
                throw new \Exception('Cannot add a submenu before adding a main menu entry.');
            }
        }
        
        $this->menus[$menu->slug] = &$menu;
        
        return $menu;
    }
    
    /**
     * Adds a submenu item
     *
     * @see addMenu()
     * @see display()
     * @param string $title The title of the submenu entry
     * @param string|array $callback Callback function to call when the selected menu page needs to be rendered
     * @param array|bool $callback_args Optional additional arguments to pass to the callback (Optiona, none by default)
     * @param bool|int $count A count that is displayed next to the menu entry, or false for none (Optional, none by default)
     * @param string $context_help String continaing context help (Optional, none by default)
     * @return MenuEntry Reference to the menu entry
     */
    public function addSubMenu($title, $callback, $callback_args = false, $count = false, $context_help = '')
    {
        return $this->addMenu($title, $callback, $callback_args, $count, $context_help, '', '', '', true);
    }
    
    /**
     * Renders (displays) all the menu entries in the WordPress Dashboard
     *
     * @return bool Returns `true` if displaying the menu was successful, `false` otherwise
     */
    public function display()
    {
        foreach ($this->menus as $menu) {
            if ($menu instanceof MenuEntry && !$menu->isDisplayed() && $menu->display() === false)
                return false;
        }
               
        return true;
    }
}

 
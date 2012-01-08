<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Menu;

/**
 * SubHeadMenu provides a single menu entry, with submenus as page subheaders.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Menu
 */
class SubHeadMenu extends CombinedMenu
{
    /** The type of menu entry type
     * @internal
     */
    protected $type = MenuEntry::MT_SETTINGS;
    
    /** Valid menu entry types for this menu
     * @internal
     */
    protected $supported_types = array(
        MenuEntry::MT_COMMENTS, MenuEntry::MT_DASHBOARD, MenuEntry::MT_LINKS, 
        MenuEntry::MT_TOOLS, MenuEntry::MT_MEDIA, MenuEntry::MT_SETTINGS, 
        MenuEntry::MT_PAGES, MenuEntry::MT_PLUGINS, MenuEntry::MT_POSTS, 
        MenuEntry::MT_THEMES, MenuEntry::MT_USERS,
    );
        
    /**
     * Adds a new menu item
     *
     * Note that adding a menu item does not display it yet, allowing
     * for possible customization.
     *
     * @see display()
     * @see add_submenu()
     * @param string $title The title to give the menu entry
     * @param string|array $callback The function to call when the menu's page nees to be rendered
     * @param array|bool $callback_args Optional additional arguments to pass to the callback (Optional, none by default)
     * @param bool $is_submenu Set to true if this is a submenu entry (False by default)
     * @return MenuEntry Reference to the menu entry
     * @throws \Exception if the specified menu is a submenu, without having added a main menu.
     * @api
     */    
    public function addMenu($title, $callback, $callback_args = false, $is_submenu = false)
    {
        $menu = parent::addMenu($title, $callback, $callback_args, $is_submenu);

        $menu->_properties['use_subheaders'] = true;
        
        return $menu;
    }
    
    /**
     * Determines whether a Subheader should be displayed
     *
     * @param MenuEntry $menu Menu entry to test
     * @param string $title Reference to title that should be displayed if this function returns true
     * @return bool Returns `true` if the menu should be displayed, `false` otherwise
     */
    public function doRenderSubHeader(MenuEntry $menu, &$title)
    {
        $active_parent_slug = $this->getActiveParentSlug();
        
        $title = ($menu->_properties['type'] == MenuEntry::MT_SUBMENU) ? $menu->title : $this->home_title;
            
        return true;
    }    
}

 

<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Menu;

use Pf4wp\Menu\MenuEntry;
use Pf4wp\Menu\CombinedMenu;

/**
 * SubHeadMenu provides a single menu entry, with submenus as page subheaders.
 *
 * @author Mike Green <myatus@gmail.com>
 */
class SubHeadMenu extends CombinedMenu
{
    protected $type = MenuEntry::MT_SETTINGS;
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
     * @param bool|int $count A count that is displayed next to the menu entry, or false for none (Optional, none by default)
     * @param string $context_help String continaing context help (Optional, none by default)
     * @param string $page_title The page title to display on a rendered page (Optional, menu entry title by default)
     * @param string $icon A small icon displayed next to the menu entry, if supported (Optional, none by default)
     * @param string $large_icon The CSS ID or URL of a large icon to display on the 
     *   rendered page (Optional, CSS ID 'icon-general-option' by default)
     * @param bool $is_submenu Set to true if this is a submenu entry (False by default)
     * @return MenuEntry Reference to the menu entry
     */    
    public function addMenu($title, $callback, $callback_args = false, $count = false, $context_help = '', $page_title = '', $icon = '', $large_icon = '', $is_submenu = false)
    {
        $menu = parent::addMenu($title, $callback, $callback_args, $count, $context_help, $page_title, $icon, $large_icon, $is_submenu);

        $menu->use_subheaders = true;
        
        return $menu;
    }
    
    /**
     * Determines whether a Subheader should be displayed
     *
     * @param MenuEntry $menu Menu entry to test
     * @param string $title Reference to title that should be displayed if this function returns true
     * @return bool Returns true if the menu should be displayed, false otherwise
     */
    public function doRenderSubHeader($menu, &$title)
    {
        $res = ($menu instanceof MenuEntry);
        
        if ($res) {
            $active_parent_slug = $this->getActiveParentSlug();
            $title = ($menu->type == MenuEntry::MT_SUBMENU) ? $menu->title : $this->home_title;
        }
            
        return $res;
    }    
}

 

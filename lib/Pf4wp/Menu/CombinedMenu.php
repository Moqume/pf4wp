<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Menu;

use Pf4wp\Menu\MenuEntry;
use Pf4wp\Menu\StandardMenu;

/**
 * CombinedMenu provides a stand-alone menu with submenus and page subheaders.
 *
 * @author Mike Green <myatus@gmail.com>
 */
class CombinedMenu extends StandardMenu
{
    const SUBMENU_ID = 'sub';
    
    private $active_menu;
    private $active_parent_slug;
  
    protected $home_title = '';
    
    /**
     * Constructor
     */
    public function __construct($id)
    {
        parent::__construct($id);
        
        $this->home_title = __('Home');
    }
    
    /**
     * Sets the default home title for subheaders
     *
     * @param string $new_home_title The home title to use
     */
    public function setHomeTitle($new_home_title)
    {
        $this->home_title = $new_home_title;
    }
        
    /**
     * Returns the active menu entry
     *
     * @return MenuEntry|bool active menu item, false if invalid
     */
    public function getActiveMenu()
    {
        if (!isset($this->active_menu)) {
            $this->active_menu = false;
            $active_submenu = (array_key_exists(self::SUBMENU_ID, $_GET)) ? trim((string)$_GET[self::SUBMENU_ID]) : '';
            
            foreach ($this->menus as $menu) {
                if ((empty($active_submenu) && $menu->isActive()) || $active_submenu == $menu->slug)
                    $this->active_menu = $menu;
            }
        }
        
        return $this->active_menu;
    }
    
    /**
     * Returns the active parent menu slug (for subheaders)
     *
     * This differens from obtaining the active menu, as this will also include the 
     * submenus and is used to display the right subheaders for the displayed (sub)menu.
     *
     * @return string|bool Slug for the active parent menu, false if invalid
     */
    public function getActiveParentSlug()
    {
        if (!isset($this->active_parent_slug)) {
            $this->active_parent_slug = false;
            
            foreach ($this->menus as $menu) {
                if (!$menu->use_subheaders && $menu->isActive())
                    $this->active_parent_slug = $menu->slug;
            }
        }
        
        return $this->active_parent_slug;
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
     * @param bool $is_submenu Set to true if this is a submenu entry (False by default)
     * @return MenuEntry Reference to the menu entry
     */
    public function addMenu($title, $callback, $callback_args = false, $count = false, $context_help = '', $page_title = '', $icon = '', $large_icon = '', $is_submenu = false)
    {
        $menu = parent::addMenu($title, $callback, $callback_args, $count, $context_help, $page_title, $icon, $large_icon, $is_submenu);


        $menu->use_subheaders = false;
        $menu->before_callback = array($this, 'onRenderSubHeader');
        
        return $menu;
    }
    
    /**
     * Adds a subheader item
     *
     * @see addMenu()
     * @see addSubMenu()
     * @see display()
     * @param MenuEntry The parent menu entry to which this subheader entry belongs
     * @param string $title The title of the subheader entry
     * @param string|array $callback Callback function to call when the selected page needs to be rendered
     * @param array|bool $callback_args Optional additional arguments to pass to the callback (Optional, none by default)
     * @param bool|int $count A count that is displayed next to the menu entry, or false for none (Optional, none by default)
     * @param string $context_help String continaing context help (Optional, none by default)
     * @return MenuEntry Reference to the menu entry, false if invalid.
     */
    public function addSubHeader(&$parent_menu, $title, $callback, $callback_args = false, $count = false, $context_help = '')
    {
        if (!$parent_menu instanceof MenuEntry)
            return false;
            
        $menu = $this->addSubMenu($title, $callback, $callback_args, $count, $context_help);
        
        $menu->use_subheaders   = true;
        $menu->parent_slug      = $parent_menu->slug; // So we know under which (sub)menu to show the headers
        
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
        if ($menu instanceof MenuEntry === false)
            return false;

        $active_parent_slug = $this->getActiveParentSlug();
    
        $res = (($menu->use_subheaders && $menu->parent_slug == $active_parent_slug) || $menu->slug == $active_parent_slug);
        
        if ($res)
            $title = ($menu->slug != $active_parent_slug) ? $menu->title : $this->home_title;
            
        return $res;
    }
    
    /* Events */
    
    /**
     * Renders a submenu-header on the page
     *
     * This event is triggered by a menu entry's 'before_callback'. It will 
     * return a callback if it is different than the original callback (due to 
     * active page selection), or NULL otherwise
     *
     * @return mixed
     */     
    public function onRenderSubHeader()
    {
        $result = null;
        
        $submenus = array();
        $active_menu = $this->getActiveMenu();
        $active_parent_slug = $this->getActiveParentSlug();
        
        foreach ($this->menus as $menu) {
            if ($this->doRenderSubHeader($menu, $title)) {
                // Note: $title will be set at this stage
                
                $is_active = ($active_menu == $menu);                
				$class = ( $is_active ) ? ' class="current"' : '';
                
                // Add count, if present
                if ( $menu->count !== false )
                    $title .= ' <span class="count">(' . $menu->count . ')</span>';
                    
				$submenus[] = '<li><a href="' .  htmlspecialchars(add_query_arg(array(self::SUBMENU_ID=>$menu->slug))) . '"' . $class . '>' . $title . '</a>';
                
                // Override the callback of the active subHEADER.
                if ( $is_active )
                    $result = $menu->callback;
            }
        }
               
        if ( count($submenus) > 1 ) {
            echo '<div><ul class="subsubsub">' . implode(" | </li>", $submenus) . '</li></ul></div>';
        }
        
        return $result;
    }
}

 

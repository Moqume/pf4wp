<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Menu;

/**
 * CombinedMenu provides a stand-alone menu with submenus and page subheaders.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Menu
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
    public function __construct($id = '', $textdomain = '')
    {
        parent::__construct($id, $textdomain);
        
        $this->home_title = __('Home', $this->textdomain);
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
     * @return MenuEntry|bool active menu item, `false` if invalid
     */
    public function getActiveMenu()
    {
        if (empty($this->active_menu)) {
            $this->active_menu = false;
            $active_submenu = (array_key_exists(self::SUBMENU_ID, $_GET)) ? trim((string)$_GET[self::SUBMENU_ID]) : '';
            
            /* Check if the submenu actually exists, and if not, return the 
             * parent as the active menu. But the cached value will NOT be
             * set at this point.
             */
            if (!array_key_exists($active_submenu, $this->menus))
                return $this->menus[$this->parent];
            
            foreach ($this->menus as $menu) {
                if ((empty($active_submenu) && $menu->isActive()) || $active_submenu == $menu->_properties['slug'])
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
     * @return string|bool Slug for the active parent menu, `false` if invalid
     */
    public function getActiveParentSlug()
    {
        if (!isset($this->active_parent_slug)) {
            $this->active_parent_slug = false;
            
            foreach ($this->menus as $menu) {
                if (!$menu->_properties['use_subheaders'] && $menu->isActive())
                    $this->active_parent_slug = $menu->_properties['slug'];
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
     * @param bool $is_submenu Set to true if this is a submenu entry (`False` by default)
     * @return MenuEntry Reference to the menu entry
     * @throws \Exception if the specified menu is a submenu, without having added a main menu.
     */
    public function addMenu($title, $callback, $callback_args = false, $is_submenu = false)
    {
        $menu = parent::addMenu($title, $callback, $callback_args, $is_submenu);

        $menu->_properties['use_subheaders'] = false;
        $menu->_properties['before_callback'] = array($this, 'onRenderSubHeader');
        
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
     * @return MenuEntry Reference to the menu entry, `false` if invalid.
     */
    public function addSubHeader(MenuEntry $parent_menu, $title, $callback, $callback_args = false)
    {
        $menu = $this->addSubMenu($title, $callback, $callback_args);
        
        $menu->_properties['use_subheaders'] = true;
        $menu->_properties['parent_slug']    = $parent_menu->_properties['slug']; // So we know under which (sub)menu to show the headers
        
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
    
        $res = (($menu->_properties['use_subheaders'] && $menu->_properties['parent_slug'] == $active_parent_slug) || $menu->_properties['slug'] == $active_parent_slug);
        
        if ($res)
            $title = ($menu->_properties['slug'] != $active_parent_slug) ? $menu->title : $this->home_title;
            
        return $res;
    }
    
    /* Events */
    
    /**
     * Renders a submenu-header on the page
     *
     * This event is triggered by a menu entry's 'before_callback'. It will 
     * return a callback if it is different than the original callback (due to 
     * active page selection), or `null` otherwise
     *
     * @return mixed
     */     
    public function onRenderSubHeader()
    {
        $result = null;
        
        $subheaders = array();
        $active_menu = $this->getActiveMenu();
        $active_parent_slug = $this->getActiveParentSlug();
        
        foreach ($this->menus as $menu) {
            if ($this->doRenderSubHeader($menu, $title)) {
                $is_active = ($active_menu == $menu);
                $title     = ($menu->count !== false) ? sprintf('%s <span class="count">(%d)</span>', $title, $menu->count) : $title;
                $url       = (empty($menu->_properties['parent_slug'])) ? menu_page_url($menu->_properties['slug'], false) : add_query_arg(array(self::SUBMENU_ID=>$menu->_properties['slug']), menu_page_url($menu->_properties['parent_slug'], false));
                
				$subheaders[] = sprintf(
                    '<li><a href="%s" %s>%s</a>', 
                    htmlspecialchars($url),
                    ($is_active) ? 'class="current"' : '',
                    $title
                );
                
                // Override the menu callbacks
                if ($is_active)
                    $result = $menu;
            }
        }
               
        if (count($subheaders) > 1)
            printf('<div><ul class="subsubsub">%s</li></ul></div>', implode(" | </li>", $subheaders));
        
        return $result;
    }
}

 

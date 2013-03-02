<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Menu;

use Pf4wp\Common\Helpers;

/**
 * StandardMenu provides a single stand-alone menu entry with submenus.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Menu
 * @api
 */
class StandardMenu
{
    const PRE_MENU_CALLBACK_SUFFIX = 'Load';

    /** Holds the active menu
     * @internal
     */
    private $active_menu;

    /** Set if the menu has been displayed (rendered)
     * @internal
     */
    private $displayed = false;

    /** The ID of the menu (used to determine the slug)
     * @internal
     */
    private $id = 'pf4wp';

    /** Text domain used for translations
     * @internal
     */
    protected $textdomain = '';

    /** Container for all menu entries
     * @internal
     */
    protected $menus = array();

    /** Holds the parent menu
     * @internal
     */
    protected $parent = '';

    /** Menu entry capability (role/permission)
     * @internal
     */
    protected $capability = '';

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
     *
     * @param string $id A unique ID to avoiding menu slug collisions
     * @param string $textdomain The textdomain for translations
     * @api
     */
    public function __construct($id = '', $textdomain = '')
    {
        if (!empty($id))
            $this->id = $id;

        $this->textdomain = (empty($textdomain) && !empty($id)) ? $id : $textdomain;
    }

    /**
     * Checks if the menu has already been displayed
     *
     * Note: This returns as soon as *any* of the menus have been displayed.
     *
     * @return bool `True` if the menu has been displayed, `false` otherwise
     * @api
     */
    public function isDisplayed()
    {
        if ($this->displayed == false) {
            foreach ($this->menus as $menu) {
                if ($menu->isDisplayed())
                    return ($this->displayed = true);
            }
        }

        return $this->displayed;
    }

    /**
     * Returns all menus from internal storage
     *
     * @return array An array of MenuEntry entries
     * @api
     */
    public function getMenus()
    {
        return $this->menus;
    }

    /**
     * Returns the active menu entry
     *
     * @return MenuEntry|bool active menu item, `false` if invalid
     * @api
     */
    public function getActiveMenu()
    {
        if (empty($this->active_menu)) {
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
     * @api
     */
    public function getParentSlug()
    {
        return $this->parent;
    }

    /**
     * Returns the hook of the parent menu entry
     *
     * @return string|bool WordPress provided hook, `false` if invalid.
     * @api
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
     * @api
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
     * @api
     */
    public function setType($new_type)
    {
        if ( !in_array($new_type, $this->supported_types) )
            throw new \Exception('This menu does not support the type requested.');

        if ( ! $this->isDisplayed() ) {
            $this->type = $new_type;

            foreach ($this->menus as $menu) {
                if ($menu->_properties['type'] != MenuEntry::MT_SUBMENU)
                    $menu->_properties['type'] = $this->type;
            }
        } else {
            throw new \Exception('The menu has already been displayed. Cannot change type');
        }
    }

    /**
     * Obtains the menu type being used
     *
     * @see \Myatu\WordPress\Plugin\Menu\MenuEntry
     * @see setType()
     * @return int Menu type
     * @api
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
     * @api
     */
    public function setCapability($new_capability)
    {
        if ( ! $this->isDisplayed() ) {
            $this->capability = $new_capability;

            foreach ($this->menus as $menu)
                $menu->capability = $this->capability;
        } else {
            throw new \Exception('The menu has already been displayed. Cannot change capabilities');
        }
    }

    /**
     * Retrieves the capabilities required to display this menu.
     *
     * @see setCapability()
     * @return string Capability as defined by WordPress
     * @api
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
     * @param bool $is_submenu Set to `true` if this is a submenu entry (`False` by default)
     * @return MenuEntry Reference to the menu entry
     * @throws \Exception if the specified menu is a submenu, without having added a main menu.
     * @api
     */
    public function addMenu($title, $callback, $callback_args = false, $is_submenu = false)
    {
        $menu = new MenuEntry($this->id);

        $menu->title      = $title;
        $menu->capability = $this->capability;

        $menu->_properties['callback']      = $callback;
        $menu->_properties['callback_args'] = $callback_args;

        // Convert callback to a "slug" - since 1.0.5
        $menu->_properties['long_slug'] = $this->id . '-' . (is_array($callback) ? $callback[1] : (string)$callback);
        $menu->_properties['slug']      = Helpers::makeSlug($menu->_properties['long_slug']);

        // Ensure the slug is unique in our menu (up to 99).
        if (array_key_exists($menu->_properties['slug'], $this->menus)) {
            $count = 1;

            while (array_key_exists($menu->_properties['slug'].$count, $this->menus) && $count < 100)
                $count++;

            $menu->_properties['slug'] .= $count;
        }

        if ( !$is_submenu ) {
            $this->parent              = $menu->_properties['slug'];
            $menu->_properties['type'] = $this->type;
        } else {
            if (!empty($this->parent)) {
                $menu->_properties['parent_slug'] = $this->parent;
                $menu->_properties['type']        = MenuEntry::MT_SUBMENU; // Submenus are always of this type
            } else {
                throw new \Exception('Cannot add a submenu before adding a main menu entry.');
            }
        }

        $this->menus[$menu->_properties['slug']] = $menu;

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
     * @return MenuEntry Reference to the menu entry
     * @api
     */
    public function addSubMenu($title, $callback, $callback_args = false)
    {
        return $this->addMenu($title, $callback, $callback_args, true);
    }

    /**
     * Renders (displays) all the menu entries in the WordPress Dashboard
     */
    public function display()
    {
        foreach ($this->menus as $menu) {
            if (!$menu->isDisplayed()) {
                $result = $menu->display();
                if ($result)
                    add_action('load-' . $menu->getHook(), array($this, 'onMenuLoad'));
            }
        }
    }

    /**
     * Menu loader event
     *
     * This will render the contextual help and 'per_page' settings, based
     * on the selected menu. If the menu callback has a matching 'Load' method,
     * it will be called too, containing the current screen as an argument.
     *
     * For example, if the menu callback is `onRenderMenu()`, and a method called
     * `onRenderMenuLoad()` exists, then it will be called prior to `onRenderMenu()`.
     *
     * This could be used, for instance, to add screen settings:
     * <code>
     *   add_action('screen_settings', ($this, 'screen_settings_callback'));
     * </code>
     *
     * where the `screen_settings_callback()` will return the details to be displayed.
     *
     */
    public function onMenuLoad()
    {
        if (($active_menu = $this->getActiveMenu()) !== false) {
            // Test if there's a method to call before the actual callback
            $before_callback = Helpers::validCallback($active_menu->_properties['callback'], static::PRE_MENU_CALLBACK_SUFFIX);
            if ($before_callback)
                call_user_func($before_callback, get_current_screen());

            $current_screen = get_current_screen();

            $context_help = $active_menu->context_help;

            // Set contextual help
            if (!empty($context_help)) {
                if (is_object($current_screen) && is_callable(array($current_screen, 'add_help_tab'))) {
                    // As of WP 3.3
                    if ($context_help instanceof \Pf4wp\Help\ContextHelp) {
                        $context_help->addTabs($current_screen);
                    } else {
                        $current_screen->add_help_tab(array(
                            'title'   => __('Overview'),
                            'id'      => 'overview',
                            'content' => $context_help,
                        ));
                    }
                } else {
                    add_contextual_help($current_screen, $context_help);
                }
            }

            $per_page_id = $active_menu->_properties['slug'] . $current_screen->id . MenuEntry::PER_PAGE_SUFFIX;

            // Check if the user has specified custom screen options
            if (isset($_POST['screen-options-apply']) &&
                isset($_POST['wp_screen_options']['value']) &&
                isset($_POST['wp_screen_options']['option']) && $_POST['wp_screen_options']['option'] == $per_page_id &&
                wp_verify_nonce($_POST['screenoptionnonce'], 'screen-options-nonce')) {

                global $current_user;

                $value = (int)$_POST['wp_screen_options']['value'];

                // Let's be reasonable
                if ($value < 1)
                    $value = (int)$active_menu->per_page;

                update_user_option($current_user->ID, $per_page_id, $value);

                // Columns
                $columns          = apply_filters('manage_' . $current_screen->id . '_columns', array());
                $existing_to_hide = get_user_option('manage' . $current_screen->id . 'columnshidden');
                if (!is_array($existing_to_hide))
                    $existing_to_hide = array();

                // Any existing columns to hide, but not part of the current $columns, are placed in $to_hide.
                $to_hide = array_diff($existing_to_hide, array_keys($columns));

                foreach ($columns as $column_id => $column_title) {
                    if (!in_array($column_id, array('_title', 'cb', 'comment', 'media', 'name', 'title', 'username', 'blogname')) &&
                        !isset($_POST[$column_id . '-hide']))
                        $to_hide[] = $column_id;
                }

                update_user_option($current_user->ID, 'manage' . $current_screen->id . 'columnshidden', $to_hide);
            }

            if ($active_menu->per_page) {
                // add_screen_option handles get_user_option, no need to pass value
                add_screen_option('per_page',
                    array(
                        'label'   => (empty($active_menu->per_page_label)) ? __('items per page', $this->textdomain) : $active_menu->per_page_label,
                        'option'  => $per_page_id,
                        'default' => (int)$active_menu->per_page,
                    )
                );
            }
        }
    }
}



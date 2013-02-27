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
 * MenuEntry provides all the details for adding and keeping track of a menu entry on
 * the WordPress Dashboard, and renders the initial portions of a page.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Menu
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

    const PER_PAGE_SUFFIX = '_per_page';

    /** Hook returned by WordPress
     * @internal
     */
    private $hook = false;

    /** Set if the menu entry has been displayed
     * @internal
     */
    private $displayed = false;

    /** The textdomain for translations
     * @internal
     */
    protected $textdomain = '';

    /** Internal properties, best not modified directly */
    public $_properties = array(
        'long_slug'       => '',
        'slug'            => '',
        'parent_slug'     => '',
        'before_callback' => '',
        'after_callback'  => '',
        'callback'        => '',
        'callback_args'   => false,
        'type'            => self::MT_SETTINGS, // default
        'use_subheaders'  => false,
    );

    /** The capability (permissions) the user needs in order to view this menu entry */
    public $capability = '';

    /** The title of the menu entry */
    public $title = '';

    /** The icon for this menu entry (valid for top level menus only) */
    public $icon = '';

    /** The count to be displayed next to the menu entry, or `false` if none */
    public $count = false;

    /** The page title to be displayed on the page for this menu entry */
    public $page_title = '';

    /** Extra string to add to the page title (this is _not_ rendered as a `<title>`) */
    public $page_title_extra = '';

    /** The large icon to be displayed on the page for this menu entry */
    public $large_icon = '';

    /** The context help to display on the page for this menu entry */
    public $context_help = '';

    /** The default value of 'per page' items, of `false` if not used */
    public $per_page = false;

    /** The label of the 'per page' items */
    public $per_page_label = '';

    /** If this menu entry has sub-headers, render them as a navigation tabs if set
     * @since 1.0.16
     */
    public $sub_as_nav = false;

    /** If set, display the page title (default)
     * @since 1.0.16
     */
    public $display_page_title = true;

    /**
     * Constructor
     *
     * @param string $textdomain The textdomain to use for translations
     */
    public function __construct($textdomain = '')
    {
        $this->textdomain = $textdomain;
    }

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
     * Returns the hook for this menu as provided by WordPress, `false` if invalid.
     *
     * @return string|bool
     */
    public function getHook()
    {
        return $this->hook;
    }

    /**
     * Returns the (parent) slug of this menu.
     *
     * @param bool $parent Whether to return the slug of the parent menu entry, or this current menu entry (default)
     * @return string
     */
    public function getSlug($parent = false)
    {
        if ($parent)
            return $this->_properties['parent_slug'];

        return $this->_properties['slug'];
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
     * Displays the menu entry on the WordPress Dashboard
     *
     * @return bool Returns `true` if successful, `false` otherwise.
     * @throws \Exception if the no title or callback function was specified
     */
    public function display()
    {
        if ( empty($this->title) ||
             empty($this->_properties['callback'] )) {
            throw new \Exception('No title or callback function specified for menu entry');
        }

        $title      = $this->title;
        $page_title = $this->page_title;
        $icon       = $this->icon;
        $capability = $this->capability;
        $parent     = $this->_properties['parent_slug'];
        $slug       = $this->_properties['slug'];

        if ( empty($slug) ) {
            $_cb  = $this->_properties['callback'];
            $slug = Helpers::makeSlug(is_array($_cb) ? $_cb[1] : (string)$_cb); // Since 1.0.5, slug is based on callback function name
            unset($_cb);
        }

        if ( empty($page_title) ) {
            $page_title = $title;
        }

        // Sanitize and add count to the title here (prior operations use a "clean" title)
        if ($this->count !== false) {
            $title = htmlspecialchars($title) . ' <span class="awaiting-mod"><span class="pending-count">' . $this->count . '</span></span>';
        } else {
            $title = htmlspecialchars($title);
        }

        // We call our own callback first
        $callback = array($this, 'onMenuCallback');

        switch ($this->_properties['type'])
        {
            case self::MT_CUSTOM:
                if ( empty($capability) ) $capability = 'read';
                $this->hook = add_menu_page($page_title, $title, $capability, $slug, $callback, $icon);
                break;

            case self::MT_SUBMENU:
                if ( empty($capability) ) $capability = 'read';
                if ( !$this->_properties['use_subheaders'] ) {
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
            $this->_properties['slug'] = $slug;
            $this->capability = $capability;
            $this->page_title = $page_title;
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
     * otherwise the callback of the result is used.
     *
     * The callback is also passed an array containing various details about the menu
     * properties, its hook and any custom defined arguments.
     */
    public function onMenuCallback()
    {
        $current_screen = get_current_screen();
        $callback       = $this->_properties['callback'];
        $callback_args  = $this->_properties['callback_args'];
        $per_page_id    = $this->_properties['slug'] . $current_screen->id . static::PER_PAGE_SUFFIX;
        $per_page_def   = $this->per_page;
        $capability     = $this->capability;

        // Perform 'before_callback' event
        ob_start();
        if ( Helpers::validCallback($this->_properties['before_callback']) !== false ) {
            $result = call_user_func($this->_properties['before_callback']);

            // If the result from 'before_callback' is not NULL, use the result as the actual callback and callback_args (override).
            if ( !empty($result) && $result instanceof MenuEntry ) {
                $callback       = $result->_properties['callback'];
                $callback_args  = $result->_properties['callback_args'];
                $per_page_id    = $result->_properties['slug'] . $current_screen->id . static::PER_PAGE_SUFFIX;
                $per_page_def   = $result->per_page;
                $capability     = $result->capability;
            }
        }
        $before_callback_output = ob_get_clean();

        // Extra permission check (if bypassed in WordPress)
        if (!current_user_can($capability))
            wp_die(__('You do not have sufficient permissions to access this page.', $this->textdomain));

        // Set final 'per page' variable
        $per_page = (int)get_user_option($per_page_id);
        if (empty($per_page))
            $per_page = $per_page_def;

        /* Render page */

        echo '<div class="wrap">';

        // Render large icon
        if (!empty($this->large_icon)) {
            if ( strpos($this->large_icon, '/') === false && substr($this->large_icon, 0, strlen('data:')) != 'data:' ) {
                // Use an icon by CSS ID
                $icon = sprintf('id="%s"', $this->large_icon);
            } else {
                // Ensure SSL is used
                $icon = is_ssl() ? preg_replace('#^http:#', 'https:', $this->large_icon) : $this->large_icon;

                // Use a background with specified URL or Data URI
                $icon = sprintf('style="background: url(\'%s\') no-repeat scroll center center transparent"', $icon);
            }
        }
        printf('<div class="icon32" %s><br /></div>', (isset($icon)) ? $icon : 'id="icon-options-general"');

        // Render title
        if ($this->display_page_title)
            printf('<h2>%s%s</h2>', $this->page_title, $this->page_title_extra);

        // Render output of before_callback
        echo $before_callback_output;

        // Perform user-defined callback
        echo '<div class="clear"></div><div>';
        if ( Helpers::validCallback($callback) )
            call_user_func($callback, $callback_args, $per_page);
        echo '</div>';

        // Perform 'afer_callback' event
        if ( Helpers::validCallback($this->_properties['after_callback']) )
            call_user_func($this->_properties['after_callback']);

        echo '</div>'; // div wrap
    }
}

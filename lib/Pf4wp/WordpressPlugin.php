<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp;

use Pf4wp\Common\Helpers;
use Pf4wp\Info\PluginInfo;
use Pf4wp\Storage\StoragePath;
use Pf4wp\Notification\AdminNotice;
use Pf4wp\Options\WordpressOptions;
use Pf4wp\Menu\StandardMenu;
use Pf4wp\Menu\MenuEntry;
use Pf4wp\Widgets\Widget;
use Pf4wp\Template\NullEngine;
use Pf4wp\Template\TwigEngine;

/**
 * WordpressPlugin (Pf4wp) provides a base framework to develop plugins for WordPress.
 *
 * The idea is to provide consistency across plugins, and allowing 
 * plugin development focus on the core functionality rather
 * than that of WordPress.
 *
 * Minimum requirements: 
 * PHP 5.3.0
 * WordPress: 3.1.0
 *
 * @author Mike Green <myatus@gmail.com>
 * @version 1.0.4
 * @package Pf4wp
 * @api
 */
class WordpressPlugin
{
    const RESOURCES_DIR    = 'resources/';
    const LOCALIZATION_DIR = 'resources/l10n/';
    const VIEWS_DIR        = 'resources/views/';
    const VIEWS_CACHE_DIR  = 'store/cache/views/';
    
    /** Instance container
     * @internal
     */
    private static $instances = array();
    
    /** Whether the plugin has been registered with WordPress
     * @internal
     */
    private $registered = false;
    
    /** Main (master) filename of the plugin, as loaded by WordPress
     * @internal
     */
    private $plugin_file = '';
    
    /** Working name of the plugin (used for options, slugs, etc.)
     * @internal
     */
    private $name = '';
    
    /** The menu attached to the plugin, if any
     * @internal
     */
    private $menu = false;
    
    /** An object handling the internal options for the plugin
     * @internal
     */
    private $internal_options;
    
    /** Array containing the default internal options
     * @internal
     */
    private $default_internal_options = array(
        'version' => '0.0',             // The version of the plugin, to track upgrade events
        'delayed_notices' => array(),   // Array containing notices that aren't displayed until possible to show them 
    );
    
    /**
     * The template engine object (EngineInterface)
     * @see Template\EngineInterface
     * @api
     */
    public $template;
    
    /**
     * The options object for the plugin (Options)
     * @see Options\Options
     * @see Options\WordpressOptions
     * @api
     */
    public $options;
    
    /**
     * If the public-side AJAX is enabled, this variable is set to `true`
     * @api
     */
    public $public_ajax = false;
    
    /**
     * The default options for the plugin, if any
     * @api
     */
    protected $default_options = array();
        
    /**
     * Constructor (Protected; use instance())
     *
     * @see instance()
     * @see register()
     * @param string $plugin_file The filename of the plugin's main file
     */
    protected function __construct($plugin_file)
    {
        if (empty($plugin_file))
            return;
        
        $this->plugin_file = $plugin_file;
        $this->name = plugin_basename(dirname($plugin_file));
        
        // Options handlers
        $this->options = new WordpressOptions($this->name, $this->default_options);
        $this->internal_options = new WordpressOptions('_' . $this->name, $this->default_internal_options); // Internal            
        
        // pre-Initialize the template engine to a `null` engine
        $this->template = new NullEngine();        
        
        // Register uninstall and (de)activation hooks
        register_activation_hook(plugin_basename($this->plugin_file), array($this, '_onActivation'));
        register_deactivation_hook(plugin_basename($this->plugin_file), array($this, '_onDeactivation'));
        register_uninstall_hook(plugin_basename($plugin_file), get_class($this) . '::_onUninstall');
        
        // Register an action for when a new blog is created on multisites
        if (function_exists('is_multisite') && is_multisite())
            add_action('wpmu_new_blog', array($this, '_onNewBlog'), 10, 1);
        
        // Widgets get initialized individually (and before WP `init` action) - PITA!
        if ( !Helpers::isNetworkAdminMode() )
            add_action('widgets_init', array($this, 'onWidgetRegister'), 10, 0);
    }
    
    /**
     * Destructor
     */
    public function __destruct()
    {
        restore_exception_handler();
    }
    
    /*---------- Helpers ----------*/
    
    /**
     * Return an instance of the plugin, optionally creating one if non-existing
     *
     * @param string $plugin_file The filename of the plugin's main file (Required on first call, optional afterwards)
     * @param bool $auto_register If set to `true`, automatically register hooks and filters that provide full functionality/events
     * @return WordpressPlugin instance
     * @throws \Exception if plugin file is omitted on first instance() call.
     */
    final public static function instance($plugin_file = '', $auto_register = true)
    {
        $class = get_called_class();

        if (!array_key_exists($class, self::$instances)) {
            if (empty($plugin_file))
                throw new \Exception('First call to instance() requires the plugin filename');
                
            self::$instances[$class] = new $class($plugin_file);
        }
        
        if ($auto_register === true)
            self::$instances[$class]->registerActions();

        return self::$instances[$class];
    }    
    
    /**
     * Registers the plugin with WordPress
     *
     * This function is called in the main plugin file, which WordPress loads. 
     *
     * Example of a main plugin file:
     * <code>
     * if (!function_exists('add_action')) return;
     *
     * $_pf4wp_file = __FILE__;
     * require dirname(__FILE__).'/vendor/pf4wp/lib/Pf4wp/bootstrap.php';
     * if (!isset($_pf4wp_check_pass) || !isset($_pf4wp_ucl) || !$_pf4wp_check_pass) return;
     * 
     * $_pf4wp_ucl->registerNamespaces(array(
     *     'Symfony\\Component\\ClassLoader'   => __DIR__.'/vendor/pf4wp/lib/vendor',
     *     'Pf4wp'                             => __DIR__.'/vendor/pf4wp/lib',
     * ));
     * $_pf4wp_ucl->registerPrefixes(array(
     *     'Twig_' => __DIR__.'/vendor/Twig/lib',
     * ));
     * $_pf4wp_ucl->registerNamespaceFallbacks(array(
     *     __DIR__.'/app',
     * ));
     * $_pf4wp_ucl->register();
     * 
     * call_user_func('My\\Plugin::register', __FILE__); // <-- Register plugin with WordPress here
     * </code>
     *
     * @param string $plugin_file The filename of the plugin's main file
     * @throws \Exception if no plugin filename was specified
     * @api
     */
    public static function register($plugin_file)
    {
        if (empty($plugin_file))
            throw new \Exception('No plugin filename was specified for register()');
            
        self::instance($plugin_file, false); // Don't register the actions here, it will be done by WordPress with the 'init' action.

        add_action('init', get_called_class() . '::instance', 10, 0);
    }
    
    /**
     * Registers all actions, hooks and filters to provide full functionality/event triggers
     *
     * A note on the use of "$this" versus the often seen "&$this": In PHP5 a copy of the object is 
     * only returned when using "clone". Also, for other use of references, the Zend Engine employs 
     * a "copy-on-write" logic, meaning that variables will be referenced instead of copied until 
     * it's actually written to. Do not circumvent Zend's optimizations!
     *
     * @see construct()
     * @api
     */
    public function registerActions()
    {
        if ($this->registered || empty($this->plugin_file))
            return;
            
        // Load locales
        $locale = get_locale();
        if ( !empty($locale) ) {
            $mofile = $this->getPluginDir() . static::LOCALIZATION_DIR . $locale . '.mo';
            
            if ( @is_file($mofile) && @is_readable($mofile) ) 
                load_textdomain($this->name, $mofile);
        }            
        
        // Plugin events (also in Multisite)
        add_action('after_plugin_row_' . plugin_basename($this->plugin_file), array($this, '_onAfterPluginText'), 10, 0);

        /* Do not register any actions after this if we're in Network Admin mode */
        if (Helpers::isNetworkAdminMode())
            return;

        // Template Engine (currently Twig)
        $views_dir = $this->getPluginDir() . static::VIEWS_DIR;
        
        if (@is_dir($views_dir) && @is_readable($views_dir)) {
            $options = array();
            
            if (defined('WP_DEBUG') && WP_DEBUG)
                $options = array_merge($options, array('debug' => true));

            if (($cache = StoragePath::validate($this->getPluginDir() . static::VIEWS_CACHE_DIR)) !== false)
                $options = array_merge($options, array('cache' => $cache));
            
            $this->template = new TwigEngine($views_dir, $options);
            
            // Add Twig translation extension automatically
            $translate_extension = new \Pf4wp\Template\Extensions\Twig\Translate();
            $translate_extension->setTextDomain($this->name);
            
            $this->template->getEngine()->addExtension($translate_extension);
        }
    
        // Internal and Admin events
        add_action('admin_menu', array($this, '_onAdminRegister'), 10, 0);
        add_action('wp_dashboard_setup', array($this, 'onDashboardWidgetRegister'), 10, 0);
        add_action('admin_notices',	array($this, '_onAdminNotices'), 10, 0);
        
		// Plugin events
        add_filter('plugin_action_links_' . plugin_basename($this->plugin_file), array($this, '_onPluginActionLinks'), 10, 1);
        
        // Public events
        add_action('parse_request',	array($this, '_onPublicInit'), 10, 0);
        
        // AJAX events
        add_action('wp_ajax_' . $this->name, array($this, '_onAjaxCall'), 10, 0);       
        if ($this->public_ajax)
            add_action('wp_ajax_nopriv_' . $this->name, array($this, '_onAjaxCall'), 10, 0);
            
        // Register a final action when WP has been loaded
        add_action('wp_loaded', array($this, '_onWpLoaded'), 10, 0);        
        
        // Done!
        $this->registered = true;
    }
    
    /**
     * Returns the plugin working name (used to access low-level functions)
     *
     * @return string Plugin working name
     * @api
     */
    public function getName()
    {
        return $this->name;
    }
        
    /**
     * Returns the plugin directory
     *
     * @return string Plugin directory (always with trailing slash)
     * @api
     */
    public function getPluginDir()
    {		
        return trailingslashit(dirname($this->plugin_file));
    }
    
    /**
     * Returns the plugin Base Name (as used by many WordPress functions/methods
     *
     * @return string Plugin base name
     * @api
     */
    public function getPluginBaseName()
    {
        return plugin_basename($this->plugin_file);
    }
    
    /**
     * Get the plugin URL
     *
     * @param bool $full If set to true, the full URL (including filename) is returned.
     * @return string Plugin URL
     * @api
     */
    public function getPluginUrl($full = false)
    {		
        if ($full)
            return WP_PLUGIN_URL . '/' . plugin_basename($this->plugin_file);
        
        // Default action
        return trailingslashit(WP_PLUGIN_URL . '/' . $this->name);
    }
    
    /**
     * Helper to obtain the resource URL.
     *
     * @param string $type Type of resource directory to retrieve (ie: 'css', 'js', 'views'), 'js' by default
     * @return array Array containing Base URL, Version and Debug string.
     * @api
     */
    public function getResourceUrl($type = 'js')
    {
        $url     = trailingslashit($this->getPluginUrl() . static::RESOURCES_DIR . $type);
        $version = PluginInfo::getInfo(true, $this->getPluginBaseName(), 'Version');
        $debug   = (defined('WP_DEBUG') && WP_DEBUG) ? '.dev' : '';
        
        return array($url, $version, $debug);
    }    
    
    
    /**
     * Get the URL of the main menu entry
     *
     * @return string|bool URL, or `false` if invalid.
     * @api
     */
    public function getParentMenuUrl()
    {
        if ($this->menu instanceof StandardMenu)
            return $this->menu->getParentUrl();
        
        return false;
    }
    
    /**
     * Returns the display name for the plugin
     *
     * @return string Display name
     * @api
     */
    public function getDisplayName()
    {
        return PluginInfo::getInfo(false, plugin_basename($this->plugin_file), 'Name');
    }
    
    /**
     * Returns the version for the plugin
     *
     * @return string Version
     * @api
     */
    public function getVersion()
    {
        return PluginInfo::getInfo(false, plugin_basename($this->plugin_file), 'Version');
    }
    
    /**
     * Retrieves the menu attached to this plugin
     *
     * @return StandardMenu|bool The menu or `false` if invalid
     * @api
     */
    public function getMenu()
    {
        if ($this->menu instanceof StandardMenu)
            return $this->menu;
        
        return false;
    }
    
    /**
     * Generates an AJAX response
     *
     * Generates an AJAX response, encoding the data as JSON data. It will also 
     * include a NONCE to protect the authenticity of the data.
     *
     * NOTE! This function will finish with a 'die()', therefore anything
     * placed after this function will not be executed.
     *
     * @param mixed|string $data The data to return, or an error string as the message 
     *		to be displayed if $is_error parameter is set to `true`
     * @param bool $is_error Optional parameter that if set to `true` will create an 
     *		AJAX error response (uses $data as the error string)
     * @return die()
     * @api
     */
    public function ajaxResponse($data, $is_error = false) {
        $out = array();
        $out['stat']  = ($is_error) ? 'fail' : 'ok';
        $out['data']  = $data;
        $out['nonce'] = wp_create_nonce($this->name . '-ajax-response');
        
        die (json_encode($out));
    }
    
    /**
     * Registers a sidebar (theme) widget
     *
     * It adds an additional call immediately after normal registration
     * via register_widget(), providing opportunity to initialize the 
     * widget and setting the owner.
     *
     * Keep in mind that the namespace needs to be included. If it is using
     * the same namespace as the plugin, one could use:
     * <code>
     *    $this->registerWidget(__NAMESPACE__.'\MyWidgetClass');
     * </code>
     *
     * @todo: find a suitable hook?
     * @param string Classname of class to register
     * @api
     */
    public function registerWidget($class)
    {
        global $wp_widget_factory;
        
        register_widget($class);
        
        if (isset($wp_widget_factory->widgets[$class])) {
            $instance = $wp_widget_factory->widgets[$class];
            
            if ($instance instanceof Widget)
                $instance->doInit($this);
        }
    }
    
    /**
     * Clears (purges) any plugin-wide managed caches
     *
     * @param bool $delete If set to `true`, the cache is deleted instead of only cleared
     * @return bool Returns `true` if the operation was successful, `false` otherwise
     * @api
     */
    public function clearCache($delete = false)
    {
        $cache_dir = $this->getPluginDir() . static::VIEWS_CACHE_DIR;
        $result    = (StoragePath::delete($cache_dir) !== false);
        
        if ($result && !$delete)
            $result = (StoragePath::validate($cache_dir) !== false);
            
        return $result;
    }
    
    /**
     * Deletes any plugin-wide managed caches
     *
     * Short-hand for clearCache(true)
     *
     * @return bool Returns `true` if the operation was successful, `false` otherwise
     * @api
     */
    public function deleteCache()
    {
        return $this->clearCache(true);
    }
    
    /**
     * Adds a delayed notice to the queue
     *
     * This is particularly useful for displaying notices after an onActivate() event, 
     * as this is triggered inside a sandbox thus preventing anything from being displayed.
     *
     * @param string $message Message to display to the end user
     * @param bool $is_error Optional parameter to indicate the message is an error message
     * @api
     */
    public function addDelayedNotice($message, $is_error = false)
    {
        $queue = $this->internal_options->delayed_notices;
        
        if (!is_array($queue))
            $queue = array();
        
        $queue[] = array($message, $is_error);
    
        $this->internal_options->delayed_notices = $queue;
    }
    
    /**
     * Clears the delayed notice queue
     *
     * @api
     */
    public function clearDelayedNotices()
    {
        $this->internal_options->delayed_notices = array();
    }
    
    /*---------- Private Helpers (callbacks have a public scope!) ----------*/
    
    /**
     * Inserts (echoes) the AJAX variables
     *
     * Exposes a number of variables to JavaScript, required for WP Ajax functions. This
     * is done here, as they are not automatically generated for the non-priviledged (public)
     * side by WP. The variables are:
     *
     * - `url`           : The URL where to send the AJAX request (location of admin-ajax.php)
     * - `action`        : The action perform (the name of the plugin)
     * - `nonce`         : The NONCE to send, used to verify the AJAX request by the plugin
     * - `nonceresponse` : The NONCE sent back as a result of the AJAX request should match this to be valid
     *
     * @internal
     */
    private function insertAjaxVars()
    {
        echo (
            '<script type="text/javascript">' . PHP_EOL .
            '//<![CDATA[' . PHP_EOL . 
            'var ' . strtr($this->name, '-', '_') . '_ajax = { ' .
            'url: \'' . admin_url('admin-ajax.php') . '\', '.
            'action: \'' . $this->name . '\', ' .
            'nonce: \'' . wp_create_nonce($this->name . '-ajax-call') . '\', ' .
            'nonceresponse: \'' . wp_create_nonce($this->name . '-ajax-response') . '\'' . 
            ' }; ' . PHP_EOL .
            '//]]>' . PHP_EOL .
            '</script>' . PHP_EOL
        );
    }

    /**
     * Attaches common Admin events to menu hooks
     * 
     * @see _onAdminRegister()
     * @param string Hook provided by WordPress for the menu item
     * @internal
     */    
    private function attachAdminLoadHooks($hook)
    {
        if (empty($hook))
            return;
            
        add_action('load-' . $hook,                array($this, '_onAdminLoad'));
        add_action('admin_print_scripts-' . $hook, array($this, '_onAdminScripts'));
        add_action('admin_print_styles-' . $hook,  array($this, 'onAdminStyles'));
    }
    
    /**
     * Iterates blogs, performing an action after each switch (multisite)
     *
     * @param mixed $action Action to perform
     * @param mixed $args Array containing parameters to pass to the action
     * @internal
     */
    private function iterateBlogsAction($action, array $args = array())
    {
        if (!Helpers::validCallback($action))
            return;
            
        // Perform action on the current blog first
        call_user_func_array($action, $args);            
        
        // If in Network Admin mode, iterate all other blogs
        if (Helpers::isNetworkAdminMode()) {
            global $wpdb, $blog_id, $switched, $switched_stack;
            
            $orig_switched_stack = $switched_stack;  // global $switched_stack
            $orig_switched       = $switched;        // global $switched 
            $orig_blog_id        = $blog_id;         // global $blog_id
            $all_blog_ids        = $wpdb->get_col( $wpdb->prepare("SELECT blog_id FROM {$wpdb->blogs} WHERE blog_id <> %d", $orig_blog_id) ); // global $wpdb

            foreach ($all_blog_ids as $a_blog_id) {
                switch_to_blog($a_blog_id);
                call_user_func_array($action, $args);
            }
            
            // Switch back to the original blog
            switch_to_blog($orig_blog_id);
            
            /* Reset the global $switched and $switched_stack, as we're back at the original now. 
             * This is faster than calling restore_current_blog() after each completed switch. 
             * See wp-includes/ms-blogs.php.
             */
            $switched       = $orig_switched;       // global $switched 
            $switched_stack = $orig_switched_stack; // global $switched_stack
        }
    }
    
    /**
     * Performs common _onActivation() actions
     *
     * @see _onActivation(), onActivation()
     * @internal
     */
    public function _doOnActivation()
    {
        // Call user-defined event
        $this->onActivation();
    }
    
    /**
     * Performs common _onDeactivation() actions
     *
     * @see _onDeactivation(), onDeactivation()
     * @internal
     */
    public function _doOnDeactivation()
    {
        // Clear delayed notices
        $this->clearDelayedNotices();
        
        // Call user-defined event
        $this->onDeactivation();
    }    
    
    /**
     * Performs common _onUninstall() actions
     *
     * @see _onUninstal(), onUninstall()
     * @internal
     */
    public function _doOnUninstall()
    {
        // Delete our options from the WP database
        $this->options->delete();
        $this->internal_options->delete();
        
        // Call user-defined event
        $this->onUninstall();
    }
    
    /*---------- Private events (the scope is public, due to external calling). ----------*/
    
    /**
     * Event called when a new blog is added (multisite)
     *
     * Here we activate this plugin for the new blog, if it is enabled site-wide. This is
     * because the _onActivate() event will not be triggered if the plugin is already activated
     * site-wide. See wp-includes/ms-functions.php; wpmu_create_blog()
     * 
     * @see _doOnActivation()
     * @param int $blog_id The new blog ID (provided by WP Action)
     * @internal
     */
    final public function _onNewBlog($blog_id)
    {
        if (($active_sitewide_plugins = get_site_option('active_sitewide_plugins')) !== false &&
            array_key_exists(plugin_basename($this->plugin_file), $active_sitewide_plugins)) {
            // Switch to the new blog
            switch_to_blog($blog_id);
            
            // Perform common _onActivation tasks just for the new blog
            $this->_doOnActivation();
            
            // Go back to the original blog
            restore_current_blog();
        }
    }
    
    /**
     * Event called when the plugin is activated
     *
     * @see onActivation(), _doOnActivation()
     * @internal
     */
    final public function _onActivation()
    {
        // Clear the plugin-wide managed cache
        $this->clearCache();
        
        $this->iterateBlogsAction(array($this, '_doOnActivation'));
    }
    
    /**
     * Event called when the plugin is deactivated
     *
     * @see onDeactivation(), _doOnDeactivation()
     * @internal
     */
    final public function _onDeactivation()
    {
        $this->iterateBlogsAction(array($this, '_doOnDeactivation'));
    }    
    
    /**
     * Static function called when the plugin is uninstalled
     *
     * Note: Remember to use the full namespace when calling this function!
     *
     * @see onUninstall(), _doOnUninstall()
     * @internal
     */
    final public static function _onUninstall()
    {
        $this_instance = self::instance('', false);
        
        // Delete the plugin-wide managed cache
        $this_instance->deleteCache();
        
        $this_instance->iterateBlogsAction(array($this_instance, '_doOnUninstall'));
    }
    
    /**
     * Event called when the WordPress is fully loaded
     *
     * @see onWpLoaded(), onUpgrade()
     * @internal
     */
    final public function _onWpLoaded()
    {
        // Check for upgrade
        $current_version = $this->getVersion();
       
        if (!empty($current_version) && ($previous_version = $this->internal_options->version) != $current_version) {
            // Clear (purge) the plugin-wide managed cache
            $this->clearCache();
            
            $this->internal_options->version = $current_version;
            
            $this->iterateBlogsAction(array($this, 'onUpgrade'), array($previous_version, $current_version));
        }
        
        $this->onWpLoaded();
    }
     
    /**
     * Register the plugin on the administrative backend (Dashboard) - Stage 1
     *
     * Calls delayed onActivation() and adds hooks for menu entries 
     * returned by onBuildMenu(), if any.
     *
     * @see onAdminInit(), onBuildMenu()
     * @internal
     */
    final public function _onAdminRegister()
    {
        if (!is_admin())
            return;

        // Additional actions to be registered
        $this->onAdminInit();
        
        // Build the menu
        $result = $this->onBuildMenu();
        
        if ($result instanceof StandardMenu) {
            $this->menu = $result;

            // Display menu (if not already displayed)
            $this->menu->display();

            // Add additional hooks for menu entries
            $menus = $this->menu->getMenus();
            if ( is_array($menus) ) {
                foreach ($menus as $menu_entry)
                    $this->attachAdminLoadHooks($menu_entry->getHook());
            }
        } else if (is_string($result)) {
            $this->attachAdminLoadHooks($result);
        }
    }
    
    /**
     * Displays a short message underneith the plugin description
     *
     * @see onAfterPluginText()
     * @internal
     */
    final public function _onAfterPluginText()
    {
        if (!is_admin())
            return;
        
        $text = $this->onAfterPluginText();
        
        if ( !empty($text) )
            printf(
                '<tr class="active column-description"><th>&nbsp;</th><td colspan="2"><div class="plugin-description" style="padding: 0 0 5px;"><em>%s</em></div></td></tr>',
                $text
            );
    }
    
    /**
     * Adds a 'Settings' link to the plugin actions as an added convenience
     *
     * @param mixed Array containing existing plugin actions
     * @internal
     */
    final public function _onPluginActionLinks($actions)
    {
        if (!is_admin())
            return;
        
        $url = $this->getParentMenuUrl();
        
        if ( !is_array($actions) )
            $actions = array();
               
        if ( !empty($url) )
            array_unshift($actions, sprintf(
                '<a href="%s" title="%s">%s</a>', 
                $url,  
                __('Configure this plugin', $this->name), 
                __('Settings', $this->name))
            );
            
        return $actions; 
    }
        
    /**
     * Admin loader event called when the selected page is about to be rendered - Stage 2
     *
     * @see onAdminLoad()
     * @internal
     */
    final public function _onAdminLoad()
    {
        if (!is_admin())
            return;
        
        // Set new exception handler
        set_exception_handler(array($this, '_onStage2Exception'));
        
        $this->onAdminLoad(get_current_screen());
    }
        
    /**
     * Loads any internal scripts before passing it to the public event
     *
     * This provices AJAX nonce for calls and replies for improved security
     *
     * @see onAdminScripts()
     * @internal
     */
    final public function _onAdminScripts()
    {
        if (!is_admin())
            return;

        $this->insertAjaxVars();
        
        $this->onAdminScripts();
    }
    
    /**
     * Event called to display Dashboard notices in the notification queue
     *
     * @internal
     */
    final public function _onAdminNotices()
    {
        if (!is_admin())
            return;

        $queue = $this->internal_options->delayed_notices;
        
        if (!empty($queue)) {
            foreach($queue as $notice)
                AdminNotice::add($notice[0], $notice[1]);
            
            $this->clearDelayedNotices();
        }
        
        AdminNotice::display();
    }
    
    /**
     * Process an AJAX call
     *
     * @see onAjaxRequest(), ajaxResponse()
     * @internal
     */
    final public function _onAjaxCall()
    {
        check_ajax_referer($this->name . '-ajax-call'); // Dies if the check fails

        header('Content-type: application/json');

        if ( !isset($_POST['func']) ||
             !isset($_POST['data']) ) 
            $this->ajaxResponse(__('Malformed AJAX Request', $this->name), true);

        $this->onAjaxRequest((string)$_POST['func'], $_POST['data']);

        // Default response
        $this->ajaxResponse('', true);        
    }
    
    /**
     * Registers public events
     *
     * @see onPublicScripts(), onPublicStyles(), onPublicLoad()
     * @internal
     */
    final public function _onPublicInit()
    {
        if (is_admin()) 
            return;

        add_action('wp_print_scripts',  array($this, '_onPublicScripts'));
        add_action('wp_print_styles',   array($this, 'onPublicStyles'));
        add_action('wp_footer',         array($this, 'onPublicFooter'));

        $this->onPublicInit();
    }
    
    /**
     * Event called public scripts
     *
     * @see onPublicScripts()
     * @internal
     */
    final public function _onPublicScripts()
    {
        if ($this->public_ajax)
            $this->insertAjaxVars();
        
        $this->onPublicScripts();
    }
    
    /**
     * Handles a Stage 2 exception
     *
     * @param \Exception $exception Exception object
     * @param int $count Count of Exception object
     * @internal
     */
    final public function _onStage2Exception($exception, $count = 1)
    {
        $abbr = function($class){ return sprintf('<abbr title="%s" style="border-bottom:1px dotted #000;cursor:help;">%s</abbr>', $class, array_pop(explode('\\', $class))); };
        
        $content = '';
        
        if ($count == 1)
            $content .= '<div style="clear:both"></div><h1>' . __('Oops! Something went wrong', $this->name) . ':</h1>';
        
        $content .= sprintf('<div class="postbox"><h2 style="border-bottom:1px solid #ddd;margin-bottom:10px;padding:5px;"><span>#%s</span> %s: %s</h2><ol>',
            $count,
            $abbr(get_class($exception)),
            $exception->getMessage()
        );
        
        foreach ($exception->getTrace() as $i => $trace) {
            $content .= '<li>';

            if ($trace['function']) {
                $content .= sprintf( __('at %s%s%s()', $this->name), 
                    (isset($trace['class'])) ? $abbr($trace['class']) : '', 
                    (isset($trace['type'])) ? $trace['type'] : '', 
                    $trace['function']
                );
            }
            
            if (isset($trace['file']) && isset($trace['line'])) {
                $content .= sprintf( __(' in <code>%s</code> line %s', $this->name), 
                    $trace['file'], 
                    $trace['line']
                );
            }
            
            $content .= '</li>';
        }

        $content .= '</ol></div>';

        echo $content;        
        
        if ($exception->getPrevious()) {
            $count++;
            $this->_onStage2Exception($exception->getPrevious(), $count);
        }
        
        die();
    }
    
    
    /*---------- Public events that are safe to override to provide full plugin functionality ----------*/
    
    /**
     * Event called when the plugin is activated
     *
     * Note: This event is called for each blog on a Multi-Site installation
     *
     * @api
     */
    public function onActivation() {}
    
    /**
     * Event called when the plugin is deactivated
     *
     * Note: This event is called for each blog on a Multi-Site installation
     *
     * @api
     */
    public function onDeactivation() {}
    
    /**
     * Event called when the plugin is uninstalled
     *
     * Note: This event is called for each blog on a Multi-Site installation
     *
     * @api
     */
    public function onUninstall() {}
    
    /**
     * Event called when the plugin is upgraded
     *
     * Note: This event is called for each blog on a Multi-Site installation
     *
     * If the previous version is 0.0, then this is a new installation.
     *
     * @param string $previous_version Previous version
     * @param string $current_version Current version
     * @api
     */
    public function onUpgrade($previous_version, $current_version) {}
    
    /**
     * Event called when WordPress is fully loaded
     *
     * Note: This event is called on both the public (front-end) and admin (back-end) sides. Use 
     * `is_admin()` if neccesary.
     *
     * @api
     */
    public function onWpLoaded() {}
    
    /**
     * Event called when Dashboard Widgets are to be registered
     *
     * @api
     */
    public function onDashboardWidgetRegister() {}
    
    /**
     * Event called when Sidebard (Theme) Widgets are to be registered
     *
     * @api
     */
    public function onWidgetRegister() {}    
     
    /**
     * Event called to build a custom menu
     * 
     * @return MenuEntry|string|bool Returns a MenuEntry, a string for a hook, or `false` if invalid
     *
     * @api
     */
    public function onBuildMenu()
    {
        return false;
    }
    
    /**
     * Event called to retrieve the text to display after the plugin description, if any
     *
     * @return string String to display
     * @api
     */
    public function onAfterPluginText()
    {
        return '';
    }
    
    /**
     * Event triggered when a public facing side of the plugin is ready for initialization
     *
     * This is a plugin-wide event. For page (screen) specific events, use onAdminLoad()
     *
     * @see onAdminLoad()
     * @api
     */
    public function onAdminInit() {}
    
    /**
     * Event called when the admin/Dashboard starts to load
     *
     * @param object|null The current screen being displayed
     * @see _onAdminLoad()
     * @api
     */
    public function onAdminLoad($current_screen) {}
    
    /**
     * Event called when the plugin is ready to load scripts for the admin/Dashboard
     *
     * @api
     */
    public function onAdminScripts() {}
    
    /**
     * Event called when the plugin is ready to load stylesheets for the admin/Dashboard
     *
     * @api
     */
    public function onAdminStyles() {}
    
    /**
     * Handle an Admin AJAX request
     *
     * This will process an AJAX call for this plugin. The 'ajaxResponse()' function
     * *MUST* be used to return any data, otherwise a 'No Event Response' error will be
     * returned to the AJAX caller.
     *
     * It will verify the NONCE prior to triggering this event. If it fails this
     * verification, an error will be returned to the AJAX caller
     *
     * Example:
     * <code>
     * function getAjax(ajaxFunc, ajaxData) {
     *     var resp = false;
     * 
     *     $.ajax({
     *         type     : 'POST',
     *         dataType : 'json',
     *         url      : my_plugin_name_ajax.url,
     *         timeout  : 5000,
     *         async    : false,
     *         data     : { action: my_plugin_name_ajax.action, func: ajaxFunc, data: ajaxData, _ajax_nonce: my_plugin_name_ajax.nonce },
     *         success  : function(ajaxResp) {
     *             if (ajaxResp.nonce == my_plugin_name_ajax.nonceresponse &amp;&amp; ajaxResp.stat == 'ok')
     *                 resp = ajaxResp.data;
     *         }
     *     });
     * 
     *     return resp;
     * }
     * </code>
     *
     * Wherein the plugin code, the onAjaxRequest() event is:
     * <code>
     * function onAjaxRequest( $func, $data ) {
     *    if ( $func == 'hello' ) {
     *        $this->ajaxResponse('Hello World!');
     *    } else {
     *        $this->ajaxResponse('I don\'t know what to do!', true);
     *    }
     * }
     * </code>
     *
     * @param string $action Action to take according to AJAX caller
     * @param mixed $data Data sent by the AJAX caller (WARNING: Data is unsanitzed)
     * @see ajaxResponse()
     * @api
     */
    public function onAjaxRequest($action, $data) {}

    /**
     * Event triggered when a public facing side of the plugin is ready for initialization
     *
     * @api
     */
    public function onPublicInit() {}
    
    /**
     * Event triggered when queueing scripts on the public facing side
     *
     * @api
     */
    public function onPublicScripts() {}
    
    /**
     * Event triggered when queueing stylesheets on the public facing side
     *
     * @api
     */
    public function onPublicStyles() {}
    
    /**
     * Event triggered when the footer is about to be displayed on the public facing side
     *
     * @api
     */
    public function onPublicFooter() {}    
}


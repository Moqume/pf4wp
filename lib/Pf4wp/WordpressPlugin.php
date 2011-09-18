<?php

/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp;

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
 * @version 0.0.1
 * @package Pf4wp
 */
class WordpressPlugin
{
    const LOCALIZATION_DIR = 'resources/l10n/';
    const VIEWS_DIR        = 'resources/views/';
    const VIEWS_CACHE_DIR  = 'store/cache/';
    
    const BEFORE_MENU_CALLBACK_SUFFIX = 'Before';
    
    private static $instances = array();            // Instance container
    private $registered = false;                    // Whether the plugin has been registered with WordPress
    private $plugin_file = '';                      // Main (master) filename of the plugin, as loaded by WordPress
    private $name = '';                             // Working name of the plugin (used for options, slugs, etc.)
    private $menu = false;                          // The menu attached to the plugin, if any
    private $internal_options;                      // An object handling the internal options for the plugin
    private $default_internal_options = array(
        'version' => '0.0',             // The version of the plugin, to track upgrade events
        'delayed_notices' => array(),   // Array containing notices that aren't displayed until possible to show them 
    );
    
    /**
     * The template engine object (EngineInterface)
     */
    public $template;
    
    /**
     * The options object for the plugin (Options)
     */
    public $options;
    
    /**
     * The default options for the plugin, if any
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
            
        // Load locales
        $locale = get_locale();
        if ( !empty($locale) ) {
            $mofile = $this->getPluginDir() . static::LOCALIZATION_DIR . $this->name . '-' . $locale . '.mo';	
            if ( @file_exists($mofile) && @is_readable($mofile) )
                load_textdomain($this->name, $mofile);
        }       
        
        // Register Uninstall hook
        register_uninstall_hook(plugin_basename($plugin_file), get_class($this) . '::_onUninstall');
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
            self::$instances[$class]->register_actions();

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
     * My\Plugin::register(__FILE__); // <-- Register plugin with WordPress here
     * </code>
     *
     * @param string $plugin_file The filename of the plugin's main file
     * @throws \Exception if no plugin filename was specified
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
     * @see construct()
     */
    public function register_actions()
    {
        if ($this->registered || empty($this->plugin_file))
            return;
            
        // Check for upgrade - done before any other events, to allow user-defined upgrades, etc.
        $current_version = PluginInfo::getInfo(false, plugin_basename($this->plugin_file), 'Version');
       
        if (!empty($current_version) && ($previous_version = $this->internal_options->version) != $current_version) {
            $this->internal_options->version = $current_version;
            
            $this->clearCache(); // May be called twice due to onActication(), but is here to prevent any potential issues if not called
            
            $this->onUpgrade($previous_version, $current_version);
        }
        
        // Template Engine (currently Twig)
        $views_dir = $this->getPluginDir() . static::VIEWS_DIR;
        
        if (@is_dir($views_dir) && @is_readable($views_dir)) {
            $options = array();
            
            if (defined('WP_DEBUG') && WP_DEBUG)
                $options = array_merge($options, array('debug' => true));
            
            if (($cache = StoragePath::validate($this->getPluginDir() . static::VIEWS_CACHE_DIR)) !== false)
                $options = array_merge($options, array('cache' => $cache));
            
            $this->template = new TwigEngine($views_dir, $options);
        } else {
            // Provide a safe fallback
            $this->template = new NullEngine();
        }            
    
        /* A note on the use of "$this" versus the often seen "&$this": In PHP5 a copy of the object is 
         * only returned when using "clone". Also, for other use of references, the Zend Engine employs 
         * a "copy-on-write" logic, meaning that variables will be referenced instead of copied until 
         * it's actually written to. Do not circumvent Zend's optimizations!
         */
        
        // (De)activation events
        register_activation_hook(plugin_basename($this->plugin_file), array($this, '_onActivation'));
        register_deactivation_hook(plugin_basename($this->plugin_file), array($this, 'onDeactivation'));
        
        // Internal and Admin events
        add_action('admin_menu', array($this, '_onAdminRegister'));
        add_action('wp_dashboard_setup', array($this, 'onDashboardWidgetRegister'));
        add_action('widgets_init', array($this, 'onWidgetRegister'));
        add_action('admin_notices',	array($this, '_onAdminNotices'));
        
		// Plugin listing events
        add_action('after_plugin_row_' . plugin_basename($this->plugin_file), array($this, '_onAfterPluginText'));
        add_filter('plugin_action_links_' . plugin_basename($this->plugin_file), array($this, '_onPluginActionLinks'), 10, 1);
        
        // Public events
        add_action('parse_request',	array($this, '_onPublicInit'));
        
        // AJAX events
        add_action('wp_ajax_' . $this->name, array($this, '_onAjaxCall'));
        
        // Done!
        $this->registered = true;
    }
    
    /**
     * Returns the plugin working name (used to access low-level functions)
     *
     * @return string Plugin working name
     */
    public function getName()
    {
        return $this->name;
    }
        
    /**
     * Returns the plugin directory
     *
     * @return string Plugin directory (always with trailing slash)
     */
    public function getPluginDir()
    {		
        return trailingslashit(dirname($this->plugin_file));
    }

    /**
     * Get the plugin URL
     *
     * @param bool $full If set to true, the full URL (including filename) is returned.
     * @return string Plugin URL
     */
    public function getPluginUrl($full = false)
    {		
        if ($full)
            return WP_PLUGIN_URL . '/' . plugin_basename($this->plugin_file);
        
        // Default action
        return trailingslashit(WP_PLUGIN_URL . '/' . $this->name);
    }
    
    /**
     * Get the URL of the main menu entry
     *
     * @return string|bool URL, or `false` if invalid.
     */
    public function getParentMenuUrl()
    {
        if ($this->menu instanceof StandardMenu)
            return $this->menu->getParentUrl();
        
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
     */
    public function ajaxResponse($data, $is_error = false) {       
        $out = array();
        $out['stat']  = ($is_error) ? 'fail' : 'ok';
        $out['data']  = $data;
        $out['nonce'] = wp_create_nonce($this->name . '-ajax-response');
        
        die (json_encode($out));
    }
    
    /**
     * Attaches common Admin events to menu hooks
     * 
     * @see _onAdminRegister()
     * @param string Hook provided by WordPress for the menu item
     */    
    private function attachAdminLoadHooks($hook) {
        if (empty($hook))
            return;
            
        add_action('load-' . $hook,                array($this, '_onAdminLoad'));
        add_action('admin_print_scripts-' . $hook, array($this, '_onAdminScripts'));
        add_action('admin_print_styles-' . $hook,  array($this, 'onAdminStyles'));
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
     * Clears (purges) any managed caches
     */
    public function clearCache()
    {
        StoragePath::delete($this->getPluginDir() . static::VIEWS_CACHE_DIR);
    }
    
    /**
     * Adds a delayed notice to the queue
     *
     * This is particularly useful for displaying notices after an onActivate() or
     * onUpgrade() event, as these are triggered inside a sandbox thus preventing 
     * anything from being displayed.
     *
     * @param string $message Message to display to the end user
     * @param bool $is_error Optional parameter to indicate the message is an error message
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
     */
    public function clearDelayedNotices()
    {
        $this->internal_options->delayed_notices = array();
    }
    
    /*---------- Private events (the scope is public, due to external calling). ----------*/
    
    /**
     * Event called when the plugin is activated
     *
     * @see onActivation()
     */
    final public function _onActivation()
    {
        if (!is_admin())
            return;
        
        // Clear managed cache
        $this->clearCache();
        
        $this->onActivation();
    }
    
    /**
     * Static function called when the plugin is uninstalled
     *
     * Note: Remember to use the full namespace when calling this function!
     *
     * @see onUninstall()
     */
    final public static function _onUninstall()
    {
        $this_instance = self::instance('', false);

        // Clear the managed cache
        $this_instance->clearCache();

        // Delete our options from the WP database
        $this_instance->options->delete();
        $this_instance->internal_options->delete();
        
        $this_instance->onUninstall();
    }
    
     
    /**
     * Register the plugin on the administrative backend (Dashboard) - Stage 1
     *
     * Calls delayed onActivation() and adds hooks for menu entries 
     * returned by onBuildMenu(), if any.
     *
     * @see onActivation(), onAdminInit(), onBuildMenu()
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

            // Add additional hooks for menu entries
            $menus = $this->menu->getMenus();
            if ( is_array($menus) ) {
                foreach ($menus as $menu_entry) {
                    if ($menu_entry instanceof MenuEntry)
                        $this->attachAdminLoadHooks($menu_entry->getHook());
                }
            }
        } else if (is_string($result)) {
            $this->attachAdminLoadHooks($result);
        }
    }
    
    /**
     * Displays a short message underneith the plugin description
     *
     * @see onAfterPluginText()
     */
    final public function _onAfterPluginText()
    {
        if (!is_admin())
            return;
        
        $text = $this->onAfterPluginText();
        
        if ( !empty($text) )
            printf(
                '<tr class="active"><th>&nbsp;</th><td colspan="2"><div class="plugin-description" style="padding: 0 0 5px;"><em>%s</em></div></td></tr>',
                $text
            );
    }
    
    /**
     * Adds a 'Settings' link to the plugin actions as an added convenience
     *
     * @param mixed Array containing existing plugin actions
     */
    final public function _onPluginActionLinks($actions)
    {
        if (!is_admin())
            return;
        
        $url = $this->getParentMenuUrl();
        
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
     * This will render the contextual help, based on the selected menu. If the menu callback
     * has a matching 'Before' method, it will be called too, containing the current screen
     * as an argument.
     *
     * For example, if the menu callback is `onRenderMenu()`, and a method called 
     * `onRenderMenuBefore()` exists, then it will be called prior to `onRenderMenu()`.
     *
     * @see onAdminLoad()
     */
    final public function _onAdminLoad()
    {
        if (!is_admin())
            return;
        
        // Set new exception handler
        set_exception_handler(array($this, '_onStage2Exception'));

        $current_screen = get_current_screen();
        
        if ($this->menu instanceof StandardMenu && ($active_menu = $this->menu->getActiveMenu()) !== false) {
            $context_help = $active_menu->context_help;

            // Set contextual help - this is not handled by the menu directly
            if (!empty($context_help))
                add_contextual_help($current_screen, $context_help);            
            
            // Test if there's a method to call before the callback (this differs from MenuEntry->before_callback!)
            $before_callback = false;
                
            if (is_array($active_menu->callback) && is_object($active_menu->callback[0])) {
                $before_callback_method = $active_menu->callback[1] . static::BEFORE_MENU_CALLBACK_SUFFIX;
                
                if (method_exists($active_menu->callback[0], $before_callback_method))
                    $before_callback = array($active_menu->callback[0], $before_callback_method);
            } else if (is_string($active_menu->callback)) {
                $before_callback_function = $active_menu->callback . static::BEFORE_MENU_CALLBACK_SUFFIX;
                
                if (function_exists($before_callback_function))
                    $before_callback = $before_callback_function;
            }
            
            if ($before_callback)
                call_user_func($before_callback, $current_screen);
        }

        $this->onAdminLoad($current_screen);
    }
        
    /**
     * Loads any internal scripts before passing it to the public event
     *
     * This provices AJAX nonce for calls and replies for improved security
     *
     * @see onAdminScripts()
     */
    final public function _onAdminScripts()
    {
        echo (
            '<script type="text/javascript">' . PHP_EOL .
            '//<![CDATA[' . PHP_EOL . 
            'var ajaxaction = \'' . $this->name . '\';' .
            'var ajaxnonce = \'' . wp_create_nonce($this->name . '-ajax-call') . '\';' .
            'var ajaxnonceresponse = \'' . wp_create_nonce($this->name . '-ajax-response') . '\';' . PHP_EOL .
            '//]]>' . PHP_EOL .
            '</script>' . PHP_EOL
        );        
        
        $this->onAdminScripts();
    }
    
    /**
     * Event called to display Dashboard notices in the notification queue
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
     */
    final public function _onAjaxCall()
    {
        check_ajax_referer($this->name . '-ajax-call'); // Dies if the check fails

        header('Content-type: application/json');

        if ( $_SERVER['REQUEST_METHOD'] != 'POST' ||
             !isset($_POST['func']) ||
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
     */
    final public function _onPublicInit()
    {
        if (is_admin()) 
            return;

        add_action('wp_print_scripts',  array($this, 'onPublicScripts'));
        add_action('wp_print_styles',   array($this, 'onPublicStyles'));
        add_action('wp_footer',         array($this, 'onPublicFooter'));

        $this->onPublicInit();
    }
    
    /**
     * Handles a Stage 2 exception
     *
     * @param \Exception $exception Exception object
     * @param int $count Count of Exception object
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
     */
    public function onActivation() {}
    
    /**
     * Event called when the plugin is deactivated
     */
    public function onDeactivation() {}
    
    /**
     * Event called when the plugin is uninstalled
     */
    public function onUninstall() {}
    
    /**
     * Event called when the plugin is upgraded
     *
     * If the previous version is 0.0, then this is a new installation.
     *
     * @param string $previous_version Previous version
     * @param string $current_version Current version
     */
    public function onUpgrade($previous_version, $current_version) {}
    
    /**
     * Event called when Dashboard Widgets are to be registered
     */
    public function onDashboardWidgetRegister() {}
    
    /**
     * Event called when Sidebard (Theme) Widgets are to be registered
     */
    public function onWidgetRegister() {}    
     
    /**
     * Event called to build a custom menu
     * 
     * @return MenuEntry|string|bool Returns a MenuEntry, a string for a hook, or `false` if invalid
     */
    public function onBuildMenu()
    {
        return false;
    }
    
    /**
     * Event called to retrieve the text to display after the plugin description, if any
     *
     * @return string String to display
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
     */
    public function onAdminInit() {}
    
    /**
     * Event called when the admin/Dashboard starts to load
     *
     * @param object|null The current screen being displayed
     * @see _onAdminLoad()
     */
    public function onAdminLoad($current_screen) {}
    
    /**
     * Event called when the plugin is ready to load scripts for the admin/Dashboard
     */
    public function onAdminScripts() {}
    
    /**
     * Event called when the plugin is ready to load stylesheets for the admin/Dashboard
     */
    public function onAdminStyles() {}
    
    /**
     * Handle an AJAX request
     *
     * This will process an AJAX call for this plugin. The 'ajaxResponse()' function
     * must be used to return any data, otherwise a 'No Event Response' error will be
     * returned to the AJAX caller.
     *
     * It will verify the NONCE prior to triggering this event. If it fails this
     * verification, an error will be returned to the AJAX caller
     *
     * Example:
     * <code>
     * $.ajax({
     *   type : 'POST',
     *   dataType : 'json',
     *   url : ajaxurl,
     *   timeout : 30000,
     *   data : {
     *     action: ajaxaction,
     *     func: 'say',
     *     data: 'Hello World',
     *     _ajax_nonce: ajaxnonce
     *    },
     *    success : function(resp){
     *      if (resp.nonce != ajaxnonceresponse) {
     *        alert ('The Ajax response could not be validated');
     *        return;
     *     }
     * 	
     *     if (resp.stat == 'fail') {
     *       alert ('There was an error: ' + resp.data);
     *     } else {
     *       alert (resp.data);
     *     }
     *   },
     *   error : function(err){
     *     alert ('There was an error obtaining the Ajax response');
     *   }
     * });
     * </code>
     *
     * Wherein the plugin code, the onAjaxRequest() event is:
     * <code>
     * function onAjaxRequest( $func, $data ) {
     *  if ( $func == 'say' ) {
     *    $this->ajaxResponse($data);
     *  } else {
     *    $this->ajaxResponse('I don\'t know what to do!', true);
     *  }
     * }
     * </code>
     *
     * @param string $action Action to take according to AJAX caller
     * @param mixed $data Data sent by the AJAX caller (WARNING: Data is unsanitzed)
     * @see ajaxResponse()
     */
    public function onAjaxRequest($action, $data) {}
    
    /**
     * Event triggered when a public facing side of the plugin is ready for initialization
     */
    public function onPublicInit() {}
    
    /**
     * Event triggered when queueing scripts on the public facing side
     */
    public function onPublicScripts() {}
    
    /**
     * Event triggered when queueing stylesheets on the public facing side
     */
    public function onPublicStyles() {}
    
    /**
     * Event triggered when the footer is about to be displayed on the public facing side
     */
    public function onPublicFooter() {}    
}

 

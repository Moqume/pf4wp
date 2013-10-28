<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp;

use Pf4wp\Common\Helpers;
use Pf4wp\Common\InternalImages;
use Pf4wp\Arrays\GlobalArrayObject;
use Pf4wp\Info\PluginInfo;
use Pf4wp\Storage\StoragePath;
use Pf4wp\Notification\AdminNotice;
use Pf4wp\Options\WordpressOptions;
use Pf4wp\Menu\StandardMenu;
use Pf4wp\Menu\MenuEntry;
use Pf4wp\Widgets\Widget;
use Pf4wp\Template\NullEngine;


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
 * @version 1.0.14
 * @package Pf4wp
 * @api
 */
class WordpressPlugin
{
    const RESOURCES_DIR    = 'resources/';
    const LOCALIZATION_DIR = 'resources/l10n/';
    const VIEWS_DIR        = 'resources/views/';
    const VIEWS_CACHE_DIR  = 'store/cache/views/';

    // Available log levels
    const LOG_DISABLED = 0;
    const LOG_ERROR    = 1;
    const LOG_WARNING  = 2;
    const LOG_DEBUG    = 3;
    const LOG_PROFILE  = 4;

    /** Instance container
     * @internal
     */
    private static $instances = array();

    /** Whether the shutdown function has been registered
     * @internal
     */
    private static $registered_shutdown = false;

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

    /** Holds data of already called functions, to prevent them being called again (if only allowed once)
     * @internal
     */
    private $was_called = array();

    /** Holds any JS code to be printed between script tags
     * @since 1.1
     * @internal
     */
    private $js_code = array();

    /**
     * Holds private globals
     * @since 1.1
     * @internal
     */
    protected $globals;

    /** An object handling the internal options for the plugin
     * @internal
     */
    private $internal_options;

    /** Array containing the default internal options
     * @internal
     */
    private $default_internal_options = array(
        'version'               => '0.0',           // The version of the plugin, to track upgrade events
        'delayed_notices'       => array(),         // Array containing notices that aren't displayed until possible to show them
        'registered_uninstall'  => false,           // Flag to indicate a registration hook has been registered - @since 1.0.14
        'remote_js_console'     => false,           // Indicates if remote JS console is disabled, or the UUID if enabled
    );

    /**
     * The template engine object (EngineInterface)
     * @see Template\EngineInterface
     * @api
     */
    public $template;

    /**
     * The template engine to use
     * @api
     */
    protected $template_engine = 'Pf4wp\Template\TwigEngine';

    /**
     * The options to pass to the template engine object upon creation
     * @since 1.0.9
     * @api
     */
    protected $template_options = array();

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
     * If the template engine should be intiialised during an AJAX call, this variable is set to `true`
     * @api
     */
    public $ajax_uses_template = false;

    /**
     * If set to `true`, public AJAX calls will be checked against a provided nonce (default)
     * @since 1.0.13
     * @api
     */
    public $verify_public_ajax = true;

    /**
     * The short name of the plugin
     * @since 1.0.7
     * @api
     */
    public $short_name;

    /**
     * If the plugin should be fully initialized in the Network Admin, set this to `true`
     * @since 1.0.7
     * @api
     */
    public $register_admin_mode = false;

    /**
     * The default options for the plugin, if any
     * @api
     */
    protected $default_options = array();

    /**
     * Flag to check if a locale has been loaded
     * @since 1.0.16
     * @api
     */
    protected $locale_loaded = false;

    /**
     * Default log level
     *
     * If set to zero, no log messages will be recorded (not recommended)
     *
     * @since 1.1
     * @api
     */
    public $log_level = self::LOG_ERROR;

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

        // Give it a short name if not specified
        if (!$this->short_name) {
            $a_name  = explode('\\', strtolower(get_called_class()));
            $a_first = true;

            foreach ($a_name as &$a_name_sec) {
                if (!$a_first) {
                    $this->short_name .= $a_name_sec[0];
                } else {
                    $this->short_name = $a_name_sec . '_';
                }

                $a_first = false;
            }
        }

        // Globals
        $this->globals = new GlobalArrayObject();

        // Register the shutdown function, if it hasn't been yet
        if (!self::$registered_shutdown) {
            register_shutdown_function(array($this, '_onShutdown'));

            self::$registered_shutdown = true;
        }

        // Options handlers
        $this->options = new WordpressOptions($this->name, $this->default_options);
        $this->internal_options = new WordpressOptions('_' . $this->name, $this->default_internal_options); // Internal

        // pre-Initialize the template engine to a `null` engine
        $this->template = new NullEngine();

        if (is_admin()) {
            // Register (de)activation hooks
            register_activation_hook(plugin_basename($this->plugin_file), array($this, '_onActivation'));
            register_deactivation_hook(plugin_basename($this->plugin_file), array($this, '_onDeactivation'));

            // Register uninstall hook (@since 1.0.14: added check if already registered, as WP does not do this and only needs to be done once)
            if (!$this->internal_options->registered_uninstall) {
                register_uninstall_hook(plugin_basename($plugin_file), get_class($this) . '::_onUninstall');
                $this->internal_options->registered_uninstall = true;
            }

            // Register an action for when a new blog is created on multisites
            if (function_exists('is_multisite') && is_multisite())
                add_action('wpmu_new_blog', array($this, '_onNewBlog'), 10, 1);

            // Widgets get initialized individually (and before WP `init` action) - PITA!
            if ( !Helpers::isNetworkAdminMode() )
                add_action('widgets_init', array($this, 'onWidgetRegister'), 10, 0);
        }
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        // Restore the 2nd stage exception handler
        restore_exception_handler();

        // Remove ourselves from the loaded instances
        unset(self::$instances[get_class($this)]);
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

        // Load locale
        $locale = get_locale();
        if ( !empty($locale) ) {
            $mofile_locations = array(
                $this->getPluginDir() . static::LOCALIZATION_DIR . $locale . '.mo', // Plugin local l10n directory
                WP_LANG_DIR . '/' . $this->name . '-' . get_locale() . '.mo',       // Global l10n directory
            );

            foreach ($mofile_locations as $mofile_location) {
                if ( @is_file($mofile_location) && @is_readable($mofile_location) ) {
                    load_textdomain($this->name, $mofile_location);
                    $this->locale_loaded = true;
                    break;
                }
            }
        }

        // Plugin events (also in Multisite)
        if (!Helpers::doingAjax() && is_admin())
            add_action('after_plugin_row_' . plugin_basename($this->plugin_file), array($this, '_onAfterPluginText'), 10, 0);

        // Do not register any actions after this if we're in Network Admin mode (unless override with register_admin_mode)
        if (Helpers::isNetworkAdminMode() && !$this->register_admin_mode) {
            $this->registered = true;
            return;
        }

        // Template Engine initialization
        $use_template_engine = (Helpers::doingAjax()) ? $this->ajax_uses_template : true;
        $views_dir           = $this->getPluginDir() . static::VIEWS_DIR;
        $template_engine     = false;

        if ($use_template_engine) {
            if (class_exists($this->template_engine)) {
                $rc = new \ReflectionClass($this->template_engine);
                if ($rc->implementsInterface('Pf4wp\Template\EngineInterface'))
                    $template_engine = $this->template_engine;
            }

            if ($template_engine && @is_dir($views_dir) && @is_readable($views_dir)) {
                $options = array('_textdomain' => $this->name);

                if (defined('WP_DEBUG') && WP_DEBUG)
                    $options['debug'] = true;

                if (($cache = StoragePath::validate($this->getPluginDir() . static::VIEWS_CACHE_DIR)) !== false)
                    $options['cache'] = $cache;

                // Replace these options with those specified by the plugin developer, if any
                $options = array_replace($options, $this->template_options);

                $this->template = new $template_engine($views_dir, $options);
            }
        }

        // Set JS console define, if ours
        if (!defined('PF4WP_JS_CONSOLE') && $this->internal_options->remote_js_console)
            define('PF4WP_JS_CONSOLE', $this->internal_options->remote_js_console);

        if (!Helpers::doingAjax() && is_admin()) {
            // Internal and Admin events
            add_action('admin_menu', array($this, '_onAdminRegister'), 10, 0);
            add_action('wp_dashboard_setup', array($this, 'onDashboardWidgetRegister'), 10, 0);
            add_action('admin_notices',	array($this, '_onAdminNotices'), 10, 0);

            // Plugin events
            add_filter('plugin_action_links_' . plugin_basename($this->plugin_file), array($this, '_onPluginActionLinks'), 10, 1);
        }

        // Public events
        add_action('parse_request',	array($this, '_onPublicInit'), 10, 0);

        // AJAX events
        add_action('wp_ajax_' . $this->name, array($this, '_onAjaxCall'), 10, 0);
        if ($this->public_ajax)
            add_action('wp_ajax_nopriv_' . $this->name, array($this, '_onPublicAjaxCall'), 10, 0);

        // Register a final action when WP has been loaded
        add_action('wp_loaded', array($this, '_onWpLoaded'), 10, 0);

        $this->onRegisterActions();

        // Check if there are any on-demand admin filters requested
        if (is_admin()) {
            $filters = array();
            if (isset($_REQUEST['filter']))
                $filters = explode(',', $_REQUEST['filter']);

            // And check the referer arguments as well if this is a POST request
            if (!empty($_POST) && isset($_SERVER['HTTP_REFERER'])) {
                $referer_args = explode('&', ltrim(strstr($_SERVER['HTTP_REFERER'], '?'), '?'));
                foreach ($referer_args as $referer_arg)
                    if (!empty($referer_arg) && strpos($referer_arg, '=') !== false) {
                        list($arg_name, $arg_value) = explode('=', $referer_arg);
                        if ($arg_name == 'filter') {
                            $filters = array_replace($filters, explode(',', $arg_value));
                            break;
                        }
                    }
            }

            // Remove any possible duplicates from filters
            $filters = array_unique($filters);

            // Fire filter events
            foreach ($filters as $filter) $this->onFilter($filter);
        }

        // Done!
        $this->registered = true;
    }

    /**
     * Translates a string using the plugin's localized locale
     *
     * @param string $string String to translate
     * @return string Translated string
     * @since 1.0.7
     * @api
     */
    public function __t($string) {
        return __($string, $this->name);
    }

    /**
     * Returns the plugin working name (used to access low-level functions)
     *
     * @return string Plugin working name
     * @api
     */
    final public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the plugin directory
     *
     * @return string Plugin directory (always with trailing slash)
     * @api
     */
    final public function getPluginDir()
    {
        return trailingslashit(dirname($this->plugin_file));
    }

    /**
     * Returns the plugin Base Name (as used by many WordPress functions/methods
     *
     * @return string Plugin base name
     * @api
     */
    final public function getPluginBaseName()
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
    final public function getPluginUrl($full = false)
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
        $version = $this->getVersion();
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
        return PluginInfo::getDirectPluginInfo($this->plugin_file, 'Name');
    }

    /**
     * Returns the version for the plugin
     *
     * @return string Version
     * @api
     */
    public function getVersion()
    {
        return PluginInfo::getDirectPluginInfo($this->plugin_file, 'Version');
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
     * Sets the log level
     *
     * A level of LOG_DISABLED (0) disables logging. It is recommended to
     * have the minimum set at LOG_ERROR (1), so to record fatal errors and
     * uncaught exceptions.
     *
     * It is permissible to set $log_level directly
     *
     * @param int $level The new log level
     * @return int The previous log level
     * @since 1.1
     * @api
     */
    public function setLogLevel($level)
    {
        $previous = $this->log_level;

        $this->log_level = ($level < self::LOG_DISABLED) ? self::LOG_DISABLED : $level;

        return $previous;
    }

    /**
     * Returns the current log level
     *
     * Note: It is permissible to set $log_level directly
     *
     * @return int
     * @since 1.1
     * @api
     */
    public function getLogLevel()
    {
        return $this->log_level;
    }

    /**
     * Returns the location of the log file
     *
     * @return string
     * @since 1.1
     * @api
     */
    public function getLogFile()
    {
        $fn = preg_replace('#[^\w\s\d\-_~,;:\[\]\(\]]|[\.]{2,}#', '', $this->getName());

        return $this->getPluginDir() . $fn . '.log';
    }

    /**
     * Logs an entry, depending on the log level
     *
     * @param string $entry The log entry to record
     * @param int $level The log level of the entry (LOG_ERROR by default)
     * @return int Returns 1 if the entry was logged successfully, 0 if unsuccessful or -1 if log level is higher than specified log level
     * @since 1.1
     * @api
     */
    final public function log($entry, $level = self::LOG_ERROR)
    {
        // Do not log this entry if the level is higher than the specified log level
        if ($level > $this->log_level)
            return -1;

        // Set a marker, so we know what type of log level/entry this is
        switch ($level) {
            case self::LOG_ERROR:   $log_marker = 'E'; break;
            case self::LOG_WARNING: $log_marker = 'W'; break;
            case self::LOG_DEBUG:   $log_marker = 'D'; break;
            case self::LOG_PROFILE: $log_marker = 'P'; break;
            default:                $log_marker = '-'; break;
        }

        // Log it
        $result = (@file_put_contents(
            $this->getLogFile(),
            sprintf(
                "%s %s \"%s v%s\" %s\n",
                date(c),
                $log_marker,
                addslashes($this->getDisplayName()),
                $this->getVersion(),
                $entry
            ),
            FILE_APPEND
        ) > 0);

        return ($result) ? 1 : 0;
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

    /**
     * Provides debug information for displaying
     *
     * The information is in the array as "Display Name" => "Display Value"
     *
     * @since 1.0.10
     * @api
     */
    public function getDebugInfo()
    {
        global $wp_version, $wpdb, $wp_object_cache;

        $mem_peak        = (function_exists('memory_get_peak_usage')) ? memory_get_peak_usage() / 1048576 : 0;
        $mem_usage       = (function_exists('memory_get_usage')) ? memory_get_usage() / 1048576 : 0;
        $mem_max         = (int) @ini_get('memory_limit');
        $mem_max_wp      = (defined('WP_MEMORY_LIMIT')) ? WP_MEMORY_LIMIT : 0;
        $upload_max      = (int) @ini_get('upload_max_filesize');
        $upload_max_post = (int) @ini_get('post_max_size');
        $upload_max_wp   = (function_exists('wp_max_upload_size')) ? wp_max_upload_size() / 1048576 : 0;
        $current_theme   = (function_exists('wp_get_theme')) ? wp_get_theme() : get_current_theme(); // WP 3.4

        // Determine Object Cache
        $obj_cache_class      = get_class($wp_object_cache);
        $obj_cache_class_vars = get_class_vars($obj_cache_class);
        $obj_cache            = sprintf('Unknown (%s)', $obj_cache_class);

        if ($obj_cache_class == 'APC_Object_Cache') {
            $obj_cache = 'APC Object Cache';
        } else if ($obj_cache_class == 'W3_ObjectCacheBridge') {
            $obj_cache = 'W3 Total Cache';
        } else if (array_key_exists('mc', $obj_cache_class_vars)) {
            $obj_cache = 'Memcache Object Cache';
        } else if (array_key_exists('global_groups', $obj_cache_class_vars)) {
            $obj_cache = 'WordPress Default';
        }

        // Sort PHP extensions alphabetically
        $php_extensions = get_loaded_extensions();
        usort($php_extensions, 'strcasecmp');

        // Fill active plugins array
        $active_plugins  = array();
        foreach (\Pf4wp\Info\PluginInfo::getInfo(true) as $plugin)
            $active_plugins[] = sprintf("'%s' by %s, version %s", $plugin['Name'], $plugin['Author'], $plugin['Version']);

        $result = array(
            'Generated On'              => gmdate('D, d M Y H:i:s') . ' GMT',
            $this->getDisplayName() . ' Version' => $this->getVersion(),

            /* Memory/Uploads Limits */
            'Memory'                    => null,
            'Memory Usage'              => sprintf('%.2f Mbytes peak, %.2f Mbytes current', $mem_peak, $mem_usage),
            'Memory Limits'             => sprintf('WordPress: %d Mbytes - PHP: %d Mbytes', $mem_max_wp, $mem_max),
            'Upload Limits'             => sprintf('WordPress: %d Mbytes - PHP: %d Mbytes filesize (%d Mbytes POST size)', $upload_max_wp, $upload_max, $upload_max_post),

            /* WordPress */
            'WordPress'                 => null,
            'WordPress Version'         => $wp_version,
            'Active Wordpress Plugins'  => implode('; ', $active_plugins),
            'Active WordPress Theme'    => $current_theme,
            'Locale'                    => sprintf('%s (%s)', get_locale(), ($this->locale_loaded) ? 'Loaded' : 'Not Loaded'),
            'Debug Mode'                => (defined('WP_DEBUG') && WP_DEBUG) ? 'Yes' : 'No',
            'Object Cache'              => $obj_cache,
            'Home URL'                  => home_url(),
            'Site URL'                  => site_url(),

            /* PHP */
            'PHP'                       => null,
            'PHP Version'               => PHP_VERSION,
            'Available PHP Extensions'  => implode(', ', $php_extensions),

            /* Server / Client Environment */
            'Server/Client Environment' => null,
            'Browser'                   => $_SERVER['HTTP_USER_AGENT'],
            'Server Software'           => $_SERVER['SERVER_SOFTWARE'],
            'Server OS'                 => php_uname(),
            'Server Load'               => (function_exists('sys_getloadavg')) ? @implode(', ', sys_getloadavg()) : 'Unavailable',
            'Database Version'          => $wpdb->get_var('SELECT VERSION()'),

            /* pf4wp */
            'pf4wp'                     => null,
            'pf4wp Version'             => PF4WP_VERSION,
            'pf4wp APC Enabled'         => (defined('PF4WP_APC') && PF4WP_APC === true) ? 'Yes' : 'No',
            'Remote JS Console Enabled' => ($uuid = $this->getRemoteJSConsole()) ? sprintf('Yes - UUID %s (%s by this plugin)', $uuid, ($this->isRemoteJSConsoleOwned() ? 'Owned' : 'Not owned')) : 'No',
            'Template Cache Directory'  => is_writable($this->getPluginDir() . static::VIEWS_CACHE_DIR) ? 'Writeable' : 'Not Writeable',
        );

        if (is_callable(array($this->template, 'getVersion')) && is_callable(array($this->template, 'getEngineName')))
            $result['Template Engine Version'] = $this->template->getEngineName()  . ' ' . $this->template->getVersion();

        return $result;
    }

    /**
     * Adds JS code to a queue to be printed within a single `<script>` tag
     *
     * @param string $js The Javascript, without any script tags
     * @since 1.1
     * @api
     */
    public function addJSQueue($js)
    {
        $this->js_code[] = $js;
    }

    /**
     * Retrieves the current queue of JS code to be printed
     *
     * @return array Array containing the Javascript in the order it was added
     * @since 1.1
     * @api
     */
    public function getJSQueue()
    {
        return $this->js_code;
    }

    /**
     * Enables or disables remote JS console
     *
     * Read more at http://jsconsole.com/remote-debugging.html
     *
     * @since 1.0.18
     * @param bool $enable If set to true, the remote JS console will be enabled, otherwise disabled.
     * @return mixed Returns the UUID if enabled, true if disabled or false if unable to enable (non-owner)
     * @api
     */
    public function setRemoteJSConsole($enable)
    {
        $uuid = $this->getRemoteJSConsole();

        // Check if we own it if enabled
        if ($uuid && !$this->isRemoteJSConsoleOwned()) {
            // Already enabled, but not owned by us
            return false;
        }

        // Enable it
        if ($enable === true) {
            // If not already enabled, do it now
            if (!$uuid) {
                $uuid = $this->internal_options->remote_js_console = Helpers::UUID();
                if (!defined('PF4WP_JS_CONSOLE'))
                    define('PF4WP_JS_CONSOLE', $uuid);
            }

            return $uuid;
        }

        // Disable it
        return (($this->internal_options->remote_js_console = false) === false);

    }

    /**
     * Returns if the remote JS console is enabled
     *
     * @since 1.0.18
     * @return mixed Returns the UUID if enabled, or false if disabled
     * @api
     */
    public function getRemoteJSConsole()
    {
        return (defined('PF4WP_JS_CONSOLE')) ? PF4WP_JS_CONSOLE : $this->internal_options->remote_js_console;
    }

    /**
     * Returns if the remote JS console is owned by this plugin
     *
     * @since 1.0.18
     * @return bool
     * @api
     */
    public function isRemoteJSConsoleOwned()
    {
        return (defined('PF4WP_JS_CONSOLE') && PF4WP_JS_CONSOLE === $this->internal_options->remote_js_console);
    }

    /**
     * Returns the URL to start a JS console session
     *
     * @since 1.0.18
     * @param mixed $html If a string is provided, then this function returns a HTML link with the specified text.
     *   If set to true, the UUID will be used as the link text.
     * @return mixed The URL for the session, or false if not enabled
     * @api
     */
    public function getRemoteJSConsoleURL($html = null)
    {
        $uuid = $this->getRemoteJSConsole();

        if (!$uuid)
            return false;

        $link = sprintf('http://jsconsole.com/?%%3Alisten%%20%s', $uuid);

        // Return as HTML
        if ($html !== null) {
            if (!is_string($html))
                $html = $this->internal_options->remote_js_console;

            return sprintf("<a href=\"%s\" target=\"_blank\">%s</a>", $link, $html);
        }

        // Return just the link
        return $link;

    }

    /*---------- Private Helpers (callbacks have a public scope!) ----------*/

    /**
     * Checks if a function has been called before
     *
     * If not called before, it will set it as called and return false, otherwise returns true
     *
     * @return bool
     * @since 1.0.11
     */
    final protected function wasCalled($calling_function)
    {
        $calling_function = get_called_class() . '\\' . $calling_function;

        // Called before
        if (isset($this->was_called[$calling_function]))
            return true;

        // Not called before
        $this->was_called[$calling_function] = true;
        return false;
    }

    /**
     * Inserts (echoes) the JS queue, and then clears it
     *
     * @since 1.1
     * @internal
     */
    protected function printJSQueue()
    {
        printf("<script type=\"text/javascript\">/* <![CDATA[ */%s/* ]]> */</script>\n", implode("", $this->js_code));

        $this->js_code = array();
    }

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
    private function queueAjaxVars()
    {
        // Standard vars
        $vars = array(
            'url'    => admin_url('admin-ajax.php'),
            'action' => $this->name,
        );

        // nonce vars
        if (is_admin() || is_user_logged_in() || $this->verify_public_ajax) {
            $vars['nonce']         = wp_create_nonce($this->name . '-ajax-call');
            $vars['nonceresponse'] = wp_create_nonce($this->name . '-ajax-response');
        }

        $this->addJSQueue(sprintf("window.%s_ajax=%s;", strtr($this->name, '-', '_'), json_encode($vars)));
    }

    /**
     * Inserts remote JS console script if enabled
     *
     * Note: For security reasons, this will only be inserted if a WordPress user
     * with administration rights is logged in.
     *
     * @since 1.0.18
     * @internal
     */
    private function insertJSConsole()
    {
        // Check if the script has already been inserted
        if (isset($this->globals->pf4wp_js_console_inserted))
            return;

        if ($uuid = $this->getRemoteJSConsole()) {
            // Check if a WordPress user is logged in and has admin rights
            if (current_user_can('manage_options')) {
                printf("<script src=\"http://jsconsole.com/remote.js?%s\"></script>\n", $uuid);

                $this->globals->pf4wp_js_console_inserted = true;
            } else {
                echo "<!-- Warning: Remote JS Console enabled, but current user does not have sufficient privileges. -->\n";

                $this->globals->pf4wp_js_console_inserted = false;
            }
        }
    }

    /**
     * Inserts a Javascript variable to indicate if logging should be enabled
     *
     * @since 1.0.18
     * @internal
     */
    private function queueJSLogFlag()
    {
        if (isset($this->globals->pf4wp_js_log_flag))
            return;

        if ((isset($this->globals->pf4wp_js_console_inserted) && $this->globals->pf4wp_js_console_inserted) ||
            (defined('WP_DEBUG') && WP_DEBUG)) {

            $this->addJSQueue('window.pf4wp_log=true;');

            // Set flag to ensure its only written once
            $this->globals->pf4wp_js_log_flag = true;
        }
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
    final public function _doOnActivation()
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
    final public function _doOnDeactivation()
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
    final public function _doOnUninstall()
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

        // Queue AJAX variables
        $this->queueAjaxVars();

        // Insert remote JS console, if enabled
        $this->insertJSConsole();

        // Queue the JS Log Flag, if enabled
        $this->queueJSLogFlag();

        // Add user-defined Admin JS
        $this->onAdminScripts();

        // Print JS Queue
        $this->printJSQueue();
    }

    /**
     * Event called to display Dashboard notices in the notification queue
     *
     * @internal
     */
    final public function _onAdminNotices()
    {
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
    final public function _onAjaxCall($verify_ajax = true)
    {
        if ($verify_ajax)
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
     * Process a public AJAX call
     *
     * Note: This will not be called if the admin is logged in, which will use _onAjaxCall instead
     *
     * @see onAjaxRequest(), ajaxResponse()
     * @since 1.0.13
     * @internal
     */
    final public function _onPublicAjaxCall()
    {
        $this->_onAjaxCall($this->verify_public_ajax);
    }

    /**
     * Registers public events
     *
     * @see onPublicScripts(), onPublicStyles(), onPublicLoad()
     * @internal
     */
    final public function _onPublicInit()
    {
        if (is_admin() || $this->wasCalled('_onPublicInit'))
            return;

        add_action('wp_print_scripts',  array($this, '_onPublicScripts'));
        add_action('wp_print_styles',   array($this, '_onPublicStyles'));
        add_action('wp_footer',         array($this, '_onPublicFooter'));

        $this->onPublicInit();
    }

    /**
     * Event called when to print public scripts
     *
     * @see onPublicScripts()
     * @internal
     */
    final public function _onPublicScripts()
    {
        if ($this->wasCalled('_onPublicScripts')) return; // Can only be called once!

        // Queue AJAX variables, if enabled on public side
        if ($this->public_ajax)
            $this->queueAjaxVars();

        // Insert remote JS console, if enabled
        $this->insertJSConsole();

        // Queue the JS Log Flag, if enabled
        $this->queueJSLogFlag();

        // Add user-defined public JS scripts
        $this->onPublicScripts();

        // Print the JS queue
        $this->printJSQueue();
    }

    /**
     * Event called when to print public styles
     *
     * @see onPublicStyles()
     * @internal
     */
    final public function _onPublicStyles()
    {
        if ($this->wasCalled('_onPublicStyles')) return; // Can only be called once!

        $this->onPublicStyles();
    }

    /**
     * Event called when ready to print public footer
     *
     * @see onPublicFooter()
     * @internal
     */
    final public function _onPublicFooter()
    {
        if ($this->wasCalled('_onPublicFooter')) return; // Can only be called once!

        $this->onPublicFooter();
    }

    /**
     * Handles a Stage 2 exception
     *
     * @filter pf4wp_error_contactmsg_[short plugin name]
     * @param \Exception $exception Exception object
     * @param int $count Count of Exception object
     * @internal
     */
    final public function _onStage2Exception($exception, $count = 1)
    {
        $abbr = function($class){ return sprintf('<abbr title="%s" style="border-bottom:1px dotted #000;cursor:help;">%s</abbr>', $class, array_pop(explode('\\', $class))); };

        $content = '';

        if ($count == 1) {
            $image_pos  = 'position:relative;bottom:8px;';
            $image_pos .=  (!is_rtl()) ? 'float:left;margin-right:8px;' : 'float:right;margin-left:8px;';

            $content .= '<div style="clear:both"></div><h1>' . InternalImages::getHTML('sys_error', 32, $image_pos) . __('Oops! Something went wrong', $this->name) . ':</h1>';
        }

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

        // Log to a file (@since 1.1)
        $error_logged = ($this->log(
            sprintf(
                '%s %d "%s" %d "%s"',
                get_class($exception),
                $count,
                $exception->getFile(),
                $exception->getLine(),
                addslashes($exception->getMessage())
            ),
            self::LOG_ERROR
        ) === 1);

        if ($exception->getPrevious()) {
            $count++;
            $this->_onStage2Exception($exception->getPrevious(), $count);
        }

        // Add a final message (@since 1.1)
        echo apply_filters(
            'pf4wp_error_contactmsg_' . $this->getName(),
            sprintf(
                __('<p>Please contact the plugin author <a href="%s" target="_blank">%s</a> with the above details%s if this problem persists.</p>', $this->getName()),
                PluginInfo::getInfo(false, $this->getPluginBaseName(), 'AuthorURI'),
                PluginInfo::getInfo(false, $this->getPluginBaseName(), 'Author'),
                ($error_logged) ? ' or the logfile' : ''
            ),
            get_class($exception),
            array(
                'type'    => -1,
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'message' => $exception->getMessage(),
            ),
            $error_logged
        );

        die();
    }

    /**
     * Shutdown callback
     *
     * This callback is used to manage fatal errors, by logging it to a file if possible and
     * providing the user with feedback as to what the error was. This avoids the
     * over-simplistic 500 errors normally generated (provided WP_DEBUG was set to false).
     *
     * NOTE: This will only apply to pf4wp instances. This may de-activate a plugin, leaving
     * only the current instance. The current instance will be referenced for certain functions,
     * such as logging, but may fail depending on where the fatal error is located (thus creating
     * a double fatal error) and so should not be relied upon - this is a best-effort feature.
     *
     * @filter pf4wp_error_contactmsg_[short plugin name]
     * @internal
     * @since 1.1
     */
    final public function _onShutdown()
    {
        $error          = error_get_last();
        $pf4wp_instance = null;

        // Check if we have an error
        if (!is_array($error) || !array_key_exists('file', $error))
            return;

        // Obtain the true plugin base, if possible. Note: plugin_basename() already normalizes slashes
        list($plugin_base_dir) = explode('/', plugin_basename($error['file']));

        // Return if the error wasn't generated by a plugin
        if (empty($plugin_base_dir))
            return;

        // Determine if the error was generated by a PF4WP instance
        foreach (self::$instances as $instance_class => $instance) {
            list($instance_base_dir) = explode('/', $instance->getPluginBaseName());

            if (strcasecmp($instance_base_dir, $plugin_base_dir) === 0) {
                $pf4wp_instance = $instance;
                break;
            }
        }

        // The plugin isn't a PF4WP instance, nothing for us to do
        if (!isset($pf4wp_instance))
            return;

        $error_type = '';

        // Handle error(s)
        switch ($error['type']) {
            case E_ERROR             : $error_type = (empty($error_type)) ? 'E_ERROR'             : $error_type;    // no break - Irrecoverable, fatal error
            case E_USER_ERROR        : $error_type = (empty($error_type)) ? 'E_USER_ERROR'        : $error_type;    // no break - Irrecoverable, fatal error (user defined)
            case E_CORE_ERROR        : $error_type = (empty($error_type)) ? 'E_CORE_ERROR'        : $error_type;    // no break - Irrecoverable, fatal core error (PHP)
            case E_COMPILE_ERROR     : $error_type = (empty($error_type)) ? 'E_COMPILE_ERROR'     : $error_type;    // no break - Irrecoverable, fatal compile-time error (Zend)
            case E_RECOVERABLE_ERROR : $error_type = (empty($error_type)) ? 'E_RECOVERABLE_ERROR' : $error_type;    // no break - Recoverable, considered fatal for now
                $deactivated      = false;
                $deactivated_msg  = '';
                $error_logged_msg = '';

                // Try logging the error to a file
                $error_logged = ($pf4wp_instance->log(
                    sprintf(
                        '%s "%s" %d "%s"',
                        $error_type,
                        $error['file'],
                        $error['line'],
                        addslashes($error['message'])
                    ),
                    self::LOG_ERROR
                ) === 1);

                if ($error_logged)
                    $error_logged_msg = sprintf(__('<p>A log of the error can be found in the file <code>%s</code></p>', $pf4wp_instance->getName()), $pf4wp_instance->getLogFile());

                // If not in WP Debug mode, de-activate the plugin if possible
                if (!defined('WP_DEBUG') || (defined('WP_DEBUG') && WP_DEBUG === false)) {
                    if (!function_exists('deactivate_plugins'))
                        @include_once(ABSPATH . 'wp-admin/includes/plugin.php');

                    if (function_exists('deactivate_plugins')) {
                        deactivate_plugins($pf4wp_instance->getPluginBaseName(), true);
                        $deactivated = true;
                        $deactivated_msg = __('<p>Because of this error, the plugin was automatically deactivated to prevent it from causing further problems with your WordPress site.</p>', $pf4wp_instance->getName());
                    }
                }

                // Display a full-fledged error in Admin
                if (is_admin()) {
                    // Allow the plugin to define a message on how to be contacted
                    $contact_msg = apply_filters(
                        'pf4wp_error_contactmsg_' . $pf4wp_instance->getName(),
                        sprintf(
                            __('<p>Please contact the plugin author <a href="%s" target="_blank">%s</a> with the above details%s if this problem persists.</p>', $pf4wp_instance->getName()),
                            PluginInfo::getInfo(false, $pf4wp_instance->getPluginBaseName(), 'AuthorURI'),
                            PluginInfo::getInfo(false, $pf4wp_instance->getPluginBaseName(), 'Author'),
                            ($error_logged) ? ' or the logfile' : ''
                        ),
                        $error_type,    // The error type name
                        $error,         // Pass the error as well
                        $error_logged   // And if the error was logged
                    );

                    wp_die(
                        sprintf(
                            __(
                                '<h1>Fatal Error (%s)</h1>'.
                                '<p><strong>There was a fatal error in the plugin %s.</strong></p>'.
                                '<p>The error occurred in file <code>%s</code> on line %d. The cause of the error was:</p>'.
                                '<p><pre>%1$s: %s</pre></p>'.
                                '%s'. // Deactivated
                                '%s'. // Error Logged
                                '%s', // Contact Message
                                $pf4wp_instance->getName()
                            ),
                            $error_type,
                            $pf4wp_instance->getDisplayName(),
                            $error['file'],
                            $error['line'],
                            $error['message'],
                            $deactivated_msg,
                            $error_logged_msg,
                            $contact_msg
                        ),
                        sprintf(__('Fatal Error (%s)', $pf4wp_instance->getName()), $error_type),
                        array('back_link' => true)
                    );
                } else {
                    /* If the plugin was deactivated, reload the page so the user does not stare at a
                     * blank page. We can't do this using headers, as they have already been sent.
                     */
                    if ($deactivated) {
                        printf(
                            "<head><script type=\"text/javascript\">/* <![CDATA[ */window.location.reload(true);/* ]]> */</script></head>\n".
                            "<body><noscript>".
                            __("Please <a href=\"%s\">click here</a> to reload this page.", $pf4wp_instance->getName()).
                            "</noscript></body>",
                            $_SERVER['REQUEST_URI']
                        );
                    }
                    return;
                }
                break;

            // Any other errors are ignored
        }
    }

    /*---------- Public events that are safe to override to provide full plugin functionality ----------*/

    /**
     * Event called when the plugin is ready to register actions
     *
     * @api
     */
    public function onRegisterActions() {}

    /**
     * Event called when a filter is requested during action registration
     *
     * Note: this is only available in the Admin/Dashboard
     *
     * @param string $filter Name of the filter
     * @api
     */
    public function onFilter($filter) {}

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
     * Event triggered when the administrative side of the plugin is ready for initialization
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


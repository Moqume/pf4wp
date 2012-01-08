<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Info;

/**
 * Class providing detailed information about WordPress plugins
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Info
 */
class PluginInfo
{
    /** Holds (caches) the current installed plugins
     * @internal
     */
    private static $installed_plugins;
    
    /**
     * Hide constructor from the public. Purely static class.
     */
    protected function __construct() {}    

    /**
     * Retrieves the details of currently installed plugin(s)
     *
     * Examples:
     *
     * To return all installed plugins, which are activated only:
     * <code>
     * $active_plugins_only = true;
     * $plugins = PluginInfo::getInfo($active_plugins_only);
     * foreach ($plugins as $plugin_filebase => $plugin_info) {
     *   echo $plugin_info['Name'] . ' is installed at ' . $plugin_filebase;
     * }
     * </code>
     *
     * To return a particular field from an installed plugin, regardless if
     * it is activated or not:
     * <code>
     * $description = PluginInfo::getInfo(false, 'Akismet', 'Description');
     * echo 'The Aksimet plugin has the following description: "' . $desription . '"';
     * </code>
     *
     * @param bool $active_only Optionally filter plugins by those that are activated
     * @param bool|string $name A string to optionally select a specific plugin by name or plugin basename (RegExp enabled, case insensitive)
     * @param bool|string $field A string to optionally select a specific field (only valid if $name is set)
     * @return array|string Details about all (or specified) plugin, or the specified plugin's field
     * @see get_plugin_info(), is_plugin_installed()
     */
    public static function getInfo($active_only = false, $name = false, $field = false)
    {
        // Create cached data for installed plugins if there's none yet:
        if ( !isset(self::$installed_plugins) ) {
            if (!function_exists('get_plugins'))
                include_once ABSPATH . '/wp-admin/includes/admin.php';
            
            // Double check before calling get_plugins()
            if (function_exists('get_plugins')) {
                self::$installed_plugins = get_plugins();
            } else {
                // get_plugins() is not available to us, return empty handed...
                if ($field) {
                    return '';
                } else {
                    return array();
                }
            }
        }
        
        $result = self::$installed_plugins;
        
        // Do we need to filter it by active plugins only?
        if ( $active_only ) {
            if (($active_plugins = get_option('active_plugins')) === false)
                $active_plugins = array();
            
            // Include plugins that are activated site-wide
            if (function_exists('is_multisite') && is_multisite()) {
                if (($active_sitewide_plugins = get_site_option('active_sitewide_plugins')) !== false)
                    $active_plugins = array_merge($active_plugins, array_keys($active_sitewide_plugins));
            }

            $result = array_intersect_key($result, array_flip($active_plugins));           
        }
        
        if ( !empty($result) && $name ) {
            $search_basename = (strpos($name, '/') !== false);
            
            // Swap results
            $_result = $result;
            $result = array();
            
            // Find the specified plugin
            foreach ($_result as $plugin_file_base => $plugin_details) {
                if ( ($search_basename && $plugin_file_base == $name) || 
                     (!$search_basename && preg_match('/' . $name . '/i', $plugin_details['Name'])) ) {
                    $result = $plugin_details;
                    $result['FileBase'] = $plugin_file_base;
                    break;
                }
            }

            // Was a specific field specified, or should we return everything?
            if ( $field ) {
                if ( array_key_exists($field, $result) ) {
                    $result = $result[$field];
                } else {
                    $result = '';
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Checks if one or more plugins is installed
     *
     * If one name is given as a string, the function will simply return a true / false. If
     * more than one / array is specified, it will return an array of the installed plugins or false
     * if none were found.
     *
     * @param string|array $names One or more names/basenames of plugins to check for installation (RegExp enabled, case insensitive)
     * @param bool $active_only Only check the plugins that are activated
     * @return bool|array (see long description)
     * @see get_installed_plugin_info()
     */
    public static function isInstalled($names, $active_only = false) {       
        if ( !is_array($names) ) {
            // We just need to find a single plugin
            $plugin = self::getInfo($active_only, $names);
            return (!empty($plugin));
        } else {
            // We need to find one or more plugins
            $results = array();
            
            foreach ($names as $name) {	
                $plugin = self::getInfo($active_only, $name);
                
                if (!empty($plugin))
                    $results[] = $plugin;
            }
            
            if ( count($results) == 0 ) {
                return false;
            } else {
                return $results;
            }
        }
    }    
}

 

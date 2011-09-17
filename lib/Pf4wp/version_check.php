<?php
/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
/* PLEASE DO NOT DELETE THE FOLLOWING LINE AND LEAVE IT AT THE TOP: */
if (!isset($_myatu_version_check) || array_shift(explode('/', plugin_basename(str_replace('\\', '/', dirname(__FILE__))), 2)) != plugin_basename(str_replace('\\', '/', dirname($_myatu_version_check)))) die('Restricted Access');

/**
 * This include file checks the PHP and WordPress versions
 *
 * If it fails either checks, it will deactivate the plugin and gracefully 
 * "die" if the end user is within the WordPress Admin/Dashboard area, or 
 * silently return otherwise.
 *
 * If the checks pass, it will set the variable $_myatu_version_check_pass 
 * to TRUE.
 *
 * Before calling this include file, please set the variable $_myatu_version_check
 * to the calling plugin file (usually the main plugin file).
 *
 * Optionally, a PHP version ($_myatu_version_check_php) or WordPress version
 * ($_myatu_version_check_php) can be specified.
 *
 * THIS FILE MUST BE PHP4 COMPATIBLE.
 *
 * @package Pf4wp
 */

global $wp_version;

unset($_myatu_version_check_pass); // Clear any previous variable set

// Default mininum PHP version
if (!isset($_myatu_version_check_php))
    $_myatu_version_check_php = '5.3.0';

// Default mininum WordPress version
if (!isset($_myatu_version_check_wp))
    $_myatu_version_check_wp = '3.1.0';   

if (($_myatu_old_php = version_compare(PHP_VERSION, $_myatu_version_check_php, '<')) || 
    version_compare($wp_version, $_myatu_version_check_wp, '<')) {
    if (is_admin()) {
        $_myatu_plugin_name = '';
        $_myatu_deactivated = '';
        
        // Attempt to load some plugin helpers provided by WordPress
        if (!function_exists('deactivate_plugins'))
            @include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        
        // De-activate the plugin, if possible
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins($_myatu_version_check, true);
            $_myatu_deactivated = '<p>Because of this error, the plugin was automatically deactivated to prevent it from causing further problems with your WordPress site.</p>';
        }
        
        // Grab the name of the plugin, if possible
        if (function_exists('get_plugin_data')) {
            $_myatu_plugin_data = get_plugin_data($_myatu_version_check);
            $_myatu_plugin_name = '"' . $_myatu_plugin_data['Name'] . '"';
        }
        
        // Gracefully "die", letting the end user know why this happened
        $_myatu_error = ($_myatu_old_php) ? 'PHP version ' . $_myatu_version_check_php : 'WordPress version ' . $_myatu_version_check_wp;
        wp_die(
            '<h1>Error: Unsupported version</h1>'.
            '<p><strong>The plugin ' . $_myatu_plugin_name . ' requires ' . $_myatu_error . ' or better.</strong></p>'.
            $_myatu_deactivated .
            '<p>Error source: <code>' . $_myatu_version_check . '</code></p>',
            'Error: Unsupported version',
            array('back_link'=>true)
        );
    } else {
        return;
    }
}

/* Cleanup of unneeded variables */

unset($_myatu_old_php);
unset($_myatu_version_check);
unset($_myatu_version_check_wp);
unset($_myatu_version_check_php);

// Set the magic "pass" variable
$_myatu_version_check_pass = true;

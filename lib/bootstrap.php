<?php
/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
/* PLEASE DO NOT DELETE THE FOLLOWING LINE AND LEAVE IT AT THE TOP: */
if (!isset($_pf4wp_file) || array_shift(explode('/', str_replace('\\', '/', plugin_basename(dirname(__FILE__))))) != plugin_basename(dirname($_pf4wp_file))) die('Restricted Access');

/**
 * This include file does a version check and creates an UCL instance.
 *
 * If it fails either the WordPress or PHP checks, it will deactivate the 
 * plugin and gracefully "die" if the end user is within the WordPress 
 * Admin/Dashboard area, or silently return otherwise.
 *
 * If the checks pass, it will set the variable $_pf4wp_check_pass 
 * to `true` and will set a variable $_pf4wp_ucl containing the UCL 
 * (UniversalClassLoader) instance.
 *
 * Before calling this include file, please set the variable $_pf4wp_file
 * to the calling plugin file (usually the main plugin file).
 *
 * Optionally, a PHP version ($_pf4wp_version_check_php) or WordPress version
 * ($_pf4wp_version_check_php) can be specified.
 *
 * @package Pf4wp
 */

global $wp_version;

/* Clear any previous variable set */

unset($_pf4wp_check_pass);
unset($_pf4wp_ucl);

/* Version Check */

// Default mininum PHP version
if (!isset($_pf4wp_version_check_php))
    $_pf4wp_version_check_php = '5.3.0';

// Default mininum WordPress version
if (!isset($_pf4wp_version_check_wp))
    $_pf4wp_version_check_wp = '3.1.0';

if (($_pf4wp_old_php = version_compare(PHP_VERSION, $_pf4wp_version_check_php, '<')) || 
    version_compare($wp_version, $_pf4wp_version_check_wp, '<')) {
    if (is_admin()) {
        $_pf4wp_plugin_name = '';
        $_pf4wp_deactivated = '';
        
        // Attempt to load some plugin helpers provided by WordPress
        if (!function_exists('deactivate_plugins'))
            @include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        
        // De-activate the plugin, if possible
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins($_pf4wp_file, true);
            $_pf4wp_deactivated = '<p>Because of this error, the plugin was automatically deactivated to prevent it from causing further problems with your WordPress site.</p>';
        }
        
        // Grab the name of the plugin, if possible
        if (function_exists('get_plugin_data')) {
            $_pf4wp_plugin_data = get_plugin_data($_pf4wp_file);
            $_pf4wp_plugin_name = '"' . $_pf4wp_plugin_data['Name'] . '"';
        }
        
        // Gracefully "die", letting the end user know why this happened
        wp_die(
            sprintf(
                '<h1>Error: Unsupported version</h1><p><strong>The plugin %s requires %s or better.</strong></p>%s<p>Error source: <code>%s</code></p>',
                $_pf4wp_plugin_name,
                ($_pf4wp_old_php) ? 'PHP version ' . $_pf4wp_version_check_php : 'WordPress version ' . $_pf4wp_version_check_wp,
                $_pf4wp_deactivated,
                $_pf4wp_file
            ),
            'Error: Unsupported version',
            array('back_link'=>true)
        );
    } else {
        return;
    }
}

/* UCL */

if (!class_exists('Symfony\Component\ClassLoader\UniversalClassLoader'))
    require_once __DIR__.'/vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php';

if (extension_loaded('apc')) {
    if (!defined('PF4WP_APC')) 
        define('PF4WP_APC', true);
        
    if (!class_exists('Symfony\Component\ClassLoader\ApcUniversalClassLoader'))
        require_once __DIR__.'/vendor/Symfony/Component/ClassLoader/ApcUniversalClassLoader.php';

    $_pf4wp_ucl = new Symfony\Component\ClassLoader\ApcUniversalClassLoader('pf4wp.' . md5($_pf4wp_file) . '.ucl.');
} else {
    if (!defined('PF4WP_APC'))
        define('PF4WP_APC', false);
        
    $_pf4wp_ucl = new Symfony\Component\ClassLoader\UniversalClassLoader();
}

/* Cleanup */

unset($_pf4wp_old_php);
unset($_pf4wp_file);
unset($_pf4wp_version_check_wp);
unset($_pf4wp_version_check_php);

// Set the magic "pass" variable
$_pf4wp_check_pass = true;
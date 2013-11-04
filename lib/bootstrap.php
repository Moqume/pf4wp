<?php
/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/* PLEASE DO NOT DELETE THE FOLLOWING TWO LINES AND LEAVE IT AT THE TOP: */
$called_base_dir = explode('/', str_replace('\\', '/', plugin_basename(dirname(__FILE__))));
if (!isset($_pf4wp_file) || array_shift($called_base_dir) != plugin_basename(dirname($_pf4wp_file))) die('Restricted Access');

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

/* Initialize some basic vars */
// Default mininum PHP version
if (!isset($_pf4wp_version_check_php))
    $_pf4wp_version_check_php = '5.3.0';

// Default mininum WordPress version
if (!isset($_pf4wp_version_check_wp))
    $_pf4wp_version_check_wp = '3.1.0';

/* Version Check */
if (($_pf4wp_old_php = version_compare(PHP_VERSION, $_pf4wp_version_check_php, '<')) ||
    version_compare($wp_version, $_pf4wp_version_check_wp, '<')) {

    // Either PHP or WordPress is older than the minimum requirement
    if (is_admin()) {
        $_pf4wp_deactivated = '';

        // Allow the plugin name to be pre-set
        if (!isset($_pf4wp_plugin_name))
            $_pf4wp_plugin_name = '';

        // Attempt to retrieve the plugin's display name
        if (function_exists('get_plugin_data')) {
            $_pf4wp_plugin_data = get_plugin_data($_pf4wp_file);

            if (isset($_pf4wp_plugin_data['Name']))
                $_pf4wp_plugin_name = '"' . $_pf4wp_plugin_data['Name'] . '"'; // Override with actual name

            unset($_pf4wp_plugin_data);
        }

        // Attempt to load some plugin helpers provided by WordPress
        if (!function_exists('deactivate_plugins'))
            @include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // De-activate the plugin, if possible
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins($_pf4wp_file, true);
            $_pf4wp_deactivated = '<p>Because of this error, the plugin was automatically deactivated to prevent it from causing further problems with your WordPress site.</p>';
        }

        // Gracefully "die", letting the end user know why this happened
        wp_die(
            sprintf(
                '<h1>Error: Unsupported %s version</h1><p><strong>The plugin %s requires %s or better, but version %s was detected.</strong></p>%s<p>Error source: <code>%s</code></p>',
                ($_pf4wp_old_php) ? 'PHP' : 'WordPress',
                $_pf4wp_plugin_name,
                ($_pf4wp_old_php) ? 'PHP version ' . $_pf4wp_version_check_php : 'WordPress version ' . $_pf4wp_version_check_wp,
                ($_pf4wp_old_php) ? PHP_VERSION : $wp_version,
                $_pf4wp_deactivated,
                $_pf4wp_file
            ),
            'Error: Unsupported version',
            array('back_link'=>true)
        );
    } else {
        // Return silently, without initializing the rest of the plugin
        return;
    }
}

/* UCL */

$_pf4wp_ucl_class = '\\Symfony\\Component\\ClassLoader\\UniversalClassLoader';

if (!class_exists($_pf4wp_ucl_class))
    require_once __DIR__.'/vendor/Symfony/Component/ClassLoader/UniversalClassLoader.php'; // Always include default UCL

if ((defined('PF4WP_APC') && PF4WP_APC === true) || (extension_loaded('apc') && function_exists('apc_store'))) {
    // Set the PF4WP_APC to true, if not yet set
    if (!defined('PF4WP_APC'))
        define('PF4WP_APC', true);

    // Use the APC UCL instead
    $_pf4wp_ucl_class = '\\Symfony\\Component\\ClassLoader\\ApcUniversalClassLoader';

    // Load the class, if not yet loaded
    if (!class_exists($_pf4wp_ucl_class))
        require_once __DIR__.'/vendor/Symfony/Component/ClassLoader/ApcUniversalClassLoader.php';

    // Double check the file loaded OK and class exists, then create it along with an APC namespace
    if (class_exists($_pf4wp_ucl_class))
        $_pf4wp_ucl = new $_pf4wp_ucl_class('pf4wp.' . md5($_pf4wp_file) . '.ucl.');
} else {
    // No APC available, use regular UCL
    if (!defined('PF4WP_APC'))
        define('PF4WP_APC', false);

    if (class_exists($_pf4wp_ucl_class))
        $_pf4wp_ucl = new $_pf4wp_ucl_class();
}

if (!isset($_pf4wp_ucl) || $_pf4wp_ucl === false) return; // Only silently return if no UCL could be loaded.

/* Cleanup */

unset($_pf4wp_ucl_class);
unset($_pf4wp_old_php);
unset($_pf4wp_file);
unset($_pf4wp_version_check_wp);
unset($_pf4wp_version_check_php);

// Set the magic "pass" variable
$_pf4wp_check_pass = true;

// Set the version
if (!defined('PF4WP_VERSION'))
    define('PF4WP_VERSION', '1.1.5');

<?php die(); ?>
/*
Plugin Name: @Name@
Plugin URI: @URI@
Description: @Description@
Version: 1.0
Author: @Author@
Author URI: @Author_URI@
*/

/* Direct call check */

if (!function_exists('add_action')) return;

/* Bootstrap */

$_pf4wp_file = __FILE__;
@Min_WP_Version@
@Min_PHP_Version@

require dirname(__FILE__).'/vendor/pf4wp/lib/bootstrap.php'; // use dirname()!

if (!isset($_pf4wp_check_pass) || !isset($_pf4wp_ucl) || !$_pf4wp_check_pass) return;

/* Register Namespaces */

$_pf4wp_ucl->registerNamespaces(array(
    'Symfony\\Component\\ClassLoader'   => __DIR__.'/vendor/pf4wp/lib/vendor',
    'Pf4wp'                             => __DIR__.'/vendor/pf4wp/lib',
    @Register_Namespaces@
));

$_pf4wp_ucl->registerPrefixes(array(
    @Register_Prefixes@
));
$_pf4wp_ucl->registerNamespaceFallbacks(array(
    __DIR__.'/app',
));
$_pf4wp_ucl->register();

/* Fire her up, Scotty! */

call_user_func('@Namespace_Double@\\Main::register', __FILE__);

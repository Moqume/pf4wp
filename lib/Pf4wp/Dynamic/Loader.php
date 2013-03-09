<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Dynamic;

/**
 * Dynamic loader (static)
 *
 * @package Pf4wp
 * @subpackage Dynamic
 * @since 1.0.6
 */
class Loader
{
    protected function __construct() {}

    /**
     * Obtains an array of available dynamic loaded classes
     *
     * @api
     * @param string $base_namespace The base namespace for the class to be loaded
     * @param string $base_dir The base directory to search for the dynamic classes
     * @param bool $only_active Return only the classes that are active
     * @return array Array based on full class name keys, containing name, description, active/inactive status and class name.
     */
    public static function get($base_namespace, $base_dir, $only_active = false)
    {
        $base_dir    = trailingslashit(realpath($base_dir));
        $dyn_classes = array();
        $iterator    = new \RecursiveIteratorIterator(new \Pf4wp\Storage\IgnorantRecursiveDirectoryIterator($base_dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        $files       = iterator_to_array(new \RegexIterator($iterator, '/^.+\.php?$/i', \RecursiveRegexIterator::GET_MATCH));

        foreach ($files as $file) {
            $file_name  = substr($file[0], strlen($base_dir), -4); // We know the length of each element, so use that (faster)
            $class_name = str_replace(DIRECTORY_SEPARATOR, '\\', $file_name);
            $class      = $base_namespace . '\\' . $class_name;

            try {
                $reflection = new \ReflectionClass($class);
            } catch (\Exception $e) {
                $reflection = false;
            }

            // Add if a valid
            if ($reflection && $reflection->implementsInterface(__NAMESPACE__ . '\\DynamicInterface') && is_callable($class . '::info')) {
                $info = $class::info();

                if ($only_active == false || ($only_active && $info['active']))
                    $dyn_classes[$class_name] = array_merge($class::info(), array('class' => $class));
            }
        }

        return $dyn_classes;
    }
}

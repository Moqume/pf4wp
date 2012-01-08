<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Storage;

use Pf4wp\WordpressPlugin;

/**
 * The StoragePath Class encapsulates some common function to work with
 * directories used for storage, ensuring they are present and can be 
 * written to.
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Storage
 * @api
 */
class StoragePath
{
    const DIR_CHMOD  = 0755;
    const FILE_CHMOD = 0644;

    /**
     * Hide constructor from the public. Purely static class.
     */
    protected function __construct() {}
    
    /**
     * Initialize the WP Filesystem
     *
     * @return bool Returns `true` if initialization was successful, `false` otherwise
     */
    private static function initWPFilesystem()
    {
        global $wp_filesystem;
        
        if (!isset($wp_filesystem) && function_exists('WP_Filesystem'))
            WP_Filesystem();
            
        return (isset($wp_filesystem) && $wp_filesystem instanceof \WP_Filesystem_Base);
    }
    
    /**
     * Checks the specified path is empty or a root directory (ie., "C:\" or "/")
     *
     * @param string $path The path to test against
     * @return bool Returns true if empty or a root directory, false otherwise
     */
    private static function isRoot($path)
    {
        return (empty($path) || preg_match('#^([A-Za-z]\:)?[\\\\/]$#', realpath($path)));
    }        
    
    /**
     * Validates a path
     *
     * This function will check if a specified path can be accessed directly and has
     * read/write permissions. If the path does not exist, it attempts to create it
     * first. If it exists, but cannot be read or written to, it will attempt to 
     * adjust the permissions accordingly. Finally, it will mark the directory and its
     * sub-directories as private, if requested (true by default)
     *
     * @param string $path Path to validate  
     * @param bool $is_private If set to true (default), the directory and sub-directories will be marked as private (Optional)
     * @return string|bool Returns the validated path if successful, `false` otherwise
     * @api
     */    
    public static function validate($path, $is_private = true)
    {
        global $wp_filesystem;

        if (self::isRoot($path))
            return false;
        
        // Ensure it ends with a trailing slash
        $path = trailingslashit($path);
        
        $valid_path = @file_exists($path);
        
        // Create the directory if it doesn't exist
        if (!$valid_path)  {
            $valid_path = @mkdir($path, self::DIR_CHMOD, true);
            
            // Try using WP Filesystem
            if (!$valid_path && self::initWPFilesystem()) {
                $parent = '';
                $children = explode('/', str_replace('\\', '/', $path));
                
                // WP Filesystem does not support nested directories for mkdir, so we immitate it
                foreach ($children as $child) {
                    $skip_root = ($parent == '');
                    
                    $parent .= $child . DIRECTORY_SEPARATOR;

                    if ( !$skip_root && !$wp_filesystem->exists($parent) )
                        $wp_filesystem->mkdir($parent, self::DIR_CHMOD);
                }
                
                // Check again using direct method
                $valid_path = @file_exists($path);
            }
        }
        
        // Ensure it's not a file
        $valid_path = ($valid_path && !is_file($path));
        
        // Ensure we have the right permissions
        if ($valid_path && (!@is_readable($path) || !@is_writable($path))) {
            $valid_path = @chmod($path, self::DIR_CHMOD);
            
            // Try using WP Filesystem
            if (!$valid_path && self::initWPFilesystem())
                $valid_path = $wp_filesystem->chmod($path, self::DIR_CHMOD);
                
            // Check again
            $valid_path = $valid_path && @is_readable($path) && @is_writable($path);
        }
        
        // Keep it private, if required
        if ($valid_path && $is_private)
            static::makePrivate($path);
        
        if ($valid_path)
            return trailingslashit(realpath($path));
            
        return false;
    }
       
    /**
     * Recursively marks a directory private
     *
     * This adds a blank index.htm file, to prevent directory browsing and a
     * .htaccess to deny access to it, if supported by the web server, to the
     * specified path and optionally all its sub-directories. 
     *
     * @param string $path Path to the directory to mark as private
     * @param bool $recursive If `true`, also make the sub-directories private (Optional, default is `true`)
     * @api
     */    
    public static function makePrivate($path, $recursive = true)
    {
        if (self::isRoot($path) || !@file_exists($path))
            return;
        
        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(new IgnorantRecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isDir())
                    static::makePrivate($fileinfo->getPathname(), false);
            }
        }

        $path = trailingslashit($path);
        $htaccess = $path . '.htaccess';
        $index    = $path . 'index.htm';

         // Create a blank index, in case .htaccess is not supported
        if (!@is_file($index)) {
            @touch($index);
            @chmod($index, self::FILE_CHMOD);
        }
        
        // Create a .htaccess
        if (!@is_file($htaccess)) {
            if ($fp = @fopen($htaccess, 'w')) {
                @fwrite($fp, 'deny from all');
                @fclose($fp);
                @chmod($htaccess, self::FILE_CHMOD);
            }
        }        
    }
    
    /**
     * Deletes the entire directory, and optionally all its sub-directories
     *
     * This will delete all files contained within the path
     *
     * @param string $path Path to the directory to delete
     * @param bool $recursive If `true`, delete all sub-directories (Optional, default is `true`)
     * @return bool Returns `true` if the path could be deleted entirely, `false` otherwise (path has remnants)
     * @api
     */
    public static function delete($path, $recursive = true)
    {
        if (self::isRoot($path))
            return false;
            
        if (!@file_exists($path))
            return true; // Nothing to do!

        $iterator = new \RecursiveIteratorIterator(new IgnorantRecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        
        foreach ($iterator as $fileinfo) {
            $res = true;
            
            if ($fileinfo->isDir() && $recursive) {
                $res = static::delete($fileinfo->getPathname(), false);
            } else if ($fileinfo->isFile()) {
                $res = @unlink($fileinfo->getPathname());
            }
            
            if (!$res)
                return false;
        }
        
        if (@is_dir($path))
            return @rmdir($path);
    }
}
<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Common;

use Pf4wp\Storage\StoragePath;

/**
 * Static class providing access to internal images
 *
 * @package Pf4wp
 * @subpackage Common
 * @api
 * @since 1.1
 */
class InternalImages
{
    private static $images = array();

    // Static class!
    protected function __construct() {}

    /**
     * Fills the $images array with available images
     *
     * @internal
     */
    private static function loadImages()
    {
        if (!empty($images))
            return;

        // Get the vendor base dir
        $images_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/resources/images';

        // Quick sanity check
        if (!is_dir($images_dir) || !is_readable($images_dir))
            return;

        StoragePath::recurseCallback($images_dir, array(__CLASS__, '_doLoadImages'), array(), false, true);
    }

    /**
     * Callback used by loadImages()
     *
     * @param SplFileInfo $file File info object passed by recurseCallback() in loadImages()
     * @internal
     */
    final public static function _doLoadImages($file)
    {
        // Check if the filename is a valid image
        if (!isset($file) || !file_is_valid_image($file))
            return;

        // Try and split it into the size and name parts
        if (preg_match('#.+\/(\d+)\/(.+)\..+#', $file, $matches) !== 1)
            return;

        list(, $size, $name) = $matches;

        $result = array($size => plugin_basename($file));

        self::$images[$name] = (isset(self::$images[$name])) ? self::$images[$name] + $result : $result;
    }

    /**
     * Retrieve the available image names
     *
     * @api
     */
    public static function getAvailableNames()
    {
        self::loadImages();

        return array_keys(self::$images);
    }

    /**
     * Checks if the size for a particular image is available
     *
     * @param string $image_name The name of the image
     * @param int $size The size of the image to check for
     * @return bool
     * @api
     */
    public static function hasSize($image_name, $size)
    {
        self::loadImages();

        return (isset(self::$images[$image_name]) && isset(self::$images[$image_name][$size]));
    }

    /**
     * Retrieve the image as an HTML image tag
     *
     * @param string $image_name Name of the image
     * @param int $size The size of the image to return
     * @return string|bool Returns the HTMl or false if the image is not available/invalid
     * @api
     */
    public static function getHTML($image_name, $size = 32, $style = null, $title = null, $alt = null)
    {
        self::loadImages();

        if (!self::hasSize($image_name, $size))
            return false;

        return sprintf(
            '<img width="%d" height="%1$s" src="%s" %s %s %s />',
            $size,
            WP_PLUGIN_URL . '/' . plugin_basename(self::$images[$image_name][$size]),
            ($style) ? sprintf('style="%s"', $style) : '',
            ($title) ? sprintf('title="%s"', $title) : '',
            ($alt) ? sprintf('alt="%s"', $alt) : ''
        );
    }
}

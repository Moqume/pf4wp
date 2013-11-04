<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Common;

/**
 * Static class providing HTTP Cookie helpers
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Common
 */
class Cookies
{
    /**
     * Checks if a cookie exists
     *
     * Simple helper for PHP isset() to provide class consistency
     *
     * @param string $name Name of the cookie
     * @return bool Will return `true` if the cookie exists, `false` otherwise
     */
    public static function has($name)
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Obtain a HTTP Cookie
     *
     * @param string $name Name of the cookie
     * @param mixed $default Default result of the cookie, if not set (optional, `false` by default)
     * @return mixed
     */
    public static function get($name, $default = false)
    {
        if (isset($_COOKIE[$name]))
            return $_COOKIE[$name];

        return $default;
    }

    /**
     * Sets an HTTP Cookie
     *
     * This functions almost exactly as the PHP setcookie() and setrawcookie() functions, with the added feature that it will also
     * set `$_COOKIE[name]=value`.
     *
     * @param string $name Name of the cookie
     * @param mixed $value Optional value to provide the cookie. If omitted, or empty, the cookie will be deleted.
     * @param int $expire Optional value, in seconds, to expire the cookie from current time or mktime() value. If ommited or set to 0 (zero), the cookie will expire at the end of the browser session.
     * @param bool $overwrite if set to `false`, any existing cookie by the same name will NOT be overwritten (optional, `true` by default)
     * @param bool $raw If set to `true`, the value is stored as-is without URL encoding (optional, `false` by default)
     * @param string $path Optional path on the server in which the cookie will be available on.
     * @param string $domain Optional domain that the cookie is available to.
     * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client (optional, `false` by default).
     * @param bool $httponly When `true` the cookie will be made accessible only through the HTTP protocol (optional, `false` by default).
     * @return bool Returns `true` if the cookie was set (regardless if it was accepted by the visitor), or `false` on error
     */
    public static function set($name, $value = '', $expire = 0, $overwrite = true, $raw = false, $path = '', $domain = '', $secure = false, $httponly = false)
    {
        $parsed_home_url = parse_url(trailingslashit(get_home_url()));

        // Give it a path, if none specified
        if ($path === '')
            $path = $parsed_home_url['path'];

        // Give it a domain, if none specified
        if ($domain === '') {
            // Extract the TLD, if possible
            if (preg_match('/(\w+\.(\w{2,3}\.)?\w+)$/', $parsed_home_url['host'], $matches)) {
                $domain = $matches[1];
            }

            if (strpos($domain, '.') === false)
                $domain = $parsed_home_url['host']; // top-most part should have at least one period (ie, no "com", "org" top-parts)

            $domain = '.' . $domain;
        }

        if ($value === '') {
            // Delete cookie
            unset($_COOKIE[$name]);
            return @setcookie($name, '', time() - 3600, $path, $domain, $secure, $httponly);
        }

        // If requested, do not overwrite the cookie if it already exists - this will return "true" as it is not an error
        if (!$overwrite && static::has($name))
            return true;

        $_COOKIE[$name] = $value;

        // Ensure expiration is in future
        $now = time();
        if ($expire != 0 && $expire < $now)
            $expire += $now; // Simply adds current time to the expiration

        if ($raw)
            return @setrawcookie($name, $value, $expire, $path, $domain, $secure, $httponly); // Raw

        return @setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * Deletes a cookie
     *
     * @param string $name Name of the cookie to delete
     * @return bool Returns `true` if the cookie was deleted, or `false` otherwise
     */
    public static function delete($name)
    {
        return static::set($name);
    }
}

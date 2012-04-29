<?php

/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pf4wp\Common;

/**
 * Static class providing some common helper methods
 *
 * @author Mike Green <myatus@gmail.com>
 * @package Pf4wp
 * @subpackage Common
 * @api
 */
class Helpers
{
    /**
     * Check if we are currently in a Network Admin mode (Multisite)
     *
     * @return bool Returns `true` if we are currently in Network Admin mode, `false` otherwise.
     * @api
     */
    public static function isNetworkAdminMode()
    {
        // Always return false if multisite isn't enabled, regardless of what WP_NETWORK_ADMIN says
        if (function_exists('is_multisite') && !is_multisite())
            return false;

        return (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN);
    }

    /**
     * Converts a string into a fairly unique, short slug
     *
     * @param string $string String to convert to a slug
     * @return string Slug
     * @api
     */
    public static function makeSlug($string)
    {
        $nr = 'jEkNpiAsuZ';
        $slug = substr(base64_encode(md5($string)), 3, 6);

        // Do not allow slugs to start with a number
        if (is_numeric($slug[0]))
            $slug = $nr[(int)$slug[0]] . $slug;

        return $slug;
    }

    /**
     * Verifies if the callback is valid
     *
     * It differes from is_callable() in that it adds a suffix before checking and has to be an actual
     * method or function (not a magic)
     *
     * @param mixed $callback Callback to check
     * @param string $suffix Adds a suffix to the callback's method or function, checking against that instead (Optional)
     * @return mixed Returns the callback (including suffix, if provided), or `false` if invalid.
     * @api
     */
    public static function validCallback($callback, $suffix = '')
    {
        if (empty($callback))
            return false;

        if (is_array($callback) && isset($callback[0]) && is_object($callback[0])) {
            $method = (empty($suffix)) ? $callback[1] : $callback[1].$suffix;

            if (method_exists($callback[0], $method))
                return array($callback[0], $method);
        } else if (is_string($callback)) {
            $function = (empty($suffix)) ? $callback : $callback.$suffix;

            if (function_exists($function))
                return $function;
        }

        return false; // Invalid or unknown callback
    }

    /**
     * Encrypts data using Blowfish
     *
     * Requires either `mcrypt` extension or `Crypt_Blowfish` PEAR package.
     *
     * @param mixed $data Data to encrypt
     * @param string $pass_phrase Secret passphrase to encrypt the data
     * @param bool $url_safe Ensure the Base 64-encoded output is URL safe (Optional, disabled by default)
     * @return string|bool Base 64-encoded encrypted data, or `false` if encryption is not available
     * @api
     */
    public static function encrypt($data, $pass_phrase = '', $url_safe = false)
    {
        @include_once "Crypt/Blowfish.php"; // PEAR

        if (($use_mcrypt = extension_loaded('mcrypt')) === false || !class_exists('\Crypt_Blowfish'))
            return false;

        $pass_phrase = hash('sha256', $pass_phrase . NONCE_SALT, true);

        if ($use_mcrypt) {
            $result = mcrypt_encrypt(MCRYPT_BLOWFISH, $pass_phrase, $data, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB), MCRYPT_RAND));
        } else {
            $bf = new \Crypt_Blowfish($pass_phrase);

            $result = $bf->encrypt($data);
        }

        $result = base64_encode($result);

        if ($url_safe)
            $result = self::base64UrlSafe($result);

        return $result;
    }

    /**
     * Decrypts data using Blowfish
     *
     * Requires either `mcrypt` extension or `Crypt_Blowfish` PEAR package. Automatically
     * handles URL safe Base64 encoding.
     *
     * @param mixed $data Data to decrypt
     * @param string $pass_phrase Secret passphrase to decrypt the data
     * @return string|bool Decrypted data, or `false` if decryption is not available
     * @api
     */
    public static function decrypt($data, $pass_phrase = '')
    {
        @include_once "Crypt/Blowfish.php"; // PEAR

        if (($use_mcrypt = extension_loaded('mcrypt')) === false || !class_exists('\Crypt_Blowfish'))
            return false;

        $pass_phrase = hash('sha256', $pass_phrase . NONCE_SALT, true);

        $data = self::base64UrlUnsafe($data);

        if ($use_mcrypt) {
            return mcrypt_decrypt(MCRYPT_BLOWFISH, $pass_phrase, base64_decode($data), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB), MCRYPT_RAND));
        } else {
            $bf = new \Crypt_Blowfish($pass_phrase);

            return $bf->decrypt(base64_decode($data));
        }
    }

    /**
     * Ensures a base64 is URL safe
     *
     * @param string $data Base64 encoded string
     * @return string URL safe Base64 string
     * @api
     */
    public static function base64UrlSafe($data)
    {
        return strtr(preg_replace('#\s#', '', $data), '+/=', '-_,');
    }

    /**
     * Makes a base64 string that's _may_ be URL safe "unsafe" again
     *
     * Note: This will leave non-URL safe base64 encodings as-is.
     *
     * @param string $data Base64 URL safe encoded string
     * @return string Base64 encoded string safe to use with `base64_encode()`
     * @api
     */
    public static function base64UrlUnsafe($data)
    {
        return strtr($data, '-_,', '+/=');
    }

    /**
     * Obtains the MIME type of a file
     *
     * @param string $file The file to obtain the MIME type of
     * @param string $default_mime The default MIME in case it could not be determined (`application/octet-stream` as per RFC 2046 [4.5.1])
     * @return string The MIME type
     * @api
     */
    public static function getMime($file, $default_mime = 'application/octet-stream')
    {
        if (@is_dir($file))
            return('httpd/unix-directory');

        // Try by *nix command
        @exec(sprintf('file -bi %s', escapeshellarg($file)), $mime, $exec_result);

        if ($exec_result == 0)
            return trim($mime[0]);

        // Try using fileinfo (finfo)
        if (class_exists('\\finfo')) {
            try {
                $finfo = new \finfo(FILEINFO_MIME);

                if ($finfo && ($mime = @$finfo->file($file)) !== false)
                    return $mime;
            } catch (\Exception $e) {}
        }

        return $default_mime;
    }

    /**
     * Embeds a file as a Data URI
     *
     * Example for HTML
     * <code>
     *   echo '<img src="' . Helpers::embedDataUri(__DIR__ . '/images/cool.jpg') . '" />';
     * </code>
     *
     * Example for CSS
     * <code>
     *   .mycoolimage { background: url('<?php echo Helpers::embedDataUri('images/cool.jpg'); ?>') no-repeat center center;
     * </code>
     *
     * @param string $file Full path to the file
     * @param string $mime Mime type of the file (Optional, detected automatically if set to `false`)
     * @param bool $force Force file to be loaded from disk, rather than transient cache
     * @return string|bool Data to embed as CSS, or `false` if an invalid or inaccessible file specified
     * @api
     */
    public static function embedDataUri($file, $mime = false, $force = false)
    {
        $cache_id = 'pf4wp_' . md5($file) .  '_embed'; // 44 chars

        // Get from cache
        if (!$force && ($result = get_transient($cache_id)) !== false)
            return $result;

        // Obtain file contents
        if (@is_file($file) && @is_readable($file) && ($fs = @filesize($file)) > 0 && ($fh = @fopen($file, 'rb')) !== false) {
            $data = @fread($fh, $fs);
            @fclose($fh);
        } else {
            return false;
        }

        // Obtan the MIME type
        if (!$mime)
            $mime = static::getMime($file);

        // Whitespace characters, including newlines, and the default 'binary' charset are removed
        $result = sprintf('data:%s;base64,%s', str_replace('; charset=binary', '', $mime), preg_replace('#\s#', '', base64_encode($data)));

        // Save to cache (1 hr)
        set_transient($cache_id, $result, 3600);

        return $result;
    }

    /**
     * Check if WP Version is higher, lower or equal to a certain version
     *
     * @param string $version Version to compare against
     * @param string $operator Operator for comparision (default is '=')
     * @since 1.0.7
     * @api
     */
    public static function checkWPVersion($version, $operator = '=')
    {
        global $wp_version;

        $_wp_version = $wp_version;
        $spacer      = strpos($_wp_version, '-');

        // Remove any extra data
        if ($spacer >= 0) {
            $_wp_version = substr($_wp_version, 0, $spacer);
        }

        return version_compare($_wp_version, $version, $operator);
    }

    /**
     * Checks if we're inside an AJAX request
     *
     * @return bool Returns `true` if we're inside an AJAX request, `false` otherwise
     * @since 1.0.7
     * @api
     */
    public static function doingAjax()
    {
        return (defined('DOING_AJAX') && DOING_AJAX);
    }
}

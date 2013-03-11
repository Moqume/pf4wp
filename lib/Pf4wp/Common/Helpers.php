<?php

/*
 * Copyright (c) 2011-2013 Mike Green <myatus@gmail.com>
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
    /** By Christian Schmidt <schmidt@php.net> - since 1.0.11 */
    private static $mime_by_extension = array (
        'ez'        => 'application/andrew-inset',
        'atom'      => 'application/atom+xml',
        'jar'       => 'application/java-archive',
        'hqx'       => 'application/mac-binhex40',
        'cpt'       => 'application/mac-compactpro',
        'mathml'    => 'application/mathml+xml',
        'doc'       => 'application/msword',
        'dat'       => 'application/octet-stream',
        'oda'       => 'application/oda',
        'ogg'       => 'application/ogg',
        'pdf'       => 'application/pdf',
        'ai'        => 'application/postscript',
        'eps'       => 'application/postscript',
        'ps'        => 'application/postscript',
        'rdf'       => 'application/rdf+xml',
        'rss'       => 'application/rss+xml',
        'smi'       => 'application/smil',
        'smil'      => 'application/smil',
        'gram'      => 'application/srgs',
        'grxml'     => 'application/srgs+xml',
        'kml'       => 'application/vnd.google-earth.kml+xml',
        'kmz'       => 'application/vnd.google-earth.kmz',
        'mif'       => 'application/vnd.mif',
        'xul'       => 'application/vnd.mozilla.xul+xml',
        'xls'       => 'application/vnd.ms-excel',
        'xlb'       => 'application/vnd.ms-excel',
        'xlt'       => 'application/vnd.ms-excel',
        'xlam'      => 'application/vnd.ms-excel.addin.macroEnabled.12',
        'xlsb'      => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
        'xlsm'      => 'application/vnd.ms-excel.sheet.macroEnabled.12',
        'xltm'      => 'application/vnd.ms-excel.template.macroEnabled.12',
        'docm'      => 'application/vnd.ms-word.document.macroEnabled.12',
        'dotm'      => 'application/vnd.ms-word.template.macroEnabled.12',
        'ppam'      => 'application/vnd.ms-powerpoint.addin.macroEnabled.12',
        'pptm'      => 'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
        'ppsm'      => 'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
        'potm'      => 'application/vnd.ms-powerpoint.template.macroEnabled.12',
        'ppt'       => 'application/vnd.ms-powerpoint',
        'pps'       => 'application/vnd.ms-powerpoint',
        'odc'       => 'application/vnd.oasis.opendocument.chart',
        'odb'       => 'application/vnd.oasis.opendocument.database',
        'odf'       => 'application/vnd.oasis.opendocument.formula',
        'odg'       => 'application/vnd.oasis.opendocument.graphics',
        'otg'       => 'application/vnd.oasis.opendocument.graphics-template',
        'odi'       => 'application/vnd.oasis.opendocument.image',
        'odp'       => 'application/vnd.oasis.opendocument.presentation',
        'otp'       => 'application/vnd.oasis.opendocument.presentation-template',
        'ods'       => 'application/vnd.oasis.opendocument.spreadsheet',
        'ots'       => 'application/vnd.oasis.opendocument.spreadsheet-template',
        'odt'       => 'application/vnd.oasis.opendocument.text',
        'odm'       => 'application/vnd.oasis.opendocument.text-master',
        'ott'       => 'application/vnd.oasis.opendocument.text-template',
        'oth'       => 'application/vnd.oasis.opendocument.text-web',
        'potx'      => 'application/vnd.openxmlformats-officedocument.presentationml.template',
        'ppsx'      => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'pptx'      => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xlsx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xltx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'docx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dotx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'vsd'       => 'application/vnd.visio',
        'wbxml'     => 'application/vnd.wap.wbxml',
        'wmlc'      => 'application/vnd.wap.wmlc',
        'wmlsc'     => 'application/vnd.wap.wmlscriptc',
        'vxml'      => 'application/voicexml+xml',
        'bcpio'     => 'application/x-bcpio',
        'vcd'       => 'application/x-cdlink',
        'pgn'       => 'application/x-chess-pgn',
        'cpio'      => 'application/x-cpio',
        'csh'       => 'application/x-csh',
        'dcr'       => 'application/x-director',
        'dir'       => 'application/x-director',
        'dxr'       => 'application/x-director',
        'dvi'       => 'application/x-dvi',
        'spl'       => 'application/x-futuresplash',
        'tgz'       => 'application/x-gtar',
        'gtar'      => 'application/x-gtar',
        'hdf'       => 'application/x-hdf',
        'js'        => 'application/x-javascript',
        'skp'       => 'application/x-koan',
        'skd'       => 'application/x-koan',
        'skt'       => 'application/x-koan',
        'skm'       => 'application/x-koan',
        'latex'     => 'application/x-latex',
        'nc'        => 'application/x-netcdf',
        'cdf'       => 'application/x-netcdf',
        'sh'        => 'application/x-sh',
        'shar'      => 'application/x-shar',
        'swf'       => 'application/x-shockwave-flash',
        'sit'       => 'application/x-stuffit',
        'sv4cpio'   => 'application/x-sv4cpio',
        'sv4crc'    => 'application/x-sv4crc',
        'tar'       => 'application/x-tar',
        'tcl'       => 'application/x-tcl',
        'tex'       => 'application/x-tex',
        'texinfo'   => 'application/x-texinfo',
        'texi'      => 'application/x-texinfo',
        't'         => 'application/x-troff',
        'tr'        => 'application/x-troff',
        'roff'      => 'application/x-troff',
        'man'       => 'application/x-troff-man',
        'me'        => 'application/x-troff-me',
        'ms'        => 'application/x-troff-ms',
        'ustar'     => 'application/x-ustar',
        'src'       => 'application/x-wais-source',
        'xhtml'     => 'application/xhtml+xml',
        'xht'       => 'application/xhtml+xml',
        'xslt'      => 'application/xslt+xml',
        'xml'       => 'application/xml',
        'xsl'       => 'application/xml',
        'dtd'       => 'application/xml-dtd',
        'zip'       => 'application/zip',
        'au'        => 'audio/basic',
        'snd'       => 'audio/basic',
        'mid'       => 'audio/midi',
        'midi'      => 'audio/midi',
        'kar'       => 'audio/midi',
        'mpga'      => 'audio/mpeg',
        'mp2'       => 'audio/mpeg',
        'mp3'       => 'audio/mpeg',
        'aif'       => 'audio/x-aiff',
        'aiff'      => 'audio/x-aiff',
        'aifc'      => 'audio/x-aiff',
        'm3u'       => 'audio/x-mpegurl',
        'wma'       => 'audio/x-ms-wma',
        'wax'       => 'audio/x-ms-wax',
        'ram'       => 'audio/x-pn-realaudio',
        'ra'        => 'audio/x-pn-realaudio',
        'rm'        => 'application/vnd.rn-realmedia',
        'wav'       => 'audio/x-wav',
        'pdb'       => 'chemical/x-pdb',
        'xyz'       => 'chemical/x-xyz',
        'bmp'       => 'image/bmp',
        'cgm'       => 'image/cgm',
        'gif'       => 'image/gif',
        'ief'       => 'image/ief',
        'jpeg'      => 'image/jpeg',
        'jpg'       => 'image/jpeg',
        'jpe'       => 'image/jpeg',
        'png'       => 'image/png',
        'svg'       => 'image/svg+xml',
        'tiff'      => 'image/tiff',
        'tif'       => 'image/tiff',
        'djvu'      => 'image/vnd.djvu',
        'djv'       => 'image/vnd.djvu',
        'wbmp'      => 'image/vnd.wap.wbmp',
        'ras'       => 'image/x-cmu-raster',
        'ico'       => 'image/x-icon',
        'pnm'       => 'image/x-portable-anymap',
        'pbm'       => 'image/x-portable-bitmap',
        'pgm'       => 'image/x-portable-graymap',
        'ppm'       => 'image/x-portable-pixmap',
        'rgb'       => 'image/x-rgb',
        'xbm'       => 'image/x-xbitmap',
        'psd'       => 'image/x-photoshop',
        'xpm'       => 'image/x-xpixmap',
        'xwd'       => 'image/x-xwindowdump',
        'eml'       => 'message/rfc822',
        'igs'       => 'model/iges',
        'iges'      => 'model/iges',
        'msh'       => 'model/mesh',
        'mesh'      => 'model/mesh',
        'silo'      => 'model/mesh',
        'wrl'       => 'model/vrml',
        'vrml'      => 'model/vrml',
        'ics'       => 'text/calendar',
        'ifb'       => 'text/calendar',
        'css'       => 'text/css',
        'csv'       => 'text/csv',
        'html'      => 'text/html',
        'htm'       => 'text/html',
        'txt'       => 'text/plain',
        'asc'       => 'text/plain',
        'rtx'       => 'text/richtext',
        'rtf'       => 'text/rtf',
        'sgml'      => 'text/sgml',
        'sgm'       => 'text/sgml',
        'tsv'       => 'text/tab-separated-values',
        'wml'       => 'text/vnd.wap.wml',
        'wmls'      => 'text/vnd.wap.wmlscript',
        'etx'       => 'text/x-setext',
        'mpeg'      => 'video/mpeg',
        'mpg'       => 'video/mpeg',
        'mpe'       => 'video/mpeg',
        'qt'        => 'video/quicktime',
        'mov'       => 'video/quicktime',
        'mxu'       => 'video/vnd.mpegurl',
        'm4u'       => 'video/vnd.mpegurl',
        'flv'       => 'video/x-flv',
        'asf'       => 'video/x-ms-asf',
        'asx'       => 'video/x-ms-asf',
        'wmv'       => 'video/x-ms-wmv',
        'wm'        => 'video/x-ms-wm',
        'wmx'       => 'video/x-ms-wmx',
        'avi'       => 'video/x-msvideo',
        'ogv'       => 'video/ogg',
        'movie'     => 'video/x-sgi-movie',
        'ice'       => 'x-conference/x-cooltalk',
    );

    /**
     * Used in UUID() function
     * @internal
     */
    private static $uuid = '';

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

        if (@is_file($file) && @is_readable($file)) {
            // Try by *nix command
            try {
                @exec(sprintf('file -bi %s', escapeshellarg($file)), $mime, $exec_result);
                if ($exec_result === 0)
                    return trim($mime[0]);
            } catch (\Exception $e) {}

            // Try using fileinfo (finfo)
            if (class_exists('\\finfo')) {
                try {
                    $finfo = new \finfo(FILEINFO_MIME);
                    if ($finfo && ($mime = @$finfo->file($file)) !== false)
                        return $mime;
                } catch (\Exception $e) {}
            }
        }

        // As a last resort, use the file extension to match it up with a mime
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return (array_key_exists($ext, self::$mime_by_extension)) ? self::$mime_by_extension[$ext] : $default_mime;
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
        $cache_id = 'pf4wp.' . md5($file) .  '.edu'; // 44 chars

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
        if ($spacer !== false) {
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

    /**
     * Generates a UUID
     *
     * @return string
     * @since 1.0.17
     * @api
     */
    public static function UUID()
    {
        // Windows COM extension
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        /* PECL UUID extension - 1000 cycles: 36.863089 ms
         *
         * Note: OSSP extension under the same module name is not
         * supported due to deprecated reference passing
         */
        if (extension_loaded('uuid') && defined('UUID_TYPE_RANDOM')) {
            // PECL UUID extension
            return strtoupper(uuid_create(UUID_TYPE_RANDOM));
        }

        // Generic - 1000 cycles: 49.832106 ms
        if (empty(self::$uuid))
            self::$uuid = wp_salt(); // Seed it first

        $hash = sha1(uniqid(null, true) . self::$uuid);

        self::$uuid = strtoupper(sprintf('%08s-%04s-%04x-%04x-%12s',
            substr($hash,  0,  8),
            substr($hash,  8,  4),
            (hexdec(substr($hash,  12,  4)) & 0x0fff) | 0x4000, // Version 4
            (hexdec(substr($hash,  16,  4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        ));

        return self::$uuid;
    }
}

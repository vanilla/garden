<?php

namespace Garden;

/**
 * Application configuration management.
 *
 * This class provides access to the application configuration information through one or more config files.
 * You can load/save config files in several formats. The file extension of the file determines what format will the file will use.
 * The following file formats are supported.
 *
 * - javascript object notation (json): .json or .json.php
 * - php source code: .php
 * - php serialized arrays: .ser or .ser.php
 * - yaml: .yml or .yml.php
 *
 * When using config files we recommend always using the .*.php extension so that the file cannot be read through its url.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009 Vanilla Forums Inc.
 * @license LGPL-2.1
 * @package Vanilla
 * @since 1.0
 */
class Config {
    /// Properties ///

    /**
     * @var array The config data.
     */
    protected static $data;

    /**
     * @var string The default path to load/save to.
     */
    protected static $defaultPath;

    /// Methods ///

    public static function defaultPath($value = '') {
        if ($value) {
            self::$defaultPath = $value;
        } elseif (!self::$defaultPath) {
            self::$defaultPath = PATH_ROOT.'/conf/config.json.php';
        }
    }

    /**
     * Return all of the config data.
     * @return array Returns an array of config data.
     */
    public static function data() {
        return self::$data;
    }

    /**
     * Get a setting from the config.
     *
     * @param string $key The config key.
     * @param mixed $default The default value if the config file doesn't exist.
     * @return mixed The value at {@link $key} or {@link $default} if the key isn't found.
     * @see \config()
     */
    public static function get($key, $default = null) {
        if (array_key_exists($key, self::$data)) {
            return self::$data[$key];
        } else {
            return $default;
        }
    }

    /**
     * Encode an array in ini format.
     * The resulting array will work with parse_ini_file() and parse_ini_string().
     *
     * @param array $data A flat, associative array of data.
     * @return string The data in ini format.
     */
//    public static function iniEncode($data) {
//        ksort($data, SORT_NATURAL | SORT_FLAG_CASE);
//
//        $result = '';
//
//        $lastSection = null;
//
//        foreach ($data as $key => $value) {
//            $section = trim(strstr($key, '.', true), '.');
//
//            if ($section !== $lastSection) {
//                if ($section) {
//                    $result .= "\n[$section]\n";
//                }
//                $lastSection = $section;
//            }
//
//            $result .= $key . ' = ';
//
//            if (is_bool($value)) {
//                $str = $value ? 'true' : 'false';
//            } elseif (is_numeric($value)) {
//                $str = $value;
//            } elseif (is_string($value)) {
//                $str = '"' . addcslashes($value, "\"") . '"';
//            }
//            $result .= $str . "\n";
//        }
//
//        return $result;
//    }

    /**
     * Load configuration data from a file.
     *
     * @param string $path An optional path to load the file from.
     * @param string $path If true the config will be put under the current config, not over it.
     * @param string $php_var The name of the php variable to load from if using the php file type.
     */
    public static function load($path = '', $underlay = false, $php_var = 'config') {
        if (!$path) {
            $path = self::$defaultPath;
        }

        $loaded = array_load($path, $php_var);

        if (empty($loaded)) {
            return;
        }

        if (!is_array(self::$data)) {
            self::$data = [];
        }

        if ($underlay) {
            self::$data = array_replace($loaded, self::$data);
        } else {
            self::$data = array_replace(self::$data, $loaded);
        }
    }

    /**
     * Save data to the config file.
     *
     * @param array $data The config data to save.
     * @param string $path An optional path to save the data to.
     * @param string $php_var The name of the php variable to load from if using the php file type.
     * @return bool Returns true if the save was successful or false otherwise.
     * @throws \InvalidArgumentException Throws an exception when the saved data isn't an array.
     */
    public static function save($data, $path = null, $php_var = 'config') {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Config::save(): Argument #1 is not an array.', 400);
        }

        if (!$path) {
            $path = static::defaultPath();
        }

        // Load the current config information so we know what to replace.
        $config = array_load($path, $php_var);
        // Merge the new config into the current config.
        $config = array_replace($config, $data);
        // Remove null config values.
        $config = array_filter($config, function ($value) {
            return $value !== null;
        });

        ksort($config, SORT_NATURAL | SORT_FLAG_CASE);

        $result = array_save($config, $path, $php_var);
        return $result;
    }
}

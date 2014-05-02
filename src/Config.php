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
    protected $data;

    /**
     * @var string The default path to load/save to.
     */
    public $defaultPath;

    /**
     * @var Config The singleton instance of the config.
     */
    static $instance;

    /// Methods ///

    public function __construct($default_path = '') {
        if (!$default_path) {
            $default_path = PATH_ROOT . '/conf/config.json.php';
        }
        $this->defaultPath = $default_path;
        $this->data = array();
    }

    /**
     * Return the singleton instance of this class.
     * @return \Garden\Config
     */
    public function __invoke() {
        return self::instance();
    }

    /**
     * Return all of the config data.
     * @return array Returns an array of config data.
     */
    public function data() {
        return $this->data;
    }

    /**
     * Get a setting from the config.
     * @param string $key The config key.
     * @param mixed $default The default value if the config file doesn't exist.
     * @see \config()
     */
    public function get($key, $default = null) {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
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
     * Get or set the singleton instance of this class.
     *
     * If you subclass the config object to provide a non-default implementation of config loading/saving
     * then you must set your subclass with this function.
     * @param Config $value The Config instance to install.
     * @return Config
     */
    public static function instance($value = null) {
        if ($value !== null) {
            self::$instance = $value;
        } elseif (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    /**
     * Load configuration data from a file into an array.
     *
     * @param string $path The path to load the file from.
     * @param string $php_var The name of the php variable to load from if using the php file type.
     * @return array The configuration data.
     */
    public static function loadArray($path, $php_var = 'config') {
        if (!file_exists($path))
            return array();

        // Get the extension of the file, but allow for .ini.php, .json.php etc.
        $ext = strstr(basename($path), '.');

        switch ($ext) {
//            case '.ini':
//            case '.ini.php':
//                $loaded = parse_ini_file($path, false, INI_SCANNER_RAW);
//                break;
            case '.json':
            case '.json.php':
                $loaded = json_decode(file_get_contents($path), true);
                break;
            case '.php':
                include $path;
                $loaded = $$php_var;
                break;
            case '.ser':
            case '.ser.php':
                $loaded = unserialize(file_get_contents($path));
                break;
            case '.yml':
            case '.yml.php':
                $loaded = yaml_parse_file($path);
                break;
            default:
                throw new \InvalidArgumentException("Invalid config extension $ext on $path.", 400);
        }
        return $loaded;
    }

    /**
     * Load configuration data from a file.
     * @param string $path An optional path to load the file from.
     * @param string $path If true the config will be put under the current config, not over it.
     * @param string $php_var The name of the php variable to load from if using the php file type.
     */
    public function load($path = '', $underlay = false, $php_var = 'config') {
        if (!$path) {
            $path = $this->defaultPath;
        }

        $loaded = static::loadArray($path, $php_var);

        if (empty($loaded))
            return;

        if ($underlay) {
            $this->data = array_replace($loaded, $this->data);
        } else {
            $this->data = array_replace($this->data, $loaded);
        }
    }

    /**
     * Encode a php array nicely.
     *
     * @param array $data The data to encode.
     */
    public static function phpEncode($data, $php_var = 'config') {
        if (is_array($data)) {
            $result = '';
            $lastHeading = '';
            foreach ($data as $key => $value) {
                // Figure out the heading.
                if (($pos = strpos($key, '.')) !== false) {
                    $heading = str_replace(array("\n", "\r"), ' ', substr($key, 0, $pos));
                } else {
                    $heading = substr($key, 0, 1);
                }

                if ($heading !== $lastHeading) {
                    if (strlen($heading) === 1) {
                        // Don't emit single letter headings, but space them out.
                        $result .= "\n";
                    } else {
                        $result .= "\n// ".$heading."\n";
                    }
                    $lastHeading = $heading;
                }

                $result .= '$'.$php_var.'['.var_export($key, true).'] = '.var_export($value, true).";\n";
            }
        } else {
            $result = "\$$php_var = ".var_export($data, true).";\n";
        }
        return $result;
    }

    /**
     *
     * @param array $data The config data to save.
     * @param string $path An optional path to save the data to.
     * @param type $php_var The name of the php variable to load from if using the php file type.
     * @return bool Returns true if the save was successful or false otherwise.
     */
    public function save($data, $path = null, $php_var = 'config') {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Config->save(): Argument #1 is not an array.', 400);
        }

        if (!$path) {
            $path = $this->defaultPath;
        }

        // Load the current config information so we know what to replace.
        $config = static::loadArray($path, $php_var);
        // Merge the new config into the current config.
        $config = array_replace($config, $data);
        // Remove null config values.
        $config = array_filter($config, function($value) {
            return $value !== null;
        });

        ksort($config, SORT_NATURAL | SORT_FLAG_CASE);

        $result = static::saveArray($config, $path, $php_var);
        return $result;
    }


    /**
     * Save an array of data to a specified path.
     *
     * @param array $data The data to save.
     * @param string $path The path to save to.
     * @param string $php_var The name of the php variable to load from if using the php file type.
     * @return bool Returns true if the save was successful or false otherwise.
     */
    public static function saveArray($data, $path, $php_var = 'config') {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Config::saveArray(): Argument #1 is not an array.', 400);
        }

        // Get the extension of the file, but allow for .ini.php, .json.php etc.
        $ext = strstr(basename($path), '.');

        switch ($ext) {
//            case '.ini':
//            case '.ini.php':
//                $ini = static::iniEncode($config);
//                $result = file_put_contents_safe($path, $ini);
//                break;
            case '.json':
            case '.json.php':
                if (defined('JSON_PRETTY_PRINT')) {
                    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                } else {
                    $json = json_encode($data);
                }
                $result = file_put_contents_safe($path, $json);
                break;
            case '.php':
                $php = "<?php\n".static::phpEncode($data, $php_var)."\n";
                $result = file_put_contents_safe($path, $php);
                break;
            case '.ser':
            case '.ser.php':
                $ser = serialize($data);
                $result = file_put_contents_safe($path, $ser);
                break;
            case '.yml':
            case '.yml.php':
                $yml = yaml_emit($data, YAML_UTF8_ENCODING, YAML_LN_BREAK);
                $result = file_put_contents_safe($path, $yml);
                break;
            default:
                throw new \InvalidArgumentException("Invalid config extension $ext on $path.", 400);
        }
        return $result;
    }
}

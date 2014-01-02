<?php

namespace Vanilla;

/**
 * Contains functionality that allows addons to be be enabled and disabled within the application to
 * enhance or change the application's functionality.
 *
 * An addon can do the following.
 *
 * 1. Any classes that the addon defines in its root, class, controllers, models, and modules
 *    directories are made available.
 * 2. The addon can contain a bootstrap.php which will be included at the app startup.
 * 3. If the addon declares any classes ending in *Plugin then those plugins will automatically
 *    bind their event handlers.
 */
class Addon {
    /// Constants ///
    const K_BOOTSTRAP = 'bootstrap'; // bootstrap path key
    const K_CLASSES = 'classes';
    const K_DIR = 'dir';
    const K_INFO = 'info'; // addon info key

    /// Properties ///

    /**
     * @var array An array that maps addon keys to full addon information.
     */
    protected static $addonMap;

    /**
     * @var array An array that maps class names to their fully namespaced class names.
     */
//    protected static $basenameMap;

    /**
     * @var array An array that maps class names to file paths.
     */
    protected static $classMap;

    /**
     * @var array An array of enabled addon keys.
     */
    protected static $enabled;

    /// Methods ///

    public static function addonMap($addon_key = null, $key = null) {
        if (self::$addonMap === null) {
            self::$addonMap = static::cacheGet('addon-map', array(get_class(), 'scanAddons'));
        }
        if ($addon_key === null)
            return self::$addonMap;
        else {
            $addon = val(strtolower($addon_key), self::$addonMap);
            if ($addon && $key) {
                return val($key, $addon);
            } elseif ($addon) {
                return $addon;
            } else {
                return null;
            }
        }
    }

    public static function autoload($class_name) {
        if ($path = static::classMap($class_name)) {
            require $path;
        }
    }

    protected static function cacheGet($key, $cache_cb) {
        // Salt the cache with the root path so that it will be invalidate if the app is moved.
        $salt = substr(md5(PATH_ROOT), 0, 10);

        $cache_path = PATH_ROOT."/cache/$key-$salt.json.php";
        if (file_exists($cache_path)) {
            $result = Config::loadArray($cache_path);
            return $result;
        } else {
            $result = $cache_cb();
            Config::saveArray($result, $cache_path);
        }
        return $result;
    }

    public static function classMap($class_name = null) {
        if (self::$classMap === null) {
            if (is_array(self::$enabled)) {
                $class_map = array();

                // Walk through the enabled plugins and add their classes to the class map.
                foreach (self::$enabled as $addon_key => $value) {
                    if ($addon_classes = static::addonMap($addon_key, self::K_CLASSES)) {
                        $class_map = array_merge($class_map, $addon_classes);
                    }
                }
                self::$classMap = $class_map;
            } else {
                self::$classMap = array();
            }
        }

        if ($class_name !== null)
            return val(strtolower($class_name), self::$classMap);
        else
            return self::$classMap;
    }

    public static function info($addon_key) {
        return static::addonMap($addon_key, self::K_INFO);
    }

    /**
     * Scan an addon directory for information.
     * @param string $dir
     */
    protected static function scanAddon($dir) {
        $dir = rtrim($dir, '/');
        $key = strtolower(basename($dir));

        // Look for the addon info array.
        $info_path = $dir.'/addon.json';
        $info = false;
        if (file_exists($info_path)) {
            $info = json_decode(file_get_contents($info_path), true);
        }
        if (!$info)
            $info = array();
        touch_val('name', $info, $key);
        touch_val('version', $info, '0.0');

        // Look for the boostrap.
        $boostrap = $dir.'/boostrap.php';
        if (!file_exists($dir.'/boostrap.php')) {
            $boostrap = '';
        }

        // Scan the appropriate subdirectories  for classes.
        $subdirs = array('/', '/classes', '/controllers', '/models', '/modules');
        $classes = array();
        foreach ($subdirs as $subdir) {
            // Get all of the php files in the subdirectory.
            $paths = glob($dir.$subdir.'/*.php');
            foreach ($paths as $path) {
                $decls = static::scanFile($path);
                foreach ($decls as $namespace_row) {
                    if (isset($namespace_row['namespace'])) {
                        $namespace = rtrim($namespace_row['namespace'], '\\').'\\';
                        $namespace_classes = $namespace_row['classes'];
                    } else {
                        $namespace = '';
                        $namespace_classes = $namespace_row;
                    }

                    foreach ($namespace_classes as $class_row) {
                        $classes[strtolower($namespace.$class_row['name'])] = $path;
                    }
                }
            }
        }

        $result = array(
            self::K_BOOTSTRAP => $boostrap,
            self::K_CLASSES => $classes,
            self::K_DIR => $dir,
            self::K_INFO => $info
        );

        return array($key, $result);
    }

    protected static function scanAddons($dir = null) {
        if (!$dir)
            $dir = PATH_ROOT.'/addons';

        $result = array();
        foreach (new \DirectoryIterator($dir) as $subdir) {
            if ($subdir->isDir() && !$subdir->isDot()) {
                list($addon_key, $addon_classes) = static::scanAddon($subdir->getPathname());
                $result[$addon_key] = $addon_classes;
            }
        }
        return $result;
    }

    /**
     *
     * Looks what classes and namespaces are defined in that file and returns the first found
     * @param string $file Path to file.
     * @return array Returns an empty array if no classes are found or an array with namespaces and classes found in the file.
     * @see http://stackoverflow.com/a/11114724/1984219
     */
    protected static function scanFile($file) {
        $classes = $nsPos = $final = array();
        $foundNS = FALSE;
        $ii = 0;

        if (!file_exists($file))
            return array();

        $er = error_reporting();
        error_reporting(E_ALL ^ E_NOTICE);

        $php_code = file_get_contents($file);
        $tokens = token_get_all($php_code);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!$foundNS && $tokens[$i][0] == T_NAMESPACE) {
                $nsPos[$ii]['start'] = $i;
                $foundNS = TRUE;
            } elseif ($foundNS && ($tokens[$i] == ';' || $tokens[$i] == '{')) {
                $nsPos[$ii]['end'] = $i;
                $ii++;
                $foundNS = FALSE;
            } elseif ($i - 2 >= 0 && $tokens[$i - 2][0] == T_CLASS && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                if ($i - 4 >= 0 && $tokens[$i - 4][0] == T_ABSTRACT) {
                    $classes[$ii][] = array('name' => $tokens[$i][1], 'type' => 'ABSTRACT CLASS');
                } else {
                    $classes[$ii][] = array('name' => $tokens[$i][1], 'type' => 'CLASS');
                }
            } elseif ($i - 2 >= 0 && $tokens[$i - 2][0] == T_INTERFACE && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                $classes[$ii][] = array('name' => $tokens[$i][1], 'type' => 'INTERFACE');
            }
        }
        error_reporting($er);
        if (empty($classes))
            return array();

        if (!empty($nsPos)) {
            foreach ($nsPos as $k => $p) {
                $ns = '';
                for ($i = $p['start'] + 1; $i < $p['end']; $i++) {
                    $ns .= $tokens[$i][1];
                }

                $ns = trim($ns);
                $final[$k] = array('namespace' => $ns, 'classes' => $classes[$k + 1]);
            }
            $classes = $final;
        }
        return $classes;
    }

    public static function start($addons = null) {
        // Load the addons from the config if they aren't passed in.
        if (!is_array($addons)) {
            $addons = config('addons', array());
        }
        // Reformat the enabled array into the form: array('addon_key' => 'addon_key')
        $enabled = array_keys(array_change_key_case(array_filter($addons)));
        $enabled = array_combine($enabled, $enabled);
        self::$enabled = $enabled;
        self::$classMap = null; // invalidate so it will rebuild

        // Enable the addon autoloader.
        spl_autoload_register(array(get_class(), 'autoload'), true);

        // Start each of the enabled addons.
        foreach (self::$enabled as $key => $value) {
            static::startAddon($key);
        }
    }

    public static function startAddon($addon_key) {
        $addon = static::addonMap($addon_key);
        if (!$addon)
            return false;

        // Bind  all of the plugin events.
        foreach ($addon[self::K_CLASSES] as $class_name => $class_path) {
            if (str_ends($class_name, 'plugin')) {
                Event::bindClass($class_name);
            }
        }

        // Run the class' bootstrap.
        if ($bootstrap_path = $addon[self::K_BOOTSTRAP]) {
            include_once $bootstrap_path;
        }
    }
}
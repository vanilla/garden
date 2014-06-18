<?php

namespace Garden;

/**
 * Contains functionality that allows addons to be be enabled and disabled within the application to
 * enhance or change the application's functionality.
 *
 * An addon can do the following.
 *
 * 1. Any classes that the addon defines in its root, /controllers, /library, /models, and /modules
 *    directories are made available.
 * 2. The addon can contain a bootstrap.php which will be included at the app startup.
 * 3. If the addon declares any classes ending in *Plugin then those plugins will automatically
 *    bind their event handlers. (also *Hooks)
 */
class Addons {
    /// Constants ///
    const K_BOOTSTRAP = 'bootstrap'; // bootstrap path key
    const K_CLASSES = 'classes';
    const K_DIR = 'dir';
    const K_INFO = 'info'; // addon info key

    /// Properties ///

    /**
     * @var array An array that maps addon keys to full addon information.
     */
    protected static $all;

    /**
     * @var string The base directory where all of the addons are found.
     */
    protected static $baseDir;

    /**
     * @var array An array that maps class names to their fully namespaced class names.
     */
//    protected static $basenameMap;

    /**
     * @var array An array that maps class names to file paths.
     */
    protected static $classMap;

    /**
     * @var array An array that maps addon keys to full addon information for enabled addons.
     */
    protected static $enabled;

    /**
     * @var array An array of enabled addon keys.
     */
    protected static $enabledKeys;

    /**
     * @var bool Signals that the addon framework is in a shared environment and shouldn't use the enabled cache.
     */
    public static $sharedEnvironment;

    /// Methods ///

    /**
     * Get all of the available addons or a single addon from the available list.
     *
     * @param string $addon_key If you supply an addon key then only that addon will be returned.
     * @param string $key Supply one of the Addons::K_* constants to get a specific key from the addon.
     * @return Returns the addon with the given key or all available addons if no key is passed.
     */
    public static function all($addon_key = null, $key = null) {
        if (self::$all === null) {
            self::$all = static::cacheGet('addons-all', array(get_class(), 'scanAddons'));
        }

        // The array should be built now return the addon.
        if ($addon_key === null) {
            return self::$all;
        } else {
            $addon = val(strtolower($addon_key), self::$all);
            if ($addon && $key) {
                return val($key, $addon);
            } elseif ($addon) {
                return $addon;
            } else {
                return null;
            }
        }
    }

    /**
     * An autoloader that will autoload a class based on which addons are enabled.
     *
     * @param string $classname The name of the class to load.
     */
    public static function autoload($classname) {
        list($fullClass, $path) = static::classMap($classname);
        if ($path) {
            require $path;
        }
    }

    /**
     * Gets/sets the base directory for addons.
     *
     * @param string $value Pass a value to set the new base directory.
     * @return string Returns the base directory for addons.
     */
    public static function baseDir($value = null) {
        if ($value !== null) {
            self::$baseDir = rtrim($value, '/');
        } elseif (self::$baseDir === null) {
            self::$baseDir = PATH_ROOT.'/addons';
        }
    }


    /**
     * Start up the addon framework.
     *
     * @param array $enabled_addons An array of enabled addons.
     */
    public static function bootstrap($enabled_addons = null) {
        // Load the addons from the config if they aren't passed in.
        if (!is_array($enabled_addons)) {
            $enabled_addons = config('addons', array());
        }
        // Reformat the enabled array into the form: array('addon_key' => 'addon_key')
        $enabled_keys = array_keys(array_change_key_case(array_filter($enabled_addons)));
        $enabled_keys = array_combine($enabled_keys, $enabled_keys);
        self::$enabledKeys = $enabled_keys;
        self::$classMap = null; // invalidate so it will rebuild

        // Enable the addon autoloader.
        spl_autoload_register(array(get_class(), 'autoload'), true, true);

        // Bind all of the addon plugin events now.
        foreach (self::enabled() as $addon) {
            if (!isset($addon[self::K_CLASSES])) {
                continue;
            }

            foreach ($addon[self::K_CLASSES] as $class_name => $class_path) {
                if (str_ends($class_name, 'plugin')) {
                    Event::bindClass($class_name);
                } elseif (str_ends($class_name, 'hooks')) {
                    // Vanilla 2 used hooks files for themes and applications.
                    $basename = ucfirst(rtrim_substr($class_name, 'hooks'));
                    deprecated($basename.'Hooks', $basename.'Plugin');
                    Event::bindClass($class_name);
                }
            }
        }

        Event::bind('bootstrap', function() {
            // Start each of the enabled addons.
            foreach (self::enabled() as $key => $value) {
                static::startAddon($key);
            }
        });
    }

    private static function cacheGet($key, $cache_cb) {
        // Salt the cache with the root path so that it will invalidate if the app is moved.
        $salt = substr(md5(static::baseDir()), 0, 10);

        $cache_path = PATH_ROOT."/cache/$key-$salt.json.php";
        if (file_exists($cache_path)) {
            $result = array_load($cache_path);
            return $result;
        } else {
            $result = $cache_cb();
            array_save($result, $cache_path);
        }
        return $result;
    }

    /**
     * A an array that maps class names to physical paths.
     *
     * @param string $classname An optional class name to get the path of.
     * @return array Returns an array in the form `[fullClassname, classPath]`.
     * If no {@link $classname} is passed then the entire class map is returned.
     * @throws \Exception Throws an exception if the class map is corrupt.
     */
    public static function classMap($classname = null) {
        if (self::$classMap === null) {
            // Loop through the enabled addons and grab their classes.
            $class_map = array();
            foreach (static::enabled() as $addon) {
                if (isset($addon[self::K_CLASSES])) {
                    $class_map = array_replace($class_map, $addon[self::K_CLASSES]);
                }
            }
            self::$classMap = $class_map;
        }

        // Now that the class map has been built return the result.
        if ($classname !== null) {
            if (strpos($classname, '\\') === false) {
                $basename = strtolower($classname);
            } else {
                $basename = strtolower(trim(strrchr($classname, '\\'), '\\'));
            }

            $row = val($basename, self::$classMap);

            if ($row === null) {
                return ['', ''];
            } elseif (is_string($row)) {
                return [$classname, $row];
            } elseif (is_array($row)) {
                return  $row;
            } else {
                return ['', ''];
            }
        } else {
            return self::$classMap;
        }
    }

    /**
     * Get all of the enabled addons or a single addon from the enabled list.
     *
     * @param string $addon_key If you supply an addon key then only that addon will be returned.
     * @param string $key Supply one of the Addons::K_* constants to get a specific key from the addon.
     * @return Returns the addon with the given key or all enabled addons if no key is passed.
     */
    public static function enabled($addon_key = null, $key = null) {
        // Lazy build the enabled array.
        if (self::$enabled === null) {
            // Make sure the enabled addons have been added first.
            if (self::$enabledKeys === null) {
                throw new \Exception("Addons::boostrap() must be called before Addons::enabled() can be called.", 500);
            }

            if (self::$all !== null || self::$sharedEnvironment) {
                // Build the enabled array by filtering the all array.
                self::$enabled = array();
                foreach (self::all() as $key => $row) {
                    if (isset($key, self::$enabledKeys)) {
                        self::$enabled[$key] = $row;
                    }
                }
            } else {
                // Build the enabled array by walking the addons.
                self::$enabled = static::cacheGet('addons-enabled', function() {
                    return static::scanAddons(null, self::$enabledKeys);
                });
            }
        }

        // The array should be built now return the addon.
        if ($addon_key === null) {
            return self::$enabled;
        } else {
            $addon = val(strtolower($addon_key), self::$enabled);
            if ($addon && $key) {
                return val($key, $addon);
            } elseif ($addon) {
                return $addon;
            } else {
                return null;
            }
        }
    }

    /**
     * Return the info array for an addon.
     *
     * @param string $addon_key The addon key.
     * @return array|null Returns the addon's info array or null if the addon wasn't found.
     */
    public static function info($addon_key) {
        $addon_key = strtolower($addon_key);

        // Check the enabled array first so that we don't load all addons if we don't have to.
        if (isset(self::$enabledKeys[$addon_key])) {
            return static::enabled($addon_key, self::K_INFO);
        } else {
            return static::all($addon_key, self::K_INFO);
        }
    }

    /**
     * Scan an addon directory for information.
     * @param string $dir
     */
    private static function scanAddonRecursive($dir, $enabled = null, &$addons) {
        $dir = rtrim($dir, '/');
        $addon_key = strtolower(basename($dir));

        // Scan the addon if it is enabled.
        if ($enabled === null || in_array($addon_key, $enabled)) {
            list($addon_key, $addon) = static::scanAddon($dir);
        } else {
            $addon = null;
        }

        // Add the addon to the collection array if one was supplied.
        if ($addon !== null)
            $addons[$addon_key] = $addon;

        // Recurse.
        $addon_subdirs = array('/addons');
        foreach ($addon_subdirs as $addon_subdir) {
            if (is_dir($dir.$addon_subdir)) {
                static::scanAddons($dir.$addon_subdir, $enabled, $addons);
            }
        }

        return array($addon_key, $addon);
    }

    /**
     * Scan an individual addon directory and return the information about that addon.
     * @param string $dir The path to the addon.
     * @return array An array in the form of `[$addon_key, $addon_row]` or `[$addon_key, null]` if the directory doesn't represent an addon.
     */
    protected static function scanAddon($dir) {
        $dir = rtrim($dir, '/');
        $addon_key = strtolower(basename($dir));

        // Look for the addon info array.
        $info_path = $dir.'/addon.json';
        $info = false;
        if (file_exists($info_path)) {
            $info = json_decode(file_get_contents($info_path), true);
        }
        if (!$info) {
            $info = array();
        }
        array_touch('name', $info, $addon_key);
        array_touch('version', $info, '0.0');

        // Look for the bootstrap.
        $bootstrap = $dir.'/bootstrap.php';
        if (!file_exists($dir.'/bootstrap.php')) {
            $bootstrap = null;
        }

        // Scan the appropriate subdirectories  for classes.
        $subdirs = array('', '/library', '/controllers', '/models', '/modules', '/settings');
        $classes = array();
        foreach ($subdirs as $subdir) {
            // Get all of the php files in the subdirectory.
            $paths = glob($dir.$subdir.'/*.php');
            foreach ($paths as $path) {
                $decls = static::scanFile($path);
                foreach ($decls as $namespace_row) {
                    if (isset($namespace_row['namespace']) && $namespace_row) {
                        $namespace = rtrim($namespace_row['namespace'], '\\').'\\';
                        $namespace_classes = $namespace_row['classes'];
                    } else {
                        $namespace = '';
                        $namespace_classes = $namespace_row;
                    }

                    foreach ($namespace_classes as $class_row) {
                        $classes[strtolower($class_row['name'])] = [$namespace.$class_row['name'], $path];
                    }
                }
            }
        }

        $addon = array(
            self::K_BOOTSTRAP => $bootstrap,
            self::K_CLASSES => $classes,
            self::K_DIR => $dir,
            self::K_INFO => $info
        );

        return array($addon_key, $addon);
    }

    protected static function scanAddons($dir = null, $enabled = null, &$addons = null) {
        if (!$dir) {
            $dir = static::$baseDir;
        }
        if ($addons === null) {
            $addons = array();
        }

        foreach (new \DirectoryIterator($dir) as $subdir) {
            if ($subdir->isDir() && !$subdir->isDot()) {
//                echo $subdir->getPathname().$subdir->isDir().$subdir->isDot().'<br />';
                static::scanAddonRecursive($subdir->getPathname(), $enabled, $addons);
            }
        }
        return $addons;
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

        if (!file_exists($file)) {
            return array();
        }

        $er = error_reporting();
        error_reporting(E_ALL ^ E_NOTICE);

        $php_code = file_get_contents($file);
        $tokens = token_get_all($php_code);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!$foundNS && $tokens[$i][0] == T_NAMESPACE) {
                $nsPos[$ii]['start'] = $i;
                $foundNS = true;
            } elseif ($foundNS && ($tokens[$i] == ';' || $tokens[$i] == '{')) {
                $nsPos[$ii]['end'] = $i;
                $ii++;
                $foundNS = false;
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
        if (empty($classes)) {
            return [];
        }

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

    public static function startAddon($addon_key) {
        $addon = static::enabled($addon_key);
        if (!$addon)
            return false;

        // Run the class' bootstrap.
        if ($bootstrap_path = val(self::K_BOOTSTRAP, $addon)) {
            include_once $bootstrap_path;
        }
    }
}
<?php

namespace Vanilla;

class Addons {
    /// Properties ///

    /// Methods ///

    /**
     * Scan a
     * @param type $dir
     */
    protected static function scanAddon($dir) {
        $dir = rtrim($dir, '/');
        $key = basename($dir);
        $subdirs = array('/classes', '/controllers', '/models', '/modules');
        $classes = array();

        foreach ($subdirs as $subdir) {
            // Get all of the php files in the subdirectory.
            $paths = glob($dir.$subdir.'/*.php');
            foreach ($paths as $path) {
                $decls = $this->scanFile($path);
                foreach ($decls as $namespace_row) {
                    if (isset($namespace_row['namespace'])) {
                        $namespace = rtrim($namespace_row['namespace'], '\\').'\\';
                        $namespace_classes = $namespace_row['classes'];
                    } else {
                        $namespace = '';
                        $namespace_classes = $namespace_row;
                    }

                    foreach ($namespace_classes as $class_row) {
                        $classes[$namespace.$class_row['name']] = $path;
                    }
                }
            }
        }

        return array($key, $classes);
    }

    protected static function scanAddons($dir = null) {
        if (!$dir)
            $dir = PATH_ROOT.'/addons';

        $result = array();
        foreach (new \DirectoryIterator($dir) as $subdir) {
            if ($subdir->isDir() && !$subdir->isDot()) {
                list($addon_key, $addon_classes) = $this->scanAddon($subdir->getPathname());
                $result[$addon_key] = $addon_classes;
            }
        }
        return $result;
    }

    /**
     *
     * Looks what classes and namespaces are defined in that file and returns the first found
     * @param string $file Path to file.
     * @return array Returns an empty array if none is found or an array with namespaces and classes found in file.
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
            return NULL;

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
        // Grab the full autoloader cache.
        $addon_map = $this->scanAddons();
    }
}
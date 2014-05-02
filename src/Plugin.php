<?php

namespace Garden;

abstract class Plugin {
    /// Properties ///

    /**
     *
     * @var array The singleton instances of the plugin subclasses.
     */
    protected static $instances;

    /// Methods ///

    public static function instance() {
        $class_name = get_called_class();

        if (!isset(self::$instances[$class_name])) {
            self::$instances[$class_name] = new $class_name();
        }
        return self::$instances[$class_name];

    }
}


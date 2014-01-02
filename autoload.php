<?php

/*
 * Defines and registers an autoloader for the vanilla framework.
 *
 * Include this file if you are not using another autoloader such as the autoloader build with composer.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009 Vanilla Forums Inc.
 * @license LGPL-2.1
 * @package Vanilla
 * @since 1.0
 */

/**
 * A psr-0 compatible autoloader for the Vanilla framework.
 * @param string $class_name
 */
function autoload_vanilla($class_name) {
    $class_name = ltrim($class_name, '\\');

    // Only load classes in the Vanilla\ namespace.
    if (strpos($class_name, 'Vanilla\\') !== 0)
        return;

    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strrpos($class_name, '\\')) {
        $namespace = substr($class_name, 0, $lastNsPos);
        $class_name = substr($class_name, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $class_name) . '.php';
    $fileName = __DIR__.'/'.$fileName;

    if (file_exists($fileName)) {
        require $fileName;
    }
}
spl_autoload_register('autoload_vanilla');

// The autoload files must be loaded after the autoloader.
require_once __DIR__.'/autoload_files.php';
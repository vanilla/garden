<?php

use \Vanilla\Event;

function autoload_vanilla($className) {
    $className = ltrim($className, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
    $fileName = __DIR__.'/'.$fileName;

    if (file_exists($fileName)) {
        require $fileName;
    }
}
spl_autoload_register('autoload_vanilla');

require_once __DIR__.'/functions/core-functions.php';

// Load the framework's overrideable functions as late as possible so that addons can override them.
Event::bind('framework_loaded', function() {
    require_once __DIR__.'/functions/formatting-functions.php';
}, Event::PRIORITY_LOW);
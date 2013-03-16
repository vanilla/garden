<?php

// Include the core functions.
require_once __DIR__.'/functions.core.php';
//require_once __DIR__.'/functions.commandline.php';
require_once __DIR__.'/functions.error.php';

spl_autoload_register('autoloadDir');

function feature($name, $force = true) {
   switch (strtolower($name)) {
      case 'commandline':
         if (PHP_SAPI !== 'cli')
            trigger_error("This script must be called from the command line.", E_USER_ERROR);
         
         require_once __DIR__.'/functions.commandline.php';
         break;
      default:
         trigger_error("Unknown feature: $name.", E_USER_ERROR);
   }
}
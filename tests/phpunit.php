<?php

// Define some paths to help with testing.
define('PATH_ROOT', realpath(__DIR__.'/..'));
define('PATH_SRC', PATH_ROOT.'/src');
define('PATH_CACHE', PATH_ROOT.'/cache');

// Autoload all of the classes.
require PATH_ROOT.'/vendor/autoload.php';

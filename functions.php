<?php

// Include the core functions.
require_once __DIR__.'/functions/core-functions.php';

// Load the framework's overrideable functions as late as possible so that addons can override them.
Event::bind('framework_loaded', function() {
    require_once __DIR__.'/functions/formatting-functions.php';
}, Event::PRIORITY_LOW);

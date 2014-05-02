<?php

/*
 * Includes the appropriate function and constant files necessary to run the vanilla framework.
 *
 * This file must be included *after* the framework has been registered with an autoloader.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009 Vanilla Forums Inc.
 * @license MIT
 * @package Vanilla
 * @since 1.0
 */

use \Garden\Event;

// Include the core functions.
require_once __DIR__.'/core-functions.php';

// Load the framework's overrideable functions late so that addons can override them.
Event::bind('bootstrap', function () {
    require_once __DIR__.'/formatting-functions.php';
}, Event::PRIORITY_LOW);

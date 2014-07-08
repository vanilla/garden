![Garden](http://cdn.vanillaforums.com/garden-logo-400.svg)
===========================================================

[![Build Status](https://img.shields.io/travis/vanilla/garden.svg?style=flat)](https://travis-ci.org/vanilla/garden) [![Coverage](http://img.shields.io/coveralls/vanilla/garden.svg?style=flat)](https://coveralls.io/r/vanilla/garden)

Garden is a mini framework for building pluggable web applications and apis. ***This framework is currently a work in progress and should be considered alpha quality code right now.***

Howdy, Stranger!
----------------

The garden framwework was born out of our work on [Vanilla Forums](http://vanillaforums.com) over the past four years. We've built a forum that started out simple, but has grown into a massively scalable, customizable solution with hundreds of plugins and themes. Over these years we've learned a lot about what we need and what we don't need in a framework, and garden is the result.

Garden is heavily inspired by the current work being done on micro frameworks around the web, most notably the [Slim Framework](http://www.slimframework.com/). We're calling garden a _mini framework_ because it has its roots in micro frameworks, but we're packing in enough functionality that it's too big to be considered micro.

Garden at a Glance
------------------

### Restful Routing

You can make a simple api by easily routing to closures or route to controllers to make a more advanced applications.

### Addons and Events

Make your application extendable with garden's addon and event framework. If you are familiar with Vanilla you can think of addons as the union of plugins and applications.

Garden's `Event` object allows you to bind to events and fire events of your own. Events allow for nearly limitless customization without having to about re-implementing huge swaths of code. You can bind to just the few events you need and snipe the functionality you need.

### Object Oriented, but not too Much

We love objects and garden has a solid object oriented foundation. However, we believe that not everything has to be an object and that a class structure should really only go so deep. For us, developing with garden is an aesthetic experience and too much object configuration works against that goal.

### Powered by Composer

Composer is the amazing package manager that has taken the PHP world by storm. Garden is implemented as a composer package making it easy to use and combine with the thousands of composer packages out there.

### Unit Tested

Garden is tested with [PHPUnit](https://phpunit.de/) and [Travis CI](https://travis-ci.org/vanilla/garden). We want to make sure that garden is high quality software that doesn't break as we add new features. Our goal is to implement all reported bugs as unit tests so that they can be fixed and never happen again.

### Open Source

Garden is free, open source software distributed under the [MIT license](http://opensource.org/licenses/MIT).

Installation
------------

*Garden requres PHP 5.4 or higher*

Garden is [PSR-4](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md) compliant and can be installed using [composer](//getcomposer.org). Just add `vanilla/garden` to your composer.json.

```json
"require": {
    "vanilla/garden": "*"
}
```

A Basic Garden Application
--------------------------

Most garden applications will make use of an .htaccess file for pretty urls and then have an index.php defined as follows:

```php
// Put your index.php in the Garden namespace or import the various classes you need.
namespace Garden;

// Define the root path of the application.
define('PATH_ROOT', __DIR__);

// Require composer's autoloader.
require_once __DIR__.'/vendor/autoload.php';

// Instantiate the application.
$app = new Application();

// Load the default config from conf/config.json.php.
Config::load();

// Enable addon functionality.
Addons::bootstrap(); // enables config('addons')

// Fire the bootstrap event so that overridable function files can be included.
Event::fire('bootstrap');

// Register some routes.
$app->route('/hello', function () use ($app) {
    echo "Hello World!";
});

$app->route('/ping', function () use ($app) {
    return "Pong";
});

// Run the application.
$app->run();
```

More to Come
------------

*This read-me is a work in progress.*

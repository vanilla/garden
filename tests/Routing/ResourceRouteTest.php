<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

use Garden\Application;
use Garden\Addons;
use Garden\Event;
use Garden\Request;
use Garden\Route;

class ResourceRouteTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Application
     */
    public $app;

    public function setUp() {
        if (!defined('PATH_ROOT')) {
            define('PATH_ROOT', realpath(__DIR__.'/fixtures'));
        }

        // Load the config.
//        Config::instance()->load(__DIR__.'/config.json');

        $this->app = new Application();

        // Enable addon functionality.
        Addons::baseDir(__DIR__.'/fixtures');
        Addons::bootstrap(['test' => true]); // enables config('addons')

        // Fire the bootstrap event so that overridable function files can be included.
        Event::fire('bootstrap');

        // Set the default return type to route info.
        Request::defaultEnvironment('ACCEPT', 'debug/routing');

        Route::globalConditions([
            'page' => 'p\d+'
        ]);

        // Add the resource route.
        $this->app
            ->route('/', '%sController')
            ->conditions(['id' => '`^\d+(-|$)`']);
    }

    /**
     * Test routes that should throw a 405 method not allowed.
     *
     * @param string $path The path info of the request.
     * @param string $method The http method.
     * @dataProvider provideMethodNotAllowed
     * @expectedException \Garden\Exception\ClientException
     * @expectedExceptionCode 405
     */
    public function testMethodNotAllowed($path, $method) {
        $result = $this->app->run(new Request($path, $method));
    }

    /**
     * Test routes that should throw a 404 not found.
     *
     * @param string $path The path info of the request.
     * @param string $method The http method.
     * @dataProvider provideNotFound
     * @expectedException \Garden\Exception\NotFoundException
     * @expectedExceptionCode 404
     */
    public function testNotFound($path, $method) {
        $result = $this->app->run(new Request($path, $method));
    }

    /**
     * @param $method
     * @param $path
     * @param $expected
     * @dataProvider provideDiscussions
     */
    public function testDiscussions($method, $path, $expected) {
        $this->runExpected($method, $path, $expected);
    }

    protected function runExpected($method, $path, $expected) {
        if (is_numeric($expected) && $expected >= 400) {
            $this->setExpectedException('\Exception', '', $expected);
        } else {
            $this->setExpectedException(null, '', null);
        }


        $request = new Request($path, $method);
        $result = $this->app->run($request);

        if (is_array($expected)) {
            foreach ($expected as $key => $value) {
                $this->assertArrayHasKey($key, $result);
                $this->assertEquals($key.'='.strtolower($value), $key.'='.strtolower($result[$key]));
            }
        }
    }

    /**
     * Provide paths that should route to a 405 method not allowed error.
     *
     * @return array Returns the method args.
     */
    public function provideMethodNotAllowed() {
        $result = [
            'badIndex' => ['/noinit', 'PATCH'],
            'badActionMethod' => ['/noinit/recent', 'POST'],
            'noIndexOrOther' => ['/optinit', 'GET']
        ];
        return $result;
    }

    /**
     * Provide paths that should route to a 404 not found error.
     *
     * @return array Returns the method args.
     */
    public function provideNotFound() {
        $result = [
            'noMethod' => ['/noinit', 'OPTIONS'],
            'tooManyArgs' => ['/noinit/recent/today/p1', 'GET'],
            'tooManyIndex' => ['/noinit/foo/bar/baz', 'GET']
        ];
        return $result;
    }

    public function provideDiscussions() {
        $result = [
            ['GET', '/discussions/', ['action' => 'index']],
            ['GET', '/discussions/p1', ['action' => 'index']],
            ['GET', '/discussions/123', ['action' => 'get']],
            ['GET', '/discussions/recent', ['action' => 'getRecent']],
            ['GET', '/discussions/recent/p2', ['action' => 'getRecent']],
            ['POST', '/discussions', ['action' => 'post']],
            ['POST', '/discussions/123', 405],
            ['PATCH', '/discussions/123', ['action' => 'patch']],
            ['DELETE', '/discussions/123', ['action' => 'delete']],
            ['DELETE', '/discussions', 405]
        ];
        return $this->addKeys($result);
    }

    protected function addKeys($arr) {
        $result = [];
        foreach ($arr as $value) {
            $key = "{$value[0]} {$value[1]}";
            $result[$key] = $value;
        }
        return $result;
    }
}

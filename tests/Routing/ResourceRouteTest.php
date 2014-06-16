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
        Request::defaultEnvironment([
            'HTTP_ACCEPT' => 'application/internal',
            'HTTP_X_ASSET' => 'meta.routing'
        ], true);

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

    /**
     * @param $method
     * @param $path
     * @param $expected
     * @dataProvider provideOptionalInit
     */
    public function testOptionalInit($method, $path, $expected) {
        $this->runExpected($method, $path, $expected);
    }

    /**
     * @param $method
     * @param $path
     * @param $expected
     * @dataProvider provideRequiredInit
     */
    public function testRequiredInit($method, $path, $expected) {
        $this->runExpected($method, $path, $expected);
    }

    /**
     * Runs a test against an expected result.
     *
     * @param string $method The http method.
     * @param string $path The path to the request.
     * @param int|array $expected The expected result. This could be any of the following:
     * - An http error code.
     * - An array of routing information.
     */
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
                $this->assertEquals([$key => $value], [$key => $result[$key]]);
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
            'tooManyIndex' => ['/noinit/foo/bar/baz', 'GET'],
            'tooManyArgs2' => ['reqinit/123/extra', 'GET'],
            'notAllowedTooManyArgs' => ['/noinit/foo', 'DELETE']
        ];
        return $result;
    }

    public function provideDiscussions() {
        $result = [
            ['GET', '/discussions/', ['action' => 'index']],
            ['GET', '/discussions/p1', ['action' => 'index']],
            ['GET', '/discussions/123', ['action' => 'get']],
            ['GET', '/discussions/pp', 404], // invalid page number
            ['GET', '/discussions/recent', ['action' => 'getRecent']],
            ['GET', '/discussions/recent/p2', ['action' => 'getRecent']],
            ['GET', '/discussions/recent/p2/extra', 404],
            ['POST', '/discussions', ['action' => 'post']],
            ['POST', '/discussions/123', 405],
            ['PATCH', '/discussions/123', ['action' => 'patch']],
            ['DELETE', '/discussions/123', ['action' => 'delete']],
            ['DELETE', '/discussions', 405]
        ];
        return $this->addKeys($result);
    }

    public function provideOptionalInit() {
        $result = [
            ['GET', '/optinit/123', ['action' => 'get']],
            ['GET', '/optinit/123/', ['action' => 'get']],
            ['POST', '/optinit', ['action' => 'post']],
            ['PATCH', '/optinit/123', ['action' => 'patch']],
            ['PATCH', '/optinit/123/1', ['action' => 'patch', 'initArgs' => [123], 'actionArgs' => [1]]],
            ['GET', '/optinit', 405],
            ['GET', '/optinit/initialize', 404],
            ['GET', '/optinit/123/initialize', 404],
            ['POST', '/optinit/123/get', 404],
            ['DELETE', '/optinit/123', 405]
        ];
        return $this->addKeys($result);
    }

    public function provideRequiredInit() {
        $result = [
            ['GET', '/reqinit', 404],
            ['GET', '/reqinit/123', ['action' => 'get', 'initArgs' => [123]]],
            ['GET', '/reqINIT/123', ['action' => 'get', 'initArgs' => [123]]],
            ['GET', '/reqinit/p3', 404], // invalid id condition
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

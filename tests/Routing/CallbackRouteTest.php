<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Routing;

use Garden\Application;
use Garden\Request;
use Garden\Exception\NotFoundException;

/**
 * Tests for the {@link CallbackRoute}.
 */
class CallbackRouteTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Application
     */
    public $app;

    /**
     * Initialize a new application before each test.
     */
    public function setup() {
        $this->app = new Application();

        // Set the default return type to route info.
        Request::defaultEnvironment([
            'HTTP_ACCEPT' => 'application/internal',
            'HTTP_X_ASSET' => 'data'
        ], true);
    }

    /**
     * Test the basic usage of the {@link CallbackRoute}.
     */
    public function testBasicCallback() {
        $this->app->route('/foo', function () {
            return ['bar' => 'baz'];
        });

        $actual = $this->app->run(new Request('/foo'));
        $this->assertEquals(['bar' => 'baz'], $actual);
    }

    /**
     * Test to make sure route require an exact match.
     *
     * @expectedException \Garden\Exception\NotFoundException
     */
    public function testExactMatch() {
        $this->app->route('/foo', function () {
            return 123;
        });

        $actual = $this->app->run(new Request('/foobar'));
    }

    /**
     * Test a basic regex expression.
     */
    public function testBasicRegex() {
        $this->app->route('/foo/?', function () {
            return ['foo'];
        });

        $actual = $this->app->run(new Request('/foo'));
        $this->assertEquals(['foo'], $actual);

        $actual2 = $this->app->run(new Request('/foo/'));
        $this->assertEquals(['foo'], $actual2);
    }

    /**
     * Test a callback with an argument.
     */
    public function testArg() {
        $this->app->route('/foo/{id}', function ($id) {
            return [$id];
        });

        $actual = $this->app->run(new Request('/foo/234'));
        $this->assertEquals([234], $actual);
    }

    /**
     * Test a callback with an optional argument.
     */
    public function testOptionalArg() {
        $callback = function ($id = 'default') {
            return [$id];
        };

        $this->app->route('/foo/{id}?', $callback);

        $actual = $this->app->run(new Request('/foo/123'));
        $this->assertEquals([123], $actual);

        $actual2 = $this->app->run(new Request('/foo/'));
        $this->assertEquals(['default'], $actual2);
    }

    /**
     * Test routing to a specific request method.
     *
     * @expectedException \Garden\Exception\NotFoundException
     */
    public function testRequestMethod() {
        $this->app->get('/foo/{id}', function ($id) {
            return [$id];
        });

        $actual = $this->app->run(new Request('/foo/123'));
        $this->assertEquals([123], $actual);

        $actual2 = $this->app->run(new Request('/foo/123', 'POST'));
    }
}

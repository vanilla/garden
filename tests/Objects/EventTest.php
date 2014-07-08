<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Objects;

use Garden\Event;
use Garden\Tests\Objects\fixtures\TestPluggable;
use Garden\Tests\Objects\fixtures\TestPlugin;

/**
 * Tests for the {@link Event} class.
 */
class EventTest extends \PHPUnit_Framework_TestCase {
    /**
     * Set up the db link for the test cases.
     */
    public static function setUpBeforeClass() {
        require_once PATH_SRC.'/functions/formatting-functions.php';
    }

    /**
     * Reset the event object before each test.
     */
    public function setup() {
        Event::reset();
    }

    /**
     * Reset the event object before after test.
     */
    public function teardown() {
        Event::reset();
    }

    /**
     * Test {@link Event::bind()} and {@link Event::fire()}.
     */
    public function testBindFire() {
        $fired = false;

        Event::bind('test', function () use (&$fired) {
            $fired = true;
        });

        Event::fire('test');

        $this->assertTrue($fired, "Event not fired.");
    }

    /**
     * Test event binding with priority.
     */
    public function testPriority() {
        $fired = [];

        Event::bind('test', function () use (&$fired) {
            $fired[] = 2;
        });

        Event::bind('test', function () use (&$fired) {
            $fired[] = 1;
        }, Event::PRIORITY_HIGH);

        Event::fire('test');

        $this->assertEquals([1, 2], $fired);
    }

    /**
     * Test {@link Event::fireFilter()}.
     */
    public function testFireFilter() {
        $inc = function ($val) {
            return $val + 1;
        };

        Event::bind('inc', $inc);
        Event::bind('inc', $inc);

        $this->assertTrue(Event::functionExists('inc'));
        $this->assertTrue(Event::functionExists('inc', true));

        $val = Event::fireFilter('inc', 0);
        $this->assertEquals(2, $val);

        $ident = Event::fireFilter('incX', 123);
        $this->assertEquals(123, $ident);
    }

    /**
     * Test {@link Event::bindClass()} and {@link Event::callUserFuncArray()}.
     */
    public function testBindClass() {
        $plugin = new TestPlugin();
        $pluggable = new TestPluggable();

        $this->assertTrue(Event::methodExists($pluggable, 'foo1'));
        $this->assertFalse(Event::methodExists($pluggable, 'foo1', true));

        Event::bindClass($plugin);

        $this->assertTrue(Event::methodExists($pluggable, 'foo1'));
        $this->assertTrue(Event::methodExists($pluggable, 'foo1', true));

        $foo1 = Event::callUserFuncArray([$pluggable, 'foo1']);
        $this->assertEquals('foo1_plugin', $foo1);

        $foo2 = Event::callUserFuncArray([$pluggable, 'foo2']);
        $this->assertEquals('foo2_plugin', $foo2);

        $foo3 = Event::callUserFuncArray([$pluggable, 'foo3']);
        $this->assertEquals('foo3_plugin', $foo3);
    }

    /**
     * Test {@link Event::callUserFuncArray()} with `_before` and `_after` handlers.
     */
    public function testCallUserFuncArrayBeforeAfter() {
        Event::bind('sort_before', function (&$arr) {
            $arr[] = 0;
        });

        Event::bind('sort_after', function (&$arr) {
            $arr[] = 1;
        });

        $arr = [20, 10];
        Event::callUserFuncArray('sort', [&$arr]);
        $this->assertEquals([0, 10, 20, 1], $arr);

        // Test with a static function.
        $str = 'foo';
        Event::bind('testPlugin_staticMethod_before', function () use (&$str) {
            $str = 'bar';
        });
        $result = Event::callUserFuncArray(['Garden\Tests\Objects\fixtures\TestPlugin', 'staticMethod'], [&$str]);
        $this->assertEquals('bar_static', $result);

        // Now test with a bound class.
        Event::reset();
        Event::bindClass('Garden\Tests\Objects\fixtures\TestPlugin');

        $arr = [20, 10];
        Event::callUserFuncArray('sort', [&$arr]);
        $this->assertEquals([0, 10, 20, 1], $arr);
    }

    /**
     * Test binding to a static method.
     */
    public function testStaticMethodBinding() {
        Event::bind('static', ['Garden\Tests\Objects\fixtures\TestPlugin', 'staticMethod']);

        $result = Event::fire('static', 'foo');
        $this->assertEquals('foo_static', $result);
    }

    /**
     * Test firing events with no event handlers.
     */
    public function testNoEventHandler() {
        $result = Event::fire('nohandler');
        $this->assertNull($result);

        $result2 = Event::fireArray('nohandler');
        $this->assertNull($result2);

        $rand = mt_rand();
        $result3 = Event::fireFilter('nohandler', $rand);
        $this->assertEquals($rand, $result3);
    }

    /**
     * Test {@link Event::functionExists}.
     */
    public function testFunctionExists() {
        // Make sure a regular function exists.
        $this->assertTrue(Event::functionExists('TRIM'));
    }

    /**
     * Test a closure that can't have a name.
     */
    public function testUnnameableEvent() {
        $result = Event::callUserFuncArray(function () {
            return 'bar';
        });

        $this->assertEquals('bar', $result);
    }

    /**
     * Test {@link Event::dumpHandlers()}.
     */
    public function testDumpHandlers() {
        $plugin = new TestPlugin();

        Event::bind('trim', 'trim');
        Event::bind('trim', [$plugin, 'rtrim'], Event::PRIORITY_LOW);
        Event::bind('trim', [get_class($plugin), 'ltrim'], Event::PRIORITY_HIGH);

        $handlers = Event::dumpHandlers();
        $expected = ['trim' => [
                'Garden\Tests\Objects\fixtures\TestPlugin::ltrim()',
                'trim()',
                'Garden\Tests\Objects\fixtures\TestPlugin->rtrim()'
            ]
        ];
        $this->assertEquals($expected, $handlers);
    }
}

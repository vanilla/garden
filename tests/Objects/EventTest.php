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

        // Now test with a bound class.
        Event::reset();
        Event::bindClass('Garden\Tests\Objects\fixtures\TestPlugin');

        $arr = [20, 10];
        Event::callUserFuncArray('sort', [&$arr]);
        $this->assertEquals([0, 10, 20, 1], $arr);
    }
}

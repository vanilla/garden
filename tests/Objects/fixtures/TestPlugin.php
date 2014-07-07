<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Objects\fixtures;


class TestPlugin {
    static $instance;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new TestPlugin();
        }
        return self::$instance;
    }

    public function testPluggable_foo1() {
        return 'foo1_plugin';
    }

    public function testPluggable_foo2_create() {
        return 'foo2_plugin';
    }

    public function testPluggable_foo3_handler() {
        return 'foo3_plugin';
    }

    public function sort_before(&$arr) {
        $arr[] = 0;
    }

    public function sort_after(&$arr) {
        $arr[] = 1;
    }
}

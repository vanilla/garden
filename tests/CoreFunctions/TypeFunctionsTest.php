<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\CoreFunctions;

use Garden\Request;

class TypeFunctionsTest extends \PHPUnit_Framework_TestCase {
    /**
     * Test {@link force_bool()} with truthy values.
     */
    public function testForceBoolTrue() {
        $this->assertTrue(force_bool(true));
        $this->assertTrue(force_bool(1));
        $this->assertTrue(force_bool('1'));
        $this->assertTrue(force_bool(50));
        $this->assertTrue(force_bool('true'));
        $this->assertTrue(force_bool('yes'));
        $this->assertTrue(force_bool(['yes']));
    }

    /**
     * Test {@link force_bool()} with falsey values.
     */
    public function testForceBoolFalse() {
        $this->assertFalse(force_bool(false));
        $this->assertFalse(force_bool('false'));
        $this->assertFalse(force_bool(null));
        $this->assertFalse(force_bool(0));
        $this->assertFalse(force_bool('no'));
        $this->assertFalse(force_bool(''));
        $this->assertFalse(force_bool('off'));
        $this->assertFalse(force_bool('disabled'));
        $this->assertFalse(force_bool([]));
    }

    /**
     * Test {@link force_int()}.
     */
    public function testForceInt() {
        $this->assertSame(123, force_int(123));
        $this->assertSame(123, force_int('123'));
        $this->assertSame(123, force_int(123.2));
        $this->assertSame(0, force_int(false));
        $this->assertSame(0, force_int(''));
        $this->assertSame(0, force_int('false'));
        $this->assertSame(0, force_int('disabled'));
        $this->assertSame(0, force_int('off'));
        $this->assertSame(0, force_int('no'));
        $this->assertSame(0, force_int([]));
        $this->assertSame(1, force_int(true));
        $this->assertSame(1, force_int('true'));
        $this->assertSame(1, force_int('yes'));
        $this->assertSame(1, force_int('on'));
        $this->assertSame(1, force_int('enabled'));
        $this->assertSame(1, force_int(['enabled']));
    }

    /**
     * Test {@link reflect_args()} with just named arguments.
     */
    public function testReflectArgsNamed() {
        $get = ['default' => 123, 'KEY' => 'foo', 'array' => ['foo' => 'bar']];
        $rargs = reflect_args('val', $get);

        $expected = val($get['KEY'], $get['array'], $get['default']);
        $actual = call_user_func_array('val', $rargs);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test {@link reflect_args()} with named and indexed arguments.
     */
    public function testReflectArgsNamedAndIndex() {
        $path = ['baz'];
        $get = ['default' => 123, 'array' => ['foo' => 'bar']];
        $rargs = reflect_args('val', $path, $get);

        $expected = val($path[0], $get['array'], $get['default']);
        $actual = call_user_func_array('val', $rargs);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test {@link reflect_args()} with a method.
     */
    public function testReflectArgsObject() {
        $r = new Request('http://localhost/foo.txt?foo=bar', 'GET');

        $callback = [$r, 'getQuery'];
        $args = ['KEY' => 'foo', 'default' => 123];
        $expected = $r->getQuery($args['KEY'], $args['default']);
        $actual = call_user_func_array($callback, reflect_args($callback, $args));
        $this->assertEquals($expected, $actual);

        // Test a static method.
        $callback2 = [get_class($r), 'defaultEnvironment'];
        $args2 = ['Key' => 'PATH_INFO'];
        $expected2 = Request::defaultEnvironment('PATH_INFO');
        $actual2 = call_user_func_array($callback2, reflect_args($callback2, $args2));
        $this->assertEquals($expected2, $actual2);
    }


    /**
     * Assert that two values are the same value and same type.
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value.
     */
//    protected function assertSame($expected, $actual) {
//        $this->assertEquals($expected, $actual);
//
//        if ($expected == $actual) {
//            $this->assertTrue($expected === $actual, "$expected !== $actual");
//        }
//    }
}

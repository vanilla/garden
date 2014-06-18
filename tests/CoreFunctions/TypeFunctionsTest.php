<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

class TypeFunctionsTest extends PHPUnit_Framework_TestCase {
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
        $this->assertExact(123, force_int(123));
        $this->assertExact(123, force_int('123'));
        $this->assertExact(123, force_int(123.2));
        $this->assertExact(0, force_int(false));
        $this->assertExact(0, force_int(''));
        $this->assertExact(0, force_int('false'));
        $this->assertExact(0, force_int('disabled'));
        $this->assertExact(0, force_int('off'));
        $this->assertExact(0, force_int('no'));
        $this->assertExact(0, force_int([]));
        $this->assertExact(1, force_int(true));
        $this->assertExact(1, force_int('true'));
        $this->assertExact(1, force_int('yes'));
        $this->assertExact(1, force_int('on'));
        $this->assertExact(1, force_int('enabled'));
        $this->assertExact(1, force_int(['enabled']));
    }

    /**
     * Assert that two values are the same value and same type.
     *
     * @param mixed $expected The expected value.
     * @param mixed $actual The actual value.
     */
    protected function assertExact($expected, $actual) {
        $this->assertEquals($expected, $actual);

        if ($expected == $actual) {
            $this->assertTrue($expected === $actual, "$expected !== $actual");
        }
    }
}

<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\CoreFunctions;


/**
 * Test the various date/time functions.
 */
class DateTimeFunctionsTest extends \PHPUnit_Framework_TestCase {
    public function testDatecmp() {
        $this->assertLessThan(0, datecmp('yesterday', 'today'));
        $this->assertGreaterThan(0, datecmp('today', 'yesterday'));
        $dt = time();
        $this->assertEquals(0, datecmp($dt, $dt));
    }
}

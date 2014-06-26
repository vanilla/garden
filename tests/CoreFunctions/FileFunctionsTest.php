<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\CoreFunctions;

/**
 * Test some of the file system functions.
 */
class FileFunctionsTest extends \PHPUnit_Framework_TestCase {
    /**
     * Test {@link file_put_contents_safe()}.
     */
    public function testFilePutContentsSafe() {
        $path = tempnam(sys_get_temp_dir(), 'phpunit_garden');
        $contents = base64_encode(openssl_random_pseudo_bytes(100));

        file_put_contents_safe($path, $contents);
        $getContents = file_get_contents($path);

        $this->assertEquals($contents, $getContents);
    }

    /**
     * Test {@link touch_dir()}.
     */
    public function testTouchDir() {
        // Test lots of nesting.
        $path = sys_get_temp_dir().'/a/b/c/d/e/f/g/h/'.sha1(microtime());
        $this->assertFileNotExists($path);

        touchdir($path);
        $this->assertFileExists($path);
        $this->assertTrue(is_dir($path), "touchdir() is supposed to create a directory.");
    }
}

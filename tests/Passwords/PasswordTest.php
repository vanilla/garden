<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

use Garden\Password\IPassword;

/**
 * Basic tests for the password objects.
 */
class BasicTest extends \PHPUnit_Framework_TestCase {

    /**
     * Tests that a password will hash and verify against that hash.
     *
     * @param IPassword $alg The class to test.
     * @dataProvider getPasswordClasses
     */
    public function testHashAndVerify(IPassword $alg) {
        $password = 'Iñtërnâtiônàlizætiøn'; // unicode password.

        $hash = $alg->hash($password);
        $this->assertTrue($alg->verify($password, $hash));
    }

    /**
     * Tests that a wrong password fails verification.
     *
     * @param IPassword $alg The class to test.
     * @dataProvider getPasswordClasses
     */
    public function testWrongPassword(IPassword $alg) {
        $password = 'password';
        $wrongPassword = 'letmein';

        $hash = $alg->hash($password);
        $this->assertFalse($alg->verify($wrongPassword, $hash));
    }

    /**
     * Tests to make sure that generated passwords don't require a regeneration.
     *
     * @param IPassword $alg The class to test.
     * @dataProvider getPasswordClasses
     */
    public function testNoRehash(IPassword $alg) {
        $password = 'somePassword!!!';

        $hash = $alg->hash($password);
        $this->assertFalse($alg->needsRehash($hash));
    }

    /**
     * Tests to make sure a null password hash never verifies.
     *
     * @param IPassword $alg The class to test.
     * @dataProvider getPasswordClasses
     */
    public function testNullHash(IPassword $alg) {
        $this->assertFalse($alg->verify('password', null));
    }

    /**
     * Gets an array of all the password classes.
     *
     * @return array Returns all of the php classes indexed by base class name.
     */
    public function getPasswordClasses() {
        $paths = glob(PATH_SRC.'/Password/*.php');
        $result = [];

        foreach ($paths as $path) {
            $classname = basename($path, '.php');

            // Skip password testing for older versions of php.
            if ($classname === 'PhpPassword' && !function_exists('password_verify')) {
                continue;
            }

            if ($classname != 'IPassword') {
                $full_classname = "\Garden\Password\\$classname";
                $obj = new $full_classname();
                if ($obj instanceof IPassword) {
                    $result[$classname] = [$obj];
                }
            }
        }
        return $result;
    }
}

<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Passwords;

use Garden\Password\DjangoPassword;
use Garden\Password\IPassword;
use Garden\Password\PhpassPassword;
use Garden\Password\VanillaPassword;

/**
 * Basic tests for the password objects.
 */
class PasswordTest extends \PHPUnit_Framework_TestCase {

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
     * Test some edge cases of {@link PhpassPassword}.
     */
    public function testPhpassPassword() {
        $pw = new PhpassPassword(false, 1);

        $password = 'password';
        $wrongPassword = 'letmein';

        $hash = $pw->hash($password);

        $this->assertTrue($pw->verify($password, $hash));
        $this->assertFalse($pw->verify($wrongPassword, $hash));
        $this->assertFalse($pw->verify(null, $hash));

    }

    /**
     * Test some edge cases of {@link PhpassPassword}.
     */
    public function testVanillaPassword() {
        $pw = new VanillaPassword(true);

        $password = 'password';
        $wrongPassword = 'letmein';

        $this->testHashAndVerify($pw);
        $this->testWrongPassword($pw);
        $this->testNullHash($pw);

        $hash = $pw->hash($password);

        $this->assertTrue($pw->verify($password, $hash));
        $this->assertFalse($pw->verify($wrongPassword, $hash));
        $this->assertFalse($pw->verify(null, $hash));
        if (CRYPT_BLOWFISH === 1) {
            $this->assertTrue($pw->needsRehash($hash));
        }
    }

    /**
     * Test the specifics of the Django password hashing algorithm.
     *
     * @param string $hashMethod The hash method to use.
     * @dataProvider provideDjangoHashMethods
     */
    public function testDjangoPassword($hashMethod) {
        $pw = new DjangoPassword($hashMethod);

        $this->testHashAndVerify($pw);
        $this->testWrongPassword($pw);
        $this->testNullHash($pw);

        $hash = $pw->hash('password');
        if (in_array($hashMethod, ['sha256', 'crypt'])) {
            $this->assertFalse($pw->needsRehash($hash));
        } else {
            $this->assertTrue($pw->needsRehash($hash));
        }

        // Test a few edge cases.
        $this->assertTrue($pw->needsRehash('foo'));
        $parts = explode('$', $hash);
        $parts[0] = 'foo';
        $badHash = implode('$', $parts);

        $this->assertTrue($pw->needsRehash($badHash));
        $this->assertFalse($pw->verify('password', $badHash));
    }

    /**
     * Get the hash methods suitable for Django.
     *
     * @return array Returns an array of django hash methods.
     */
    public function provideDjangoHashMethods() {
        return [
            'md5' => ['md5'],
            'sha1' => ['sha1'],
            'sha256' => ['sha256'],
            'crypt' => ['crypt']
        ];
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

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
use Garden\Password\PhpPassword;
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
     *
     * @param int $hashMethod One of the PhpassPassword::HASH_* constants.
     * @dataProvider providePhpassHashMethods
     */
    public function testPhpassPassword($hashMethod) {
        $pw = new PhpassPassword($hashMethod, 1);

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
        $pw = new VanillaPassword();

        $password = 'password';
        $wrongPassword = 'letmein';

        $this->testHashAndVerify($pw);
        $this->testWrongPassword($pw);
        $this->testNullHash($pw);

        $hash = $pw->hash($password);

        $this->assertTrue($pw->verify($password, $hash));
        $this->assertFalse($pw->verify($wrongPassword, $hash));
        $this->assertFalse($pw->verify(null, $hash));
    }

    /**
     * Test some old password scenarios in Vanilla.
     */
    public function testVanillaPasswordCompat() {
        $pw = new VanillaPassword();

        $password = 'password';

        // Test md5.
        $md5 = md5($password);
        $this->assertTrue($pw->verify($password, $md5));
        $this->assertTrue($pw->needsRehash($md5));

        // Test plain text.
        $this->assertTrue($pw->verify($password, $password));
        $this->assertTrue($pw->needsRehash($password));

        // Test Phpass.
        $phpass = new PhpassPassword(PhpassPassword::HASH_PHPASS);
        $phphash = $phpass->hash($password);
        $this->assertTrue($pw->verify($password, $phphash));
        $this->assertTrue($pw->needsRehash($phphash));

        $this->assertFalse($pw->verify($password, '423432'));

        $pw->setHashMethod(PhpassPassword::HASH_BLOWFISH);
        $hash = $pw->hash($password);
        $this->assertTrue($pw->verify($password, $hash));
        $this->assertFalse($pw->needsRehash($hash));

        $pw->setHashMethod(PhpassPassword::HASH_EXTDES);
        $hash2 = $pw->hash($password);
        $this->assertTrue($pw->verify($password, $hash2));
        $this->assertFalse($pw->needsRehash($hash2));

        $pw->setHashMethod(PhpassPassword::HASH_PHPASS);
        $hash3 = $pw->hash($password);
        $this->assertTrue($pw->verify($password, $hash3));
        $this->assertFalse($pw->needsRehash($hash3));
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
    }

    /**
     * Test some Django password edge cases.
     */
    public function testDjangoPasswordEdgeCases() {
        $pw = new DjangoPassword('sha256');

        $this->assertTrue($pw->needsRehash('foo'));

        $hash = $pw->hash('password');
        $parts = explode('$', $hash);
        $parts[0] = 'foo';
        $badHash = implode('$', $parts);

        $this->assertTrue($pw->needsRehash($badHash));
        $this->assertFalse($pw->verify('password', $badHash));

        $pw = new DjangoPassword('foo');
        $this->setExpectedException('\Exception');
        $hash = $pw->hash('fooo');
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
     * Provide the various hash methods for the {@link PhpassPassword} class.
     *
     * @return array Returns an array of hash methods.
     */
    public function providePhpassHashMethods() {
        return [
            'phpass' => [PhpassPassword::HASH_PHPASS],
            'extdes' => [PhpassPassword::HASH_EXTDES],
            'blowfish' => [PhpassPassword::HASH_BLOWFISH],
            'best' => [PhpassPassword::HASH_BEST],
        ];
    }

    /**
     * Gets an array of all the password classes.
     *
     * @return array Returns all of the php classes indexed by base class name.
     */
    public function getPasswordClasses() {
//        return [
//            'VanillaPassword' => [new VanillaPassword()]
//        ];

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

        // Add some extra passwords here.
        $result['PhpPassword bcrypt'] = [new PhpPassword(PASSWORD_BCRYPT)];

        return $result;
    }
}

<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cookie\Tests;

use Garden\Cookie\SecureCookie;


class BasicTest extends \PHPUnit_Framework_TestCase {

    /**
     *
     * @return SecureCookie Returns the new secure cookie.
     */
    public function createCookie() {
        $cookie = new SecureCookie([
            'encryptionKey' => SecureCookie::generateRandomKey(),
            'signatureKey' => SecureCookie::generateRandomKey()
        ]);

        return $cookie;
    }

    /**
     * Test a basic encode to make sure there aren't any exceptions.
     *
     * @param mixed $data The data to encode.
     * @dataProvider provideSampleData
     */
    public function testEncode($data) {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode($data);
    }

    /**
     * Test to make sure that the secure cookies are url safe.
     *
     * @param mixed $data The data to encode.
     * @dataProvider provideSampleData
     */
    public function testUrlEncode($data) {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode($data);

        $this->assertEquals($encoded, rawurlencode($encoded));
    }

    /**
     * Test to make sure that {@link SecureCookie::generateRandomKey()} generates a random key of the proper length.
     */
    public function testGenerateRandomKey() {
        for ($i = 1; $i <= 100; $i++) {
            $key = SecureCookie::generateRandomKey($i);
            $this->assertEquals($i, strlen($key));
        }
    }

    /**
     * Test to make sure that encoded data matches decoded data.
     *
     * @param mixed $data The data to test.
     * @dataProvider provideSampleData
     */
    public function testEncodeDecode($data) {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode($data);
        $decoded = $cookie->decode($encoded, true);

        $this->assertJsonStringEqualsJsonString(
            json_encode($data, JSON_PRETTY_PRINT),
            json_encode($decoded, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Tests invalid cookies.
     *
     * @param mixed $data The data to test.
     * @throws \Exception Throws an exception for bad cookies.
     * @dataProvider provideBadCookies
     * @expectedException \Exception
     * @expectedExceptionCode 400
     */
    public function testBadCookies($data) {
        $cookie = $this->createCookie();

        $this->assertFalse($cookie->decode($data, false));

        try {
            $cookie->decode($data, true);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * Tests a cookie with no signature.
     *
     * @param mixed $data The data to test.
     * @dataProvider provideSampleData
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testStripSignature($data) {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode($data);
        $stripped = substr($encoded, 0, strrpos($encoded, SecureCookie::SEP) + 1);

        $decoded = $cookie->decode($stripped, true);
        $this->assertNotEquals($data, $decoded);

    }

    /**
     * Tests a cookie with an invalid signature.
     *
     * @param mixed $data The data to test.
     * @dataProvider provideSampleData
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testInvalidSignature($data) {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode($data);
        $invalid = $encoded.SecureCookie::generateRandomKey(10);

        $decoded = $cookie->decode($invalid, false);
        $this->assertFalse($decoded);

        $cookie->decode($invalid, true);
    }

    /**
     * Tests to make sure an expired session produces an error.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 401
     */
    public function testExpiredSession() {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode('data', time() - $cookie->maxAge - 10);

        $decoded = $cookie->decode($encoded, false);
        $this->assertFalse($decoded);

        $cookie->decode($encoded, true);
    }

    /**
     * Tests to make sure an invalid cipher method produces an exception.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 422
     */
    public function testInvalidCipher() {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode('data');
        $invalid = $cookie->twiddle($encoded, 2, 'foo');

        $decoded = $cookie->decode($invalid, false);
        $this->assertFalse($decoded);

        $cookie->decode($invalid);
    }

    /**
     * Tests for an invalid initialization vector in the secure cookie.
     */
    public function testInvalidIV() {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode('data');

        $emptyIV = $cookie->twiddle($encoded, 3, '');
        $this->assertFalse($cookie->decode($emptyIV, false));

        $badIV = $cookie->twiddle($encoded, 3, 'bad');
        $this->assertFalse($cookie->decode($badIV, false));
    }

    /**
     * Tests bad encrypted data.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testBadEncryption() {
        $cookie = $this->createCookie();
        $encoded = $cookie->encode('data');

        $badEncrypt = $cookie->twiddle($encoded, 0, 'bad');
        $this->assertFalse($cookie->decode($badEncrypt, false));

        $cookie->decode($badEncrypt, true);
    }

    /**
     * Provide a variety of sample data to encode with a {@link SecureCookie}.
     *
     * @return array Returns an array of arrays suitable to pass into tests.
     */
    public function provideSampleData() {
        $result = [
            'int' => [1],
            'timestamp' => [time()],
            'string' => ["Hello world"],
            'array' => [[1, 2, 3]],
            'dictionary' => [['uid' => 1234567, 't' => SecureCookie::generateRandomKey(10)]],
            'nested' => [['a' => 1234, 'b' => [1, 2, 3]]]
        ];

        return $result;
    }

    /**
     * @return array
     */
    public function provideBadCookies() {
        $result = [
            'true' => [true],
            'false' => [false],
            'array' => [['snub']],
            'badString' => ['bad_cookie'],
            'badParts' => ['a.b.c']
        ];

        return $result;
    }
}

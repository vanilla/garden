<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Objects;

use Garden\SecureString;

/**
 * Unit tests for the {@link Garden\SecureString} class.
 */
class SecureStringTest extends \PHPUnit_Framework_TestCase {

    /**
     * Test a set of data against a spec.
     *
     * @param mixed $data The data to test.
     * @param array $spec A secure string spec.
     * @dataProvider provideDataAndSpecs
     */
    public function testSpec($data, array $spec) {
        $ss = new SecureString();

        $this->assertNotNull($data);
        $encoded = $ss->encode($data, $spec, true);
        $decoded = $ss->decode($encoded, $spec, true);

        $this->assertEquals($data, $decoded);
    }

    /**
     * Test a bad password when decoding.
     *
     * @param mixed $data The data to test.
     * @param array $spec A secure string spec.
     * @dataProvider provideDataAndSpecs
     * @throws \Exception Throws an exception when a bad password is encountered.
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testBadPasswords($data, array $spec) {
        $ss = new SecureString();

        $this->assertNotNull($data);

        try {
            $encoded = $ss->encode($data, $spec, true);
        } catch (\Exception $ex) {
            throw new \Exception("Error encoding data.", 400);
        }

        $badSpec = $spec;
        foreach ($badSpec as &$password) {
            $password = uniqid('bad', true);
        }

        $null = $ss->decode($encoded, $badSpec, false);
        $this->assertNull($null);

        try {
            $decoded = $ss->decode($encoded, $badSpec, true);
        } catch (\Exception $ex) {
            if ($ex->getCode() === 400) {
                $bad = true;
            }
            throw $ex;
        }
    }

    /**
     * Test an edge-case from the bad password test.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testBadPasswordEdge() {
        $ss = new SecureString();

        $data = 1;
        $spec = ['aes256' => 'pw53bf4508bd3fa7.04556227'];
        $badSpec = ['aes256' => 'bad53bf4508c386d0.86764110'];
        $encoded = 'dsJ-cg_yasvDXLroH15Cdw.RD_dr-J8UJRYRDFJt_9MCw.aes256';

        $decoded = $ss->decode($encoded, $badSpec, true);
    }

    /**
     * Test a missing password when decoding.
     *
     * @param mixed $data The data to test.
     * @param array $spec A secure string spec.
     * @dataProvider provideDataAndSpecs
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testMissingDecodePassword($data, $spec) {
        $ss = new SecureString();

        $this->assertNotNull($data);

        $encoded = $ss->encode($data, $spec, true);

        $null = $ss->decode($encoded, [], false);
        $this->assertNull($null);

        $decoded = $ss->decode($encoded, [], true);
    }

    /**
     * Test encoding a string with a missing spec.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 400
     */
    public function testUnsupportedSpecEncode() {
        $ss = new SecureString();

        $data = 'Hello world!';
        $spec = ['foo' => 'bar'];

        $encoded = $ss->encode($data, $spec, false);
        $this->assertNull($encoded);

        $ss->encode($data, $spec, true);
    }

    /**
     * Test decoding a string with an invalid spec.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testUnsupportedSpecDecode() {
        $ss = new SecureString();

        $data = 'Hello world!';
        $spec = ['aes128' => 'bar'];

        $encoded = $ss->encode($data, $spec, false);
        $this->assertNotNull($encoded);

        $badEncoded = $ss->twiddle($encoded, 2, 'foo');
        $decoded = $ss->decode($badEncoded, $spec, false);
        $this->assertNull($decoded);

        $ss->decode($badEncoded, $spec, true);
    }

    /**
     * Test decoding a string with a missing signature.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testMissingSignature() {
        $ss = new SecureString();

        $data = 'Hello world!';
        $spec = ['hsha1' => SecureString::generateRandomKey()];

        $encoded = $ss->encode($data, $spec, false);
        $this->assertNotNull($encoded);

        $badEncoded = $ss->twiddle($encoded, 2, '');
        $decoded = $ss->decode($badEncoded, $spec, false);
        $this->assertNull($decoded);

        $ss->decode($badEncoded, $spec, true);
    }

    /**
     * Test decoding a string with an expired timestamp.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testExpiredTimestamp() {
        $ss = new SecureString();
        $ss->timestampExpiry(-1000);

        $data = 'Hello world!';
        $spec = ['hsha1' => SecureString::generateRandomKey()];

        $encoded = $ss->encode($data, $spec, false);
        $this->assertNotNull($encoded);

        $decoded = $ss->decode($encoded, $spec, false);
        $this->assertNull($decoded);

        $ss->decode($encoded, $spec, true);
    }

    /**
     * Test a string that is only a base64 url encoded hash.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testInsecureString() {
        $ss = new SecureString();
        $str = base64url_encode(json_encode(['foo' => 'bar']));

        $decoded = $ss->decode($str, [], false);
        $this->assertNull($decoded);

        $ss->decode($str, [], true);
    }

    /**
     * There is a test case where the string decrypts, but comes up with garbage.
     *
     * @expectedException \Exception
     * @expectedExceptionCode 403
     */
    public function testEdgePw() {
        $ss = new SecureString();

        $data = 1;
        $spec = ['aes256' => 'pw53a8f0c6f2fa95.12344432'];
        $encoded = 'cCAX_rOEtQYn30sFZFVI5g.7eQq12ifs9nDjEQGRHkoiw.aes256';
        $badSpec = ['aes256' => 'bad53a8f0c704ada8.12162063'];

        $decoded = $ss->decode($encoded, $spec);
        $this->assertEquals($data, $decoded);

        $badDecoded = $ss->decode($encoded, $badSpec, true);
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
            'unicode' => ['Iñtërnâtiônàlizætiøn'],
            'array' => [[1, 2, 3]],
            'dictionary' => [['uid' => 1234567, 't' => sha1(mt_rand())]],
            'nested' => [['a' => 1234, 'b' => [1, 2, 3]]]
        ];

        return $result;
    }

    public function provideSpecs() {
        $result = [
            'aes128' => [['aes128' => SecureString::generateRandomKey()]],
            'aes256' => [['aes256' => uniqid('pw', true)]],
            'hsha1' => [['hsha1' => uniqid('pw', true)]],
            'hsha256' => [['hsha256' => uniqid('pw', true)]],
            'aes128-hsha1' => [['aes128' => uniqid('pw', true), 'hsha1' => uniqid('pw', true)]],
            'aes256-hsha256' => [['aes256' => uniqid('pw', true), 'hsha256' => uniqid('pw', true)]],
            'double sign' => [['hsha1' => uniqid('pw', true), 'hsha256' => uniqid('pw', true)]]
        ];

        return $result;
    }

    public function provideDataAndSpecs() {
        $data = $this->provideSampleData();
        $specs = $this->provideSpecs();

        $result = [];
        foreach ($data as $dkey => $drow) {
            foreach ($specs as $skey => $srow) {
                $result["$skey: $dkey"] = array_merge($drow, $srow);
            }
        }
        return $result;
    }
}

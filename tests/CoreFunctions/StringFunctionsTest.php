<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

class StringFunctionsTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        global $translations;
        $translations = [];
    }


    /**
     * Test {@link base64url_encode()} and {@link base64url_decode()}.
     *
     * @param string $str The string to test.
     * @dataProvider provideSomeStrings
     */
    public function testBase64UrlEncodeDecode($str) {
        $encoded = base64url_encode($str);
        $decoded = base64url_decode($encoded);

        $this->assertEquals($str, $decoded);
    }

    /**
     * Test {@link implode_assoc()}.
     */
    public function testImplodeAssoc() {
        $data = ['foo' => 'bar', 'bar' => 'baz'];

        $impoded = implode_assoc(', ', ': ', $data);
        $expected = 'foo: bar, bar: baz';

        $this->assertEquals($expected, $impoded);
    }

    /**
     * Test {@link is_url()}.
     *
     * @param string $url The url to test.
     * @param bool $expected What {@link is_url()} is supposed to return.
     * @dataProvider provideUrls
     */
    public function testIsUrl($url, $expected) {
        $actual = is_url($url);

        $this->assertTrue(is_bool($actual));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test {@link ltrim_substr()}.
     */
    public function testLTrimSubstr() {
        $this->assertEquals('bar', ltrim_substr('foobar', 'foo'));
        $this->assertEquals('foo', ltrim_substr('foofoo', 'foo'));
        $this->assertEquals('foo', ltrim_substr('foofoo', 'FOO'));
        $this->assertEquals('short', ltrim_substr('short', 'shortLong'));
    }

    /**
     * Test {@link rtrim_substr()}.
     */
    public function testRTrimSubstr() {
        $this->assertEquals('foo', rtrim_substr('foobar', 'bar'));
        $this->assertEquals('foo', rtrim_substr('foofoo', 'foo'));
        $this->assertEquals('foo', rtrim_substr('foofoo', 'FOO'));
        $this->assertEquals('short', rtrim_substr('short', 'longshort'));
    }

    /**
     * Test {@link str_begins()}.
     */
    public function testStrBegins() {
        $this->assertTrue(str_begins('foobar', 'foo'));
        $this->assertTrue(str_begins('foobar', 'FOO'));
        $this->assertFalse(str_begins('foobar', 'foop'));
        $this->assertFalse(str_begins('foo', 'foobar'));
    }

    /**
     * Test {@link str_ends()}.
     */
    public function testStrEnds() {
        $this->assertTrue(str_ends('foobar', 'bar'));
        $this->assertTrue(str_ends('foobar', 'BAR'));
        $this->assertFalse(str_ends('foobar', 'abar'));
        $this->assertFalse(str_ends('foo', 'bazfoo'));
    }

    /**
     * Test basic {@link t()} functionality.
     */
    public function testT() {
        global $translations;
        $translations = ['Hello' => 'Bonjour'];

        $this->assertEquals('Bonjour', t('Hello'));
        $this->assertEquals('Bonjour', t('Hello', 'Goodbye'));

        $this->assertEquals('foo', t('foo'));
        $this->assertEquals('foobar', t('foo', 'foobar'));
        $this->assertEquals('literal', t('@literal', 'default'));
    }

    /**
     * Test {@link sprintft()}.
     */
    public function testSprintft() {
        global $translations;
        $translations = ['Hello %s!' => 'Bonjour %s!'];

        $this->assertEquals('Bonjour Montreal!', sprintft('Hello %s!', 'Montreal'));
        $this->assertEquals('Hello Montreal!', sprintft('@Hello %s!', 'Montreal'));
    }

    /**
     * Provide some strings for testing.
     *
     * @return array
     */
    public function provideSomeStrings() {
        $strs = [
            'Hello World!',
            123,
            'Iñtërnâtiônàlizætiøn',
            json_encode(['foo' => 'bar']),
        ];

        $result = [];
        foreach ($strs as $str) {
            $result[$str] = [$str];
        }

        // Add a more binary type string.
        $cipher = 'aes-128-cbc';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        $encrypted = openssl_encrypt('Hello World!', 'aes-128-cbc', 'password', OPENSSL_RAW_DATA, $iv);
        $result['Encrypted'] = [$encrypted];

        return $result;
    }

    /**
     * Provide some urls for {@link StringFunctionsTest::testIsUrl()}.
     *
     * @return array Returns test urls and expected results.
     */
    public function provideUrls() {
        $urls = [
            'http' => ['http://foo.com', true],
            'path' => ['/path', false],
            'https' => ['https://foo.com', true],
            'schemaless' => ['//foo.com', true],
            'number' => [123, false],
            'short' => ['/', false],
            'empty' => ['', false]
        ];

        return $urls;
    }
}

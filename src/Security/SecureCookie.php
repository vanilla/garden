<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Security;

/**
 * A class for creating secure cookie payloads based on SCS: KoanLogic's Secure Cookie Sessions for HTTP (rfc6896).
 *
 * @link https://tools.ietf.org/html/rfc6896
 */
class SecureCookie {
    /// Constants ///

    const SEP = '.';

    /// Properties ///

    /**
     * @var string The name of the cipher to use when encrypting data.
     */
    public $cipher;

    /**
     * @var string The key used to encrypt the cookie data.
     */
    public $encryptionKey;

    /**
     * @var string The key used to sign the cookie data.
     */
    public $signatureKey;

    /**
     * @var int The maximum age of cookies in seconds.
     */
    public $maxAge;

    /// Methods ///

    /**
     * Initialize a new {@link SecureCookie} object.
     *
     * @param array $config Configuration options for the secure cookie.
     *
     * - encryptionKey: The key used to encrypt the cookie data.
     * - signatureKey: The key used to sign the cookie data.
     * - cipher: The cipher used to encrypt the cookie data. This must be a valid method for {@link openssl_encrypt()}.
     *   Defaults to **aes-123-cbc**.
     * - maxAge: The maximum age of cookies in seconds.
     *
     * @see openssl_get_cipher_methods()
     */
    public function __construct($config = []) {
        $config = array_change_key_case($config) + [
            'encryptionkey' => '',
            'signaturekey' => '',
            'cipher' => 'aes-128-cbc',
            'maxage' => strtotime('1 year', 0)
        ];

        $this->encryptionKey = $config['encryptionkey'];
        $this->signatureKey = $config['signaturekey'];
        $this->cipher = $config['cipher'];
        $this->maxAge = $config['maxage'];
    }

    /**
     * Base64 Encode a string, but make it suitable to be passed in a url.
     *
     * @param string $str The string to encode.
     * @return string The encoded string.
     */
    public static function base64urlEncode($str) {
        return trim(strtr(base64_encode($str), '+/', '-_'), '=');
    }

    /**
     * Decode a string that was encoded using base64UrlEncode().
     *
     * @param string $str The encoded string.
     * @return string The decoded string.
     */
    public static function base64urlDecode($str) {
        return base64_decode(strtr($str, '-_', '+/'));
    }

    /**
     * Encode a piece of data into the secure cookie format.
     *
     * @param mixed $data The data to encode.
     * @param bool $throw Whether or not to throw an exception or return false on error.
     * @return string Returns the encoded cookie as a string.
     */
    public function encode($data, $throw = true) {
        if (!$this->encryptionKey || !$this->signatureKey) {
            return $this->exception($throw, 'Missing the encryption key or signature key.', 400);
        }

        // We're using aod
        $IV = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $eIV = self::base64urlEncode($IV);
        $eATIME = static::base64urlEncode(time());
        $eDATA = $this->encrypt($data, $IV, true);

        $TID = $this->cipher;
        $eTID = static::base64urlEncode($TID);

        $str = implode(static::SEP, [$eDATA, $eATIME, $eTID, $eIV]);
        $eAUTHTAG = static::base64urlEncode(hash_hmac('sha1', $str, $this->signatureKey, true));

        $result = $str.static::SEP.$eAUTHTAG;
        return $result;
    }

    /**
     * Decode and validate data that was created with {@link SecureCookie::encode()}.
     *
     * @param string $encoded The encoded cookie value.
     * @param bool $throw Whether or not to throw an exception on error or just return false.
     * @return bool|mixed Returns either the data or false on error.
     * @throws Exception Throws an exception if {@link $throw} is true and {@link $encoded} has an error.
     */
    public function decode($encoded, $throw = true) {
        // Make sure the cookie is a string.
        if (!is_string($encoded)) {
            return $this->exception($throw, 'Cookie is not a string.', 400);
        }

        $parts = explode(static::SEP, $encoded);

        // Verify that the cookie has enough parts.
        if (count($parts) !== 5) {
            return $this->exception($throw, 'Cookie does not have the correct parts.', 400);
        }

        list($eDATA, $eATIME, $eTID, $eIV, $eAUTHTAG) = $parts;

        // Make sure the cipher method is supported.
        $TID = static::base64urlDecode($eTID);
        if (!in_array($TID, openssl_get_cipher_methods())) {
            return $this->exception($throw, "Cipher method $TID not supported.", 422);
        }

        // Verify the signature.
        $eAUTHTAG_calc = static::base64urlEncode(hash_hmac('sha1', implode(static::SEP, [$eDATA, $eATIME, $eTID, $eIV]), $this->signatureKey, true));
        if ($eAUTHTAG !== $eAUTHTAG_calc) {
            return $this->exception($throw, 'Invalid signature.', 403);
        }

        // Verify that the session hasn't timed out.
        $timestamp = (int)static::base64urlDecode($eATIME);
        if (time() - $timestamp > $this->maxAge) {
            return $this->exception($throw, 'Session expired.', 401);
        }

        // Make sure the iv isn't empty.
        $IV = static::base64urlDecode($eIV);
        if (!$IV || strlen($IV) !== openssl_cipher_iv_length($TID)) {
            return $this->exception($throw, 'Invalid cypher initialization vector.', 403);
        }

        // Everything checks out. Decrypt the data.
        $json_data = openssl_decrypt(static::base64urlDecode($eDATA), $TID, $this->encryptionKey, true, $IV);

        if ($json_data === false) {
            return $this->exception($throw, 'Invalid encrypted data.', 403);
        }

        $data = json_decode($json_data, true);
        return $data;
    }

    /**
     * Encrypt some data with this object's cipher method.
     *
     * @param mixed $data The data to encrypt.
     * @param string $iv A binary string containing the initialization vector for the encryption.
     * @param bool $encode Whether or not to encode the encrypted string with {@link SecureCookie::base64urlEncode()}.
     * @return string Returns the encrypted string.
     */
    protected function encrypt($data, $iv, $encode = false) {
        $json_data = json_encode($data, JSON_UNESCAPED_SLASHES);

        $result = openssl_encrypt($json_data, $this->cipher, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);
        if ($encode) {
            $result = static::base64urlEncode($result);
        }
        return $result;
    }

    /**
     * Generate a random string suitable for use as an encryption or signature key.
     *
     * @param int $len The number of characters in the key.
     * @return string Returns a base64url encoded string representing the random key.
     */
    public static function generateRandomKey($len = 32) {
        $bytes = ceil($len * 3 / 4);

        return substr(self::base64urlEncode(openssl_random_pseudo_bytes($bytes)), 0, $len);
    }

    /**
     * Twiddle a value in an encoded cookie to another value.
     *
     * This method is mainly for testing so that an invalid cookie can be created
     * that will still have a proper signature.
     *
     * @param string $cookie A valid cookie to twiddle.
     * @param int $index The index of the new value.
     * @param string $value The new value. This will be base64url encoded.
     * @param bool $resign Whether or not to re-sign the cookie.
     * @return string Returns the new encoded cookie.
     */
    public function twiddle($cookie, $index, $value, $resign = true) {
        $parts = explode(static::SEP, $cookie);
        if ($index == 1 && is_int($value)) {
            $parts[$index] = static::base64urlEncode($value);
        } else {
            $parts[$index] = static::base64urlEncode($value);
        }

        if ($resign) {
            array_pop($parts);
            $eAUTHTAG = static::base64urlEncode(
                hash_hmac('sha1', implode(static::SEP, $parts), $this->signatureKey, true)
            );
            $parts[] = $eAUTHTAG;
        }

        return implode(static::SEP, $parts);
    }

    /**
     * Throw an exception or return false.
     *
     * @param bool $throw Whether or not to throw an exception.
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @return bool Returns false if {@link $throw} is false.
     * @throws \Exception Throws an exception with {@link $message} and {@link $code}.
     */
    protected function exception($throw, $message, $code) {
        if ($throw) {
            throw new \Exception($message, $code);
        }
        return false;
    }
}
